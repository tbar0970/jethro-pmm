import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { login } from "../auth.js";

test.describe("SMS per-recipient message override", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("per-recipient override is preserved and sent", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

    // 1. Navigate to the Calvin family (familyid=2)
    await page.goto("./?view=families&familyid=2");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message textarea with a token
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill("Hello %firstname%");

    // 4. Check the "Message Preview" checkbox to show the preview panel
    const previewCheckbox = smsForm.locator(".sms-preview-checkbox");
    await previewCheckbox.check();

    // 5. Wait for the preview panel to render all 3 recipient rows
    const previewPanel = smsForm.locator(".sms-preview-panel");
    await expect(previewPanel).toBeVisible();

    // Wait for preview entries to arrive (they each contain the person's first name)
    await expect(async () => {
      const text = await previewPanel.innerText();
      // John Calvin, Idelette de Bure, Pierre de Bure
      expect(text).toContain("John");
      expect(text).toContain("Idelette");
      expect(text).toContain("Pierre");
    }).toPass({ timeout: to(10000) });

    // 6. Find the row for John Calvin and click the pencil/edit button
    const previewRows = smsForm.locator(".sms-preview-msg");
    const firstMsgText = await previewRows.first().innerText();
    expect(firstMsgText).toContain("Hello");

    // Click the edit button for the first recipient
    const editToggleButton = smsForm.locator(".sms-preview-edit-toggle").first();
    await expect(editToggleButton).toBeVisible();
    await editToggleButton.click();

    // 7. The override textarea for that row should appear
    const editBox = smsForm.locator(".sms-preview-edit").first();
    await expect(editBox).toBeVisible();

    // 8. Clear and type a custom message
    const customMessage = "Hi John, this is a CUSTOM message for you";
    await editBox.fill(customMessage);

    // 9. Blur the textarea by clicking elsewhere and wait for Datastar to process
    const statuslinePost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST"
    );
    await messageBox.click(); // blur the edit box
    await statuslinePost;

    // 10. Assert John Calvin's preview row is highlighted (yellow background)
    const firstMsg = smsForm.locator(".sms-preview-msg").first();
    await expect(firstMsg).toContainText(customMessage);
    // The highlight is rgb(255, 243, 205) which is #fff3cd
    await expect(firstMsg).toHaveCSS("background-color", "rgb(255, 243, 205)");

    // 11. Assert the other rows (Idelette, Pierre) still show the token-expanded default message
    const allMessages = await smsForm.locator(".sms-preview-msg").all();
    expect(allMessages.length).toBeGreaterThanOrEqual(2);

    // Second and third rows should still contain "Hello" (not the override)
    const secondMsgText = await allMessages[1].innerText();
    expect(secondMsgText).toContain("Hello");
    expect(secondMsgText).not.toContain("CUSTOM");

    const thirdMsgText = await allMessages[2].innerText();
    expect(thirdMsgText).toContain("Hello");
    expect(thirdMsgText).not.toContain("CUSTOM");

    // 12. Type an extra character in the main compose box
    const statuslinePost2 = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST"
    );
    await messageBox.fill("Hello %firstname% extra");
    await statuslinePost2;

    // 13. Assert John Calvin's override textarea still contains the custom message (not reset)
    const editBoxAfterChange = smsForm.locator(".sms-preview-edit").first();
    const editBoxValue = await editBoxAfterChange.inputValue();
    expect(editBoxValue).toBe(customMessage);

    // 14. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 15. Wait for success toast/result
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message successfully sent to 3 recipients", {
      timeout: to(15000),
    });

    // Verify the final batch POST reached the provider
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-per-recipient-override", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.contacts.length, "final batch must have at least one recipient").toBeGreaterThanOrEqual(1);
    expect(captured.json.countryCode).toBe(61);
    expect(captured.json.sender, "sender must be present").toBeDefined();
  });
});
