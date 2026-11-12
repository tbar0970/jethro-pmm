import { execFileSync } from "node:child_process";
import { randomUUID } from "node:crypto";
import { test, expect } from "../fixtures.js";
import { to } from "../timeouts.js";
import { DEMO_USERNAME, PERSONA_PASSWORD } from "../auth.js";
import { type Page, type Locator } from "@playwright/test";

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

let jethroOffset: string | undefined;

/**
 * UTC offset ("+11:00") of Jethro's TIMEZONE setting at this moment.
 *
 * include/init.php sets each Jethro DB session's time_zone to this offset, so
 * NOW() values Jethro writes are in TIMEZONE wall-clock time.  A raw mariadb
 * session would otherwise use the server's default zone (UTC on CI), and date
 * comparisons against Jethro-written DATETIMEs would break whenever the two
 * zones' dates differ.  Computed once per file.
 */
function jethroUtcOffset(): string {
  if (jethroOffset === undefined) {
    const tz = execFileSync("mariadb", ["jethro_functest", "-sNBe",
      "SELECT value FROM setting WHERE symbol='TIMEZONE'"], { encoding: "utf8" }).trim() || "UTC";
    const name = new Intl.DateTimeFormat("en", { timeZone: tz, timeZoneName: "longOffset" })
      .formatToParts(new Date()).find((p) => p.type === "timeZoneName")!.value; // "GMT+11:00" or "GMT"
    jethroOffset = name === "GMT" ? "+00:00" : name.slice(3);
  }
  return jethroOffset;
}

/** Run SQL against jethro_functest in Jethro's own time zone; returns tab-separated output. */
function sql(statement: string): string {
  return execFileSync("mariadb", ["jethro_functest", "-sNBe", `SET time_zone = '${jethroUtcOffset()}'; ${statement}`],
    { encoding: "utf8" }).trim();
}

async function staffLogin(page: Page, host: string): Promise<void> {
  await page.goto(`${host}/index.php`);
  const loginBox = page.locator("#login-box");
  if (await loginBox.isVisible().catch(() => false)) {
    await page.fill('input[name="username"]', DEMO_USERNAME);
    await page.fill('input[name="password"]', PERSONA_PASSWORD);
    await page.click('input[value="Log In"]');
    await expect(loginBox).toHaveCount(0);
  }
}

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

test.describe("Member directory share consent (#792)", () => {
  const LAST_NAME = "Consentfixture";
  // VIEWNAME | VIEWEMAIL | VIEWMOBILE - what the portal checkbox grants.
  const FULL_MASK = "805306369";

  interface ConsentFixture {
    groupId: number;
    groupId2: number;
    viewerId: number;
    subjectId: number;
    outsiderId: number;
    viewerFamilyId: number;
    subjectFamilyId: number;
    viewerEmail: string;
    subjectEmail: string;
    outsiderEmail: string;
  }

  /**
   * One could-share group with two members (viewer, subject) and an outsider
   * who is not a member.  The viewer and outsider share a family; the subject
   * has their own family, so the member view's family branch never lists them
   * and the share branch is the only way they can be visible.  Nobody has
   * consented yet.  Fixture-private rows only; shared personas untouched.
   */
  function installConsentFixture(): ConsentFixture {
    const suffix = randomUUID();
    const fixture = {
      viewerEmail: `share-viewer-${suffix}@example.org`,
      subjectEmail: `share-subject-${suffix}@example.org`,
      outsiderEmail: `share-outsider-${suffix}@example.org`,
    };
    const passwordHex = sql("SELECT HEX(password) FROM staff_member WHERE username = 'demo'");
    const personTemplate = `SELECT @family_id, 'ReplaceMe', '${LAST_NAME}', p.gender, p.age_bracketid,
        'ReplaceEmail', 'ReplaceMobile', '', '', p.status, p.congregationid, '', UNHEX('${passwordHex}')
      FROM _person p WHERE p.id = 4`;
    const ids = sql(`
      START TRANSACTION;
      INSERT INTO _person_group
        (name, categoryid, owner, is_archived, show_add_family, share_member_details, attendance_recording_days)
        VALUES ('Consent Fixture', NULL, NULL, 0, 'no', 1, 0);
      SET @group_id = LAST_INSERT_ID();
      INSERT INTO _person_group
        (name, categoryid, owner, is_archived, show_add_family, share_member_details, attendance_recording_days)
        VALUES ('Consent Fixture B', NULL, NULL, 0, 'no', 1, 0);
      SET @group_id2 = LAST_INSERT_ID();
      INSERT INTO family
        (family_name, address_street, address_suburb, address_state, address_postcode, home_tel, status, history)
        SELECT 'Consentfixture', address_street, address_suburb, address_state, address_postcode, '', status, ''
        FROM family WHERE id = (SELECT familyid FROM _person WHERE id = 4);
      SET @viewer_family = LAST_INSERT_ID();
      SET @family_id = @viewer_family;
      INSERT INTO _person
        (familyid, first_name, last_name, gender, age_bracketid, email, mobile_tel, work_tel,
         remarks, status, congregationid, history, member_password)
        ${personTemplate.replace("'ReplaceMe'", "'Viewer'").replace("'ReplaceEmail'", `'${fixture.viewerEmail}'`).replace("'ReplaceMobile'", "'0491570107'")};
      SET @viewer_id = LAST_INSERT_ID();
      INSERT INTO _person
        (familyid, first_name, last_name, gender, age_bracketid, email, mobile_tel, work_tel,
         remarks, status, congregationid, history, member_password)
        ${personTemplate.replace("'ReplaceMe'", "'Outsider'").replace("'ReplaceEmail'", `'${fixture.outsiderEmail}'`).replace("'ReplaceMobile'", "'0491570108'")};
      SET @outsider_id = LAST_INSERT_ID();
      INSERT INTO family
        (family_name, address_street, address_suburb, address_state, address_postcode, home_tel, status, history)
        SELECT 'Consentfixture', address_street, address_suburb, address_state, address_postcode, '', status, ''
        FROM family WHERE id = (SELECT familyid FROM _person WHERE id = 4);
      SET @subject_family = LAST_INSERT_ID();
      SET @family_id = @subject_family;
      INSERT INTO _person
        (familyid, first_name, last_name, gender, age_bracketid, email, mobile_tel, work_tel,
         remarks, status, congregationid, history, member_password)
        ${personTemplate.replace("'ReplaceMe'", "'Subject'").replace("'ReplaceEmail'", `'${fixture.subjectEmail}'`).replace("'ReplaceMobile'", "'0491570106'")};
      SET @subject_id = LAST_INSERT_ID();
      INSERT INTO person_group_membership (personid, groupid, membership_status)
        VALUES (@viewer_id, @group_id, (SELECT id FROM person_group_membership_status WHERE is_default = 1)),
               (@subject_id, @group_id, (SELECT id FROM person_group_membership_status WHERE is_default = 1)),
               (@viewer_id, @group_id2, (SELECT id FROM person_group_membership_status WHERE is_default = 1)),
               (@subject_id, @group_id2, (SELECT id FROM person_group_membership_status WHERE is_default = 1));
      SELECT @group_id, @viewer_id, @subject_id, @outsider_id, @viewer_family, @subject_family, @group_id2;
      COMMIT;
    `).split("\t").map(Number);
    return {
      groupId: ids[0], viewerId: ids[1], subjectId: ids[2], outsiderId: ids[3],
      viewerFamilyId: ids[4], subjectFamilyId: ids[5], groupId2: ids[6],
      ...fixture,
    };
  }

  /** Staff-equivalent consent seed/clear (the staff group view does this via the form). */
  function seedConsent(subjectId: number, groupId: number, granted: boolean, mask: number = Number(FULL_MASK)): void {
    if (granted) {
      sql(`INSERT IGNORE INTO _person_share_consent (personid, groupid, data_permissions, consented_on, granted_by)
        VALUES (${subjectId}, ${groupId}, ${mask}, NOW(), 'staff')`);
    } else {
      sql(`DELETE FROM _person_share_consent WHERE personid = ${subjectId} AND groupid = ${groupId}`);
    }
  }

  function seedDefaultConsent(personId: number, granted: boolean): void {
    if (granted) {
      sql(`INSERT IGNORE INTO _person_share_consent (personid, groupid, data_permissions, consented_on, granted_by)
        VALUES (${personId}, 0, ${FULL_MASK}, NOW(), 'staff')`);
    } else {
      sql(`DELETE FROM _person_share_consent WHERE personid = ${personId} AND groupid = 0`);
    }
  }

  /** Replace the viewer's default and group consent rows with a known state. */
  function setStoredConsent(f: ConsentFixture, defMask: number | null, rowMask: number | null, rowInherit = 0): void {
    sql(`DELETE FROM _person_share_consent WHERE personid = ${f.viewerId} AND groupid IN (0, ${f.groupId})`);
    if (defMask !== null) {
      sql(`INSERT INTO _person_share_consent (personid, groupid, data_permissions, inherit_mask, consented_on, granted_by)
        VALUES (${f.viewerId}, 0, ${defMask}, 0, NOW(), 'user')`);
    }
    if (rowMask !== null) {
      sql(`INSERT INTO _person_share_consent (personid, groupid, data_permissions, inherit_mask, consented_on, granted_by)
        VALUES (${f.viewerId}, ${f.groupId}, ${rowMask}, ${rowInherit}, NOW(), 'user')`);
    }
  }

  function consentLocators(page: Page, f: ConsentFixture) {
    return {
      name: page.locator(`input[name="share_visible[${f.groupId}]"]`),
      contact: page.locator(`input[name="share_contact[${f.groupId}]"]`),
      defName: page.locator('input[name="share_visible_default"]'),
      defContact: page.locator('input[name="share_contact_default"]'),
      nameUseDefault: page.locator(`#share-use-default-visible-${f.groupId}`),
      nameSetIndividually: page.locator(`#share-set-individually-visible-${f.groupId}`),
      contactSetIndividually: page.locator(`#share-set-individually-contact-${f.groupId}`),
      nameState: page.locator(`#share-set-individually-state-visible-${f.groupId}`),
      contactState: page.locator(`#share-set-individually-state-contact-${f.groupId}`),
    };
  }

  async function expectCheckbox(locator: Locator, checked: boolean, disabled?: boolean): Promise<void> {
    if (checked) await expect(locator).toBeChecked();
    else await expect(locator).not.toBeChecked();
    if (disabled === true) await expect(locator).toBeDisabled();
    else if (disabled === false) await expect(locator).toBeEnabled();
  }

  function rowState(f: ConsentFixture): string {
    return sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), -1), IFNULL(MAX(inherit_mask), -1)
      FROM _person_share_consent WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`);
  }

  function defaultState(f: ConsentFixture): string {
    return sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), -1)
      FROM _person_share_consent WHERE personid = ${f.viewerId} AND groupid = 0`);
  }

  function cleanupFixture(f: ConsentFixture): void {
    sql(`DELETE FROM _person WHERE id IN (${f.viewerId}, ${f.subjectId}, ${f.outsiderId});
      DELETE FROM family WHERE id IN (${f.viewerFamilyId}, ${f.subjectFamilyId});
      DELETE FROM _person_group WHERE id IN (${f.groupId}, ${f.groupId2});`);
  }

  test("portal columns save, inherit and revert", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);
      await page.goto("./?view=sharing");
      const vis = page.locator(`input[name="share_visible[${f.groupId}]"]`);
      const con = page.locator(`input[name="share_contact[${f.groupId}]"]`);
      const visLink = page.locator(`#share-use-default-visible-${f.groupId}`);
      const conLink = page.locator(`#share-use-default-contact-${f.groupId}`);
      const visCustomise = page.locator(`#share-set-individually-visible-${f.groupId}`);
      const conCustomise = page.locator(`#share-set-individually-contact-${f.groupId}`);
      const nameState = page.locator(`#share-set-individually-state-visible-${f.groupId}`);

      // No explicit row and no default: both columns are read-only mirrors.
      await expect(vis).toBeDisabled();
      await expect(vis).not.toBeChecked();
      await expect(con).toBeDisabled();
      await expect(visLink).toBeHidden();
      await expect(visCustomise).toBeVisible();
      await expect(page.locator(`#share-uses-default-visible-${f.groupId}`)).toBeVisible();
      await expect(page.locator(`#share-set-individually-state-visible-${f.groupId}`)).toBeHidden();
      await expect(page.locator("#body")).toContainText("Using default");

      // Toggling the default updates the single effective checkbox.
      await page.check('input[name="share_visible_default"]');
      await expect(vis).toBeChecked();
      await expect(vis).toBeDisabled();
      await page.uncheck('input[name="share_visible_default"]');
      await expect(vis).not.toBeChecked();

      // "(set individually)" leaves inherit mode; the explicit choice saves.
      await visCustomise.click();
      await expect(vis).toBeEnabled();
      await expect(visLink).toBeVisible();
      await expect(page.locator(`#share-uses-default-visible-${f.groupId}`)).toBeHidden();
      await expect(nameState).toBeVisible();
      await vis.check();
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), 0), MAX(granted_by), MAX(DATE(consented_on) = CURDATE())
        FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe("1\t1\t2\tuser\t1");
      await expect(page.locator("#body")).toContainText("(partly uses default)");

      // Making contact explicit records the full mask.
      await conCustomise.click();
      await con.check();
      await page.getByRole("button", { name: "Save" }).click();
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), -1) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe(`1\t${FULL_MASK}\t0`);

      // "(use default)" on contact returns just that column to the default.
      await conLink.click();
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), -1) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe("1\t1\t2");
      await expect(conLink).toBeHidden();
      await expect(con).toBeDisabled();

      // Person-wide default (groupid 0): ticking contact ticks name, but
      // unticking contact leaves the name choice alone.
      const visDefault = page.locator('input[name="share_visible_default"]');
      const conDefault = page.locator('input[name="share_contact_default"]');
      await conDefault.check();
      await expect(visDefault).toBeChecked();
      await conDefault.uncheck();
      await expect(visDefault).toBeChecked();
      // Clearing the name clears contact, so contact never shares without a name.
      await conDefault.check();
      await visDefault.uncheck();
      await expect(conDefault).not.toBeChecked();
      await visDefault.check();
      await conDefault.check();
      // The group row keeps its explicit Visible and its contact follows the
      // default's on state.
      await expect(con).toBeChecked();
      await expect(con).toBeDisabled();
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*), MAX(granted_by), IFNULL(MAX(data_permissions), 0) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = 0`)).toBe(`1\tuser\t${FULL_MASK}`);
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), -1) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe("1\t1\t2");
      await expect(vis).toBeChecked();

      // "(use default)" on Visible too: both columns inherit, so the row goes.
      await visLink.click();
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe("0");

      // Turning the whole default off.
      await page.uncheck('input[name="share_visible_default"]');
      await page.uncheck('input[name="share_contact_default"]');
      await page.getByRole("button", { name: "Save" }).click();
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = 0`)).toBe("0");

      // Contact implies Name: sharing contact also records the name grant,
      // even when Name itself still follows the (off) default, and clearing
      // Name clears contact.
      await conCustomise.click();
      await con.check();
      await expect(vis).toBeChecked();
      await page.getByRole("button", { name: "Save" }).click();
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), -1) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe(`1\t${FULL_MASK}\t0`);
      // Unticking contact leaves the name ticked...
      await con.uncheck();
      await expect(con).not.toBeChecked();
      await expect(vis).toBeChecked();
      // ... while clearing the name clears contact.
      await vis.uncheck();
      await expect(con).not.toBeChecked();

      // Repeated action toggling must keep an action visible (regression: an
      // old jQuery handler hid the clicked link, so a later toggle left none).
      await visLink.click();
      await expect(visCustomise).toBeVisible();
      await visCustomise.click();
      await expect(visLink).toBeVisible();
      await visLink.click();
      await expect(visCustomise).toBeVisible();
    } finally {
      cleanupFixture(f);
    }
  });

  test("default signals drive inherited rows both ways", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);
      await page.goto("./?view=sharing");
      const c = consentLocators(page, f);
      const vis = c.name;
      const con = c.contact;
      const visDefault = c.defName;
      const conDefault = c.defContact;

      // The row inherits both columns and starts off.
      await expect(vis).toBeDisabled();
      await expect(con).toBeDisabled();
      await expect(vis).not.toBeChecked();
      await expect(con).not.toBeChecked();

      // Ticking the default contact also ticks the default name, so the
      // inherited row shows both columns checked.
      await conDefault.check();
      await expect(visDefault).toBeChecked();
      await expect(con).toBeChecked();
      await expect(vis).toBeChecked();

      // Unticking contact leaves the name shared.
      await conDefault.uncheck();
      await expect(con).not.toBeChecked();
      await expect(vis).toBeChecked();

      // Saving writes only the default row (name); the group stays inherited.
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = ${f.groupId}`)).toBe("0");
      expect(sql(`SELECT data_permissions FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid = 0`)).toBe("1");
    } finally {
      cleanupFixture(f);
    }
  });

  test("unticking the default name clears inherited row checkboxes", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);
      seedDefaultConsent(f.viewerId, true);
      await page.goto("./?view=sharing");
      const c = consentLocators(page, f);
      const vis = c.name;
      const con = c.contact;
      const visDefault = c.defName;
      const conDefault = c.defContact;
      await expect(visDefault).toBeChecked();
      await expect(conDefault).toBeChecked();
      await expect(vis).toBeChecked();
      await expect(vis).toBeDisabled();
      await expect(con).toBeChecked();
      await expect(con).toBeDisabled();

      // Unticking the default name clears the default contact too, and the
      // inherited row follows both signals down; it must not latch itself on.
      await visDefault.uncheck();
      await expect(conDefault).not.toBeChecked();
      await expect(vis).not.toBeChecked();
      await expect(vis).toBeDisabled();
      await expect(con).not.toBeChecked();
      await expect(con).toBeDisabled();

      // Saving clears the default row; nothing explicit is left behind.
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent
        WHERE personid = ${f.viewerId} AND groupid IN (0, ${f.groupId})`)).toBe("0");
    } finally {
      cleanupFixture(f);
    }
  });

  test("checkbox permutations: defaults and inherited rows", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);

      // 1. No defaults, row inherits: ticking default contact ticks both
      //    defaults and both mirrors; unticking contact keeps the name on.
      setStoredConsent(f, null, null);
      await page.goto("./?view=sharing");
      let c = consentLocators(page, f);
      await expectCheckbox(c.name, false, true);
      await expectCheckbox(c.contact, false, true);
      await c.defContact.check();
      await expectCheckbox(c.defName, true);
      await expectCheckbox(c.name, true, true);
      await expectCheckbox(c.contact, true, true);
      await c.defContact.uncheck();
      await expectCheckbox(c.defName, true);
      await expectCheckbox(c.name, true, true);
      await expectCheckbox(c.contact, false, true);
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(defaultState(f)).toBe("1\t1");
      expect(rowState(f)).toBe("0\t-1\t-1");

      // 2. Both defaults on, row inherits: unticking the default name clears
      //    the default contact and both mirrors, without pinning the row.
      setStoredConsent(f, Number(FULL_MASK), null);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await expectCheckbox(c.name, true, true);
      await expectCheckbox(c.contact, true, true);
      await c.defName.uncheck();
      await expectCheckbox(c.defContact, false);
      await expectCheckbox(c.name, false, true);
      await expectCheckbox(c.contact, false, true);
      await page.getByRole("button", { name: "Save" }).click();
      await expect(page.locator("#body")).toContainText("Sharing settings saved");
      expect(defaultState(f)).toBe("0\t-1");
      expect(rowState(f)).toBe("0\t-1\t-1");

      // 3. Both defaults on, row inherits: unticking default contact leaves the
      //    name default (and the row's name) on.
      setStoredConsent(f, Number(FULL_MASK), null);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await c.defContact.uncheck();
      await expectCheckbox(c.defName, true);
      await expectCheckbox(c.name, true, true);
      await expectCheckbox(c.contact, false, true);
      await page.getByRole("button", { name: "Save" }).click();
      expect(defaultState(f)).toBe("1\t1");
      expect(rowState(f)).toBe("0\t-1\t-1");
    } finally {
      cleanupFixture(f);
    }
  });

  test("checkbox permutations: explicit rows", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);

      // 1. Explicit name only: ticking row contact ticks the name; unticking
      //    contact leaves the name ticked.
      setStoredConsent(f, null, 1, 0);
      await page.goto("./?view=sharing");
      let c = consentLocators(page, f);
      await expectCheckbox(c.name, true, false);
      await expectCheckbox(c.contact, false, false);
      await c.contact.check();
      await expectCheckbox(c.name, true, false);
      await c.contact.uncheck();
      await expectCheckbox(c.name, true, false);
      await expectCheckbox(c.contact, false, false);
      await page.getByRole("button", { name: "Save" }).click();
      expect(rowState(f)).toBe("1\t1\t0");

      // 2. Clearing the name clears contact and saves an explicit refusal.
      setStoredConsent(f, null, Number(FULL_MASK), 0);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await c.name.uncheck();
      await expectCheckbox(c.contact, false, false);
      await page.getByRole("button", { name: "Save" }).click();
      expect(rowState(f)).toBe("1\t0\t0");

      // 3. "(use default)" on the name while contact is explicit: contact
      //    implies the name, so the save keeps the name explicit.
      setStoredConsent(f, Number(FULL_MASK), Number(FULL_MASK), 0);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await c.nameUseDefault.click();
      await expectCheckbox(c.name, true, true);
      await page.getByRole("button", { name: "Save" }).click();
      expect(rowState(f)).toBe(`1\t${FULL_MASK}\t0`);

      // 4. "(use default)" on the name with no contact: the row keeps only the
      //    contact refusal and inherits the name.
      setStoredConsent(f, null, 1, 0);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await c.nameUseDefault.click();
      await page.getByRole("button", { name: "Save" }).click();
      expect(rowState(f)).toBe("1\t0\t1");

      // 5. Making inherited contact explicit on a default-full row normalises
      //    the name grant too.
      setStoredConsent(f, Number(FULL_MASK), null);
      await page.goto("./?view=sharing");
      c = consentLocators(page, f);
      await c.contactSetIndividually.click();
      await expectCheckbox(c.contact, true, false);
      await page.getByRole("button", { name: "Save" }).click();
      expect(rowState(f)).toBe(`1\t${FULL_MASK}\t0`);
    } finally {
      cleanupFixture(f);
    }
  });

  test("deselected default clears an inherited name despite explicit contact", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);
      seedDefaultConsent(f.viewerId, true);
      seedConsent(f.viewerId, f.groupId, true);
      await page.goto("./?view=sharing");
      const vis = page.locator(`input[name="share_visible[${f.groupId}]"]`);
      const con = page.locator(`input[name="share_contact[${f.groupId}]"]`);

      const cellWidth = () => vis.evaluate((el) => el.closest("td")!.getBoundingClientRect().width);
      const widthBefore = await cellWidth();

      // Follow the default for Name while Contact stays explicitly shared.
      await page.click(`#share-use-default-visible-${f.groupId}`);
      await expect(vis).toBeDisabled();
      await expect(vis).toBeChecked();
      expect(await cellWidth()).toBeCloseTo(widthBefore, 0);

      // Unticking the default Name now leaves the inherited Name unchecked,
      // even though Contact is still explicitly shared.
      await page.uncheck('input[name="share_visible_default"]');
      await expect(vis).not.toBeChecked();
      await expect(vis).toBeDisabled();
      await expect(con).toBeChecked();
      await expect(con).toBeEnabled();
    } finally {
      cleanupFixture(f);
    }
  });

  test("consent drives People search and group page visibility", async ({ page }) => {
    const f = installConsentFixture();
    try {
      await memberLogin(page, f.viewerEmail);
      const search = `./?view=people&search=${LAST_NAME}`;

      // Nobody consented: the search shows the viewer's own family only.
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.viewerEmail);
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);

      // Subject consents (seeded, as the staff "Edit sharing settings" form writes it)...
      seedConsent(f.subjectId, f.groupId, true);
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.subjectEmail);
      // ...and appears on the group page alongside the viewer themself.
      await page.goto(`./?view=_groups&groupid=${f.groupId}`);
      await expect(page.locator("#body")).toContainText(f.subjectEmail);
      await expect(page.locator("#body")).toContainText(f.viewerEmail);

      // Withdrawing the consent removes them again.
      seedConsent(f.subjectId, f.groupId, false);
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.viewerEmail);
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);

      // Visible-only consent lists the name but withholds contact details.
      seedConsent(f.subjectId, f.groupId, true, 1);
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText("Subject");
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);
      await page.goto(`./?view=_groups&groupid=${f.groupId}`);
      await expect(page.locator("#body")).toContainText("Subject");
      await expect(page.locator("#body")).not.toContainText(f.subjectEmail);

      // Sharing two groups with the viewer must not duplicate the person, and
      // contact consent in either group is shown once.
      seedConsent(f.subjectId, f.groupId, true, 1);
      seedConsent(f.subjectId, f.groupId2, true);
      expect(sql(`SET @current_member_id = ${f.viewerId}; SELECT COUNT(*) FROM member WHERE id = ${f.subjectId}`)).toBe("1");
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.subjectEmail);
      seedConsent(f.subjectId, f.groupId, false);
      seedConsent(f.subjectId, f.groupId2, false);

      // The subject's person-wide default (groupid 0) applies where no
      // explicit row exists.
      seedDefaultConsent(f.subjectId, true);
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.subjectEmail);

      // Explicit deny rows override the default in both shared groups
      sql(`INSERT IGNORE INTO _person_share_consent (personid, groupid, data_permissions, consented_on, granted_by)
        VALUES (${f.subjectId}, ${f.groupId}, 0, NOW(), 'staff'),
               (${f.subjectId}, ${f.groupId2}, 0, NOW(), 'staff')`);
      await page.goto(search);
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);
      sql(`DELETE FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid IN (${f.groupId}, ${f.groupId2})`);
      await page.goto(search);
      await expect(page.locator("#member-list")).toContainText(f.subjectEmail);

      seedDefaultConsent(f.subjectId, false);
      await page.goto(search);
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);
    } finally {
      cleanupFixture(f);
    }
  });

  test("group page and directory refuse non-members", async ({ page }) => {
    const f = installConsentFixture();
    try {
      seedConsent(f.subjectId, f.groupId, true);
      await memberLogin(page, f.outsiderEmail);

      // Outsider is not a group member: the group page is refused even though
      // the subject has consented, and the share branch cannot list them.
      await page.goto(`./?view=_groups&groupid=${f.groupId}`);
      await expect(page.locator("#body")).toContainText("This group is not available");
      await page.goto(`./?view=people&search=${LAST_NAME}`);
      await expect(page.locator("#member-list")).toContainText(f.viewerEmail); // own family still visible
      await expect(page.locator("#member-list")).not.toContainText(f.subjectEmail);

      // No could-share membership: the Sharing page has nothing to offer.
      await page.goto("./?view=sharing");
      await expect(page.locator("#body")).toContainText("not currently in any groups that support sharing");
    } finally {
      cleanupFixture(f);
    }
  });

  test("group edit page edits only the Visible column", async ({ page }) => {
    const f = installConsentFixture();
    try {
      seedDefaultConsent(f.subjectId, true);
      seedConsent(f.subjectId, f.groupId, true);
      const host = `http://${process.env.FUNCTEST_WEB_HOST || "127.0.0.1"}:${process.env.FUNCTEST_WEB_PORT}`;
      await staffLogin(page, host);

      // The group member list shows one consent column.
      await page.goto(`${host}/?view=groups&groupid=${f.groupId}&edit_consents=1`);
      await expect(page.locator('input[name^="share_contact["]')).toHaveCount(0);
      await expect(page.locator('th:has-text("Shares contact info?")')).toHaveCount(0);

      // Unticking records an explicit refusal for both columns.
      await page.uncheck(`input[name="share_visible[${f.subjectId}]"]`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), 0)
        FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("1\t0\t0");

      // Re-ticking restores the name grant only; contact needs a fresh opt-in.
      await page.goto(`${host}/?view=groups&groupid=${f.groupId}&edit_consents=1`);
      await page.check(`input[name="share_visible[${f.subjectId}]"]`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("1\t0");
    } finally {
      cleanupFixture(f);
    }
  });

  test("staff per-column '(use default)' reverts an explicit row", async ({ page }) => {
    const f = installConsentFixture();
    try {
      // An explicit group grant plus a person-wide default: the staff
      // Sharing tab offers "(use default)" beside each column.
      seedDefaultConsent(f.subjectId, true);
      seedConsent(f.subjectId, f.groupId, true);

      // The members project's baseURL is /members/, so reach the staff app
      // absolutely.  Before the fix, this click was misrouted into the
      // members-area permission branch ("Members may only change their own
      // sharing consent") because the staff user system also *has* a
      // getCurrentMember() method (it returns NULL).
      const host = `http://${process.env.FUNCTEST_WEB_HOST || "127.0.0.1"}:${process.env.FUNCTEST_WEB_PORT}`;
      await staffLogin(page, host);

      await page.goto(`${host}/?view=persons&personid=${f.subjectId}`);
      await page.click('a[data-toggle="tab"][href="#sharing"]');

      // Consent carried over by the upgrade is attributed to the migration,
      // not to a staff member.
      sql(`UPDATE _person_share_consent SET granted_by = 'upgrade'
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`);
      await page.reload();
      await page.click('a[data-toggle="tab"][href="#sharing"]');
      await expect(page.locator('#sharing')).toContainText("migrated from the old group setting");
      sql(`UPDATE _person_share_consent SET granted_by = 'staff'
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`);
      await page.reload();
      await page.click('a[data-toggle="tab"][href="#sharing"]');

      // "(use default)" beside Visible reverts only that column; the row is
      // rewritten when the form is saved, not on click.
      await page.click(`#share-use-default-visible-${f.groupId}`);
      const visAfter = page.locator(`input[name="share_visible[${f.groupId}]"]`);
      await expect(visAfter).toBeDisabled();
      await expect(visAfter).toBeChecked();
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe(`${FULL_MASK}\t0`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      await expect(page.locator("#body")).not.toContainText("Members may only change their own sharing consent");
      // Contact is still shared, so the name cannot inherit away: it is kept
      // explicitly granted.
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe(`${FULL_MASK}\t0`);

      // Reverting contact to the default leaves Name explicit and Contact
      // inherited (both effective, since the default grants contact)...
      await page.reload();
      await page.click('a[data-toggle="tab"][href="#sharing"]');
      await page.click(`#share-use-default-contact-${f.groupId}`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("1\t2");

      // ... and reverting Name too leaves both columns following the default,
      // so the explicit row is deleted; the person-wide default remains.
      await page.reload();
      await page.click('a[data-toggle="tab"][href="#sharing"]');
      await page.click(`#share-use-default-visible-${f.groupId}`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("0");
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = 0`)).toBe("1");

      // A group row that was not part of the rendered form is ignored: a
      // group joined between rendering and submission must not be recorded as
      // an explicit refusal (or grant) the person never saw.
      const forged = await page.context().request.post(
        `${host}/?view=persons&personid=${f.subjectId}`,
        { form: { share_consent_submitted: "1", [`share_visible[${f.groupId}]`]: "1" } }
      );
      expect(forged.ok()).toBeTruthy();
      expect(sql(`SELECT COUNT(*) FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("0");

      // Server-side guard: a submitted contact grant always stores the name grant.
      const forced = await page.context().request.post(
        `${host}/?view=persons&personid=${f.subjectId}`,
        { form: {
            share_consent_submitted: "1",
            [`share_contact[${f.groupId}]`]: "1",
            [`share_rendered[${f.groupId}]`]: "0:3",
        } }
      );
      expect(forced.ok()).toBeTruthy();
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe(`${FULL_MASK}\t0`);

      // With an inherited row and an on default, refusing writes a refusal;
      // re-ticking restores the name only (contact needs a fresh opt-in).
      sql(`DELETE FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`);
      await page.goto(`${host}/?view=groups&groupid=${f.groupId}&edit_consents=1`);
      await expect(page.locator(`input[name="share_visible[${f.subjectId}]"]`)).toBeChecked();
      await page.uncheck(`input[name="share_visible[${f.subjectId}]"]`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT COUNT(*), IFNULL(MAX(data_permissions), 0), IFNULL(MAX(inherit_mask), 0)
        FROM _person_share_consent WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("1\t0\t0");

      await page.goto(`${host}/?view=groups&groupid=${f.groupId}&edit_consents=1`);
      await page.check(`input[name="share_visible[${f.subjectId}]"]`);
      await page.getByRole("button", { name: "Save sharing settings" }).click();
      await expect(page.getByText("Sharing settings updated")).toBeVisible();
      expect(sql(`SELECT data_permissions, inherit_mask FROM _person_share_consent
        WHERE personid = ${f.subjectId} AND groupid = ${f.groupId}`)).toBe("1\t0");
    } finally {
      cleanupFixture(f);
    }
  });
});
