import { test as base, expect, type Response } from "@playwright/test";

/**
 * Stack-trace guard for Jethro functional tests.
 *
 * Jethro renders PHP/system errors inline — either in the initial page HTML or
 * in a Datastar/AJAX fragment — as an <div class="alert alert-error"> carrying
 * an <h4> title and, when SHOW_ERROR_DETAILS is on (the DEV default), a
 * <pre> block with the backtrace (see System_Controller::_reportError).
 *
 * A passing functional test must never silently render one of these.  This
 * fixture scans every text/html response the test receives (full page loads
 * *and* fragments) plus the final page content, and fails the test when it
 * spots a stack trace or error banner.
 */

interface ErrorPattern {
  label: string;
  pattern: RegExp;
}

const JETHRO_ERROR_PATTERNS: ErrorPattern[] = [
  {
    // _handleException() → 'Fatal Error (Exception)'
    label: "fatal exception banner",
    pattern: /<h4>\s*Fatal Error \(Exception\)\s*<\/h4>/i,
  },
  {
    // handleError() → 'SYSTEM ERROR (ERROR|WARNING|NOTICE)' etc.
    label: "system error banner",
    pattern: /<h4>\s*SYSTEM ERROR(?: \([A-Z]+\))?\s*<\/h4>/i,
  },
  {
    // The <pre> backtrace header: "<b>Line 176 of File /…/view_….php</b>"
    label: "stack trace header",
    pattern: /<b>\s*Line \d+ of File [^<]*\.(?:php|inc)\s*<\/b>/i,
  },
  {
    // print_r(debug_backtrace()) frames: "[file] => /…/system_controller.class.php"
    label: "stack trace frame",
    pattern: /\[file\]\s*=>\s*[^<\n]*\.(?:php|inc)/i,
  },
];

function findJethroError(html: string): { label: string; match: string; index: number } | null {
  for (const { label, pattern } of JETHRO_ERROR_PATTERNS) {
    const match = pattern.exec(html);
    if (match) {
      return { label, match: match[0], index: match.index };
    }
  }
  return null;
}

export const test = base.extend({
  page: async ({ page }, use) => {
    let detectedError: string | null = null;

    const scan = (html: string): void => {
      if (detectedError) return;
      const hit = findJethroError(html);
      if (!hit) return;

      const start = Math.max(0, hit.index - 500);
      detectedError = [
        `Jethro error/stack trace detected in an HTML response (${hit.label}).`,
        "",
        `Matched: ${hit.match}`,
        "",
        "HTML excerpt:",
        html.slice(start, hit.index + 1000),
      ].join("\n");
    };

    const onResponse = async (response: Response): Promise<void> => {
      const contentType = response.headers()["content-type"] ?? "";
      if (!contentType.includes("html")) return;
      try {
        scan(await response.text());
      } catch {
        // Body already consumed, or the page navigated away mid-download.
      }
    };
    page.on("response", onResponse);

    await use(page);

    // Belt-and-braces: the final DOM may hold an error that was not served as
    // a distinct response (e.g. one painted before this listener attached).
    try {
      scan(await page.content());
    } catch {
      // Page may already be tearing down.
    }

    if (detectedError) {
      throw new Error(detectedError);
    }
  },
});

export { expect };
