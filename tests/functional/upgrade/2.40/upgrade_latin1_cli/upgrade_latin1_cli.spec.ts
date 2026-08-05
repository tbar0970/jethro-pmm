import { test, expect } from "@playwright/test";
import { execSync } from "node:child_process";
import * as path from "node:path";
import { fileURLToPath } from "node:url";

test.describe.configure({ mode: "serial" });

const __filename = fileURLToPath(import.meta.url);
const scriptDir = path.dirname(__filename);

test.beforeAll(() => {
  execSync(`bash "${path.join(scriptDir, "setup.sh")}"`, { stdio: "inherit" });
});

test("CLI latin1-to-utf8mb4 upgrade", async () => {
  // Run the CLI upgrader against the test database.
  execSync(`bash "${path.join(scriptDir, "run_upgrader.sh")}"`, {
    stdio: "inherit",
  });

  // Post-upgrade validation: check collations and data integrity.
  execSync(`bash "${path.join(scriptDir, "validate.sh")}"`, {
    stdio: "inherit",
  });
});
