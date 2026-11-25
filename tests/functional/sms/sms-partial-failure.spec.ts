import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { login } from "../auth.js";

/**
 * A send where the gateway accepts one recipient and rejects another must be
 * reported as a partial success: the accepted message is still logged, and the
 * user is told who failed. One message went out, so it must not look like a
 * total failure.
 */
test.describe("SMS partial failure", () => {
  test("one accepted recipient and one failed are reported separately", async ({ page, request }) => {
    await login(page);

    // Family 14: Jamison (mock fails this one) and Bridget.
    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();
    await smsForm.locator(".sms-message").fill("Partial failure test");

    const smsResponse = page.waitForResponse(
      (r) => r.url().includes("call=sms") && !r.url().includes("statusline") && r.status() === 200,
    );
    await smsForm.locator(".bulk-sms-submit").click();
    await smsResponse;

    // Both the success and the failure are reported: not an overall error.
    await expect(page.locator("#sms-send-response-bulk")).toBeHidden();
    const results = page.locator("#bulk-sms-results");
    await expect(results).toBeVisible({ timeout: to(10000) });
    await expect(results).toContainText(/successfully sent/i);
    await expect(results).toContainText(/failed/i);
    await expect(results).toContainText("Jamison");

    // The gateway saw both recipients in one request.
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-partial-failure", "lastPost"),
    );
    const captured = await lastPost.json();
    expect(captured, "the mock should have captured the send").not.toBeNull();
    expect(captured.json.contacts).toHaveLength(2);
  });
});
