import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

test.describe("SMS unicode never policy", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("Send button is disabled when message contains emoji in 'never' unicode mode", async ({
    page,
  }) => {
    // 1. Navigate to Magdalena Luther's person page (personid=185)
    await page.goto("./?view=persons&personid=185");
    await page.waitForLoadState("load");

    // 2. Hover and click the mobile number link to open the dropdown
    const mobileLink = page.locator("#mobile-185");
    await expect(mobileLink).toBeVisible();
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

    const sendBtn = modal.locator(".sms-submit");
    const statusLine = page.locator("#sms-statusline");

    // 5. Type emoji message
    const messageTextarea = page.locator("#send-sms-modal .sms-message");
    await messageTextarea.fill("Hello 🎉 this has emoji");

    // 6. Wait for server-rendered statusline update (retries until visible)
    await expect(statusLine).toContainText("Unicode characters are not allowed", { timeout: to(5000) });

    // 7. Assert Send button is disabled
    await expect(sendBtn).toBeDisabled();

    // 8. Clear the textarea and type plain text
    await messageTextarea.fill("Hello this is plain text only");

    // 9. Wait for statusline to re-render and warning to disappear
    await expect(statusLine).not.toContainText("Unicode characters are not allowed", { timeout: to(5000) });

    // 10. Assert Send button is now enabled
    await expect(sendBtn).toBeEnabled();
  });
});
