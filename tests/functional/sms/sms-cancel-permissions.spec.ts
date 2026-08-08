import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login, DEMO_USERNAME } from "../auth.js";

/**
 * Only the sender (or a sysadmin) may cancel a scheduled send. The restricted
 * persona 6pmonly holds SMS - send but is not the sender, so its POST must be
 * refused and the send must stay scheduled; the owner can then cancel it.
 */
test.describe("SMS cancel permissions", () => {
  test("a non-sender cannot cancel someone else's scheduled send", async ({ page, context }) => {
    await login(page, DEMO_USERNAME);

    const messageText = `Cancel permission test ${Date.now()}`;

    // Schedule a send from the demo account.
    await page.goto("./?view=families&familyid=2");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const form = page.locator("#smshttp");
    await expect(form).toBeVisible();
    await form.locator(".sms-message").fill(messageText);
    await form.locator(".sms-schedule-toggle").check();
    const when = await page.evaluate(() => {
      const d = new Date();
      d.setHours(d.getHours() + 24);
      const p = (n: number) => String(n).padStart(2, "0");
      return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    });
    await form.locator('input[type="datetime-local"][name="send_at"]').fill(when);
    await form.locator(".bulk-sms-submit").click();
    await expect(page.locator("#bulk-sms-results")).toContainText(/scheduled for sending/i, { timeout: to(15000) });

    // The scheduled send's id is in its Cancel container on the Messages page.
    await page.goto("./?view=persons__messages");
    const scheduledRow = page.locator("tr[id^='message_']", { hasText: messageText });
    await expect(scheduledRow).toBeVisible({ timeout: to(10000) });
    const smsId = (await scheduledRow.getAttribute("id"))!.replace("message_", "");

    // Log in as the restricted persona and try to cancel it directly: the
    // Cancel link is not rendered for them, so this is the request a hostile
    // client would send.
    await page.goto("./?logout=1");
    await login(page, "6pmonly");
    const refused = await context.request.post(`./?call=sms_cancel&sms_id=${smsId}`);
    expect(await refused.text()).toContain("Permission denied");

    // Still scheduled for the owner (and cancellable by them).
    await page.goto("./?logout=1");
    await login(page, DEMO_USERNAME);
    await page.goto("./?view=persons__messages");
    const row = page.locator(`#message_${smsId}`);
    await expect(row.locator('span.message-status[title^="scheduled"]').first()).toBeVisible();
    await row.locator('[id^="sms-cancel-"] a').click();
    await expect(row.locator('[id^="sms-cancel-"]')).toContainText(/Cancelled \d+ deliver/i, { timeout: to(10000) });
  });
});
