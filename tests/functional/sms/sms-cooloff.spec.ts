import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { mockProfile, scenarioProvider, sentSms } from "./scenario.js";
import { login } from "../auth.js";

test.describe("SMS cooloff delay", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(mockProfile(test.info()), "lastPost"));
    await login(page);
  });

  test("immediate send is scheduled with a future send time, Cancel link, and Datastar polling", async ({
    page,
    request,
  }) => {
    // Projects (cellcast/v5/mobilemessage) share the DB and may start in the
    // same millisecond, so the project name keeps the body unique.
    const timestamp = `${test.info().project.name}-${new Date().toISOString().replace(/[:.]/g, "-")}`;
    const message = `Cooloff test ${timestamp}`;

    // 1. Visit Idelette de Bure's person page (personid=3)
    await page.goto("./?view=persons&personid=3");
    await page.waitForLoadState("load");

    // 2. Open the SMS modal via the mobile number dropdown
    const mobileLink = page.locator("#mobile-3");
    await expect(mobileLink).toBeVisible();
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();

    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 3. Fill and send — SMS_SEND_COOLOFF=30 defers the send by ~30s
    const messageTextarea = modal.locator(".sms-message");
    await messageTextarea.fill(message);
    const sendTime = Date.now();

    // 4. Click send and wait for the page to reload.
    //    jethro-sms.js finishSend() hides the modal then calls
    //    location.reload() (line 310).  We must let that complete
    //    before navigating away, otherwise page.goto() collides.
    const reloadPromise = page.waitForEvent('load', { timeout: to(10000) });
    await modal.locator(".sms-submit").click();
    await reloadPromise;

    // 5. Verify the mock proxy captured the POST
    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    const captured = await lastPost.json();

    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    const sent = sentSms(test.info(), captured.json);

    expect(sent.scheduledAt, "cooloff should give immediate sends a send time").not.toBeNull();
    // SMS_SEND_COOLOFF=30s → the send time should be 28-34s in the future.
    // MobileMessage sends anything scheduled ≤60s ahead at once, so its
    // provider clamps the cool-off up to now+90s (its clock, whole seconds).
    const [minAhead, maxAhead] = scenarioProvider(test.info()) === "mobilemessage" ? [88_000, 96_000] : [28_000, 34_000];
    expect(sent.scheduledAt! - sendTime).toBeGreaterThan(minAhead);
    expect(sent.scheduledAt! - sendTime).toBeLessThan(maxAhead);

    expect(sent.message).toBe(message);
    expect(sent.recipients).toEqual(["0491570158"]);

    // 6. Navigate to Messages page — renders from the local DB.
    //    The message is non-final (scheduled_send_at is in the future),
    //    so it shows a 🕐 icon, a Cancel link, and Datastar polling.
    await page.goto("./?view=persons__messages");
    await page.waitForLoadState("load");

    const messageRow = page.locator(`tr[data-body*="${timestamp}"]`);
    await expect(messageRow).toBeVisible({ timeout: to(10000) });

    // 7. SCHEDULED state: 🕐 icon is visible
    const statusCell = messageRow.locator("span.message-status");
    await expect(statusCell).toContainText("🕐", { timeout: to(5000) });

    // 8. Cancel link is present while the message has a future ScheduledSendAt
    const cancelLink = messageRow.locator("a", { hasText: /cancel/i });
    await expect(cancelLink).toHaveCount(1);

    // 9. Non-final deliveries get Datastar polling spans for live updates
    const pollingSpans = messageRow.locator("span.message-attribution");
    await expect(pollingSpans).toHaveCount(1, { timeout: to(5000) });

    // TODO: verify the SCHEDULED → DELIVERED transition once the
    // call_sms_info batch path (listRecentDeliveries) correctly updates
    // the local DB.  sms-scheduled-status.spec.ts covers scheduled → sent.
  });
});
