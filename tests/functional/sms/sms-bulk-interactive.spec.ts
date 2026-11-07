import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { login } from "../auth.js";

// This lives in its own scenario rather than as a third describe in
// sms-bulk.spec.ts because the mock server keeps only the most recent send per
// profile — one lastPost.json per provider+profile scope, see
// smsmockserver/src/Meta.php::recordPost().  This test sends and then asserts
// on that slot, and so do three tests in sms-bulk.spec.ts; those three are
// serial with respect to each other, but with fullyParallel this one ran
// alongside them against the shared sms-bulk profile and read whichever send
// landed last.  It failed with "Greetings, Magdalena Luther!" — the message
// from sms-bulk.spec.ts's %(concat ...)% test — in place of its own
// "Hello <timestamp>".  A separate profile gives this send a slot of its own,
// so the collision is structurally impossible and the suite keeps running
// 8-wide.
const PROFILE = "tests/functional/sms/sms-bulk-interactive";

test.describe("SMS bulk — in-page Datastar interactivity then send", () => {
  test.beforeEach(async ({ page, request }) => {
    // Drop any slot left by an earlier run, so a stale recording can never
    // stand in for this run's send.
    await request.delete(mockMeta(PROFILE, "lastPost"));
    await login(page);
  });

  test("live char count, statusline, preview panel, sms_type switch, then send", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

    // 1. Navigate to the Luther family (familyid=3) and open the bulk SMS form
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    const form = page.locator("#smshttp");
    await expect(form).toBeVisible();

    // 2. Fill in a short message and wait for the debounced SSE round-trip
    const statuslinePost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").fill("Hello");
    await statuslinePost;

    // 3. Verify the live client-side char count
    await expect(form.locator(".sms-charcount-instant")).toHaveText("5 chars");

    // 4. Verify the server-rendered statusline shows segment/cost: 3 family
    //    adults on 2 phones (Martin and Katharina share one) → 2 messages.
    //    Segment cost comes from the provider.
    const statusLine = form.locator("#sms-statusline-bulk");
    await expect(statusLine).toContainText("1 segment");
    await expect(statusLine).toContainText("2 recipients");
    await expect(statusLine).toContainText("$0.09");

    // 5. Preview panel wrapper is initially hidden
    const previewWrap = form.locator(".sms-preview-wrap");
    await expect(previewWrap).toBeHidden();

    // 6. Tick the preview toggle checkbox
    await form.locator(".sms-preview-checkbox").check();
    await expect(previewWrap).toBeVisible();

    // 7. Verify the preview panel shows each adult's name and the message
    const previewPanel = form.locator("#sms-preview-panel-bulk");
    await expect(previewPanel).toContainText("Martin Luther");
    await expect(previewPanel).toContainText("Katharina von Bora");
    await expect(previewPanel).toContainText("Magdalena Luther");
    // 2 rows (one per phone), each with "Hello" as the message text
    const previewMessages = previewPanel.locator(".sms-preview-msg");
    await expect(previewMessages).toHaveCount(2);
    for (const msg of await previewMessages.all()) {
      await expect(msg).toHaveText("Hello");
    }

    // 8. Append a token to the compose box and wait for the re-render
    const statuslinePost2 = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").fill("Hello" + " %firstname%");
    await statuslinePost2;

    // 9. Preview rows now show expanded names, not the raw token; the
    //    shared phone gets both names in one message
    await expect(previewPanel).toContainText("Hello Martin & Katharina");
    await expect(previewPanel).toContainText("Hello Magdalena");

    // 10. Switch sms_type to "families" — the family adults are Martin and
    //     Katharina, who share a phone: 1 message
    await page.locator("#sms_type_family").check();
    await expect(statusLine).toContainText("1 recipients");
    await expect(statusLine).toContainText("$0.04");

    // 11. Uncheck all person checkboxes — no recipients selected.
    //     The statusline should show no segment/cost line when no one
    //     is selected (previously showed a misleading 1-recipient fallback).
    const personCheckboxes = page.locator('input[name="personid[]"]:checked');
    const checkedCount = await personCheckboxes.count();
    for (let i = 0; i < checkedCount; i++) {
      await personCheckboxes.nth(0).uncheck();
    }
    // Trigger a re-post by appending a space to the message
    const zeroRecipPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").press("Space");
    await zeroRecipPost;
    // Statusline should NOT contain a recipient count or cost
    await expect(statusLine).not.toContainText("recipients");
    await expect(statusLine).not.toContainText("$");


    // 12. Select only person 186 (Martin Luther, primary school — a child).
    //     sms_type is still "family" (adults in selected persons' families).
    //     Even though the only selected person is a child, family mode
    //     correctly finds the adults in his family: Martin and Katharina,
    //     on one shared phone.
    await page.locator('input[name="personid[]"][value="186"]').check();
    const childPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").press("Space");
    await childPost;
    await expect(statusLine).toContainText("1 recipients");


    // 13. Switch to person mode. Only Martin (child, no mobile) is selected
    //     → 0 recipients.
    const personPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await page.locator("#sms_type_person").check();
    await personPost;
    await expect(statusLine).not.toContainText("recipients");
    await expect(statusLine).not.toContainText("$");

    // 14. Also select Katharina von Bora (personid=5, has a mobile)
    //     → 1 recipient.
    await page.locator('input[name="personid[]"][value="5"]').check();
    const kathPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").press("Space");
    await kathPost;
    await expect(statusLine).toContainText("1 recipients");

    // 15. Tick "Create Note", fill subject, and Send.
    const noteSubject = `SMS follow-up ${timestamp}`;
    await form.locator(".saveasnote").check();
    await form.locator('input[name="note_subject"]').fill(noteSubject);
    const uniqueMsg = `Hello ${timestamp}`;
    await form.locator(".sms-message").fill(uniqueMsg);
    await form.locator(".bulk-sms-submit").click();

    // 16. Verify send result
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message successfully sent", { timeout: to(15000) });

    // 16b. Verify the POST body contains the final sent message
    const lastPost = await request.get(mockMeta(PROFILE, "lastPost"));
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toBe(uniqueMsg);
    expect(captured.json.contacts).toHaveLength(1);
    expect(captured.json.countryCode).toBe(61);
    // 17. Navigate to the Luther family page and verify a Family Note was created
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");
    await expect(page.locator("body")).toContainText(noteSubject);
  });
});
