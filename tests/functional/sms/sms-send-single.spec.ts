import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { login } from "../auth.js";

test.describe("SMS send single-person via modal", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta("tests/functional/sms/sms-send-single", "lastPost"));
    await login(page);
  });

  test("send SMS with Create Note and verify message and associated note", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
    const message = `Hello John ${timestamp}`;

    // 1. Visit John Calvin's person page (personid=2)
    await page.goto("./?view=persons&personid=2");
    await page.waitForLoadState("load");

    // 2. Hover and click the mobile number link to open the dropdown
    const mobileLink = page.locator("#mobile-2");

    await expect(mobileLink).toBeVisible();
    await mobileLink.hover();
    await page.waitForTimeout(50);
    await mobileLink.click();

    // 3. Click "SMS via Jethro" to open the modal
    const smsLink = page.locator('.dropdown-menu a[href="#send-sms-modal"]');
    await expect(smsLink).toBeVisible({ timeout: to(5000) });
    await smsLink.click();

    // 4. Wait for the SMS modal to become visible
    const modal = page.locator("#send-sms-modal");
    await expect(modal).toBeVisible({ timeout: to(5000) });

    // 5. Fill the message
    const messageTextarea = page.locator("#send-sms-modal .sms-message");
    await messageTextarea.fill(message);

    // 6. Tick "Create Note" — the note subject and date fields appear
    const saveAsNote = page.locator("#send-sms-modal .saveasnote");
    await saveAsNote.check();
    const noteSubject = page.locator('#send-sms-modal input[name="note_subject"]');
    await expect(noteSubject).toBeVisible();
    await noteSubject.fill(`SMS note ${timestamp}`);

    // 7. Click Send (AJAX modal send). On a successful send from a person page
    //    the app sets location.hash = #message_<id> and reloads the page (see
    //    finishSend in resources/js/jethro-sms.js), re-rendering the Messages
    //    history with the just-sent entry and activating the Messages tab.
    const sendButton = page.locator("#send-sms-modal .sms-submit");
    await sendButton.click();

    // 8. Assert the sent message appears. Asserting on the entry directly lets
    //    expect() auto-retry across the app's post-send reload, so there is no
    //    explicit page.goto to race it ("interrupted by another navigation").
    //    Scope to THIS run's unique timestamped message rather than .first() —
    //    the shared demo DB also holds multi-recipient (sms-multi) entries from
    //    the bulk-send specs, hidden by default, which would otherwise match
    //    first.
    const messagesTab = page.locator("#messages.tab-pane");
    const messageEntry = messagesTab
      .locator(".history-entry")
      .filter({ hasText: message });
    await expect(messageEntry).toBeVisible({ timeout: to(15000) });
    await expect(messagesTab).toBeVisible();
    await expect(messageEntry).toContainText(message);

    // 9. Verify the POST body via /meta/lastPost
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-send-single", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toBe(message);
    expect(captured.json.contacts).toEqual(["0491570159"]); // John Calvin's fixed test-data mobile (sent as entered; Cellcast normalises)
    expect(captured.json.countryCode).toBe(61);
    expect(captured.json.sender).toBeDefined();

    // 10. Verify the "Associated note" link exists and click it
    const noteLink = messageEntry.locator('a[href^="#note_"]');
    await expect(noteLink).toContainText("Associated note");
    const noteHref = await noteLink.getAttribute("href");
    const noteId = noteHref?.replace("#note_", "") ?? "";

    // Click the note tab link to activate the Notes pane,
    // then look for our note by its ID.
    const notesTabLink = page.locator('a[href="#notes"][data-toggle="tab"]');
    await notesTabLink.click();
    const notesTab = page.locator("#notes.tab-pane");
    await expect(notesTab).toBeVisible();

    const noteElement = notesTab.locator(`#note_${noteId}`);
    await expect(noteElement).toBeVisible();
    await expect(noteElement.locator(".content")).toContainText(message);

    // 11. Verify the note status is 'pending' (SMS follow-up notes
    //     should default to pending so they appear in action lists).
    await expect(noteElement.locator('.status')).toHaveAttribute('data-note-status', 'pending');
  });
});
