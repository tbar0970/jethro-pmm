import { test, expect } from "../fixtures.js";
import { login } from "../auth.js";
import { mockMeta } from "./smsmock-url.js";

const PROFILE = "tests/functional/sms/sms-permissions";

/**
 * kidscare holds no SMS permission and is not a SysAdmin (see
 * docs/docs/developer/reference/functest-personas.mdx). Each SMS endpoint must
 * refuse the request a hostile client would send, and nothing may reach the
 * gateway.
 */
test.describe("SMS endpoints refuse a persona without SMS permissions", () => {
  test.beforeEach(async ({ page, request }) => {
    await request.delete(mockMeta(PROFILE, "lastPost"));
    await login(page, "kidscare");
  });

  test.afterEach(async ({ request }) => {
    const lastPost = await request.get(mockMeta(PROFILE, "lastPost"));
    expect(await lastPost.json(), "a refused request reached the SMS gateway").toBeNull();
  });

  test("?call=sms", async ({ context }) => {
    const res = await context.request.post("./?call=sms", {
      form: { "personid[]": "5", message: `Refused send ${Date.now()}` },
    });
    expect(await res.json()).toEqual({ error: "You do not have permission to send SMS messages." });
  });

  test("?call=sms_sendernum", async ({ context }) => {
    const res = await context.request.post("./?call=sms_sendernum", {
      form: { action: "register", number: "0491570110", label: "Mine" },
    });
    expect(await res.text()).toContain("Permission denied");
  });

  for (const operation of ["synchronizeHistory", "registerSenderId", "deleteSenderNumber"]) {
    test(`?call=admin_statuspanel_operation_sms ${operation}`, async ({ context }) => {
      const res = await context.request.post("./?call=admin_statuspanel_operation_sms", {
        form: { operation },
      });
      expect(await res.text()).toContain("Permission denied");
    });
  }
});
