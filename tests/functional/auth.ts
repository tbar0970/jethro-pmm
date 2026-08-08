import { type Page } from "@playwright/test";
import { to } from "./timeouts.js";

// Credentials — local test instance only
const USERNAME = "demo";
const PASSWORD = "qfntt7eYuwHs123";

/**
 * Log in to the Jethro test instance.
 *
 * Navigates relative to baseURL so the test-scenario prefix is preserved
 * across page loads.  The prefix is set in playwright.config.ts per scenario.
 */
export async function login(page: Page): Promise<void> {
  // console.log("Before login");

  await page.goto("./index.php");
  // We might already be logged in (no login form)
  const loginButton = page.locator('input[value="Log In"]');
  if (await loginButton.isVisible().catch(() => false)) {
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
      // Jethro redirects to build_url([]) = the base URL after login
      page.waitForURL("**/", { timeout: to(1000) }).catch(() => {}),
      loginButton.click(),
    ]);
  }
  // console.log("After login");

}
