import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, scenarioProvider, sentSms } from "./scenario.js";
import { login } from "../auth.js";

test.describe("SMS admin history filters", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("Send SMS to family, then filter history by recipient name and body", async ({
    page,
    request,
  }) => {
    // Heavy global messages page (seed data has ~14k deliveries) rendered
    // twice here; under concurrent load this legitimately approaches the
    // default 30s budget, so give it headroom rather than racing the clock.
    test.slow();
    // 1. Generate a unique timestamp for this test run
    const ts = Date.now().toString();
    const messageBody = `Filter test message ${ts}`;

    // 2. Navigate to Mann family (familyid=5)
    await page.goto("./?view=families&familyid=5");
    await page.waitForLoadState("load");

    // 3. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 4. Fill the message with the timestamped body
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill(messageBody);

    // 5. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    const results = page.locator("#bulk-sms-results");
    // 6. Wait for success result
    await expect(results).toContainText("Message successfully sent", {
      timeout: to(15000),
    });

    // Verify the POST body matches what was sent
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    const sent = sentSms(test.info(), captured.json);
    expect(sent.message).toContain("Filter test message");
    expect(sent.recipients.length, "Mann family should have multiple recipients").toBeGreaterThanOrEqual(2);

    // 7. Navigate to Persons > SMS page
    await page.goto("./?view=persons__messages");
    await page.waitForLoadState("load");

    // 8. Wait for history table to render with at least one row
    const historyTable = page.locator("table.sms-history-table tbody");
    await expect(historyTable).toBeVisible();
    const rows = page.locator("table.sms-history-table tbody tr");
    await expect(rows).not.toHaveCount(0, { timeout: to(10000) });

    // 10. Recipient filter: "Sena Mann" keeps our row and hides the others.
    const recipientFilterInput = page.locator("#sms-filter-recipient");
    await expect(recipientFilterInput).toBeVisible();
    const matchingRow = rows.filter({ hasText: messageBody });
    const otherRow = rows.filter({ hasNotText: "Sena Mann" }).first();
    const hadOtherRow = (await otherRow.count()) > 0;
    await recipientFilterInput.fill("Sena Mann");

    await expect(matchingRow).toContainText("Sena Mann");
    if (hadOtherRow) {
      // Without this the filter could be doing nothing at all.
      await expect(otherRow).toBeHidden({ timeout: to(5000) });
    }

    // 12. Clear the recipient filter
    await recipientFilterInput.fill("");
    await expect(matchingRow).toBeVisible({ timeout: to(5000) });

    // 13. Body filter: the timestamp keeps our row and hides the rest.
    const bodyFilterInput = page.locator("#sms-filter-body");
    await expect(bodyFilterInput).toBeVisible();
    const nonMatchingRow = rows.filter({ hasNotText: ts }).first();
    const hadNonMatching = (await nonMatchingRow.count()) > 0;
    await bodyFilterInput.fill(ts);

    await expect(matchingRow).toBeVisible({ timeout: to(5000) });
    if (hadNonMatching) {
      await expect(nonMatchingRow).toBeHidden({ timeout: to(5000) });
    }

    // 15. Clear the body filter; our row stays visible.
    await bodyFilterInput.fill("");
    await expect(matchingRow).toBeVisible({ timeout: to(5000) });
  });

  test("Send History tab: Cost column, Single recipients only filter, and Body text filter", async ({
    page,
  }) => {
    test.skip(scenarioProvider(test.info()) !== "cellcast", "filters seeded history only; provider-independent");
    // Heavy global messages page (seed data has ~14k deliveries) with many
    // client-side filter interactions; give headroom under concurrent load.
    test.slow();
    // 1. Navigate to Messages page
    await page.goto("./?view=persons__messages");
    await page.waitForLoadState("load");

    // 2. Verify filter fields are present (no tabs — content is shown directly)

    // 3. Verify filter fields are present
    const senderFilter = page.locator("#sms-filter-sender");
    const recipientFilter = page.locator("#sms-filter-recipient");
    const singleOnlyCheckbox = page.locator("#sms-filter-single-only");
    const bodyFilter = page.locator("#sms-filter-body");
    const dateFromFilter = page.locator("#sms-filter-date-from");
    const dateToFilter = page.locator("#sms-filter-date-to");
    const costCheckbox = page.locator("#sms-filter-show-cost");
    const clearButton = page.locator("#sms-filter-clear");

    await expect(senderFilter).toBeVisible();
    await expect(recipientFilter).toBeVisible();
    await expect(singleOnlyCheckbox).toBeVisible();
    await expect(bodyFilter).toBeVisible();
    await expect(dateFromFilter).toBeVisible();
    await expect(dateToFilter).toBeVisible();
    await expect(costCheckbox).toBeVisible();
    await expect(clearButton).toBeVisible();

    // 4. Verify history table has rows
    const rows = page.locator("table.sms-history-table tbody tr");
    await expect(rows).not.toHaveCount(0, { timeout: to(10000) });

    // 5. Click 'Cost' checkbox and verify cost column appears
    // Cost column header and cells are initially display:none (data-show="$showCost")
    const costHeader = page.locator('th[data-show="$showCost"]');
    await expect(costHeader).toBeHidden();

    await costCheckbox.check();
    await page.waitForTimeout(200);

    // After checking, the cost column header should become visible
    await expect(costHeader).toBeVisible();

    // Verify cost cells have their display:none removed (Datastar data-show toggle)
    const firstCostCell = page.locator("td.sms-history-cost").first();
    await expect(async () => {
      const display = await firstCostCell.evaluate(
        (el) => el.style.display
      );
      expect(display).toBe("");
    }).toPass({ timeout: to(5000) });

    // Verify the cost total element is present and updated
    const costTotal = page.locator("#sms-cost-total");
    await expect(costTotal).toBeVisible();
    await expect(costTotal).not.toHaveText("$0.00");
    // 6. Click 'Single recipients only' checkbox
    await singleOnlyCheckbox.check();
    await page.waitForTimeout(200);

    // 7. Verify no visible rows have multiple recipients — Datastar hides
    //    filtered rows with style.display="none".  Playwright's :visible
    //    pseudo-selector skips those, so we assert count is zero.
    const visibleMultiRows = page.locator('tr[data-multi="1"]:visible');
    await expect(visibleMultiRows).toHaveCount(0);

    // Verify there is at least one visible single-recipient row
    const firstSingleVisible = page.locator('tr[data-multi="0"]:visible').first();
    await expect(firstSingleVisible).toBeVisible({ timeout: to(5000) });

    // 8. Clear the single-recipient filter, then type 'Men' in Body text field
    await singleOnlyCheckbox.uncheck();
    await page.waitForTimeout(150);

    await bodyFilter.fill("Men");

    // 9. Every visible row matches 'men' (case-insensitive), and at least one
    //    does. Asserting the non-matching count is 0 means the filter cannot
    //    pass by doing nothing.
    await expect(
      page.locator('table.sms-history-table tbody tr:visible[data-body*="men" i]'),
    ).not.toHaveCount(0, { timeout: to(10000) });
    await expect(
      page.locator('table.sms-history-table tbody tr:visible:not([data-body*="men" i])'),
    ).toHaveCount(0, { timeout: to(10000) });
  });
});
