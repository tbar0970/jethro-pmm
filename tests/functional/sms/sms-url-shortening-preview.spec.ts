import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";

test.describe("SMS URL shortening preview", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("Preview shows shortened URL, not original URL", async ({ page }) => {
    const testURL =
      "https://example.com/very-long-path/to/some/event?utm_source=church&utm_medium=sms";
    // previewShorten: substr(sha256(url + '::' + campaign), 0, 6)
    // where campaign = substr(URLSHORTENER_API_KEY, 0, 8) = 'test-moc'
    // php -r "echo substr(hash('sha256', '${testURL}::test-moc'), 0, 6);"
    const shortenedURLPrefix = "https://jethro.au/s/e733b0";

    // Use Calvin family (familyid=2) bulk form — it has a preview panel,
    // unlike the single-person modal.
    await page.goto("./?view=families&familyid=2");
    await page.waitForLoadState("load");

    // The bulk SMS form is hidden by default — activate via the bulk-action chooser.
    const chooser = page.locator("#bulk-action-chooser");
    await expect(chooser).toBeVisible();
    await chooser.selectOption("smshttp");

    const smsForm = page.locator("#smshttp");
    await expect(smsForm).toBeVisible();

    // Fill the message with a long URL — SMS_SHORTEN_URLS=true ticks the "Shorten URLs" checkbox by default, so it is auto-wrapped.
    const messageBox = smsForm.locator(".sms-message");
    // Enable preview BEFORE filling so the statusline fires with preview=true.
    const previewCheckbox = smsForm.locator(".sms-preview-checkbox");
    await expect(previewCheckbox).toBeVisible();
    await previewCheckbox.check();

    const statuslineResponse = page.waitForResponse(
      (r) => r.url().includes("call=sms_statusline") && r.status() === 200
    );
    await messageBox.fill(`See details at ${testURL}`);
    await statuslineResponse;

    // The preview panel (class selector, scoped to the form) becomes visible
    // once the Datastar SSE patches it with preview rows.
    const previewPanel = smsForm.locator(".sms-preview-panel");
    await expect(previewPanel).toBeVisible({ timeout: to(10000) });

    // Retry until preview rows are rendered and the shortened URL appears.
    // Preview uses previewShorten() — deterministic local hash, no real API call.
    await expect(async () => {
      const text = await previewPanel.innerText();
      expect(text).toContain(shortenedURLPrefix);
      expect(text).not.toContain("very-long-path");
    }).toPass({ timeout: to(10000) });

    // Do NOT click Send — previewShorten is safe but the real send would call
    // jethro.au/api/shorten which is out of scope for this test.
  });
});
