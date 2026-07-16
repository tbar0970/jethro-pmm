import { test, expect } from "@playwright/test";
import { login } from "../auth.js";

test.describe("Login", () => {
  test("logs in with demo credentials and lands on the home page", async ({ page }) => {
    await login(page);

    // After successful login, the login form should no longer be visible
    // and we should see the standard post-login UI.
    await expect(page.locator('input[value="Log In"]')).not.toBeVisible();

    // Jethro's home page typically shows the main menu.
    // The navbar-brand or a known nav element confirms we're inside the app.
    await expect(page.locator("h1:has-text('Jethro PMM')")).toBeVisible();
  });
});
