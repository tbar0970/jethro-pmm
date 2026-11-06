import { test, expect } from "../fixtures.js";
import { getMessage, isTransientSocketError, searchMessages, waitForMessage, type MailpitMessage } from "../email/mailpit-api.js";
import { DEMO_USERNAME, PERSONA_PASSWORD } from "../auth.js";
import { to } from "../timeouts.js";

/**
 * Member self-registration round-trip (the oldest server-sent email flow in
 * Jethro): the members-area login page emails an activation link, following it
 * signs the member in and asks them to choose a password.
 *
 * Email capture is the `mailpit` process-compose service (see
 * DEVELOPMENT_FUNCTESTS.md, "8. Email capture — MailPit (Unix domain sockets)"). The member-rego settings
 * come from the demo database (settings table → constants via
 * Config_Manager::defineConstant): MEMBER_REGO_EMAIL_SUBJECT is
 * "Setting up your account", MEMBER_REGO_EMAIL_FROM_ADDRESS and
 * MEMBER_REGO_FAILURE_EMAIL are jethro@localhost.
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

// The shared mailbox and subject of the "unknown email" notification
// (MEMBER_REGO_FAILURE_EMAIL, and the subject hard-coded in
// members/include/member_user_system.class.php).
const REGO_FAILURE_EMAIL = "jethro@localhost";
const REGO_FAILURE_SUBJECT = "Member Account request from unknown email";

/**
 * The unknown-email notification naming $address, polling until it arrives.
 *
 * waitForMessage() cannot scope this one: every run notifies the same
 * REGO_FAILURE_EMAIL with the same subject, so recipient + subject + notBefore
 * match other workers' notifications too, and the newest match is usually one
 * of theirs. The body is the only part that names the address this run
 * submitted, so scan every matching message created after $notBefore and
 * return the one whose Text contains $address.
 *
 * The scan rides transient unix-socket transport failures for the whole window
 * exactly like waitForMessage(): MailPit is a process-compose service with
 * restart: "always", so a restart mid-poll must not fail the test outright.
 * Real faults (HTTP errors, malformed responses) still surface immediately.
 */
async function waitForFailureNotificationNaming(address: string, notBefore: number): Promise<MailpitMessage> {
    const timeoutMs = to(15000);
    const deadline = timeoutMs > 0 ? Date.now() + timeoutMs : Number.POSITIVE_INFINITY;
    let lastError: unknown = null;
    while (Date.now() < deadline) {
        try {
            const hits = await searchMessages({ to: REGO_FAILURE_EMAIL, subject: REGO_FAILURE_SUBJECT });
            for (const hit of hits) {
                const created = new Date(hit.Created).getTime();
                if (Number.isNaN(created) || created < notBefore) continue;
                const msg = await getMessage(hit.ID);
                if (msg.Text.includes(address)) return msg;
            }
        } catch (err) {
            if (!isTransientSocketError(err)) throw err;
            lastError = err;
        }
        await sleep(500);
    }
    const reason = lastError instanceof Error ? `; last error: ${lastError.message}` : "";
    throw new Error(`No MailPit notification naming ${address} after ${new Date(notBefore).toISOString()} within ${timeoutMs}ms${reason}`);
}

/** Sleep for `ms` milliseconds between polls. */
function sleep(ms: number): Promise<void> {
    const { promise, resolve } = Promise.withResolvers<void>();
    setTimeout(resolve, ms);
    return promise;
}

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

test("member registration: invalid email address is rejected on the form", async ({ page }) => {
    await page.goto("./index.php");
    // input[type=email] makes the browser block submission before the server sees
    // it. Relax the type so this test exercises the server-side validation
    // branch (the contract for non-browser clients), which Emailer::validateAddress()
    // backs. No email is attempted for an invalid address.
    await page.locator('input[name="email"]').evaluate((el) => el.setAttribute("type", "text"));
    await page.locator('input[name="email"]').fill("not-an-email");
    await page.locator('input[name="password-request"]').click();
    await expect(page.getByText("You have entered an invalid email address")).toBeVisible({ timeout: to(15000) });
});

test("member registration: unknown email notifies the rego failure address", async ({ page }) => {
    const notBefore = Date.now();
    // Unique per run so the body filter below cannot match another run's
    // notification: Date.now() alone can repeat within a millisecond across
    // parallel workers.
    const unknown = `no-such-person-${crypto.randomUUID().slice(0, 8)}@example.org`;

    await page.goto("./index.php");
    await page.locator('input[name="email"]').fill(unknown);
    await page.locator('input[name="password-request"]').click();
    await expect(page.getByText("Account request received")).toBeVisible({ timeout: to(15000) });

    const msg = await waitForFailureNotificationNaming(unknown, notBefore);
    expect(msg.Text).toContain(unknown);
});

test("admin sends a member activation email from the person page", async ({ page }) => {
    const notBefore = Date.now();

    // The staff app lives at the webroot; this project's baseURL is /members/.
    await page.goto("/");
    await page.locator('input[name="username"]').fill(DEMO_USERNAME);
    await page.locator('input[name="password"]').fill(PERSONA_PASSWORD);
    await page.locator('input[value="Log In"]').click();
    await expect(page.locator('input[value="Log In"]')).not.toBeVisible({ timeout: to(10000) });

    await page.goto("/?view=persons&personid=11"); // Sena Mann: non-staff demo person
    await page.locator('a[href="#accounts"]').click();
    await page.getByRole("button", { name: /activation email/ }).click();
    await expect(page.getByText(/activation email has been sent/i)).toBeVisible({ timeout: to(15000) });

    const msg = await waitForMessage(
        { to: "sena.mann@example.org", subject: "Setting up your account" },
        notBefore,
        to(15000),
    );
    expect(msg.Text).toContain("activate your");
});
