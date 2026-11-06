import { defineConfig } from "@playwright/test";

/**
 * Playwright functional tests for Jethro.
 *
 * The functest server uses FUNCTEST_WEB_PORT, resolved from _default_ by
 * devbox.json's init hook unless explicitly overridden. Run tests via ./devbox.
 * See docs/docs/developer/reference/JETHRO_UTILS.md.
 * Tests assume the database has been loaded with demo data by functest_databases_setup.
 */

const FUNCTEST_HOST = process.env.FUNCTEST_WEB_HOST || "127.0.0.1";
const PORT = process.env.FUNCTEST_WEB_PORT;
if (!PORT) {
  throw new Error("FUNCTEST_WEB_PORT is not set: run via ./devbox (e.g. `./devbox run functests`)");
}
const HOST = `http://${FUNCTEST_HOST}:${PORT}`;

export default defineConfig({
  globalSetup: "./global-setup.ts",
  testDir: ".",
  // Under the Inspector (--debug / PWDEBUG=1) disable all timeouts so paused
  // PHP breakpoints (Xdebug step-debugging) can't fail the test mid-session.
  timeout: process.env.PWDEBUG ? 0 : 30000,
  fullyParallel: true,
  workers: 8,
  expect: { timeout: process.env.PWDEBUG ? 0 : 10000 },
  use: { browserName: "chromium", baseURL: `${HOST}/`, screenshot: "only-on-failure" },
  projects: [
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
