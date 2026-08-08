import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * A send with the default 30-second SMS_SEND_COOLOFF goes to the provider as a
 * scheduled send, so the Messages page first shows it 'scheduled'. Once the
 * send time passes, the page's status polling (?call=sms_info, which asks the
 * provider via listRecentDeliveries()) must turn it into 'sent'.
 *
 * Test data: the Williamson family (familyid=14) — Jamison and Bridget have
 * mobiles. The mock profile keeps sent messages 'sent' for an hour.
 */
test.describe("SMS status after the send cool-off", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("scheduled during the 30s cool-off, then sent", async ({ page }) => {
    test.setTimeout(to(180000));
    const tag = `cooloff-status-${Date.now()}`;

    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const smsForm = page.locator("#smshttp");
    await smsForm.locator(".sms-message").fill(`Status check ${tag}`);
    await smsForm.locator(".bulk-sms-submit").click();
    await expect(page.locator("#bulk-sms-results")).toContainText(/successfully sent/, { timeout: to(15000) });

    // Straight after sending: inside the cool-off, both deliveries scheduled.
    // The cool-off gives a guaranteed ~25s scheduled window; retry within it
    // so a slow page render under suite load can't read past the flip.
    await page.goto("./?view=persons__messages");
    const row = page.locator("tr[id^='message_']", { hasText: tag });
    const statuses = row.locator(".message-status");
    await expect(statuses).toHaveCount(2, { timeout: to(10000) });
    await expect(async () => {
      const titles = await statuses.evaluateAll((els) => els.map((e) => e.getAttribute("title") ?? ""));
      expect(titles.length).toBe(2);
      for (const title of titles) {
        expect(title).toMatch(/^scheduled/);
      }
    }).toPass({ timeout: to(15000) });
    const cancelLink = row.locator("[id^='sms-cancel-'] a");
    await expect(cancelLink).toHaveCount(1);

    // After the send time the page's own polling picks up the provider's
    // status — no reload. Titles read "<status>: <send time>". Under suite
    // load the interval polls can time out against the busy mock, so reload
    // once mid-wait: rendering an overdue delivery fires an immediate
    // catch-up fetch (data-init).
    await page.waitForTimeout(to(20000));
    await page.reload();
    await expect(statuses).toHaveCount(2, { timeout: to(10000) });
    await expect
      .poll(async () => statuses.evaluateAll((els) => els.map((e) => (e.getAttribute("title") ?? "").split(":")[0])), {
        // MobileMessage clamps the cool-off to 90 s (its API needs >60 s lead);
        // the other providers send after ~30 s, so allow for the longest.
        timeout: to(120000),
        intervals: [2000],
      })
      .toEqual(["sent", "sent"]);
    // The send has gone, so the poll that turned it 'sent' also clears the
    // Cancel link rendered while it was scheduled.
    await expect(cancelLink).toHaveCount(0);
  });
});
