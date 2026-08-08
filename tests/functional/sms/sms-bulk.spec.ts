import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js"
import { login } from "../auth.js";

test.describe("SMS bulk send from families view", () => {
  // With per-scenario FrankenPHP instances, each scenario gets its own
  // PHP process and cookie jar — no session collisions.
  test.beforeEach(async ({ page }) => {
    await login(page);
  });
  // Both tests send SMS to the same Luther family and create notes on
  // Katharina von Bora's person page — serial avoids DB-level interference.
  test.describe.configure({ mode: "serial" });

  test("compose, preview, and send SMS to Luther family", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

    // 1. Navigate to the Luther family (familyid=3)
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill(`Hello %firstname% ${timestamp}`);

    // 4. Tick "Save as Note" and enter a timestamped subject
    const saveAsNote = smsForm.locator(".saveasnote");
    await saveAsNote.check();
    const noteSubject = smsForm.locator("input[name=note_subject]");
    await expect(noteSubject).toBeVisible();
    await noteSubject.fill(`SMS test note ${timestamp}`);

    // 5. Click "Message Preview" checkbox
    const previewCheckbox = smsForm.locator(".sms-preview-checkbox");
    await previewCheckbox.check();

    // Wait for preview panel to populate (debounced AJAX, ~2s)
    const previewPanel = smsForm.locator(".sms-preview-panel");
    await expect(previewPanel).toBeVisible();
    // Wait for preview entries to arrive (they each contain the person's name)
    await expect(async () => {
      const text = await previewPanel.innerText();
      expect(text).toContain("Martin");
      expect(text).toContain("Katharina");
      expect(text).toContain("Magdalena");
    }).toPass({ timeout: to(10000) });

    // 6. Verify the statusline shows segment count (no longer test mode)
    const statusLine = page.locator("#sms-statusline-bulk");
    await expect(statusLine).toContainText(/segment/);

    // 7. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 8. Verify send result appears — success message with 3 recipients
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message successfully sent to 3 recipients", { timeout: to(15000) });
    // 5 recipients skipped due to no mobile number
    await expect(results).toContainText("5 recipients were not sent");
    await expect(results).toContainText("Martin Luther");
    await expect(results).toContainText("Katharina von Bora");
    await expect(results).toContainText("Magdalena Luther");

    // 8b. Verify token expansion reached the provider: the last POST body
    //     contains the expanded first name, not the raw %firstname% token.
    //     (TokenExpandingSmsProvider makes one HTTP call per recipient, so
    //     we check the last one has been expanded.)
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-bulk", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toMatch(/Hello (Martin|Katharina|Magdalena)/);
    expect(captured.json.message).not.toContain("%firstname%");
    expect(captured.json.contacts).toHaveLength(1);
    expect(captured.json.countryCode).toBe(61);
    expect(captured.json.sender).toBe("Jethro");

    // 9. Visit Katharina von Bora's person page and verify the note was created
    await page.goto("./?view=persons&personid=5#notes");
    await page.waitForLoadState("load");

    // The #notes tab-pane should now be visible (active tab)
    const notesTab = page.locator("#notes.tab-pane");
    await expect(notesTab).toBeVisible();

    // Look for the timestamped note subject in the notes list
    const noteEntry = page.locator(`#notes .history-entry:has-text("${timestamp}")`);
    await expect(noteEntry).toBeVisible({ timeout: to(10000) });
    // Verify the token %firstname% was expanded in the note body.
    // With note_type=family, the note body uses the first recipient's
    // expansion — which is unpredictable, so check for the common prefix.
    await expect(noteEntry.locator('.content')).toContainText('Hello');

    // 10. Click the Messages tab and verify the sent SMS is recorded
    //     with expanded tokens
    const messagesTabLink = page.locator('a[href="#messages"][data-toggle="tab"]');
    await messagesTabLink.click();
    const messagesTab = page.locator("#messages.tab-pane");
    await expect(messagesTab).toBeVisible();
    // Multi-recipient SMS entries are hidden by default —
    // click the checkbox to reveal them before searching.
    await page.locator("#show-multi-sms").check();
    const messageEntry = messagesTab.locator(".history-entry.sms-multi", { hasText: timestamp });
    await expect(messageEntry).toBeVisible({ timeout: to(10000) });
    await expect(messageEntry).toContainText('Hello');
  });

  test("compose, preview, and send SMS with concat function to Luther family", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");

    // 1. Navigate to the Luther family (familyid=3)
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message with concat s-expression
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill(`%(concat "Greetings, " firstname " " lastname "!")% ${timestamp}`);

    // 4. Tick "Save as Note" and enter a timestamped subject
    const saveAsNote = smsForm.locator(".saveasnote");
    await saveAsNote.check();
    const noteSubject = smsForm.locator("input[name=note_subject]");
    await expect(noteSubject).toBeVisible();
    await noteSubject.fill(`SMS concat test note ${timestamp}`);

    // 5. Click "Message Preview" checkbox
    const previewCheckbox = smsForm.locator(".sms-preview-checkbox");
    await previewCheckbox.check();

    // Wait for preview panel to populate (debounced AJAX, ~2s)
    const previewPanel = smsForm.locator(".sms-preview-panel");
    await expect(previewPanel).toBeVisible();

    // 6. Wait for preview messages to appear with expanded concat output
    const previewMessages = previewPanel.locator(".sms-preview-msg");
    await expect(async () => {
      const msgs = await previewMessages.all();
      expect(msgs.length).toBeGreaterThanOrEqual(1);

      // Collect all message texts
      const texts: string[] = [];
      for (const m of msgs) {
        texts.push((await m.innerText()).trim());
      }

      // Verify specific expansions appear for known family members. Martin
      // and Katharina share a mobile: one message with both names combined.
      expect(texts).toContain(`Greetings, Martin & Katharina Luther & von Bora! ${timestamp}`);
      expect(texts).toContain(`Greetings, Magdalena Luther! ${timestamp}`);

      // Verify messages are NOT all identical (personalisation is working)
      const unique = [...new Set(texts)];
      expect(unique.length).toBeGreaterThan(1);
    }).toPass({ timeout: to(10000) });

    // 7. Verify the statusline shows segment count
    const statusLine = page.locator("#sms-statusline-bulk");
    await expect(statusLine).toContainText(/segment/);

    // 8. Click Send
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 9. Verify send result indicates success
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText(/successfully sent/, { timeout: to(15000) });
    await expect(results).toContainText("Martin Luther");
    await expect(results).toContainText("Katharina von Bora");
    await expect(results).toContainText("Magdalena Luther");

    // 9b. Verify concat expansion in the last POST body
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-bulk", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toMatch(/Greetings, (Martin|Katharina|Magdalena)/);
    expect(captured.json.message).not.toContain("%(concat");
    expect(captured.json.contacts).toHaveLength(1);
    expect(captured.json.countryCode).toBe(61);

    // 10. Visit Katharina von Bora's person page and verify note/message expansion
    await page.goto("./?view=persons&personid=5#notes");
    await page.waitForLoadState("load");

    const notesTab = page.locator("#notes.tab-pane");
    await expect(notesTab).toBeVisible();

    // Verify the note was created with expanded concat output
    const noteEntry = page.locator(`#notes .history-entry:has-text("${timestamp}")`);
    await expect(noteEntry).toBeVisible({ timeout: to(10000) });
    await expect(noteEntry.locator('.content')).toContainText('Greetings,');

    // 11. Click the Messages tab and verify expanded token in message history
    const messagesTabLink = page.locator('a[href="#messages"][data-toggle="tab"]');
    await messagesTabLink.click();
    const messagesTab = page.locator("#messages.tab-pane");
    await expect(messagesTab).toBeVisible();
    await page.locator("#show-multi-sms").check();
    const messageEntry = messagesTab.locator(".history-entry.sms-multi", { hasText: timestamp });
    await expect(messageEntry).toBeVisible({ timeout: to(10000) });
    await expect(messageEntry).toContainText('Greetings,');
  });

  test("send SMS from families page and verify in person messages tab", async ({ page, request }) => {
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
    const message = `Hello Luthers ${timestamp}`;

    // 1. Navigate to the Luther family (familyid=3)
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");

    // 2. Open the bulk-action chooser and select "Send SMS"
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    // The SMS form (#smshttp) should now be visible
    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // 3. Fill the message (no tokens — plain text with timestamp)
    const messageBox = smsForm.locator(".sms-message");
    await messageBox.fill(message);

    // 4. Click Send (no note, no preview)
    const sendButton = smsForm.locator(".bulk-sms-submit");
    await sendButton.click();

    // 5. Verify send result — 3 recipients (the Luther family adults with mobiles)
    const results = page.locator("#bulk-sms-results");
    await expect(results).toContainText("Message successfully sent to 3 recipients", { timeout: to(15000) });
    await expect(results).toContainText("Martin Luther");
    await expect(results).toContainText("Katharina von Bora");
    await expect(results).toContainText("Magdalena Luther");

    // 5b. Verify POST body has the plain message (no token expansion)
    const lastPost = await request.get(
      mockMeta("tests/functional/sms/sms-bulk", "lastPost")
    );
    const captured = await lastPost.json();
    expect(captured, "mock proxy did not capture an SMS POST").not.toBeNull();
    expect(captured.json.message).toBe(message);
    expect(captured.json.countryCode).toBe(61);
    // 6. Navigate to Katharina von Bora's person page and click Messages tab
    await page.goto("./?view=persons&personid=5#messages");
    await page.waitForLoadState("load");

    const messagesTab = page.locator("#messages.tab-pane");
    await expect(messagesTab).toBeVisible();
    await page.locator("#show-multi-sms").check();
    const messageEntry = messagesTab.locator(".history-entry.sms-multi", { hasText: timestamp });
    await expect(messageEntry).toBeVisible({ timeout: to(10000) });
    await expect(messageEntry).toContainText(message);
  });
});

test.describe("SMS bulk composer - Datastar live reactivity", () => {
  // Compose-only (no send), so these don't touch the DB and run independently.
  // They exercise the server-driven (HATEOAS/Datastar) statusline + preview
  // that replaced the old client-side segment/cost maths.
  test.beforeEach(async ({ page }) => {
    await login(page);
    await page.goto("./?view=families&familyid=3");
    await page.waitForLoadState("load");
    await page.locator("#bulk-action-chooser").selectOption("smshttp");
    await expect(page.locator("#smshttp")).toBeVisible();
  });

  test("instant char count is client-side; segment/cost statusline is server-rendered", async ({ page }) => {
    const form = page.locator("#smshttp");
    // Capture the debounced server round-trip so we can assert it fired.
    const statuslinePost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").fill("Hi %firstname%");

    // Instant: the live char count is a pure client-side Datastar signal
    // ($message.length) reflecting the raw template length immediately.
    await expect(form.locator(".sms-charcount-instant")).toHaveText("14 chars");

    // Server-authoritative: segment count, recipient count and cost are
    // computed server-side and morphed into #sms-statusline-bulk via SSE.
    await statuslinePost;
    const statusLine = page.locator("#sms-statusline-bulk");
    // 3 adults, 2 phones (Martin and Katharina share one) → 2 messages.
    await expect(statusLine).toContainText("2 recipients");
    await expect(statusLine).toContainText("1 segment");
    await expect(statusLine).toContainText("$");
  });

  test("changing sms_type recomputes the recipient set server-side", async ({ page }) => {
    const form = page.locator("#smshttp");
    await form.locator(".sms-message").fill("Hi %firstname%");
    // "the selected persons" -> the 3 Luther family adults with mobiles,
    // Magdalena among them.
    await form.locator(".sms-preview-checkbox").check();
    const previewPanel = form.locator(".sms-preview-panel");
    await expect(previewPanel).toContainText("Magdalena Luther", { timeout: to(10000) });

    // "the adults in the selected persons' families" (Martin and Katharina)
    // re-posts via data-on:change and the server returns a different set.
    await page.locator("#sms_type_family").check();
    await expect(previewPanel).not.toContainText("Magdalena Luther");
    await expect(previewPanel).toContainText("Katharina von Bora");
  });

  test("editing a per-recipient message highlights the row and re-renders server-side", async ({ page }) => {
    const form = page.locator("#smshttp");
    await form.locator(".sms-message").fill("Hi %firstname%");
    await form.locator(".sms-preview-checkbox").check();

    // Preview panel populates with per-recipient expansions (server-rendered).
    const firstMsg = form.locator(".sms-preview-msg").first();
    await expect(firstMsg).toContainText("Hi ", { timeout: to(10000) });

    // Pencil -> the edit <textarea> for that row appears (data-show toggled by
    // the $editingRow signal set on data-on:click).
    await form.locator(".sms-preview-edit-toggle").first().click();
    const editBox = form.locator(".sms-preview-edit").first();
    await expect(editBox).toBeVisible();

    // Type a custom override and blur -> data-on:blur re-posts; the server
    // re-renders the panel with the override applied and the row highlighted.
    const override = "Custom override for this one recipient";
    await editBox.fill(override);
    await form.locator(".sms-message").click(); // blur

    await expect(firstMsg).toContainText(override);
    await expect(firstMsg).toHaveCSS("background-color", "rgb(255, 243, 205)"); // #fff3cd
  });

  test("typing in the compose box never falsely marks preview rows as edited", async ({ page }) => {
    const form = page.locator("#smshttp");

    // Fill the message first (reliable), then open the preview.
    const fillPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").fill("Hi %firstname%");
    await fillPost;
    await form.locator(".sms-preview-checkbox").check();

    // The preview shows the per-recipient expansions...
    const firstMsg = form.locator(".sms-preview-msg").first();
    await expect(firstMsg).toContainText("Hi ", { timeout: to(10000) });

    // Now, while the preview is open, append one character. Regression:
    // the pre-filled override <textarea> in the preview panel must not
    // auto-submit its expansion on the compose-box re-post (which the
    // server would mistake for a manual edit, highlighting the row).
    const appendPost = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
    );
    await form.locator(".sms-message").press("x");
    await appendPost;
    const highlighted = await form.locator(".sms-preview-msg").evaluateAll((els) =>
      els.filter((e) => getComputedStyle(e).backgroundColor === "rgb(255, 243, 205)").length,
    );
    expect(highlighted).toBe(0);
  });
});
