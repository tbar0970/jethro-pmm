import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * With "the adults in the selected persons' families", the parents are the
 * recipients and %selected.firstname% names the selected children
 * (JethroSmsRecipient::$selectedPersonIds, Jethro\Sms\recipientTokens()).
 */
test.describe("SMS %selected.firstname% names the selected persons", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("parents sharing a mobile get one message naming both selected children", async ({ page }) => {
    // Brown family: Franklin and Corrie (adults) share a mobile; select only
    // their children Burton and Chastity.
    await page.goto("./?view=families&familyid=63");
    await page.waitForLoadState("load");
    for (const adult of ["140", "142"]) {
      await page.locator(`input[name='personid[]'][value='${adult}']`).uncheck();
    }

    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const form = page.locator("#smshttp");
    await expect(form).toBeVisible();
    // %selected.*% are only offered once "the adults in the selected persons' families" is chosen.
    const selectedHint = form.locator("#sms-token-hint-bulk .sms-selected-tokens");
    await expect(form.locator("#sms-token-hint-bulk")).toBeVisible();
    await expect(selectedHint).toBeHidden();
    await form.locator("#sms_type_family").check();
    await expect(selectedHint).toBeVisible();
    await expect(selectedHint).toContainText("%selected.firstname%");
    await form.locator("#sms_type_person").check();
    await expect(selectedHint).toBeHidden();
    await form.locator("#sms_type_family").check();
    await form.locator(".sms-preview-checkbox").check();
    await form.locator(".sms-message").fill("Hi %firstname%, %selected.firstname% has camp Friday");

    // One shared number: one message, both parents' names, each child once.
    await expect(form.locator(".sms-preview-panel .sms-preview-msg")).toHaveText(
      ["Hi Franklin & Corrie, Burton & Chastity has camp Friday"],
      { timeout: to(10000) },
    );
  });

  test("the single-person modal does not offer %selected.*%", async ({ page }) => {
    // It has no "adults in the selected persons' families" option, so the
    // tokens would only repeat the recipient's own names.
    await page.goto("./?view=persons&personid=2");
    await page.waitForLoadState("load");
    const hint = page.locator("#send-sms-modal #sms-token-hint");
    await expect(hint).toContainText("%firstname%");
    await expect(hint).not.toContainText("%selected.");
  });
});
