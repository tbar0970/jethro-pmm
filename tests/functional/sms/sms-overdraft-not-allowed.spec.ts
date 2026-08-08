import { to } from "../timeouts.js";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { login } from "../auth.js";
import type { Page } from "@playwright/test";

/**
 * The provider offers an overdraft (SmsCapability::OVERDRAFT) but this tenant
 * is not afforded it (SMS_OVERDRAFT_ALLOWED off, e.g. on free credits): with no
 * credit Send is blocked, and a send posted directly is refused.
 * See docs/docs/developer/reference/sms-balance.md.
 */
const PROFILE = "tests/functional/sms/sms-overdraft-not-allowed";

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

test.describe("SMS overdraft not afforded", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(PROFILE, "lastPost"));
    await login(page);
  });

  test("blocks Send", async ({ page, request }) => {
    const { form, statusline } = await compose(page, "No overdraft for you");
    await expect(statusline).toContainText("Insufficient balance: 2 segments needed, 0 available", { timeout: to(5000) });
    await expect(form.locator(".bulk-sms-submit")).toBeDisabled();
    const lastPost = await request.get(mockMeta(PROFILE, "lastPost"));
    expect(await lastPost.json(), "no send may be attempted").toBeNull();
  });

  test("refuses a send posted past the disabled Send button", async ({ context, request }) => {
    // Katharina von Bora (personid 5) has a mobile: one segment, no credit.
    const res = await context.request.post("./?call=sms", {
      form: { "personid[]": "5", message: `No overdraft ${Date.now()}` },
    });
    expect(await res.json()).toMatchObject({ error: "Insufficient balance: 1 segment needed, 0 available." });
    const lastPost = await request.get(mockMeta(PROFILE, "lastPost"));
    expect(await lastPost.json(), "no send may be attempted").toBeNull();
  });
});
