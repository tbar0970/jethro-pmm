import { test, expect } from "../fixtures.js";
import { waitForMessage } from "../email/mailpit-api.js";
import { to } from "../timeouts.js";

/**
 * Member self-registration round-trip (the oldest server-sent email flow in
 * Jethro): the members-area login page emails an activation link, following it
 * signs the member in and asks them to choose a password.
 *
 * Email capture is the `mailpit` process-compose service (see
 * DEVELOPMENT_FUNCTESTS.md, "Email capture (MailPit)"). The member-rego settings
 * come from the demo database (settings table → constants via
 * Config_Manager::defineConstant): MEMBER_REGO_EMAIL_SUBJECT is
 * "Setting up your account" and MEMBER_REGO_EMAIL_FROM_ADDRESS is
 * jethro@localhost.
 *
 * Person 9 in the demo data (Torrie Lockman) has a unique email address, no member
 * account and is not a staff persona, so the flow is unambiguous and nobody else in
 * the suite logs in as this person. Her family's address_state is a valid option,
 * so the members view renders without the demo data's "Geneva is not a valid value
 * for field address_state" notice (which the fixtures stack-trace guard would fail
 * on). The demo database is reloaded before every suite run, so the account this
 * test creates does not leak into later runs.
 */

const MEMBER_EMAIL = "torrie.lockman@example.org";
const NEW_PASSWORD = "MemberTest42"; // >= MEMBER_PASSWORD_MIN_LENGTH (7), letters+numbers

test("member registration: emailed activation link signs in and sets a password", async ({ page }) => {
    const notBefore = Date.now(); // ignore leftover matches from previous suite runs

    await page.goto("./index.php");
    await page.locator('input[name="email"]').fill(MEMBER_EMAIL);
    await page.locator('input[name="password-request"]').click();
    await expect(page.getByText("Account request received")).toBeVisible({ timeout: to(15000) });

    const msg = await waitForMessage({ to: MEMBER_EMAIL, subject: "Setting up your account" }, notBefore, to(15000));
    // The URL shares its line with the sentence around it, so match it, don't split lines.
    const link = msg.Text.match(/https?:\/\/\S*verify=\S+/)?.[0];
    expect(link, "activation link in the email body").toBeTruthy();

    await page.goto(link!); // verifies the token and shows the set-password form
    await expect(page.getByText("your email address has now been verified")).toBeVisible({ timeout: to(15000) });

    await page.locator('input[name="password1"]').fill(NEW_PASSWORD);
    await page.locator('input[name="password2"]').fill(NEW_PASSWORD);
    await page.locator('input[name="set-password"]').click();

    await expect(page.getByText("Your account is complete and you are now logged in.")).toBeVisible({ timeout: to(15000) });
    await expect(page.locator("#login-box")).toHaveCount(0); // no longer on the login page

    // The activation link is single-use: setting the password consumed the
    // request (resethash cleared), so the link no longer verifies anyone.
    await page.goto("./?logout=1");
    await page.goto(link!);
    await expect(page.getByText("The account request is not valid")).toBeVisible({ timeout: to(15000) });

    // The chosen password is the one stored, not just the session the
    // verification left open: sign in again with it.
    await page.locator('input[name="email"]').fill(MEMBER_EMAIL);
    await page.locator('input[name="password"]').fill(NEW_PASSWORD);
    await page.locator('input[name="login-request"]').click();
    await expect(page.locator("#login-box")).toHaveCount(0);
});
