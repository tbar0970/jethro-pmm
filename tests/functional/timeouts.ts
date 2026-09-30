/**
 * Debug-aware assertion timeout.
 *
 * Returns 0 (= wait forever) when running under the Playwright Inspector
 * (--debug / PWDEBUG=1), so that a PHP request paused at an Xdebug breakpoint
 * can't expire an in-flight assertion and tear the test down mid-session.
 * Normal runs get the given timeout unchanged.
 *
 * Usage: await expect(locator).toBeVisible({ timeout: to(5000) });
 */
export const to = (ms: number): number => (process.env.PWDEBUG ? 0 : ms);
