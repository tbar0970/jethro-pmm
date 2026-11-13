import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { login } from "../auth.js";

test.describe("SMS unicode always policy", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta("tests/functional/sms/sms-unicode-always", "lastPost"));
    await login(page);
  });

  test("Emoji message is permitted and sends in 'always' unicode mode", async ({
    page,
    request,
  }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
    const message = `Hello Pierre ${timestamp}! 🎉 Great news today.`;

    // 1. Navigate to Pierre de Bure's person page (personid=190)
    await page.goto("./?view=persons&personid=190");
    await page.waitForLoadState("load");

    // 2. Open the SMS modal
    const mobileLink = page.locator("#mobile-190");
    await expect(mobileLink).toBeVisible();
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();

    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 3. Type an emoji message and wait for the server-rendered statusline.
    const messageTextarea = modal.locator(".sms-message");
    const statuslineResponse = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.status() === 200
    );
    await messageTextarea.fill(message);
    await statuslineResponse;

    // 4. Statusline must NOT show the unicode-blocked warning.
    const statusLine = page.locator("#sms-statusline");
    await expect(statusLine).not.toContainText("Remove special characters");
    await expect(statusLine).not.toContainText("Unicode characters are not allowed");

    // 5. Send button must be enabled — the 'always' policy never blocks emoji.
    const sendBtn = modal.locator(".sms-submit");
    await expect(sendBtn).toBeEnabled();

    // 6. Click Send and wait for the page to reload (finishSend calls location.reload()).
    const reloadPromise = page.waitForEvent('load', { timeout: to(10000) });
    await sendBtn.click();
    await reloadPromise;

    // 7. Verify the POST body contains the emoji (UCS-2 content reached provider)
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-unicode-always", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toContain("🎉");
    // Pierre de Bure's test-data mobile: Cellcast is sent the number as stored
    // (local 04…); the gateway applies the country code.
    expect(captured.json.contacts).toHaveLength(1);
    expect(captured.json.contacts[0].replace(/\D/g, "")).toMatch(/^(0|61)?491570157$/);
    expect(captured.json.countryCode).toBe(61);

    // 8. Navigate to Messages tab and confirm the sent message is visible
    await page.goto("./?view=persons&personid=190#messages");
    await page.waitForLoadState("load");
    const messagesTab = page.locator("#messages.tab-pane");
    const messageEntry = messagesTab
      .locator(".history-entry")
      .filter({ hasText: timestamp });
    await expect(messageEntry).toBeVisible({ timeout: to(15000) });
    await expect(messageEntry).toContainText("🎉");
  });
});
