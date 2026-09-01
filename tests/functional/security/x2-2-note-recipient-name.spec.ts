import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";
import { execSync } from "node:child_process";
import { writeFileSync, mkdtempSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

/**
 * Security finding X2.2 — stored XSS via the add-note modal recipient name
 * (docs/docs/developer/reference/securityreview/Sep26/xss-and-content-injection.md).
 *
 * jethro.js did
 *   $('#add-note-modal .note-recipient-name').html($this.attr('data-name'))
 * The data-name attribute is ents()-escaped in the HTML, but .attr() returns
 * the DECODED string and .html() parses it as markup again — so a person
 * named `<img src=x onerror=...>` executes script in a PERM_EDITNOTE user's
 * session when they open the modal. The fix uses .text().
 *
 * This spec seeds a person with an HTML payload name and a saved report that
 * renders the note-link column, opens the modal, and asserts the name is
 * rendered as text (no <img> element, no script execution).
 */


/** Write a SQL script to a temp file (avoids shell quote-mangling) and return its path. */
function writeSqlFile(sql: string): string {
  const dir = mkdtempSync(join(tmpdir(), "jethro-sec-"));
  const p = join(dir, "seed.sql");
  writeFileSync(p, sql);
  return p;
}

const PAYLOAD = '<img src=x onerror="window.__xss=1">';

function setup(): { personId: string; reportId: string } {
  // Tidy any leftovers from crashed runs.
  execSync(`mariadb jethro_functest -e "DELETE FROM person_query WHERE name='zz_x22_report'"`);
  execSync(`mariadb jethro_functest -e "DELETE FROM _person WHERE first_name LIKE '<img%' AND last_name='ZzXss'"`);
  execSync(`mariadb jethro_functest -e "DELETE FROM family WHERE family_name='ZzXss Family'"`);

  // Disjoint id space (200000+) so parallel runs of the other security
  // specs never collide on the shared tables.
  const nextId = Number(
    execSync(`mariadb jethro_functest -N -e "SELECT GREATEST(IFNULL((SELECT MAX(id) FROM _person WHERE id>=200000 AND id<300000), 200000), IFNULL((SELECT MAX(id) FROM family WHERE id>=200000 AND id<300000), 200000), IFNULL((SELECT MAX(id) FROM staff_member WHERE id>=200000 AND id<300000), 200000)) + 1"`).toString().trim(),
  );
  execSync(
    `mariadb jethro_functest -e "INSERT INTO family (id, family_name, address_street, address_suburb, address_state, address_postcode, home_tel, status, history) VALUES (${nextId}, 'ZzXss Family', '', '', '', '', '', 4, '')"`,
  );
  execSync(
    `mariadb jethro_functest -e "INSERT INTO _person (id, first_name, last_name, gender, email, mobile_tel, work_tel, remarks, status, history, familyid, age_bracketid, congregationid) SELECT ${nextId}, '<img src=x onerror=\\"window.__xss=1\\">', 'ZzXss', 'male', 'zz_x22@example.com', mobile_tel, work_tel, remarks, status, history, ${nextId}, age_bracketid, congregationid FROM _person WHERE id=1"`,
  );
  // The scratch person copies person 1's status; filter on it so the report
  // is non-empty.
  const status = execSync(
    `mariadb jethro_functest -N -e "SELECT status FROM _person WHERE id=1"`,
  ).toString().trim();
  const params = execSync(
    `php -r 'echo serialize(array("rules"=>array("p.status"=>array(${status})),"show_fields"=>array("p.first_name","p.last_name","note_link"),"group_by"=>"","sort_by"=>"p.last_name","include_groups"=>array(),"exclude_groups"=>array()));'`,
  ).toString();
  // Pipe the SQL through a file: shell double-quote processing would strip
  // the " characters inside the serialized params (232-byte corruption).
  const sql = `INSERT INTO person_query (name, owner, params, mailchimp_list_id, show_on_homepage) VALUES ('zz_x22_report', NULL, '${params.replace(/'/g, "''")}', '', '');`;
  execSync(`mariadb jethro_functest < "${writeSqlFile(sql)}"`);
  const reportId = execSync(
    `mariadb jethro_functest -N -e "SELECT id FROM person_query WHERE name='zz_x22_report'"`,
  ).toString().trim();
  return { personId: String(nextId), reportId };
}

function cleanup(): void {
  execSync(`mariadb jethro_functest -e "DELETE FROM person_query WHERE name='zz_x22_report'"`);
  execSync(`mariadb jethro_functest -e "DELETE FROM _person WHERE first_name LIKE '<img%' AND last_name='ZzXss'"`);
  execSync(`mariadb jethro_functest -e "DELETE FROM family WHERE family_name='ZzXss Family'"`);
}

test("note recipient name renders as text, not markup", async ({ page }) => {
  const { personId, reportId } = setup();
  try {
    await login(page);
    await page.goto(`?view=persons__reports&queryid=${reportId}`);
    await page.waitForLoadState("domcontentloaded");

    const noteLink = page.locator(`a.note-link[data-personid="${personId}"]`);
    await expect(noteLink).toBeVisible();
    await noteLink.click();

    const recipient = page.locator("#add-note-modal .note-recipient-name");
    await expect(recipient).toBeVisible();

    // The name must be inserted as TEXT: literal payload characters, and no
    // <img> element inside the modal.
    await expect(recipient).toHaveText(`${PAYLOAD} ZzXss`);
    expect(await page.locator("#add-note-modal img").count()).toBe(0);
    const xss = await page.evaluate(() => (window as unknown as Record<string, unknown>).__xss);
    expect(xss).toBeUndefined();
  } finally {
    cleanup();
  }
});
