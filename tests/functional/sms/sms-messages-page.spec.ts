import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

test.describe("Messages page", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("non-final deliveries have Datastar polling spans", async ({ page }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
    const message = `Messages-page test ${timestamp}`;

    // 1. Send a scheduled SMS to the Calvin family
    await page.goto("./?view=families&familyid=2");
    await page.waitForLoadState("load");

    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    await smsForm.locator(".sms-message").fill(message);

    const scheduleCheckbox = smsForm.locator(".sms-schedule-toggle");
    await expect(scheduleCheckbox).toBeVisible();
    await scheduleCheckbox.check();

    const dateTimeInput = smsForm.locator('input[type="datetime-local"][name="send_at"]');
    await expect(dateTimeInput).toBeVisible();

    await page.evaluate(() => {
      const input = document.querySelector(
        'input[type="datetime-local"][name="send_at"]'
      ) as HTMLInputElement;
      const dt = new Date(Date.now() + 24 * 3600_000);
      input.value = dt.toISOString().slice(0, 16);
      input.dispatchEvent(new Event("change", { bubbles: true }));
    });

    await smsForm.locator(".bulk-sms-submit").click();
    await expect(page.locator("#bulk-sms-results")).toContainText(
      "Message scheduled for sending to 3 recipients",
      { timeout: to(15000) }
    );

    // 2. Navigate to the Messages page
    await page.goto("./?view=persons__messages");
    await page.waitForLoadState("load");

    const historyTable = page.locator(".sms-history-table tbody");
    await expect(historyTable).toBeVisible();

    // 3. Find the row for our message
    const messageRow = page.locator(`tr[data-body*="${timestamp}"]`);
    await expect(messageRow).toBeVisible({ timeout: to(10000) });

    // 4. Assert scheduled deliveries have Datastar polling spans.
    // data-on-interval__duration.Ns is the full attribute name; CSS [attr]
    // requires exact match, so use class-only selector and count all
    // message-attribution spans (only scheduled deliveries get them).
    const pollingSpans = messageRow.locator('span.message-attribution');
    // Calvin family has 3 recipients — all scheduled
    await expect(pollingSpans).toHaveCount(3, { timeout: to(5000) });

    // Each polling span shows the 🕐 icon (inside the inner message-status span)
    const firstStatusSpan = pollingSpans.first().locator('span.message-status');
    await expect(firstStatusSpan).toContainText('🕐');

    // 5. The scheduled time should appear after the icon
    // (formatted as "in X hours" or as an absolute date)
    await expect(firstStatusSpan).toContainText(/\d/);
  });
});
