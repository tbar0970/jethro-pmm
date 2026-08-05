import { execFileSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";

// <root>/tests/functional/global-setup.ts -> <root>
const PROJECT_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "../..",
);

/**
 * Compiles the css/js bundles for the current JETHRO_VERSION.
 *
 * jethro_compile is resolved from PATH (devbox puts devbox.d/bin on PATH) and,
 * given no arguments, defaults to $JETHRO_VERSION. We run it from the project
 * root so it can find resources/{css,js}. A non-zero exit throws, aborting the
 * run before any test.
 */
export default function globalSetup(): void {
  execFileSync("jethro_compile", [], { cwd: PROJECT_ROOT, stdio: "inherit" });
}
