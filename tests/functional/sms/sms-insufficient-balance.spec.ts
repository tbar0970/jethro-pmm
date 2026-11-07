import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { mockProfile } from "./scenario.js";
import { login } from "../auth.js";

/**
 * The account has no credit. makeStatuslineConfig() passes the provider's
 * balance to jethro-sms/src/sms_statusline.php, which warns and blocks the
 * Send button before anything is sent. Each scenario profile sets balance 0.
 */
test.describe("SMS send blocked by insufficient balance", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(mockProfile(test.info()), "lastPost"));
    await login(page);
  });

  test("warns and disables Send before any send is attempted", async ({ page, request }) => {
    // Williamson family: two recipients, so one segment needs two credits.
    await page.goto("./?view=families&familyid=14");
    await page.waitForLoadState("load");
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");
    const form = page.locator("#smshttp");
    await expect(form).toBeVisible();

    // The statusline is posted on input; wait for the server's verdict.
    const statuslinePost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").fill("No credit");
    await statuslinePost;

    const statusline = page.locator("#sms-statusline-bulk");
    await expect(statusline).toContainText("Insufficient balance", { timeout: to(5000) });
    await expect(statusline).toContainText("2 segments needed, 0 available");

    // Send is disabled, and nothing reached the provider.
    await expect(form.locator(".bulk-sms-submit")).toBeDisabled();
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    expect(await lastPost.json(), "no send may be attempted without credit").toBeNull();
  });
});
