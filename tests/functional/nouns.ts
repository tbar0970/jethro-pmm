import { expect, type Page } from "@playwright/test";
import { to } from "./timeouts.js";

/**
 * Read-only "nouns" checks for the Jethro data model.
 *
 * Every Jethro "noun" is a db_object subclass (person, family, person_group,
 * congregation, service, ...); every verb is a view that lists or renders it
 * (?view=...).  These checks log in (once, by the caller) and then walk the
 * verbs, asserting that the expected records are showing.  Nothing here ever
 * submits a form.
 *
 * The same checks run against both the demo-data instance (lookaround.spec.ts) and
 * the wizard-built instance (walkthrough.spec.ts).  Each instance gets its own
 * expectation set: the wizard-built one starts from a blank database and only
 * contains the data the walkthrough "verbs" tests create, so its samples are
 * the records those tests add, matching the reference instance at
 * http://localhost:8081/ where the two overlap.
 */
export interface NounCheck {
  /** the db_object subclass being validated */
  noun: string;
  /** the verb: full query string (view + params) that lists/renders the noun */
  view: string;
  /** expected page heading in #body; omit when the view may redirect (search hits) */
  title?: string;
  /** records that must be visible on the view */
  samples?: string[];
  /** match samples as input values rather than page text (forms) */
  asInput?: boolean;
  /** match sample text exactly (whole element text) */
  exact?: boolean;
  /** click this tab (ul.nav-tabs a) before asserting samples */
  clickTab?: string;
  /** the view is expected to render with no data (title check only) */
  empty?: boolean;
}

/**
 * Nouns that have no standalone view: they are sub-objects rendered inside a
 * parent's view, so they are validated indirectly via their parent noun.
 */
export const SUBOBJECTS: Record<string, string> = {
  abstract_note: "note",
  person_note: "note",
  family_note: "note",
  note_comment: "note",
  action_plan_note: "action_plan",
  action_plan_age_bracket: "action_plan",
  custom_field_option: "custom_field",
  custom_field_value: "person",
  person_group_headcount: "person_group",
  person_group_membership: "person_group",
  person_group_membership_status: "person_group",
  planned_absence: "person",
  roster_role_assignment: "roster_view",
  roster_role_team: "roster_role",
  roster_view_role_membership: "roster_view",
  roster_view_service_field: "roster_view",
  congregation_service_component: "service_component",
  service_component_tag: "service_component",
  service_component_tagging: "service_component",
  service_item: "service",
  service_bible_reading: "service",
  attendance_record: "attendance",
  headcount: "attendance",
  checkin: "attendance",
  venue: "service", // venues are used by services; no standalone list view
};

export const DEMO_NOUNS: NounCheck[] = [
  { noun: "person", view: "view=persons__list_all&search=Calvin", samples: ["John Calvin"] },
  { noun: "person", view: "view=persons__list_all&search=Luther", samples: ["Martin Luther", "Hans Luther", "Elizabeth Luther"] },
  { noun: "person", view: "view=persons__list_all&search=von Bora", samples: ["Katharina von Bora"] },
  { noun: "family", view: "view=families__list_all&search=Calvin", samples: ["Calvin"] },
  { noun: "family", view: "view=families__list_all&search=Lockman", samples: ["Lockman"] },
  { noun: "person_group", view: "view=groups__list_all", samples: ["Newsletter", "Kids Church - Parents", "Band - Arvo"] },
  { noun: "person_group_category", view: "view=groups__manage_categories", title: "Person Group Categories", samples: ["MINISTRY", "SUNDAY SERVICES", "ADMIN"] },
  { noun: "congregation", view: "view=admin__congregations", title: "Congregations", exact: true, samples: ["4pm", "4pm Kids", "6pm", "None", "External Supporters"] },
  { noun: "service", view: "view=services__list_all&start_date=2026-01-01&end_date=2026-12-31", title: "Service Schedule", samples: ["Shepherd", "Christmas Day"] },
  { noun: "staff_member", view: "view=admin__user_accounts", title: "User Accounts", samples: ["Dennis Demo", "John Calvin", "Idelette de Bure"] },
  { noun: "service_component", view: "view=services__component_library", clickTab: "Prayers", samples: ["Confession AS1"] },
  { noun: "service_component_category", view: "view=services__component_library", samples: ["Songs", "Prayers", "Creeds", "Other"] },
  { noun: "roster_role", view: "view=rosters__define_roster_roles", title: "Define Roster Roles", samples: ["Preacher", "Reader", "Band"] },
  { noun: "roster_view", view: "view=rosters__define_roster_views", title: "Define Roster Views", samples: ["4pm Church", "6pm Church"] },
  { noun: "custom_field", view: "view=admin__custom_fields", title: "Custom Fields", asInput: true, samples: ["Date of Birth", "WWCC Number", "Friend Of"] },
  { noun: "person_status", view: "view=admin__system_configuration", asInput: true, samples: ["Core", "Crowd", "Contact", "Archived"] },
  { noun: "age_bracket", view: "view=admin__system_configuration", asInput: true, samples: ["Adult", "High school", "Baby"] },
  { noun: "action_plan", view: "view=admin__action_plans", title: "Action plans", samples: ["1st Visit (Church)", "Present last Sunday"] },
  // The demo data's notes are all status "no_action"; the notes views only
  // list pending notes, so this noun renders its empty state here.
  { noun: "note", view: "view=notes__for_immediate_action", title: "Notes For Immediate Action", empty: true },
  { noun: "person_query", view: "view=persons__reports", title: "Person Reports", samples: ["Visitors - last 5 weeks"] },
  { noun: "note_template", view: "view=admin__note_templates", title: "Configure Note Templates", empty: true },
  { noun: "attendance_record", view: "view=attendance__display&params_submitted=1&cohortids[]=c-2&start_date=2024-01-01&end_date=2024-12-31&format=sequential&order=status", title: "Display attendance", samples: ["Dennis Demo"] },
];

export const WALKTHROUGH_NOUNS: NounCheck[] = [
  // Persons/families created by the wizard (Dennis Demo / Demo) plus the
  // families and members the walkthrough verbs tests add.
  { noun: "person", view: "view=persons__list_all&search=Calvin", samples: ["John Calvin"] },
  { noun: "person", view: "view=persons__list_all&search=Luther", samples: ["Martin Luther"] },
  { noun: "person", view: "view=persons__list_all&search=von Bora", samples: ["Katharina von Bora"] },
  { noun: "person", view: "view=persons__list_all&search=Beza", samples: ["Theodore Beza"] },
  { noun: "person", view: "view=persons__list_all&search=Farel", samples: ["Guillaume Farel"] },
  { noun: "family", view: "view=families__list_all&search=Calvin", samples: ["Calvin"] },
  { noun: "family", view: "view=families__list_all&search=Luther", samples: ["Luther"] },
  { noun: "family", view: "view=families__list_all&search=Beza", samples: ["Beza"] },
  { noun: "family", view: "view=families__list_all&search=Farel", samples: ["Farel"] },
  { noun: "person_group", view: "view=groups__list_all", samples: ["Newsletter", "Kids Church - Parents", "Band - Arvo"] },
  { noun: "person_group_category", view: "view=groups__manage_categories", title: "Person Group Categories", samples: ["MINISTRY", "SUNDAY SERVICES", "ADMIN", "KIDS"] },
  { noun: "congregation", view: "view=admin__congregations", title: "Congregations", exact: true, samples: ["4pm", "4pm Kids", "6pm", "None", "External Supporters"] },
  { noun: "service", view: "view=services__list_all&start_date=2026-01-01&end_date=2026-12-31", title: "Service Schedule", samples: ["Shepherd"] },
  { noun: "staff_member", view: "view=admin__user_accounts", title: "User Accounts", samples: ["Dennis Demo", "John Calvin", "Idelette de Bure"] },
  { noun: "service_component", view: "view=services__component_library", clickTab: "Prayers", samples: ["Confession AS1"] },
  { noun: "service_component_category", view: "view=services__component_library", samples: ["Songs", "Prayers", "Creeds", "Other"] },
  { noun: "roster_role", view: "view=rosters__define_roster_roles", title: "Define Roster Roles", samples: ["Preacher", "Reader", "Band"] },
  { noun: "roster_view", view: "view=rosters__define_roster_views", title: "Define Roster Views", samples: ["4pm Church"] },
  { noun: "custom_field", view: "view=admin__custom_fields", title: "Custom Fields", asInput: true, samples: ["Date of Birth", "WWCC Number"] },
  { noun: "person_status", view: "view=admin__system_configuration", asInput: true, samples: ["Core", "Crowd", "Contact", "Archived"] },
  { noun: "age_bracket", view: "view=admin__system_configuration", asInput: true, samples: ["Adult", "High school", "Baby"] },
  { noun: "action_plan", view: "view=admin__action_plans", title: "Action plans", samples: ["1st Visit (Church)", "Present last Sunday"] },
  // The "Ill health" note was marked No Further Action; the execute_plans test
  // may create new pending notes via action plan execution.
  { noun: "note_template", view: "view=admin__note_templates", title: "Configure Note Templates", samples: ["Welcome Follow-up"] },
  { noun: "person_query", view: "view=persons__reports", title: "Person Reports" },
  { noun: "roster_role_assignment", view: "view=rosters__display_roster_assignments&viewid=1&start_date=2026-08-02&end_date=2026-09-13", title: "4pm Church" },
  { noun: "attendance_record", view: "view=attendance__display&params_submitted=1&cohortids[]=c-1&start_date=2026-01-01&end_date=2026-12-31&format=sequential&order=status", title: "Display attendance", samples: ["Dennis Demo"] },
  { noun: "note", view: "view=notes__for_immediate_action", title: "Notes For Immediate Action", empty: true },
];

export async function validateNouns(page: Page, nouns: NounCheck[]): Promise<void> {
  for (const n of nouns) {
    await page.goto(`?${n.view}`);
    if (n.title) {
      await expect(page.locator("#body h1")).toHaveText(n.title, { timeout: to(10000) });
    }
    if (n.clickTab) {
      await page.locator(`ul.nav-tabs a:has-text("${n.clickTab}")`).click();
    }
    if (n.samples) {
      for (const sample of n.samples) {
        // Scope to #body: the masthead's hidden "Logged in as" dropdown
        // contains person names too, and would otherwise win .first().
        const loc = n.asInput
          ? page.locator("#body").locator(`input[value="${sample}"]`)
          : page.locator("#body").getByText(sample, n.exact ? { exact: true } : undefined);
        await expect(loc.first()).toBeVisible({ timeout: to(10000) });
      }
    }
  }
}
