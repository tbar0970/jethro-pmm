import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";
import type { Page } from "@playwright/test";

/**
 * Balance close to zero: 2 credits, two recipients. A 1-segment message needs
 * exactly the balance and may be sent; a 2-segment one needs 4 and is blocked,
 * although SMS_OVERDRAFT_ALLOWED is on: Cellcast offers no overdraft.
 * See docs/docs/developer/reference/sms-balance.md.
 */

/** Open the Williamson (familyid=14, two mobiles) bulk SMS form, type $message and wait for the statusline's verdict. */
async function compose(page: Page, message: string) {
  await page.goto("./?view=families&familyid=14");
  await page.waitForLoadState("load");
  const chooser = page.locator("#bulk-action-chooser");
  await expect(chooser).toBeVisible();
  await chooser.selectOption("smshttp");
  const form = page.locator("#smshttp");
  await expect(form).toBeVisible();
  const statuslinePost = page.waitForResponse(
    (r) => r.url().includes("call=sms_statusline") && r.request().method() === "POST",
  );
  await form.locator(".sms-message").fill(message);
  await statuslinePost;
  return { form, statusline: page.locator("#sms-statusline-bulk") };
}

test.describe("SMS compose with a balance close to zero", () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test("a send needing exactly the balance may go ahead", async ({ page }) => {
    const { form, statusline } = await compose(page, "Exactly enough credit");
    // The cost line ("1 segment → 2 recipients = $…" or "… chars remaining in this segment").
    await expect(statusline).toContainText(/segment/, { timeout: to(5000) });
    await expect(statusline).not.toContainText("Insufficient balance");
    await expect(form.locator(".bulk-sms-submit")).toBeEnabled();
  });

  test("a send needing more than the balance is blocked", async ({ page }) => {
    // 200 GSM characters: 2 segments (153 each once concatenated) per recipient.
    const { form, statusline } = await compose(page, "Two segments. ".repeat(14) + "x".repeat(4));
    await expect(statusline).toContainText("Insufficient balance: 4 segments needed, 2 available", { timeout: to(5000) });
    await expect(form.locator(".bulk-sms-submit")).toBeDisabled();
  });
});
