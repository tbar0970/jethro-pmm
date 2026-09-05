import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";
import { DEMO_NOUNS, validateNouns } from "../nouns.js";

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

  test("validates the demo data across every object type (read-only)", async ({ page }) => {
    // ~30 sequential page loads: the default 30s budget is too thin for that
    // when 8 workers share one php-fpm pool, so allow 3x (Playwright slow()).
    test.slow();
    await login(page);

    // Walk the app's nouns (db_object subclasses) via the verbs (views) that
    // list them, asserting the reference records are showing. Nothing here
    // submits a form. The same walk runs against the wizard-built instance in
    // walkthrough.spec.ts, with the records that instance's verbs tests add.
    await validateNouns(page, DEMO_NOUNS);
  });
});
