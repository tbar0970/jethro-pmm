import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { type Page } from "@playwright/test";
import { login, DEMO_USERNAME } from "../auth.js";

// Restricted staff accounts see only SMS deliveries to persons they may see:
// the smsdelivery view (db_objects/smsdelivery.class.php) keeps deliveries to
// persons visible through the restricted `person` view, plus raw numbers.
//
// Persona: 6pmonly (staff_member 8; persons row in tests/functional/testdata/demodata-2.40-DEV.sql.gz)
// has "SMS - view all" and "SMS - send", restricted to the 6pm congregation.
//
// Fixture messages (tests/functional/testdata/fakesmses.sql.gz, loaded in a
// fixed order into a freshly built database before every run, so their IDs are
// stable). Recipient names are asserted too, so fixture drift fails loudly.
const MIXED_SMS = 134; // 2 recipients in 6pm, 2 in another congregation
const MIXED_IN_6PM = { "100": "Pamelia Lang", "130": "Deetta Berge" };
const MIXED_OUTSIDE = { "145": "Randi Parker", "164": "Hee Keeling" };
const NONE_IN_6PM_SMS = 192; // 4 recipients, none in 6pm
const NONE_IN_6PM_RECIPIENTS = ["62", "108", "143", "146"];

const SIXPM_USERNAME = "6pmonly";

/** Person IDs linked as recipients in a Persons → Messages row. */
async function recipientIds(page: Page, smsId: number): Promise<string[]> {
  const hrefs = await page
    .locator(`#message_${smsId} td.sms-history-recipients a[href*="personid="]`)
    .evaluateAll((links) => links.map((a) => a.getAttribute("href") ?? ""));
  return hrefs.map((h) => /personid=(\d+)/.exec(h)?.[1] ?? "").sort();
}

/** Every recipient person ID linked anywhere on the Messages page. */
async function allRecipientIds(page: Page): Promise<Set<string>> {
  const hrefs = await page
    .locator('td.sms-history-recipients a[href*="personid="]')
    .evaluateAll((links) => links.map((a) => a.getAttribute("href") ?? ""));
  return new Set(hrefs.map((h) => /personid=(\d+)/.exec(h)?.[1] ?? ""));
}

async function openMessages(page: Page): Promise<void> {
  await page.goto("./?view=persons__messages");
  await expect(page.locator(".sms-history-table tbody")).toBeVisible({ timeout: to(15000) });
}

test.describe("Persons → Messages for a congregation-restricted account", () => {
  test("unrestricted demo sees every recipient (control)", async ({ page }) => {
    await login(page, DEMO_USERNAME);
    await openMessages(page);

    expect(await recipientIds(page, MIXED_SMS)).toEqual(
      Object.keys({ ...MIXED_IN_6PM, ...MIXED_OUTSIDE }).sort(),
    );
    for (const name of Object.values({ ...MIXED_IN_6PM, ...MIXED_OUTSIDE })) {
      await expect(page.locator(`#message_${MIXED_SMS} td.sms-history-recipients`)).toContainText(name);
    }
    expect(await recipientIds(page, NONE_IN_6PM_SMS)).toEqual([...NONE_IN_6PM_RECIPIENTS].sort());
  });

  test("6pmonly sees only 6pm recipients, and not messages without any", async ({ page }) => {
    await login(page, SIXPM_USERNAME);
    await openMessages(page);

    // The mixed message is listed, with only its 6pm recipients.
    expect(await recipientIds(page, MIXED_SMS)).toEqual(Object.keys(MIXED_IN_6PM).sort());
    const recipientsCell = page.locator(`#message_${MIXED_SMS} td.sms-history-recipients`);
    for (const name of Object.values(MIXED_IN_6PM)) {
      await expect(recipientsCell).toContainText(name);
    }
    for (const name of Object.values(MIXED_OUTSIDE)) {
      await expect(recipientsCell).not.toContainText(name);
    }

    // A message with no 6pm recipient isn't listed at all.
    await expect(page.locator(`#message_${NONE_IN_6PM_SMS}`)).toHaveCount(0);

    // No out-of-restriction recipient is linked anywhere on the page.
    const shown = await allRecipientIds(page);
    for (const id of [...Object.keys(MIXED_OUTSIDE), ...NONE_IN_6PM_RECIPIENTS]) {
      expect(shown.has(id), `person ${id} (outside 6pm) must not be shown`).toBe(false);
    }
  });
});

// Status polling (?call=sms_info) must not answer for deliveries the account
// can't see. Only deliveries that are still pollable (scheduled, via the
// current provider) produce output, so schedule two sends as demo: one to a 6pm
// family and one to a family in another congregation.
test.describe.serial("?call=sms_info for a congregation-restricted account", () => {
  const FAMILY_6PM = 34; // Rose: all members in 6pm
  const FAMILY_OTHER = 15; // Robertson: all members in another congregation
  const deliveryIds: { inside?: string; outside?: string } = {};

  async function scheduleToFamily(page: Page, familyId: number, message: string): Promise<void> {
    await page.goto(`./?view=families&familyid=${familyId}`);
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();
    await smsForm.locator(".sms-message").fill(message);
    await smsForm.locator(".sms-schedule-toggle").check();
    await page.evaluate(() => {
      const input = document.querySelector('input[type="datetime-local"][name="send_at"]') as HTMLInputElement;
      input.value = new Date(Date.now() + 24 * 3600_000).toISOString().slice(0, 16);
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });
    await smsForm.locator(".bulk-sms-submit").click();
    await expect(page.locator("#bulk-sms-results")).toContainText("Message scheduled for sending", { timeout: to(15000) });
  }

  /** The first delivery ID of the Messages-page row whose body contains $marker. */
  async function firstDeliveryId(page: Page, marker: string): Promise<string> {
    const span = page.locator(`tr[data-body*="${marker}"] span.message-attribution[id^="sms-delivery-status-"]`).first();
    await expect(span).toBeVisible({ timeout: to(10000) });
    return (await span.getAttribute("id"))!.replace("sms-delivery-status-", "");
  }

  test("demo schedules a send to a 6pm family and to another family", async ({ page }) => {
    const stamp = new Date().toISOString().replace(/[:.]/g, "-");
    await login(page, DEMO_USERNAME);
    await scheduleToFamily(page, FAMILY_6PM, `Restricted-visibility inside ${stamp}`);
    await scheduleToFamily(page, FAMILY_OTHER, `Restricted-visibility outside ${stamp}`);

    await openMessages(page);
    deliveryIds.inside = await firstDeliveryId(page, `inside ${stamp}`);
    deliveryIds.outside = await firstDeliveryId(page, `outside ${stamp}`);
  });

  test("6pmonly gets the status of the 6pm delivery but nothing for the other", async ({ page }) => {
    expect(deliveryIds.inside && deliveryIds.outside, "set by the previous test").toBeTruthy();
    await login(page, SIXPM_USERNAME);

    const inside = await page.request.get(`./?call=sms_info&id=${deliveryIds.inside}`);
    expect(inside.ok()).toBe(true);
    expect(await inside.text()).toContain(`sms-delivery-status-${deliveryIds.inside}`);

    const outside = await page.request.get(`./?call=sms_info&id=${deliveryIds.outside}`);
    expect(outside.ok()).toBe(true);
    expect(await outside.text()).not.toContain(`sms-delivery-status-${deliveryIds.outside}`);
  });
});
