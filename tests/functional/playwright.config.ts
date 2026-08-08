import { defineConfig } from "@playwright/test";

/**
 * Playwright functional tests for Jethro.
 *
 * The functest server uses FUNCTEST_WEB_PORT, resolved from _default_ by
 * devbox.json's init hook unless explicitly overridden. Run tests via ./devbox.
 * See docs/docs/developer/reference/JETHRO_UTILS.md.
 * Each SMS scenario gets its own Jethro instance at /tests/functional/sms/{name}/.
 * Tests assume the database has been loaded with demo data by functest_databases_setup.
 */

const FUNCTEST_HOST = process.env.FUNCTEST_WEB_HOST || "127.0.0.1";
const PORT = process.env.FUNCTEST_WEB_PORT;
if (!PORT) {
  throw new Error("FUNCTEST_WEB_PORT is not set: run via ./devbox (e.g. `./devbox run functests`)");
}
const HOST = `http://${FUNCTEST_HOST}:${PORT}`;

const SMS_SCENARIOS = [
  "sms-bulk",
  // Sends, like sms-bulk, but on its own mock profile: both specs assert on
  // the mock's per-profile lastPost recording and run in parallel.
  "sms-bulk-interactive",
  "sms-sender-options",
  "sms-sender-default",
  "sms-unicode-when-free",
  "sms-unicode-always",
  "sms-unicode-never",
  "sms-admin-history-filters",
  "sms-send-single",
  "sms-schedule-and-cancel",
  "sms-per-recipient-override",
  "sms-opted-out-recipient",
  "sms-url-shortening-preview",
  "sms-failed-send",
  // Zero credit: the statusline warns and blocks Send.
  "sms-insufficient-balance",
  // No mobile number: the statusline says who would be left out, and blocks
  // Send when that is everyone.
  "sms-left-out-recipients",
  // %selected.firstname%: family mode names the selected children.
  "sms-selected-token",
  "sms-partial-failure",
  "sms-cancel-permissions",
  // kidscare (no SMS perms) is refused by every SMS endpoint.
  "sms-permissions",
  "sms-sender-number-registration",
  "sms-2fa",
  // 2FA fails closed: login refused when the gateway rejects the code send,
  // or when conf.php still has a legacy 2FA_SMS_* setting.
  "sms-2fa-gateway-down",
  "sms-2fa-misconfigured",
  "sms-cooloff",
  "sms-messages-page",
  // Restricted staff persona 6pmonly sees only deliveries to persons it may see.
  "sms-restricted-visibility",
  "5centsms-senderid-cache-test",
  "sms-wrong-profile",
  // Brown family members share one mobile: one SMS and one preview row per number.
  "sms-shared-mobile",
  // Default 30s cool-off: 'scheduled' on the Messages page, then 'sent' via polling.
  "sms-scheduled-status",
  // A sent message is polled on to delivered (not left at ✓).
  "sms-delivered-status",
  // The SMS status panel reports the provider's credit balance.
  "sms-status-panel",
];

// Provider-sensitive scenarios (schedule/cancel, cool-off, status lifecycle,
// send failure, opt-outs, sender registration, history) also run against each
// of MATRIX_PROVIDERS: the same spec, with its own <name>-<suffix>.conf and
// mock profile. sms/scenario.ts reads each project's metadata.smsProvider.
const PROVIDER_MATRIX = [
  "sms-schedule-and-cancel",
  "sms-cooloff",
  "sms-scheduled-status",
  "sms-delivered-status",
  "sms-failed-send",
  "sms-opted-out-recipient",
  "sms-sender-number-registration",
  "sms-admin-history-filters",
  "sms-status-panel",
  "sms-insufficient-balance",
];
const MATRIX_PROVIDERS = [
  { suffix: "v5", smsProvider: "5centsmsv5" },
  { suffix: "mobilemessage", smsProvider: "mobilemessage" },
];
 
export default defineConfig({
  testDir: ".",
  // Cancels the scheduled-SMS polling backlog left by previous runs.
  globalSetup: "./global-setup.ts",
  // Under the Inspector (--debug / PWDEBUG=1) disable all timeouts so paused
  // PHP breakpoints (Xdebug step-debugging) can't fail the test mid-session.
  timeout: process.env.PWDEBUG ? 0 : 30000,
  fullyParallel: true,
  workers: 8,
  expect: { timeout: process.env.PWDEBUG ? 0 : 10000 },
  use: { browserName: "chromium", baseURL: `${HOST}/`, screenshot: "only-on-failure" },
  projects: [
    ...SMS_SCENARIOS.map((name) => ({
      name,
      use: { baseURL: `${HOST}/tests/functional/sms/${name}/` },
      testMatch: [`sms/${name}.spec.ts`],
      ...(PROVIDER_MATRIX.includes(name) ? { metadata: { smsProvider: "cellcast" } } : {}),
    })),
    ...MATRIX_PROVIDERS.flatMap(({ suffix, smsProvider }) =>
      PROVIDER_MATRIX.map((name) => ({
        name: `${name}-${suffix}`,
        use: { baseURL: `${HOST}/tests/functional/sms/${name}-${suffix}/` },
        testMatch: [`sms/${name}.spec.ts`],
        metadata: { smsProvider },
      })),
    ),
    // The mock answers as the live APIs did: bin/record against the mock,
    // diffed with smsmockserver/recordings/<provider>/actual/. No browser.
    { name: "smsmock-recordings", testMatch: ["smsmock/recordings.spec.ts"] },
    { name: "lookaround", testMatch: ["lookaround/lookaround.spec.ts"] },
    {
      // Member self-registration: emailed activation link -> set password ->
      // logged in. Uses the MailPit capture (email/mailpit-api.ts) and reads the
      // standard demo database, whose settings table supplies the MEMBER_REGO_* values.
      name: "member-rego",
      testMatch: ["members/member-rego.spec.ts"],
      use: { baseURL: `${HOST}/members/` },
    },
    // CSV person importer (#1577); reads the standard demo database at the root URL.
    { name: "import", testMatch: ["import/import.spec.ts"] },
    {
      // Members-area regressions (#1173, #1433) and the post-login redirect.
      // The members area lives at /members/ under the shared functest webroot
      // and reads the standard demo database, so no scenario .conf is needed.
      name: "members",
      testMatch: ["members/members.spec.ts"],
      use: { baseURL: `${HOST}/members/` },
    },
    {
      name: "walkthrough",
      testMatch: ["walkthrough/walkthrough.spec.ts"],
      // Served under the /tests/functional/walkthrough/walkthrough/ prefix; the
      // walkthrough.conf scenario (required by the functest conf.php) points
      // the app at the empty jethro_functest_walkthrough database.
      use: { baseURL: `${HOST}/tests/functional/walkthrough/` },
    },
  ],
});
