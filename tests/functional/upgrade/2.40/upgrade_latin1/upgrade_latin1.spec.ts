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

  // NEEDS_UTF8MB4_UPGRADE was set by setup.sh. The home page must now show a
  // sysadmin notice rather than auto-applying the (irreversible) fix.
  const body = page.locator("body");
  await expect(body).toContainText(/This database needs a character-set upgrade to utf8mb4/);

  // The operator reviews and applies the fix from Admin → Upgrade.
  await page.goto("?view=admin__upgrade");
  await page.locator('input[name="fix_database_charset"]').click();
  await expect(page.locator("body")).toContainText(/table\(s\) converted to utf8mb4_unicode_ci/);

  // Applying the fix clears the flag, so the home-page notice is gone.
  await page.goto("./index.php");
  await expect(page.locator("body")).not.toContainText(/This database needs a character-set upgrade/);

  // Verify the persons list shows demo data names.
  await page.goto("?view=persons__list_all");
  await expect(page.locator("body")).toContainText("Déññïs Dëmø");

  // Post-upgrade validation.
  execSync(`bash "${path.join(scriptDir, "validate.sh")}"`, { stdio: "inherit" });
});
