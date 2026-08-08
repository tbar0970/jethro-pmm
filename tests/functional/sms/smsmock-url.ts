/**
 * Shared URL helpers for the mock SMS server.
 *
 * The mock server is served at /smsmockserver/ through the functest nginx.
 * PORT matches the HOST constant in playwright.config.ts — read from
 * FUNCTEST_WEB_PORT with a default of 8089.
 */

const PORT = process.env.FUNCTEST_WEB_PORT || "8089";
const HOST = process.env.FUNCTEST_WEB_HOST || "127.0.0.1";

/** Base URL of the mock SMS server. */
export const SMS_MOCK_BASE = `http://${HOST}:${PORT}/smsmockserver`;

/**
 * Build a mock /meta endpoint URL.
 *
 * Example:
 *   mockMeta("tests/functional/sms/sms-bulk", "lastPost")
 *   → http://127.0.0.1:8089/smsmockserver/tests/functional/sms/sms-bulk/meta/lastPost
 *
 * @param profile  e.g. "tests/functional/sms/sms-bulk"
 * @param endpoint e.g. "lastPost", "lastRequest", "registrations"
 */
export function mockMeta(profile: string, endpoint: string): string {
    return `${SMS_MOCK_BASE}/${profile}/meta/${endpoint}`;
}
