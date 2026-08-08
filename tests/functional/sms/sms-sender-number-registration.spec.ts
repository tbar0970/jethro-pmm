import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, scenarioProvider, type ScenarioProvider } from "./scenario.js";
import { login } from "../auth.js";

// A persona per provider run: the runs are parallel, and the shared functest
// database records a number as verified for the first person it is verified
// for (DbLoggingSmsProvider::verifySenderNumber()). Each can send, and no
// other scenario sends from or verifies its mobile.
const SENDERS: Record<ScenarioProvider, { username: string; personid: number }> = {
  cellcast: { username: "smallgroupleader", personid: 170 },
  "5centsmsv5": { username: "bandleader", personid: 175 },
  mobilemessage: { username: "rostersetter", personid: 61 },
};

test.describe("SMS sender number registration", () => {
  test.beforeEach(async ({ page, request }) => {
    // Clean up any registration from a previous run — stale registrations
    // with ApprovalDelay=0 default to "approved" in the mock, making
    // verifySenderNumber() return true and hiding the registration UI.
    await request.delete(mockMeta(mockProfile(test.info()), "registrations"));
    await login(page, SENDERS[scenarioProvider(test.info())].username);
  });

  test("Register own mobile number as SMS sender", async ({ page }) => {
    const provider = scenarioProvider(test.info());
    const SENDER_PERSONID = SENDERS[provider].personid;

    // 1. Navigate to the sender's own person page
    await page.goto(`./?view=persons&personid=${SENDER_PERSONID}`);
    await page.waitForLoadState("load");

    // 2. Find and hover over the mobile number to trigger the SMS modal dropdown
    const mobileLink = page.locator(`#mobile-${SENDER_PERSONID}`);
    await expect(mobileLink).toBeVisible();
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();

    // 3. Click "SMS via Jethro" to open the modal
    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    // 4. Wait for the SMS modal to become visible
    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 5. The sender area shows "needs verifying" — the provider's sender list
    // doesn't include the user's mobile yet
    const registerStatus = page.locator(`#sms-register-person_${SENDER_PERSONID}`);
    await expect(registerStatus).toBeVisible();
    await expect(registerStatus).toContainText("needs verifying");

    // 6. Click the "verify now" link — triggers Datastar @post to ?call=sms_sendernum&action=register
    const registerLink = page.locator(".sms-register-number");
    const regResponse = page.waitForResponse(
      (r) => r.url().includes("call=sms_sendernum") && r.status() === 200
    );
    await registerLink.click();
    await regResponse;

    // 7. Brief pause for Datastar response to arrive
    await page.waitForTimeout(150);

    // 8. Datastar patches #sms-register-person_<id> with the provider's answer.
    await expect(registerStatus).not.toContainText("needs verifying", { timeout: to(5000) });
    switch (provider) {
      case "cellcast":
        // {"status": true, "message": "Custom number created"} (or "Number
        // already exist in system" when already registered — e.g. parallel
        // runs); Cellcast treats both as success.
        await expect(registerStatus).toContainText(/created|verified|success|already exist/i, { timeout: to(5000) });
        break;
      case "5centsmsv5":
        // 5CentSMS verifies out of band: it texts the number a link to click
        // (bandleader's mobile, 0491571175).
        await expect(registerStatus).toContainText(/Verification link sent to (0|61)?491571175\./, { timeout: to(5000) });
        break;
      case "mobilemessage":
        // So does MobileMessage, naming the number it texted (rostersetter's).
        await expect(registerStatus).toContainText(/Verification link sent to (0|61)491571061\./, { timeout: to(5000) });
        break;
    }
  });
});
