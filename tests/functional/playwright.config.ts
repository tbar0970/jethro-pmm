import { defineConfig } from "@playwright/test";

/**
 * Playwright functional tests for Jethro.
 *
 * The functest Jethro server runs on http://127.0.0.1:${FUNCTEST_WEB_PORT:-8089}
 * (see process-compose.yml); port is read from FUNCTEST_WEB_PORT or defaults to 8089.
 * Tests assume the database has been loaded with demo data by functest_database_setup.
 */

const PORT = process.env.FUNCTEST_WEB_PORT || "8089";
const FUNCTEST_HOST = process.env.FUNCTEST_WEB_HOST || "127.0.0.1";
const HOST = `http://${FUNCTEST_HOST}:${PORT}`;

export default defineConfig({
  testDir: ".",
  // Under the Inspector (--debug / PWDEBUG=1) disable all timeouts so paused
  // PHP breakpoints (Xdebug step-debugging) can't fail the test mid-session.
  timeout: process.env.PWDEBUG ? 0 : 30000,
  fullyParallel: true,
  workers: 4,
  expect: { timeout: process.env.PWDEBUG ? 0 : 10000 },
  use: { browserName: "chromium", baseURL: `${HOST}/`, screenshot: "only-on-failure" },
  projects: [
    { name: "lookaround", testMatch: ["lookaround/lookaround.spec.ts"] },
    {
      name: "walkthrough",
      testMatch: ["walkthrough/walkthrough.spec.ts"],
      // Served under the /tests/functional/walkthrough/walkthrough/ prefix; the
      // walkthrough.conf scenario (required by the functest conf.php) points
      // the app at the empty jethro_functest_walkthrough database.
      use: { baseURL: `${HOST}/tests/functional/walkthrough/` },
    },
    {
      name: "upgrade_latin1",
      testMatch: ["upgrade/2.40/upgrade_latin1/upgrade_latin1.spec.ts"],
      use: { baseURL: `${HOST}/tests/functional/upgrade/2.40/upgrade_latin1/` },
    },
    {
      name: "upgrade_utf8mb3",
      testMatch: ["upgrade/2.40/upgrade_utf8mb3/upgrade_utf8mb3.spec.ts"],
      use: { baseURL: `${HOST}/tests/functional/upgrade/2.40/upgrade_utf8mb3/` },
    },
    {
      name: "upgrade_latin1_cli",
      testMatch: ["upgrade/2.40/upgrade_latin1_cli/upgrade_latin1_cli.spec.ts"],
      use: { baseURL: `${HOST}/tests/functional/upgrade/2.40/upgrade_latin1_cli/` },
    },
  ],
});
