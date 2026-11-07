import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, sentSms } from "./scenario.js";
import { login } from "../auth.js";

let scheduledTimestamp = "";
test.describe("SMS schedule send and cancel", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  // Both tests share DB state — the cancel test depends on the schedule test's row.
  test.describe.configure({ mode: "serial" });

  test("schedule SMS to Calvin family for future delivery", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
    const message = `Scheduled test ${timestamp}`;

    // 1. Navigate to the Calvin family (familyid=2)
    await page.goto("./?view=families&familyid=2");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message textarea
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill(message);

    // 4. Verify the "Schedule Send…" checkbox is visible (capability gating)
    const scheduleCheckbox = smsForm.locator(".sms-schedule-toggle");
    await expect(scheduleCheckbox).toBeVisible();

    // 5. Check "Schedule Send…" — the datetime picker should appear
    await scheduleCheckbox.check();
    const dateTimeInput = smsForm.locator('input[type="datetime-local"][name="send_at"]');
    await expect(dateTimeInput).toBeVisible();

    // 6. Set send_at to a datetime 24 hours in the future
    await page.evaluate(() => {
      const el = document.querySelector(
        'input[type="datetime-local"][name="send_at"]'
      ) as HTMLInputElement;
      if (el) {
        // 24 hours from now
        const future = new Date();
        future.setHours(future.getHours() + 24);
        const year = future.getFullYear();
        const month = String(future.getMonth() + 1).padStart(2, "0");
        const day = String(future.getDate()).padStart(2, "0");
        const hours = String(future.getHours()).padStart(2, "0");
        const minutes = String(future.getMinutes()).padStart(2, "0");
        el.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        el.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });

    // 7. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 8. Wait for success result with 3 recipients
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message scheduled for sending to 3 recipients", {
      timeout: to(15000),
    });
    await expect(results).toContainText("John Calvin");
    await expect(results).toContainText("Idelette de Bure");
    await expect(results).toContainText("Pierre de Bure");

    // 9. Verify the provider was asked for a deferred send to all 3 recipients
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    const sent = sentSms(test.info(), captured.json);
    expect(sent.scheduledAt, "a deferred send must carry its send time").not.toBeNull();
    expect(sent.scheduledAt!, "send time must be in the future").toBeGreaterThan(Date.now());
    expect(sent.message).toContain("Scheduled test");
    expect(sent.recipients).toHaveLength(3);
    expect(sent.sender).toBeDefined();

    // Store timestamp for the next test
    scheduledTimestamp = timestamp;
  });

  test("scheduled SMS appears as 'scheduled' then can be cancelled", async ({ page }) => {
    const timestamp = scheduledTimestamp;
    if (!timestamp) {
      throw new Error("Schedule test did not run or failed to set timestamp");
    }

    // 1. Navigate to Persons > SMS page
    await page.goto("./?view=persons__messages");
    await page.waitForLoadState("load");

    // 2. Wait for the history table to render
    const historyTable = page.locator(".sms-history-table tbody");
    await expect(historyTable).toBeVisible();

    // 3. Locate the SMS just sent by finding a row containing the timestamp
    const messageRow = page.locator(`tr[data-body*="${timestamp}"]`);
    await expect(messageRow).toBeVisible({ timeout: to(10000) });

    // 4. Assert at least one delivery row shows a scheduled icon.
    // renderSmsDeliveryStatusIcon uses title="scheduled: …" on message-status spans.
    const scheduledBadge = messageRow.locator('span.message-status[title^="scheduled"]').first();
    await expect(scheduledBadge).toBeVisible();

    // 5. Find and click the Cancel button for this SMS entry
    const cancelContainer = messageRow.locator('[id^="sms-cancel-"]');
    const cancelButton = cancelContainer.locator("a");
    await expect(cancelButton).toBeVisible();

    // Wait for the Datastar POST to complete before asserting the badge change
    const cancelPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_cancel") && r.status() === 200
    );

    await cancelButton.click();
    await cancelPost;

    // 6. Assert the cancel took effect via the handler's Datastar morph, in
    //    place — NOT by reloading the page. The cancel response morphs
    //    #sms-cancel-<id> to "Cancelled N deliveries." and each
    //    #sms-delivery-status-<id> span to title="cancelled" (see
    //    calls/call_sms_cancel.class.php). Reloading would re-read the shared
    //    messages table, which a concurrent test's status poll can rebuild
    //    globally (syncRecentDeliveries drops/recreates smsdelivery + sms),
    //    stomping this row's cancelled status and making the check flaky.
    await expect(cancelContainer).toContainText(/Cancelled \d+ deliver/i, {
      timeout: to(10000),
    });
    const cancelledBadge = messageRow
      .locator('span.message-status[title^="cancelled"]')
      .first();
    await expect(cancelledBadge).toBeVisible({ timeout: to(10000) });
  });
});
