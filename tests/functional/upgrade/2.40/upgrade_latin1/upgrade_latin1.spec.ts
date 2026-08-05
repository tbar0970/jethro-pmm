import { test, expect } from "@playwright/test";
import { execSync } from "node:child_process";
import * as path from "node:path";
import { fileURLToPath } from "node:url";
import { login } from "../../../auth.js";

test.describe.configure({ mode: "serial" });

const __filename = fileURLToPath(import.meta.url);
const scriptDir = path.dirname(__filename);

test.beforeAll(() => {
  execSync(`bash "${path.join(scriptDir, "setup.sh")}"`, { stdio: "inherit" });
});

test("latin1-to-utf8mb4 upgrade", async ({ page }) => {
  await login(page);

  // After login, Charset_Fixer runs on the home page because
  // NEEDS_UTF8MB4_UPGRADE was set by setup.sh. Verify key output lines.
  // ALTER DATABASE (third line) requires SUPER which the functest user
  // lacks, so only check the first two structural lines.
  const body = page.locator("body");
  await expect(body).toContainText(/FIXING DATABASE ENCODINGS FOR JETHRO/);
  await expect(body).toContainText(/table\(s\) converted to utf8mb4_unicode_ci/);

  // Verify the persons list shows demo data names.
  await page.goto("?view=persons__list_all");
  await expect(page.locator("body")).toContainText("Déññïs Dëmø");

  // Post-upgrade validation.
  execSync(`bash "${path.join(scriptDir, "validate.sh")}"`, { stdio: "inherit" });
});
