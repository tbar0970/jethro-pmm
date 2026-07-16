import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * Role-based permission checks.
 *
 * Each restricted staff_member persona ships in the demo-data dump with
 * congregation/group restrictions (account_congregation_restriction /
 * account_group_restriction) and a permissions bitmask.  All personas share
 * the demo password; tests/functional/testdata/test_personas.sql (applied by
 * process-compose's functest_databases_setup) resets their hashes and adds
 * the `noperms` account.
 *
 * Behaviour verified by hand against the running instance before encoding
 * here:
 *   - a view the account lacks the menu permission for is not in its session
 *     menu, and the dispatcher renders "Error: Undefined view" (#body);
 *   - a ?call= above the account's permission floor renders Jethro's
 *     "You don't have permission to perform this operation" error page
 *     (asserted via page.request, which the fixtures' stack-trace guard does
 *     not scan — the guard would fail on the intentional error banner);
 *   - person/group visibility is enforced by the `person`/`person_group` DB
 *     views, independent of permissions.
 */
test.describe("Role-based permission checks", () => {
  // Persons/families named below are demo-data reference records: the
  // Lockmans and Curtis Jacobs are 6pm (congregation 4), the Calvins are 4pm
  // (congregation 2; John Calvin is also in "Home Group Leaders"), the
  // Luthers are mixed (Elizabeth is 4pm Kids), the Williamsons are 4pm, and
  // "Newsletter" / "Band - Arvo" are groups nobody here is restricted to.

  test("6pmonly sees only its 6pm congregation and lacks sysadmin", async ({ page }) => {
    await login(page, "6pmonly");

    await page.goto("./?view=persons__list_all&search=Lockman");
    await expect(page.locator("#body")).toContainText("Torrie Lockman");
    await expect(page.locator("#body")).toContainText("Nakia Lockman");

    await page.goto("./?view=persons__list_all&search=Calvin");
    await expect(page.locator("#body")).not.toContainText("John Calvin");

    // The attendance cohort chooser is congregation-scoped.
    await page.goto("./?view=attendance__display");
    await expect(page.locator("#body")).toContainText("6pm");
    await expect(page.locator("#body")).not.toContainText("4pm Kids");
    await expect(page.locator("#body")).not.toContainText("External Supporters");

    // PERM_SYSADMIN views are not in this account's menu.
    await page.goto("./?view=admin__user_accounts");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
  });

  test("rostersetter manages rosters but not services, notes or attendance", async ({
    page,
  }) => {
    await login(page, "rostersetter");

    await page.goto("./?view=rosters__define_roster_views");
    await expect(page.locator("#body h1")).toHaveText("Define Roster Views");

    // No PERM_VIEWSERVICE.
    await page.goto("./?view=services__list_all");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");

    // No PERM_EDITATTENDANCE/VIEWATTENDANCE.
    await page.goto("./?view=attendance__display");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");

    // Security fix Z1 (change kwsmxvkr): _execute_plans declares
    // PERM_EDITNOTE, which this account lacks.
    await page.goto("./?view=_execute_plans");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
  });

  test("smallgroupleader sees only its restricted groups and their members", async ({
    page,
  }) => {
    await login(page, "smallgroupleader");

    // Group-restricted: only "Home Group Leaders" (group 9) and
    // "MEN #1 Chris (Wed. 5:30pm)" (group 11) are visible.
    await page.goto("./?view=groups__list_all");
    await expect(page.locator("#body")).toContainText("Home Group Leaders");
    await expect(page.locator("#body")).toContainText("MEN #1 Chris (Wed. 5:30pm)");
    await expect(page.locator("#body")).not.toContainText("Newsletter");
    await expect(page.locator("#body")).not.toContainText("Band - Arvo");

    // Person visibility follows the group restriction: John Calvin is in
    // group 9; the Williamsons are in neither restricted group.
    await page.goto("./?view=persons__list_all&search=Calvin");
    await expect(page.locator("#body")).toContainText("John Calvin");
    await page.goto("./?view=persons__list_all&search=Williamson");
    await expect(page.locator("#body")).not.toContainText("Bridget Williamson");

    await page.goto("./?view=attendance__display");
    await expect(page.locator("#body h1")).toHaveText("Display attendance");
  });

  test("bandleader sees 4pm plus its bands, but not attendance", async ({ page }) => {
    await login(page, "bandleader");

    // Congregation 2 (4pm) plus groups 32/33 (the bands).
    await page.goto("./?view=persons__list_all&search=Calvin");
    await expect(page.locator("#body")).toContainText("John Calvin");
    await page.goto("./?view=persons__list_all&search=Lockman");
    await expect(page.locator("#body")).not.toContainText("Torrie Lockman");

    await page.goto("./?view=groups__list_all");
    await expect(page.locator("#body")).toContainText("Band - Arvo");
    await expect(page.locator("#body")).not.toContainText("Newsletter");

    await page.goto("./?view=services__list_all");
    await expect(page.locator("#body h1")).toHaveText("Service Schedule");
    await page.goto("./?view=services__component_library");
    await expect(page.locator("#body h1")).toHaveText("Service Component Library");

    // No VIEWATTENDANCE.
    await page.goto("./?view=attendance__display");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
  });

  test("kidscare sees only 4pm Kids and lacks all SMS permissions", async ({ page }) => {
    await login(page, "kidscare");

    // Congregation 3 (4pm Kids).
    await page.goto("./?view=persons__list_all&search=Luther");
    await expect(page.locator("#body")).toContainText("Elizabeth Luther");
    await page.goto("./?view=persons__list_all&search=Calvin");
    await expect(page.locator("#body")).not.toContainText("John Calvin");

    await page.goto("./?view=attendance__display");
    await expect(page.locator("#body")).toContainText("4pm Kids");
    await expect(page.locator("#body")).not.toContainText("6pm");
    await expect(page.locator("#body")).not.toContainText("External Supporters");

    // No PERM_VIEWSMS: the Messages view is out of the menu (security
    // finding S5's sibling gate — only SMS-permitted accounts reach it).
    await page.goto("./?view=persons__messages");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
  });

  test("noperms is denied every permission-gated view and call", async ({
    page,
    baseURL,
  }) => {
    await login(page, "noperms");

    // Views are denied at the dispatcher (not in the session menu).
    await page.goto("./?view=persons__messages");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
    await page.goto("./?view=_execute_plans");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");
    await page.goto("./?view=admin__user_accounts");
    await expect(page.locator("#body")).toContainText("Error: Undefined view");

    // Calls are denied by the Z1/Z2 permission registry
    // (Call::getRequiredPermissionLevel, enforced in System_Controller).
    // Fetched via page.request because the denial renders as Jethro's fatal
    // error banner, which the fixtures' stack-trace guard fails on.
    const rosterCsv = await page.request.get(`${baseURL}?call=display_roster&viewid=1`);
    expect(rosterCsv.status()).toBe(500);
    expect(await rosterCsv.text()).toContain("have permission to perform this operation");
  });
});

test.describe("Login", () => {
  test("logs in with demo credentials and lands on the home page", async ({ page }) => {
    await login(page);

    // After successful login, the login form should no longer be visible
    // and we should see the standard post-login UI.
    await expect(page.locator('input[value="Log In"]')).not.toBeVisible();

    // Jethro's home page typically shows the main menu.
    // The navbar-brand or a known nav element confirms we're inside the app.
    await expect(page.locator("h1:has-text('Jethro PMM')")).toBeVisible();
  });
});
