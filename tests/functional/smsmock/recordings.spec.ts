import { test, expect } from "@playwright/test";
import { spawnSync } from "node:child_process";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { SMS_MOCK_BASE } from "../sms/smsmock-url.js";

/**
 * The mock must answer as the live APIs did.
 *
 * smsmockserver/bin/check records each provider's cases against the mock
 * (profiles shipped in smsmockserver/profiles/) into
 * recordings/<provider>/mockresults/ and diffs them with the committed live
 * recording in recordings/<provider>/actual/ (normalised files only).
 *
 * After a provider changes its API: re-record actual/ against the live API
 * (see smsmockserver/README.md), review the diff, then fix the mock.
 */

// <root>/tests/functional/smsmock/recordings.spec.ts -> <root>
const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../..");

test("the mock answers as the live APIs did", () => {
  const result = spawnSync("php", ["smsmockserver/bin/check"], {
    cwd: PROJECT_ROOT,
    encoding: "utf8",
    env: { ...process.env, SMSMOCK_URL: SMS_MOCK_BASE },
  });
  expect(result.status, `${result.stdout}${result.stderr}`).toBe(0);
});
