import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, sentSms } from "./scenario.js";
import { login } from "../auth.js";

test.describe("SMS send failure — provider error", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("Send failure is reported to the user", async ({ page, request }) => {
    // Use Williamson family (familyid=14) bulk form — the bulk result div
    // (#bulk-sms-results) reliably surfaces errors from the provider.
    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");

    // The bulk SMS form is hidden by default — activate via the bulk-action chooser.
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // Fill message and send — the mock rejects every send with "Insufficient credits".
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill("Test failed send message");

    const sendButton = smsForm.locator(".bulk-sms-submit");
    const smsResponse = page.waitForResponse(
      (r) =>
        r.url().includes("call=sms") &&
        !r.url().includes("statusline") &&
        r.status() === 200
    );
    await sendButton.click();
    await smsResponse;

    // Provider total failure → data.error → shown in #sms-send-response-bulk
    // (not #bulk-sms-results, which is for per-recipient partial failures).
    const errorDiv = page.locator("#sms-send-response-bulk");
    await expect(errorDiv).toBeVisible({ timeout: to(10000) });

    // Must not claim success.
    await expect(errorDiv).not.toContainText("successfully sent");

    // Must contain a failure/error message from the proxy response.
    await expect(errorDiv).toContainText(/failed|error|could not|Insufficient|Unable/i, {
      timeout: to(5000),
    });

    // Verify a POST was actually attempted (not silently skipped)
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "an SMS POST should have been attempted before the failure was returned").not.toBeNull();
    const sent = sentSms(test.info(), captured.json);
    expect(sent.message).toBe("Test failed send message");
    expect(sent.recipients.length, "both Williamson members should be in the POST").toBeGreaterThanOrEqual(2);
  });
});
