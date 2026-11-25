import type { Page } from "@playwright/test";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

/**
 * System-wide search (?view=_mixed_search), as rewritten by #1571.
 *
 * The page searches live while the user types: Datastar fetches the `results`
 * fragment and morphs `#mixed-search-results`. A full page load (Enter) still
 * works and jumps straight to the record when there is exactly one match.
 *
 * Runs against the standard demo database at the root URL, like the import
 * project, so no scenario `.conf` is needed. Every assertion works on data from
 * the demo dump (Calvin/Luther persons and families, the "Friend Of" searchable
 * custom field); tests are read-only and safe among parallel workers.
 */

const REGION = "#mixed-search-results";

/** A GET against the current scenario, returning the response without following redirects. */
async function get(page: Page, url: string) {
  return page.request.get(url, { maxRedirects: 0 });
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test("full page shows the live search form and its results", async ({ page }) => {
  await page.goto("?view=_mixed_search&search=Calvin");
  await expect(page.getByRole("heading", { name: "System-Wide Search" })).toBeVisible();
  await expect(page.locator('input[name="search"]')).toHaveValue("Calvin");
  const results = page.locator(REGION);
  await expect(results).toContainText("Calvin Family");
  await expect(results).toContainText("John Calvin");
});

test("name matches print the family with its members indented beneath", async ({ page }) => {
  await page.goto("?view=_mixed_search&search=Calvin");
  const html = await page.locator(REGION).innerHTML();
  expect(html.indexOf("Calvin Family")).toBeGreaterThanOrEqual(0);
  expect(html.indexOf("Calvin Family")).toBeLessThan(html.indexOf("John Calvin"));
  expect(html).toContain('&nbsp;&nbsp;&nbsp;&nbsp;<i class="icon-user"></i> John Calvin');
});

test("Search in: name does not search contact remarks", async ({ page }) => {
  // "Wittenberg" appears in Martin Luther's contact remarks and the Luther
  // family's street address, but in no person, family, group or report name.
  await page.goto("?view=_mixed_search&search=Wittenberg&searchin=name");
  await expect(page.locator("#body")).toContainText("No results were found");
});

test("Search in: contact remarks annotates the matched text", async ({ page }) => {
  await page.goto("?view=_mixed_search&search=Wittenberg&searchin=remarks");
  const match = page.locator(REGION).locator(".search-match").first();
  await expect(match).toContainText("Contact Remarks:");
  await expect(match.locator("b")).toHaveText("Wittenberg");
});

test("Search in: email matches exactly and annotates the row", async ({ page }) => {
  // One email matches, so a full page load would redirect; inspect the fragment instead.
  const response = await get(
    page,
    "?view=_mixed_search&fragment=results&search=mluther@wittenberg.edu.de&searchin=email",
  );
  const body = await response.text();
  expect(body).toContain("Martin Luther");
  expect(body).toContain('Email: <b>mluther@wittenberg.edu.de</b>');
});

test("Search in: address finds the family", async ({ page }) => {
  await page.goto("?view=_mixed_search&search=Collegienstrasse&searchin=address");
  const results = page.locator(REGION);
  await expect(results).toContainText("Luther Family");
  await expect(results.locator(".search-match").first()).toContainText("Street Address:");
});

test("Search in: a searchable custom field", async ({ page }) => {
  // custom_5 is the demo's "Friend Of" field (searchable), whose values include "Torrie".
  await page.goto("?view=_mixed_search&search=Torrie&searchin=custom_5");
  const match = page.locator(REGION).locator(".search-match").first();
  await expect(match).toContainText("Friend Of:");
  await expect(match.locator("b")).toHaveText("Torrie");
});

test("Search for: families filters out persons", async ({ page }) => {
  // Exactly one family matches, so a full page load would redirect; inspect the fragment.
  const response = await get(page, "?view=_mixed_search&fragment=results&search=Calvin&searchtype=f");
  const body = await response.text();
  expect(body).toContain("Calvin Family");
  expect(body).not.toContain("icon-user");
});

test("the results fragment is the morph target only", async ({ page }) => {
  const response = await get(page, "?view=_mixed_search&fragment=results&search=Calvin");
  expect(response.status()).toBe(200);
  const body = await response.text();
  expect(body.trimStart().startsWith('<div id="mixed-search-results">')).toBe(true);
  expect(body).toContain("Calvin Family");
  expect(body).not.toContain("<form");
  expect(body).not.toContain("Search results");
});

test("a single match redirects on a full load but not in a fragment", async ({ page }) => {
  // The email is unique to Idelette de Bure (personid 3) in the demo data.
  const fragment = await get(
    page,
    "?view=_mixed_search&fragment=results&search=idelette1540@hotmail.com&searchin=email",
  );
  expect(fragment.status()).toBe(200);
  expect(await fragment.text()).toContain("Idelette");

  // A full page load must instead jump to the one match.
  await page.goto("?view=_mixed_search&search=idelette1540@hotmail.com&searchin=email");
  await expect(page).toHaveURL(/view=persons/);
  expect(new URL(page.url()).searchParams.get("personid")).toBe("3");
});

test("name matches and other-field matches are split into sections", async ({ page }) => {
  // "John" matches persons/group by name, and Idelette's remarks and two Lang
  // families' addresses on other fields. The name matches list first (persons,
  // then the group) under no heading; the Other matches heading follows.
  await page.goto("?view=_mixed_search&search=John");
  const results = page.locator(REGION);
  const html = await results.innerHTML();
  const index = (text: string) => {
    const i = html.indexOf(text);
    expect(i).toBeGreaterThanOrEqual(0);
    return i;
  };
  const calvin = index("John Calvin");
  const alissa = index("Alissa Johnston");
  const josie = index("Josie Johnston");
  const group = index("MIXED #2 John");
  const other = index("Other matches (3)");
  const remarks = index("Idelette de Bure");
  expect(alissa).toBeGreaterThan(calvin);
  expect(josie).toBeGreaterThan(alissa);
  expect(group).toBeGreaterThan(josie);
  expect(other).toBeGreaterThan(group);
  expect(remarks).toBeGreaterThan(other);
  await expect(results.locator("h3.search-section, h4.search-section")).toHaveCount(1);
  await expect(results.locator("h4.search-section")).toContainText("Other matches (3)");
});

test("a search that matches only on other fields has no section headings", async ({ page }) => {
  // "Wittenberg" appears only in remarks/address in the demo data.
  await page.goto("?view=_mixed_search&search=Wittenberg");
  const results = page.locator(REGION);
  await expect(results).toContainText("Martin Luther");
  await expect(results.locator("h3.search-section, h4.search-section")).toHaveCount(0);
});

test("typing updates the results region in place", async ({ page }) => {
  await page.goto("?view=_mixed_search");
  const results = page.locator(REGION);
  // The empty region collapses (so it is not "visible"), but it is still the morph target.
  await expect(results).toHaveText("");

  await page.locator('input[name="search"]').fill("Calvin");
  await expect(results).toContainText("John Calvin");
  // The live handler keeps the URL in step, so a reload or Back preserves the search.
  await expect(page).toHaveURL(/[?&]search=Calvin/);
});

test("% and _ in the search term are matched literally", async ({ page }) => {
  // No name, address, remarks or searchable custom field in the demo data contains
  // '%' or '_', so each of these must find nothing. Before, the term went into
  // LIKE unescaped: '%' matched every person with remarks, '_' every person.
  const queries = [
    "search=%25&searchin=remarks", // '%'
    "search=_&searchin=name", // '_'
    "search=C_lvin&searchin=name", // 'C_lvin' would match John Calvin
    "search=Calvin%25&searchin=name", // 'Calvin%' would match John Calvin
    "search=__XED&searchin=name&searchtype=g", // '__XED' would match the MIXED groups
  ];
  for (const query of queries) {
    const response = await get(page, `?view=_mixed_search&fragment=results&${query}`);
    const body = await response.text();
    expect(body, query).toContain("No results were found");
  }
});

test("a fragment request is refused rather than answered with the login page", async ({
  playwright,
}) => {
  // After the session expires, the live search's fetch must not receive the login
  // page: it is HTML, and Datastar would treat it as a patch to splice into the page.
  const base = test.info().project.use.baseURL;
  const anonymous = await playwright.request.newContext();
  const response = await anonymous.get(`${base}?view=_mixed_search&fragment=results&search=Calvin`);
  expect(response.status()).toBe(401);
  await anonymous.dispose();
});

test("array-shaped search parameters are ignored", async ({ page }) => {
  // `search[]=x` and `searchtype[]=x` were TypeErrors (fatal); `searchin[]=x`
  // raised an "Array to string" warning. The full page runs both the search
  // and the form, so it exercises all three parameters.
  const queries = [
    "search[]=x", // no search term
    "search=Calvin&searchin[]=x", // falls back to "everything"
    "search=Calvin&searchtype[]=x", // falls back to "everything"
  ];
  for (const query of queries) {
    const response = await get(page, `?view=_mixed_search&${query}`);
    expect(response.status(), query).toBe(200);
    const body = await response.text();
    expect(body, query).not.toContain("SYSTEM ERROR");
    if (query.includes("Calvin")) expect(body, query).toContain("John Calvin");
  }
});

test("changing Search in or Search for re-runs the search", async ({ page }) => {
  // The live handler listens for `input` on the form only; radios fire `input`
  // when they change. "Wittenberg" is in no name, only in remarks and an address.
  await page.goto("?view=_mixed_search&search=Wittenberg&searchin=name");
  const results = page.locator(REGION);
  await expect(results).toContainText("No results were found");

  await page.check('input[name="searchin"][value="remarks"]');
  await expect(results).toContainText("Martin Luther");
  await expect(page).toHaveURL(/[?&]searchin=remarks/);

  await page.locator("summary", { hasText: "Search for..." }).click();
  await page.check('input[name="searchtype"][value="f"]');
  await expect(results).toContainText("No results were found");
  await expect(page).toHaveURL(/[?&]searchtype=f/);
});
