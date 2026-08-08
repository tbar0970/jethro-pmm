import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"

const SMS_2FA_PROFILE = "tests/functional/sms/sms-2fa";
const USERNAME = "demo";
const PASSWORD = "qfntt7eYuwHs123";

test.describe.serial("2FA login", () => {
  test.beforeEach(async ({ request }) => {
    await request.delete(mockMeta(SMS_2FA_PROFILE, "lastPost"));
  });

  test("correct OTP logs the user in", async ({ page, request }) => {
    // 1. Navigate to login page
    await page.goto("./index.php");

    // 2. Fill and submit credentials
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('input[value="Log In"]');

    // 3. Login should pause at the 2FA form
    const codeInput = page.locator('input[name="2fa_code"]');
    await expect(codeInput).toBeVisible({ timeout: to(10000) });

    // 4. The OTP was sent synchronously before this page rendered, so
    //    /meta/lastPost already holds the Cellcast POST body.
    const lastPost = await request.get(mockMeta(SMS_2FA_PROFILE, "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();

    const message: string = captured?.json?.message ?? "";
    const match = message.match(/\b(\d{6})\b/);
    expect(match, `No 6-digit OTP found in message: "${message}"`).not.toBeNull();
    const otp = match![1];

    // 5. Fill OTP and submit. The server redirects to / (bare root, dropping the
    //    test prefix). Wait for navigation, then go back under the test prefix.
    await Promise.all([
      page.waitForURL('**/', { timeout: to(10000) }),
      codeInput.fill(otp).then(() => page.click('input[value="Go"]')),
    ]);

    // Navigate back under the test prefix — the session cookie is already set.
    await page.goto('./?view=home');
    await expect(page.locator('#jethro-nav')).toBeVisible({ timeout: to(10000) });
  });

  test("wrong OTP shows an error and stays on 2FA form", async ({ page, request }) => {
    await page.goto("./index.php");
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('input[value="Log In"]');

    const codeInput = page.locator('input[name="2fa_code"]');
    await expect(codeInput).toBeVisible({ timeout: to(10000) });

    // Use the /meta/lastPost endpoint to verify a POST was captured
    // (even though we don't use the real OTP here)
    const lastPost = await request.get(mockMeta(SMS_2FA_PROFILE, "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();

    // Enter a wrong code
    await codeInput.fill("000000");
    await page.click('input[value="Go"]');

    await expect(page.locator(".alert-error")).toContainText(
      "Incorrect 2-factor code"
    );
    await expect(codeInput).toBeVisible();
  });
});
