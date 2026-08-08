import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { DEMO_USERNAME, PERSONA_PASSWORD } from "../auth.js";

/**
 * 2FA is required but the SMS gateway rejects the code send.
 * 2FA must fail closed: the login is refused, never let through without a
 * second factor.
 */
test("login is refused when the 2FA code cannot be sent", async ({ page }) => {
  await page.goto("./index.php");
  await page.fill('input[name="username"]', DEMO_USERNAME);
  await page.fill('input[name="password"]', PERSONA_PASSWORD);
  await page.click('input[value="Log In"]');

  await expect(page.locator(".alert-error")).toContainText("System error during 2-factor auth", { timeout: to(10000) });
  await expect(page.locator('input[name="2fa_code"]')).toHaveCount(0);

  // Not logged in: a staff page still shows the login form.
  await page.goto("./?view=home");
  await expect(page.locator('input[name="username"]')).toBeVisible();
  await expect(page.locator("#jethro-nav")).toHaveCount(0);
});
