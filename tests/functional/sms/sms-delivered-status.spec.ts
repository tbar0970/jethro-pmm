import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * 'sent' is not a final status: the gateway has accepted the message but the
 * handset hasn't confirmed it. The Messages page must keep polling a sent
 * delivery until the provider reports it delivered (✓✓), not stop at ✓.
 *
 * Test data: the Williamson family (familyid=14) — Jamison and Bridget have
 * mobiles. The mock confirms delivery 5s after the send time.
 */
test.describe("SMS status after sending", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("a sent message goes on to delivered", async ({ page }) => {
    test.setTimeout(to(180000));
    const tag = `delivered-status-${Date.now()}`;

    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const smsForm = page.locator("#smshttp");
    await smsForm.locator(".sms-message").fill(`Delivery check ${tag}`);
    await smsForm.locator(".bulk-sms-submit").click();
    await expect(page.locator("#bulk-sms-results")).toContainText(/successfully sent/, { timeout: to(15000) });

    // Status is the title's prefix: "<status>: <send time>" / "Delivered: <time>".
    await page.goto("./?view=persons__messages");
    const statuses = page.locator("tr[id^='message_']", { hasText: tag }).locator(".message-status");
    await expect(statuses).toHaveCount(2, { timeout: to(10000) });

    // Through the 30s cool-off (scheduled) and past 'sent', without reloading.
    await expect
      .poll(
        () => statuses.evaluateAll((els) => els.map((e) => (e.getAttribute("title") ?? "").split(":")[0].toLowerCase())),
        { timeout: to(120000), intervals: [2000] },
      )
      .toEqual(["delivered", "delivered"]);
  });
});
