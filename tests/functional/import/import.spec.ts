import { type Page } from "@playwright/test";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";
import { to } from "../timeouts.js";

/**
 * CSV person importer (?view=admin__import): upload → preview → import.
 *
 * Every spec imports persons whose names carry a random tag, so runs stay
 * isolated in the shared jethro_functest database. Values used below
 * (congregation "6pm", status "Core", age bracket "Adult") are demo-data
 * reference records.
 *
 * Matching rules under test (docs/docs/developer/reference/import.md):
 *   - the personid column (or "ID", as Jethro's own CSV exports name it) is
 *     optional;
 *   - a non-blank ID matches that person exactly, but only when "Update
 *     existing persons" is ticked, and an unknown ID is a row error;
 *   - rows without an ID fall back to first + last name matching.
 */

const HEADER = "family_name,last_name,first_name,congregation,status,age_bracket,gender,email";

/** Random letters-only tag, unique per call, safe in person names. */
function uniqueTag(): string {
  let tag = "";
  for (let i = 0; i < 8; i++) tag += String.fromCharCode(97 + Math.floor(Math.random() * 26));
  return "Imp" + tag;
}

/** A CSV data row for an adult in the 6pm congregation. */
function personRow(familyName: string, firstName: string, email = ""): string {
  return `${familyName},${familyName},${firstName},6pm,Core,Adult,male,${email}`;
}

/**
 * Upload a CSV on the import page and submit the first stage.
 * All imported persons are recorded in a new group named $groupName.
 */
async function uploadCsv(
  page: Page,
  csv: string,
  { groupName, matchExisting = true }: { groupName: string; matchExisting?: boolean },
): Promise<void> {
  await page.goto("./?view=admin__import");
  await page.locator('input[type="file"][name="import"]').setInputFiles({
    name: "import.csv",
    mimeType: "text/csv",
    buffer: Buffer.from(csv + "\n"),
  });
  await page.locator('input[name="new_group_name"]').fill(groupName);
  await page.locator('input[name="match_existing"]').setChecked(matchExisting);
  await page.locator('input[type="submit"][value^="Continue"]').click();
}

/** The value cell of a row in the confirm-stage summary table. */
function summaryValue(page: Page, label: string) {
  return page.locator("table tr", { has: page.locator("td", { hasText: label }) }).locator("td").nth(1);
}

/** Confirm the previewed import and wait for the redirect to the import's group. */
async function proceed(page: Page): Promise<void> {
  page.once("dialog", (dialog) => dialog.accept());
  await Promise.all([
    page.waitForURL(/view=groups&groupid=\d+/, { timeout: to(20000) }),
    page.locator('input[name="confirm_import"]').click(),
  ]);
  await expect(page.locator(".alert-success")).toContainText("Import complete");
}

/** Import one new person in their own family; returns their person ID. */
async function importNewPerson(page: Page, familyName: string, firstName: string): Promise<number> {
  await uploadCsv(page, [HEADER, personRow(familyName, firstName)].join("\n"), {
    groupName: `${familyName} setup`,
  });
  await expect(summaryValue(page, "New persons to be created")).toHaveText("1 within new families");
  await proceed(page);
  const personid = await page
    .locator("#body tr[data-personid]", { hasText: `${firstName} ${familyName}` })
    .getAttribute("data-personid");
  const id = Number(personid);
  expect(id).toBeGreaterThan(0);
  return id;
}

test.describe("CSV person import", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("imports new persons from a CSV without a personid column (#1577)", async ({ page }) => {
    const family = uniqueTag();
    await uploadCsv(page, [HEADER, personRow(family, "Adam"), personRow(family, "Beth")].join("\n"), {
      groupName: `${family} group`,
    });

    await expect(page.locator("#body")).not.toContainText("must contain");
    await expect(page.locator("#body")).toContainText("Your import is ready to run");
    await expect(summaryValue(page, "New families to be created")).toHaveText("1");
    await expect(summaryValue(page, "New persons to be created")).toHaveText("2 within new families");

    await proceed(page);
    await expect(page.locator("#body h1")).toContainText(`${family} group`);
    await expect(page.locator("#body")).toContainText(`Adam ${family}`);
    await expect(page.locator("#body")).toContainText(`Beth ${family}`);
  });

  test("matches an existing person by first and last name", async ({ page }) => {
    const family = uniqueTag();
    const id = await importNewPerson(page, family, "Carl");

    await uploadCsv(page, [HEADER, personRow(family, "Carl", `carl.${family}@example.com`)].join("\n"), {
      groupName: `${family} update`,
    });
    await expect(summaryValue(page, "Existing persons to be updated")).toHaveText("1");
    await expect(summaryValue(page, "New persons to be created")).toHaveText("0");
    await expect(page.locator(`a[href="?view=persons&personid=${id}"]`)).toBeVisible();
  });

  for (const idHeader of ["personid", "ID"]) {
    test(`matches an existing person by the "${idHeader}" column`, async ({ page }) => {
      const family = uniqueTag();
      const id = await importNewPerson(page, family, "Dora");
      const email = `dora.${family.toLowerCase()}@example.com`;

      // The ID wins over the (different) names; the blank-ID row that follows
      // joins the matched person's family as a new member.
      await uploadCsv(
        page,
        [
          `${idHeader},${HEADER}`,
          `${id},${personRow(family, "Renamed", email)}`,
          `,${personRow(family, "Eric")}`,
        ].join("\n"),
        { groupName: `${family} update` },
      );
      await expect(page.locator("#body")).not.toContainText("Unrecognised column");
      await expect(summaryValue(page, "Existing persons to be updated")).toHaveText("1");
      await expect(summaryValue(page, "New persons to be created")).toHaveText("1 within existing families");
      await expect(summaryValue(page, "New families to be created")).toHaveText("0");

      await proceed(page);
      await expect(page.locator("#body")).toContainText(`Dora ${family}`);
      await expect(page.locator("#body")).toContainText(`Eric ${family}`);
      await expect(page.locator("#body")).not.toContainText(`Renamed ${family}`);

      await page.goto(`./?view=persons&personid=${id}`);
      await expect(page.locator("#body")).toContainText(email);
    });
  }

  test("rejects a personid that matches no person", async ({ page }) => {
    const family = uniqueTag();
    await uploadCsv(page, [`personid,${HEADER}`, `2147483000,${personRow(family, "Fred")}`].join("\n"), {
      groupName: `${family} group`,
    });
    await expect(page.locator(".alert-error, .alert-danger")).toContainText(
      `Row 1 (Fred ${family}): There is no person with ID 2147483000`,
    );
    await expect(page.locator("#body")).not.toContainText("Your import is ready to run");
  });

  test("rejects a personid when updating existing persons is disabled", async ({ page }) => {
    const family = uniqueTag();
    const id = await importNewPerson(page, family, "Gina");

    await uploadCsv(page, [`personid,${HEADER}`, `${id},${personRow(family, "Gina")}`].join("\n"), {
      groupName: `${family} group`,
      matchExisting: false,
    });
    await expect(page.locator(".alert-error, .alert-danger")).toContainText(
      `Row 1 (Gina ${family}): Person ID ${id} was supplied but updating existing persons is not enabled`,
    );
    await expect(page.locator("#body")).not.toContainText("Your import is ready to run");
  });
});
