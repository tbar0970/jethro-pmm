import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

test.describe("SMS sender options dropdown", () => {
  // With per-scenario FrankenPHP instances, each scenario gets its own
  // PHP process — no session conflicts with other test scenarios.
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("sms_sender dropdown shows only configured 'Foo' and 'Bar' options", async ({ page }) => {
    // 1. Visit Katharina von Bora's person page (personid=5)
    await page.goto("./?view=persons&personid=5");
    await page.waitForLoadState("load");

    // 2. Hover the mobile number link to build the dropdown menu
    //    (the JS handler builds it lazily on mouseenter/focus/touchstart)
    const mobileLink = page.locator("#mobile-5");
    await expect(mobileLink).toBeVisible();
    await mobileLink.hover();
    // Give the JS handler time to build the dropdown markup
    await page.waitForTimeout(50);

    // 3. Click the mobile link to open the Bootstrap 2.3.1 dropdown
    await mobileLink.click();

    // 4. Click "SMS via Jethro" in the dropdown menu
    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    // 5. Wait for the SMS modal to become visible
    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 6. Validate the #sms_sender select has exactly 'Foo' and 'Bar' options
    const senderSelect = page.locator("#sms_sender");
    await expect(senderSelect).toBeVisible();
    const optionValues = await senderSelect.locator("option").evaluateAll(
      (els) => els.map((el) => (el as HTMLOptionElement).value)
    );
    expect(optionValues).toEqual(["Foo", "Bar"]);
  });
});
