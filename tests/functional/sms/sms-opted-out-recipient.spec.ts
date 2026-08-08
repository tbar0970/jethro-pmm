import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, sentSms } from "./scenario.js";
import { login } from "../auth.js";
import { to } from "../timeouts.js";

test.describe("SMS to family with opted-out member", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("Opted-out family member is excluded from send and reported", async ({
    page,
    request,
  }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

    // 1. Navigate to the Williamson family (familyid=14)
    // This family has 2 mobile recipients: Jamison (opted out) and Bridget (gets SMS)
    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message
    const messageBox = smsForm.locator(".sms-message");
    const testMessage = `Test opted-out ${timestamp}`;
    await messageBox.fill(testMessage);

    // 4. Wait for statusline to populate (debounced AJAX call)
    // Jamison is opted out: not counted, so 1 recipient (Bridget only)
    const statuslinePostPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes("call=sms_statusline") && resp.status() === 200
    );
    await statuslinePostPromise;
    await page.waitForTimeout(150); // Datastar SSE DOM morphing

    const statusLine = page.locator("#sms-statusline-bulk");
    await expect(statusLine).toBeVisible({ timeout: to(5000) });
    await expect(statusLine).toContainText("1 recipient");

    // 4b. The preview lists Jamison, greyed as opted out with no edit pencil;
    //     Bridget's row is normal
    await smsForm.locator(".sms-preview-checkbox").check();
    const optedOutRow = smsForm.locator(".sms-preview-panel tr.sms-preview-optedout");
    await expect(optedOutRow).toContainText("Jamison Williamson", { timeout: to(10000) });
    await expect(optedOutRow).toContainText("Opted out of SMS");
    await expect(optedOutRow.locator(".sms-preview-edit-toggle")).toHaveCount(0);
    await expect(smsForm.locator(".sms-preview-panel .sms-preview-msg")).toHaveText([testMessage]);

    // 5. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 6. Wait for the send result to appear
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message successfully sent to Bridget Williamson", {
      timeout: to(15000),
    });

    // 7. Verify that Bridget received the SMS
    await expect(results).toContainText("Bridget Williamson");

    // 8. Verify that Jamison is reported as opted-out
    // Expected text: "Jamison Williamson has opted out of receiving SMS"
    await expect(results).toContainText("Jamison Williamson", {
      timeout: to(5000),
    });
    await expect(results).toContainText("has opted out of receiving SMS");

    // 9. Verify via /meta/lastPost that only Bridget's number was POSTed
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    const sent = sentSms(test.info(), captured.json);
    expect(sent.recipients, "POST body should contain only Bridget's number").toEqual(["0491570156"]);
    expect(sent.message).toContain("Test opted-out");
  });
});
