import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

test.describe("SMS unicode when_free policy", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("statusline warns to remove emoji when message exceeds one UCS-2 segment in when_free mode", async ({ page }) => {
    // 1. Visit Katharina von Bora's person page (personid=5)
    await page.goto("./?view=persons&personid=5");
    await page.waitForLoadState("load");

    // 2. Hover and click the mobile number link to open the dropdown
    const mobileLink = page.locator("#mobile-5");
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();

    // 3. Click "SMS via Jethro" to open the modal
    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    // 4. Wait for the SMS modal to become visible
    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 5. Type a long message containing an emoji into the message textarea
    const messageTextarea = page.locator("#send-sms-modal .sms-message");
    await messageTextarea.fill("A far far far far far far far far far too-long SMS message with emoji 🙂");

    // 6. Wait for the server-rendered statusline to update
    const statusLine = page.locator("#sms-statusline");
    await expect(statusLine).toContainText("Remove special characters", { timeout: to(5000) });
  });

  test("Send button is disabled (server-driven) while the message is unicode-blocked", async ({ page }) => {
    await page.goto("./?view=persons&personid=5");
    await page.waitForLoadState("load");
    const mobileLink = page.locator("#mobile-5");
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();
    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();
    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });
    const sendBtn = modal.locator(".sms-submit");

    // A long emoji message is unicode-blocked in when_free mode; the server
    // sets $smsSendBlocked and the Send button's data-attr:disabled disables it.
    await modal.locator(".sms-message").fill("A far far far far far far far far far too-long SMS message with emoji 🙂");
    await expect(sendBtn).toBeDisabled();

    // Clearing the emoji unblocks it -> the button re-enables.
    await modal.locator(".sms-message").fill("Short clean message");
    await expect(sendBtn).toBeEnabled();
  });
});
