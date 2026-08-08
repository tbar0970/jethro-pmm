import { execFileSync } from "node:child_process";
import { execSync } from "node:child_process";
import { realpathSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

// <root>/tests/functional/global-setup.ts -> <root>
const PROJECT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);
// Mirrors HOST in playwright.config.ts, which reads the same env vars.
const FUNCTEST_HOST = process.env.FUNCTEST_WEB_HOST || "127.0.0.1";
const FUNCTEST_PORT = process.env.FUNCTEST_WEB_PORT || "8089";
const FUNCTEST_BASE_URL = `http://${FUNCTEST_HOST}:${FUNCTEST_PORT}`;

/**
 * Every checkout of this repo shares one FUNCTEST_WEB_PORT, so a stale
 * functest_jethro_server from another checkout can silently own the port.
 * This checkout's own functest nginx then fails to bind and gives up
 * (process-compose restart cap), and the suite would run against the wrong
 * code and database — with confusing symptoms like "table sms_note doesn't
 * exist" fatals from a schema this tree doesn't have.
 *
 * The functest webroot serves functestinfo.php, which prints the serving
 * checkout's path as JSON. Refuse to run unless it matches this checkout.
 */
async function assertFunctestServerIsThisCheckout() {
  let res: Response;
  try {
    res = await fetch(`${FUNCTEST_BASE_URL}/functestinfo.php`, { signal: AbortSignal.timeout(10_000) });
  } catch {
    throw new Error(
      `Functest server not reachable at ${FUNCTEST_BASE_URL}. ` +
        "Start it with: devbox services start functest_jethro_server",
    );
  }
  let servedPath: string | undefined;
  if (res.ok) {
    const info = (await res.json().catch(() => null)) as { instance_path?: unknown } | null;
    servedPath = typeof info?.instance_path === "string" ? info.instance_path : undefined;
  }
  if (!servedPath) {
    throw new Error(
      `The server at ${FUNCTEST_BASE_URL} did not report its Jethro instance path ` +
        `(HTTP ${res.status}, or unparseable JSON, from /functestinfo.php). It is probably a ` +
        "checkout older than functestinfo.php, or not a functest Jethro at all. Stop whatever " +
        `owns port ${FUNCTEST_PORT}, then: devbox services start functest_jethro_server`,
    );
  }
  const expectedPath = realpathSync(PROJECT_ROOT);
  if (servedPath !== expectedPath) {
    throw new Error(
      `Port hijack: the functest server at ${FUNCTEST_BASE_URL} is serving Jethro from ` +
        `${servedPath}, but these tests are for ${expectedPath}. Tests would run against ` +
        "the wrong code and database. To fix:\n" +
        `  devbox -c ${servedPath} services stop functest_jethro_server\n` +
        "  devbox services start functest_jethro_server",
    );
  }
}
/**
 * Suite-wide setup, run once before any test.
 *
 * Compiles the css/js bundles for the current JETHRO_VERSION.
 *
 * jethro_compile is resolved from PATH (devbox puts devbox.d/bin on PATH) and,
 * given no arguments, defaults to $JETHRO_VERSION. We run it from the project
 * root so it can find resources/{css,js}. A non-zero exit throws, aborting the
 * run before any test.
 *
 * Cancels SMS deliveries left in 'scheduled' by earlier runs of this suite.
 *
 * A scheduled delivery renders a Datastar polling span on every page that
 * lists it — `?call=sms_info`, as often as every 2 seconds per batch for the
 * first hour after its send time (see smsScheduledPollIntervalSecs()) — and
 * each first-in-batch poll makes an HTTP call from PHP back into the same
 * php-fpm pool that is serving the pages. The specs that schedule sends
 * (sms-cooloff, sms-schedule-and-cancel, sms-messages-page) leave those rows
 * behind, so without this the polling load grows with every run until the pool
 * saturates and unrelated tests start timing out.
 *
 * functest_databases_setup loads demo data that contains no scheduled
 * deliveries, so anything still scheduled at suite start is a leftover.
 * (`mariadb` needs no credentials: devbox.d/mariadb/my.cnf defaults to the
 * jethro user, and devbox.d/bin is on PATH in the devbox shell.)
 */
export default async function globalSetup() {
  await assertFunctestServerIsThisCheckout();
  execFileSync("jethro_compile", [], { cwd: PROJECT_ROOT, stdio: "inherit" });
}
