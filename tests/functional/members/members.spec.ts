import { execFileSync } from "node:child_process";
import { randomUUID } from "node:crypto";
import { test, expect } from "../fixtures.js";
import { to } from "../timeouts.js";
import { type Page } from "@playwright/test";

/**
 * Members-area regressions for restricted-staff visibility (#1173, #1433).
 *
 * Magdalena Luther ('kidscare' staff account, visibility restricted to
 * congregation 3) logs in to the members area.  Her family (the Luthers,
 * family 3) spans congregations: Martin (#4), Katharina (#5) and Hans (#6)
 * are congregation 2.  The member view shows the whole family, but before
 * the members-area/staff DB identity separation the shared 'person' view
 * applied her staff restrictions, so family members outside congregation 3
 * could not be loaded:
 *
 *  - ?view=_edit_me silently skipped them (#1173; the member view listed
 *    them, getDBObject('person', id) returned null);
 *  - a planned absence for Martin covering a rostered date crashed in
 *    Person::toString with 'Undefined array key first_name' (#1433).
 *
 * functest_databases_setup (process-compose.yml) seeds person 185 with the
 * standard demo password and a login email; Martin Luther is rostered as
 * Preacher in the demo data.
 */

const MEMBER_EMAIL = "magdalena.luther@example.org";
// Same well-known password as the staff personas (staff_member id 1).
const MEMBER_PASSWORD = "qfntt7eYuwHs123";
const ADULT_MEMBER_EMAIL = "nathanial.adams@example.org";

async function memberLogin(page: Page, email: string = MEMBER_EMAIL): Promise<void> {
  await page.goto("./index.php");
  const loginBox = page.locator("#login-box");
  if (await loginBox.isVisible().catch(() => false)) {
    await loginBox.locator('input[name="email"]').fill(email);
    await loginBox.locator('input[name="password"]').fill(MEMBER_PASSWORD);
    await Promise.all([
      page.waitForLoadState("load", { timeout: to(1000) }),
      loginBox.locator('input[name="login-request"]').click(),
    ]);
  }
  // Successfully logged in: the login form is gone.
  await expect(page.locator("#login-box")).toHaveCount(0);
}

test.describe("Members area with restricted staff visibility", () => {
  test("#1173: family members outside the staff restriction appear on the edit page", async ({ page }) => {
    await memberLogin(page);
    await page.goto("./?view=_edit_me");
    // Martin (#4), Katharina (#5) and Hans (#6) are congregation 2.  Before
    // the fix the person view hid them from kidscare's staff account, so
    // edit_me silently skipped them (and warned about hidden members).
    // There are two Martins in the family (#4 and #186): both must appear.
    await expect(page.getByRole("heading", { name: "Martin Luther" })).toHaveCount(2);
    await expect(page.getByRole("heading", { name: "Katharina von Bora" })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Hans Luther" })).toBeVisible();
    // Magdalena is a minor, so only her own form is editable - but nobody
    // may be reported as hidden any more.
    await expect(page.locator(".alert-info")).toHaveCount(0);
  });

  test("#1173: adult member can edit restricted-visibility family members", async ({ page }) => {
    // Nathanial Adams ('smallgroupleader', restricted to groups 9 and 11):
    // none of his family are in those groups, so before the fix only his own
    // form rendered.  Jong (#168), Ngoc (#171) and Harris (#172) are
    // non-archived and must now be editable.
    await memberLogin(page, ADULT_MEMBER_EMAIL);
    await page.goto("./?view=_edit_me");
    await expect(page.locator('input[name="person_168email"]')).toHaveCount(1); // Jong
    await expect(page.locator('input[name="person_171email"]')).toHaveCount(1); // Ngoc
    await expect(page.locator('input[name="person_172email"]')).toHaveCount(1); // Harris
  });

  test("#1433: roster-clash message names the family member", async ({ page }) => {
    await memberLogin(page);
    await page.goto("./?view=_add_planned_absence");
    // Whole family is ticked by default; Martin (person 4, first in the
    // list) is rostered in the demo data, so the save must be refused with
    // his name in the message - not a stack trace (fixtures guard).
    await expect(page.locator('input[name="personid[]"][value="4"]')).toBeChecked();
    const start = new Date(Date.now() + 2 * 864e5);
    const end = new Date(Date.now() + 300 * 864e5); // wide enough to hit a Martin roster date
    await page.fill('input[name="start_date_d"]', String(start.getDate()));
    await page.selectOption('select[name="start_date_m"]', String(start.getMonth() + 1));
    await page.fill('input[name="start_date_y"]', String(start.getFullYear()));
    await page.fill('input[name="end_date_d"]', String(end.getDate()));
    await page.selectOption('select[name="end_date_m"]', String(end.getMonth() + 1));
    await page.fill('input[name="end_date_y"]', String(end.getFullYear()));
    await page.locator("#add-planned_absence button[type=submit]").click();
    // Refused with the roster-clash message naming Martin; nothing saved.
    await expect(page.locator("#body")).toContainText(
      "Martin Luther is already assigned to roster roles during the absent period",
    );
  });

  test("planned absence without roster clash still saves", async ({ page }) => {
    await memberLogin(page);
    await page.goto("./?view=_add_planned_absence");
    // Untick everyone except Katharina (#5, not rostered in the demo data).
    for (const id of ["4", "6", "7", "185", "186", "187", "188"]) {
      await page.uncheck(`input[name="personid[]"][value="${id}"]`);
    }
    const start = new Date(Date.now() + 2 * 864e5);
    const end = new Date(Date.now() + 30 * 864e5);
    await page.fill('input[name="start_date_d"]', String(start.getDate()));
    await page.selectOption('select[name="start_date_m"]', String(start.getMonth() + 1));
    await page.fill('input[name="start_date_y"]', String(start.getFullYear()));
    await page.fill('input[name="end_date_d"]', String(end.getDate()));
    await page.selectOption('select[name="end_date_m"]', String(end.getMonth() + 1));
    await page.fill('input[name="end_date_y"]', String(end.getFullYear()));
    await page.locator("#add-planned_absence button[type=submit]").click();
    await expect(page.locator("#body")).toContainText("Planned absence saved");
  });
});

/**
 * A successful members login must redirect (Post/Redirect/Get), as the staff
 * login does.  Without the redirect the logged-in page is the response to the
 * login POST, so reloading or restoring the tab re-sends the email and
 * password.
 */
test.describe("Members login redirects after success", () => {
  async function submitLogin(page: Page, path: string): Promise<void> {
    await page.goto(path);
    const loginBox = page.locator("#login-box");
    await loginBox.locator('input[name="email"]').fill(MEMBER_EMAIL);
    await loginBox.locator('input[name="password"]').fill(MEMBER_PASSWORD);
    await Promise.all([
      page.waitForURL(/view=/, { waitUntil: "load", timeout: to(5000) }),
      loginBox.locator('input[name="login-request"]').click(),
    ]);
    await expect(page.locator("#login-box")).toHaveCount(0);
  }

  test("lands on the requested page, and reloading it is a plain GET", async ({ page }) => {
    await submitLogin(page, "./?view=rosters");
    expect(new URL(page.url()).searchParams.get("view")).toBe("rosters");

    const [reload] = await Promise.all([
      page.waitForRequest((r) => r.isNavigationRequest()),
      page.reload(),
    ]);
    expect(reload.method()).toBe("GET");
    await expect(page.locator("#login-box")).toHaveCount(0);
  });

  test("reloading after the session cookie is lost shows a clean login form", async ({ page }) => {
    await submitLogin(page, "./?view=home");
    // As when a phone discards the browser-session cookie overnight.
    await page.context().clearCookies();
    await page.reload();

    const loginBox = page.locator("#login-box");
    await expect(loginBox).toBeVisible();
    await expect(loginBox.locator(".alert-error")).toHaveCount(0);
  });
});

test.describe("Staff recovery contacts are protected in the members area", () => {
  function sql(statement: string): string {
    return execFileSync("mariadb", ["jethro_functest", "-sNBe", statement], { encoding: "utf8" }).trim();
  }

  /** Own family and credentials: never modify shared personas or their contacts. */
  function installFixture(active: boolean) {
    const suffix = randomUUID();
    const memberEmail = `contact-member-${suffix}@example.org`;
    const staffEmail = `contact-staff-${suffix}@example.org`;
    // Read outside the transaction: INSERT ... SELECT would lock shared staff rows.
    const passwordHex = sql("SELECT HEX(password) FROM staff_member WHERE username = 'demo'");
    const ids = sql(`
      START TRANSACTION;
      INSERT INTO family
        (family_name, address_street, address_suburb, address_state, address_postcode, home_tel, status, history)
        SELECT 'Contact Fixture', address_street, address_suburb, address_state, address_postcode, '', status, ''
        FROM family WHERE id = (SELECT familyid FROM _person WHERE id = 9);
      SET @family_id = LAST_INSERT_ID();
      INSERT INTO _person
        (familyid, first_name, last_name, gender, age_bracketid, email, mobile_tel, work_tel,
         remarks, status, congregationid, history, member_password)
        SELECT @family_id, 'Member', 'Contact Fixture', p.gender, p.age_bracketid,
          '${memberEmail}', '0491570101', '', '', p.status, p.congregationid, '', UNHEX('${passwordHex}')
        FROM _person p WHERE p.id = 4;
      SET @member_id = LAST_INSERT_ID();
      INSERT INTO _person
        (familyid, first_name, last_name, gender, age_bracketid, email, mobile_tel, work_tel,
         remarks, status, congregationid, history, member_password)
        SELECT @family_id, 'Staff', 'Contact Fixture', p.gender, p.age_bracketid,
          '${staffEmail}', '0491570102', '', '', p.status, p.congregationid, '', UNHEX('${passwordHex}')
        FROM _person p WHERE p.id = 4;
      SET @staff_id = LAST_INSERT_ID();
      INSERT INTO staff_member (id, username, password, active, permissions)
        VALUES (@staff_id, 'contact-${suffix}', UNHEX('${passwordHex}'), ${active ? 1 : 0}, 1);
      SELECT @family_id, @member_id, @staff_id;
      COMMIT;
    `).split("\t").map(Number);
    return { familyId: ids[0], memberId: ids[1], staffId: ids[2], memberEmail, staffEmail };
  }

  for (const active of [true, false]) {
    for (const self of [false, true]) {
      test(`${self ? "self" : "family"} edits with ${active ? "active" : "inactive"} staff account`, async ({ page }) => {
        const fixture = installFixture(active);
        try {
          await memberLogin(page, self ? fixture.staffEmail : fixture.memberEmail);
          await page.goto("./?view=_edit_me");
          const memberPrefix = `person_${fixture.memberId}`;
          const staffPrefix = `person_${fixture.staffId}`;
          const updatedMemberEmail = `updated-${fixture.memberEmail}`;
          await page.locator(`input[name="${memberPrefix}email"]`).fill(updatedMemberEmail);
          await page.locator(`input[name="${memberPrefix}mobile_tel"]`).fill("0491570103");
          // Non-recovery fields remain editable for staff without required 2FA.
          await page.locator(`input[name="${staffPrefix}work_tel"]`).fill("0491570105");
          // Forge protected contact values regardless of their read-only presentation.
          await page.evaluate((prefix) => {
            const form = document.querySelector<HTMLInputElement>('[name="family_details_submitted"]')!.form!;
            for (const [field, value] of Object.entries({
              email: "contact-attacker@example.org", mobile_tel: "0491570104",
            })) {
              for (const existing of form.querySelectorAll(`[name="${prefix}${field}"]`)) existing.remove();
              const input = document.createElement("input");
              input.type = "hidden";
              input.name = `${prefix}${field}`;
              input.value = value;
              form.appendChild(input);
            }
          }, staffPrefix);
          await page.getByRole("button", { name: "Save", exact: true }).click();
          await expect(page.locator("#body")).toContainText("Details saved");
          expect(sql(`SELECT email, REPLACE(mobile_tel, ' ', ''), REPLACE(work_tel, ' ', '')
            FROM _person WHERE id = ${fixture.staffId}`).split("\t")).toEqual([
            fixture.staffEmail, "0491570102", "0491570105",
          ]);
          expect(sql(`SELECT email, REPLACE(mobile_tel, ' ', '')
            FROM _person WHERE id = ${fixture.memberId}`).split("\t")).toEqual([
            updatedMemberEmail, "0491570103",
          ]);
          await page.goto("./?view=_edit_me");
          // Read-only contacts are unnamed, so the browser never submits them.
          const staffEmail = page.locator(`#${staffPrefix}email`);
          const staffMobile = page.locator(`#${staffPrefix}mobile_tel`);
          await expect(staffEmail).toHaveValue(fixture.staffEmail);
          await expect(page.locator(`[name="${staffPrefix}email"], [name="${staffPrefix}mobile_tel"]`)).toHaveCount(0);
          await expect(staffEmail).not.toBeEditable();
          await expect(staffMobile).not.toBeEditable();
          expect((await staffMobile.inputValue()).replace(/\D/g, "")).toBe("0491570102");
          await expect(staffEmail).toHaveAttribute("title", /security/i);
          await expect(staffMobile).toHaveAttribute("title", /security/i);
          await expect(page.locator(`input[name="${staffPrefix}work_tel"]`)).toBeVisible();
          await expect(page.locator(`input[name="${memberPrefix}email"]`)).toHaveValue(updatedMemberEmail);
        } finally {
          sql(`DELETE FROM db_object_lock WHERE
              (object_type = 'person' AND objectid IN (${fixture.memberId}, ${fixture.staffId}))
              OR (object_type = 'family' AND objectid = ${fixture.familyId});
            DELETE FROM staff_member WHERE id = ${fixture.staffId};
            DELETE FROM _person WHERE familyid = ${fixture.familyId};
            DELETE FROM family WHERE id = ${fixture.familyId};`);
        }
      });
    }
  }
});
