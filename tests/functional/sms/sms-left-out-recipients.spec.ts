import { to } from "../timeouts.js";
import { type Page } from "@playwright/test";
import { test, expect } from "../fixtures.js";
import { mockMeta } from "./smsmock-url.js";
import { mockProfile } from "./scenario.js";
import { login } from "../auth.js";

/**
 * Recipients without a mobile number can't be sent an SMS. The statusline
 * (Call_SMS_Statusline, Jethro\Sms\leftOutNotice()) says so while the message
 * is being composed: it blocks Send when nobody is left to send to, and
 * otherwise says how many will be left out.
 */
test.describe("SMS statusline names recipients the send would leave out", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(mockProfile(test.info()), "lastPost"));
    await login(page);
  });

  /** Open the bulk SMS form, type a message and wait for the server's statusline. */
  async function composeBulkSms(page: Page, message: string) {
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
    return form;
  }

  test("nobody has a mobile number: says so and blocks Send", async ({ page, request }) => {
    // Group 72 (Kids Club): several members, none with a mobile number. The
    // count isn't fixed, so members can be added to the demo data.
    await page.goto("./?view=groups&groupid=72");
    await page.waitForLoadState("load");
    const boxes = page.locator("input[name='personid[]']");
    await expect(boxes.first()).toBeVisible();
    const members = await boxes.count();
    expect(members, "the plural wording needs several members").toBeGreaterThan(1);
    for (const box of await boxes.all()) await box.check();

    const form = await composeBulkSms(page, "Hello");

    const reason = `No SMS can be sent: none of the ${members} recipients has a mobile number.`;
    await expect(page.locator("#sms-statusline-bulk")).toContainText(reason, { timeout: to(5000) });
    await expect(form.locator(".bulk-sms-submit")).toBeDisabled();
    await expect(form.locator(".sms-disabled-reason")).toHaveText(reason);

    const lastPost = await request.get(mockMeta(mockProfile(test.info()), "lastPost"));
    expect(await lastPost.json(), "nothing may be sent").toBeNull();
  });

  test("some have no mobile number: says how many, Send stays enabled", async ({ page }) => {
    // Marvin family: Roderick has a mobile number, Irwin has none.
    await page.goto("./?view=families&familyid=12");
    await page.waitForLoadState("load");

    const form = await composeBulkSms(page, "Hello");

    const statusline = page.locator("#sms-statusline-bulk");
    await expect(statusline).toContainText("1 of the 2 recipients will not be sent this: 1 has no mobile number.", { timeout: to(5000) });
    await expect(statusline).toContainText("→ 1 recipients"); // the cost line counts only the one who will be sent it
    await expect(form.locator(".bulk-sms-submit")).toBeEnabled();
  });
});
