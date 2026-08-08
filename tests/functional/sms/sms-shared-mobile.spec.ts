import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { login } from "../auth.js";

/**
 * Recipients sharing a mobile number get one SMS (DeduplicatingSmsProvider —
 * see docs/docs/developer/sms/smsarchitecture.md).
 *
 * Test data: the Brown family (familyid=63) — Franklin (140), Corrie (142)
 * and Chastity (143) all have mobile 0491-570-159; Burton (141) has none.
 */
const PROFILE = "tests/functional/sms/sms-shared-mobile";
const SHARED_MOBILE = /491570159$/;

test.describe("SMS bulk send to family members sharing a mobile", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("preview shows one row for the shared number; one SMS goes to it", async ({ page, request }) => {
    const tag = `dup-${Date.now()}`;

    await page.goto("./?view=families&familyid=63");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();
    await smsForm.locator(".sms-message").fill(`Hello %firstname% ${tag}`);

    // One preview row for the shared handset: everyone's name, and the
    // combined message the phone will receive.
    await smsForm.locator(".sms-preview-checkbox").check();
    const previewRows = smsForm.locator(".sms-preview-panel tbody tr");
    await expect(previewRows).toHaveCount(1, { timeout: to(10000) });
    await expect(previewRows.locator("td").first()).toHaveText("Franklin Brown\nCorrie Brown\nChastity Brown", { useInnerText: true });
    await expect(previewRows.locator(".sms-preview-msg")).toHaveText(`Hello Franklin & Corrie & Chastity ${tag}`);

    await smsForm.locator(".bulk-sms-submit").click();
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText(/successfully sent/, { timeout: to(15000) });
    for (const name of ["Franklin Brown", "Corrie Brown", "Chastity Brown"]) {
      await expect(results).toContainText(name);
    }

    // The provider received exactly one message for this send, addressed to
    // the shared number, with the first names combined.
    const resp = await request.get(mockMeta(PROFILE, "sms"));
    const { messages } = await resp.json();
    const sent = messages.filter((m: { body: string }) => m.body.includes(tag));
    expect(sent).toHaveLength(1);
    expect(sent[0].destination).toMatch(SHARED_MOBILE);
    for (const name of ["Franklin", "Corrie", "Chastity"]) {
      expect(sent[0].body).toContain(name);
    }
    expect(sent[0].body).toMatch(/^Hello \S+ & \S+ & \S+ /);
    expect(sent[0].body).not.toContain("%firstname%");
  });
});
