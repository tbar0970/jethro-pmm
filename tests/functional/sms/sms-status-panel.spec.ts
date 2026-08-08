import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * The System Configuration → SMS status panel reports the provider's account
 * balance (getBalance() → GET account/balance). Each scenario profile sets
 * the fake account's balance to 42, so the exact figure proves the provider
 * read it from the provider's own reply shape — Cellcast's
 * data.sms_balance, 5CentSMS's balance.credits, or MobileMessage's
 * credit_balance. The unreachable-gateway path is covered by sms-wrong-profile.
 */
test.describe("SMS status panel", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("shows the account's credit balance", async ({ page }) => {
    // The panel is returned by ?call=admin_statuspanel_sms&datastar={}
    await page.goto("./?call=admin_statuspanel_sms&datastar=%7B%7D");
    const panel = page.locator("#status-panel-sms");
    await expect(panel).toContainText("Operational", { timeout: 5000 });
    await expect(panel).toContainText("42 SMSes remaining");
    await expect(panel).toContainText("Credits remaining");
  });
});
