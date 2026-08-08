import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { login } from "../auth.js";
import type { Page } from "@playwright/test";

/**
 * The provider offers an overdraft (SmsCapability::OVERDRAFT) and this tenant
 * is afforded it (SMS_OVERDRAFT_ALLOWED): with no credit the statusline only
 * warns, and the send reaches the provider.
 * See docs/docs/developer/reference/sms-balance.md.
 */
const PROFILE = "tests/functional/sms/sms-overdraft";

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

test.describe("SMS send on an afforded overdraft", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(PROFILE, "lastPost"));
    await login(page);
  });

  test("warns but keeps Send enabled, and sends", async ({ page, request }) => {
    const message = `Overdrawn ${Date.now()}`;
    const { form, statusline } = await compose(page, message);
    await expect(statusline).toContainText("Not enough SMS credit: 2 segments needed, 0 remaining", { timeout: to(5000) });
    await expect(statusline).not.toContainText("Insufficient balance");
    const send = form.locator(".bulk-sms-submit");
    await expect(send).toBeEnabled();

    await send.click();
    await expect(page.locator("#bulk-sms-results")).toContainText("successfully sent to 2 recipients", { timeout: to(15000) });
    const lastPost = await request.get(mockMeta(PROFILE, "lastPost"));
    expect(JSON.stringify(await lastPost.json()), "the send must reach the provider").toContain(message);
  });
});
