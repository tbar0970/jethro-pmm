/**
 * Query helpers for the MailPit email-capture service.
 *
 * MailPit (process-compose service `mailpit`) is the functest email sink: Jethro's
 * SENDMAIL_COMMAND conf define pipes mail into its SMTP unix socket,
 * so every email Jethro sends during a suite lands there. This module is the
 * "was an email sent?" wrapper — the email analogue of mockMeta() for SMS.
 *
 * Both MailPit listeners are UNIX DOMAIN SOCKETS in a short per-checkout
 * directory under the user's runtime dir (MAILPIT_SOCKET_DIR in devbox.json:
 * socket paths are limited to ~107 characters, so they cannot live under the
 * checkout; Devbox namespaces the runtime directory by project, and there are
 * no TCP ports, so concurrent checkouts never clash), and all 8 Playwright
 * workers plus every suite run share ONE MailPit instance, whose temporary
 * SQLite store also lives under that directory —
 * always scope queries by recipient and subject and pass a notBefore
 * timestamp; summary lists come back newest-first. The API side uses node's
 * http.request({socketPath}) because fetch() cannot address a unix socket.
 */

import { execFileSync } from "node:child_process";
import * as nodeHttp from "node:http";
import * as nodePath from "node:path";

/**
 * MailPit's socket directory (process-compose service `mailpit`).
 *
 * Short and per-checkout: $DEVBOX_RUNTIME_DIR/mailpit, configured in devbox.json.
 * Outside an activated shell, ask the checkout's Devbox launcher for the same
 * configured environment (as devbox.d/*_jethro_server/conf.php does).
 * See docs/docs/developer/reference/JETHRO_UTILS.md.
 */
function mailpitSocketDir(): string {
    const fromEnv = process.env.MAILPIT_SOCKET_DIR;
    if (fromEnv) return fromEnv;
    const root = nodePath.resolve(__dirname, "..", "..", "..");
    return execFileSync(nodePath.join(root, "devbox"), ["run", "--pure", "--", "printenv", "MAILPIT_SOCKET_DIR"], {
        cwd: root,
        encoding: "utf8",
    }).trimEnd();
}

const HTTP_SOCKET = nodePath.join(mailpitSocketDir(), "http.sock");

/** MailPit's --webroot (process-compose service `mailpit`); every API path lives under it. */
const WEBROOT = "/emailmockserver";

/** One address as MailPit serialises it (Name is "" when the header had none). */
export interface MailpitAddress {
    Name: string;
    Address: string;
}

/** Summary shape from /api/v1/messages (subset). MailPit marshals an absent
 * recipient header as null — a message with only a Bcc header comes back with
 * "To": null and "Cc": null — so both lists are nullable; the per-message
 * payload instead sends [] (see MailpitMessage). */
export interface MailpitSummary {
    ID: string;
    To: MailpitAddress[] | null;
    Cc: MailpitAddress[] | null;
    Subject: string;
    Created: string;
}

/** Full message from /api/v1/message/{ID} (subset). NOTE: unlike the summary
 * payload, the detail payload has no Created field, and it sends [] (never
 * null) for an absent recipient header. */
export interface MailpitMessage extends Omit<MailpitSummary, "Created" | "To" | "Cc"> {
    From: MailpitAddress;
    To: MailpitAddress[];
    Cc: MailpitAddress[];
    Text: string;
    HTML: string;
}

export interface MessageMatch {
    /** Exact recipient address, case-insensitive. */
    to?: string;
    /** Substring of the subject. */
    subject?: string;
}

/** One HTTP round trip to MailPit must finish within this; the local socket answers in ms. */
const REQUEST_TIMEOUT_MS = 10000;

/**
 * Raw request over MailPit's HTTP unix socket (fetch() can't do unix), returning
 * the response body as text. Non-2xx, a hung socket and a connection error all
 * reject with the API path in the message; api() additionally rejects a
 * non-JSON body.
 */
function request(apiPath: string, method = "GET"): Promise<string> {
    const { promise, resolve, reject } = Promise.withResolvers<string>();
    // Every failure names the call: a bare socket error ("connect ENOENT") says
    // nothing about which MailPit request broke.
    const fail = (err: Error) => reject(new Error(`MailPit ${method} ${apiPath} failed: ${err.message}`, { cause: err }));
    const req = nodeHttp.request({ socketPath: HTTP_SOCKET, path: WEBROOT + apiPath, method }, (res) => {
        let data = "";
        // Decode once the response is complete rather than per chunk: a UTF-8
        // character split across chunks would otherwise arrive as U+FFFD.
        res.setEncoding("utf8");
        res.on("data", (chunk: string) => (data += chunk));
        res.on("error", fail);
        res.on("end", () => {
            if ((res.statusCode ?? 500) >= 400) {
                reject(new Error(`MailPit ${method} ${apiPath} failed: HTTP ${res.statusCode}${data ? `: ${data.slice(0, 200)}` : ""}`));
            } else {
                resolve(data);
            }
        });
    });
    req.setTimeout(REQUEST_TIMEOUT_MS, () => {
        req.destroy(); // no error argument: the rejection below already names the call
        reject(new MailpitRequestTimeoutError(`MailPit ${method} ${apiPath} timed out after ${REQUEST_TIMEOUT_MS}ms`));
    });
    req.on("error", fail);
    req.end();
    return promise;
}

/** JSON request over MailPit's HTTP unix socket. */
async function api<T>(apiPath: string, method = "GET"): Promise<T> {
    const text = await request(apiPath, method);
    try {
        return JSON.parse(text) as T;
    } catch {
        throw new Error(`MailPit ${method} ${apiPath} returned non-JSON: ${text.slice(0, 200)}`);
    }
}

/** Newest-first summary list (bounded by MailPit's --max, 500 here). */
export async function listMessages(limit = 500): Promise<MailpitSummary[]> {
    const data = await api<{ messages?: MailpitSummary[] }>(`/api/v1/messages?limit=${limit}`);
    return data.messages ?? [];
}

export function matches(summary: MailpitSummary, match: MessageMatch): boolean {
    // A summary with no To header has To: null (a Bcc-only message): it simply
    // matches no recipient, it is not an error.
    if (match.to && !(summary.To ?? []).some((t) => t.Address.toLowerCase() === match.to!.toLowerCase())) return false;
    if (match.subject && !summary.Subject.includes(match.subject)) return false;
    return true;
}

/** Newest-first summaries matching recipient and/or subject. */
export async function searchMessages(match: MessageMatch): Promise<MailpitSummary[]> {
    const all = await listMessages();
    return all.filter((m) => matches(m, match));
}

/** Fetch one full message (Text/HTML body included). */
export async function getMessage(id: string): Promise<MailpitMessage> {
    return api<MailpitMessage>(`/api/v1/message/${encodeURIComponent(id)}`);
}

/**
 * Newest full message matching the criteria that was created after $notBefore
 * (epoch ms). Freshness is judged on the SUMMARY's Created: the
 * /api/v1/message/{id} detail payload does not include a Created field at all
 * (new Date(undefined) is NaN). Null when nothing matches.
 */
export async function latestMessage(match: MessageMatch, notBefore = 0): Promise<MailpitMessage | null> {
    const hits = await searchMessages(match);
    for (const hit of hits) {
        const created = new Date(hit.Created).getTime();
        if (!Number.isNaN(created) && created >= notBefore) return getMessage(hit.ID);
    }
    return null;
}

/**
 * Raised by request()'s watchdog when one HTTP round trip exceeds
 * REQUEST_TIMEOUT_MS. A distinct type (not message-sniffing) so a hostile or
 * coincidental body like "timed out after 12ms" can never be mistaken for it.
 */
export class MailpitRequestTimeoutError extends Error {}

/**
 * Transient unix-socket transport failures a poll window must ride out while
 * the MailPit service (restart: "always") is briefly unreachable or mid-restart.
 * Recognised structurally, by errno code — following request()'s `cause` chain,
 * because it wraps transport errors to name the call — and by the watchdog's
 * typed timeout. Anything else (HTTP errors, auth failures, malformed JSON) is
 * a real fault and must surface immediately. Exported for regression tests.
 */
export function isTransientSocketError(err: unknown): boolean {
    const transient = ["ENOENT", "ECONNREFUSED", "ECONNRESET", "EPIPE", "ECONNABORTED", "ETIMEDOUT", "EAGAIN"];
    for (let e: unknown = err, depth = 0; e instanceof Error && depth < 8; e = e.cause, depth++) {
        if (e instanceof MailpitRequestTimeoutError) return true;
        const code = (e as NodeJS.ErrnoException).code;
        if (code && transient.includes(code)) return true;
    }
    return false;
}

/**
 * Poll until a matching message NEWER than $notBefore (epoch ms) exists. Jethro
 * sends synchronously before the confirmation page renders, but under pool
 * contention the SMTP write can lose the race with the browser — hence the retry
 * loop. The freshness floor keeps a matching leftover from a PREVIOUS suite run
 * (MailPit's store persists across runs while the service stays up) from being
 * returned while the new message is still in flight.
 *
 * $timeoutMs <= 0 polls indefinitely: timeouts.ts's to() returns 0 (wait
 * forever) under the Playwright Inspector, and that must not turn into "give up
 * at once".
 */
export async function waitForMessage(
    match: MessageMatch,
    notBefore = 0,
    timeoutMs = 15000,
    intervalMs = 500,
): Promise<MailpitMessage> {
    const deadline = timeoutMs > 0 ? Date.now() + timeoutMs : Number.POSITIVE_INFINITY;
    let lastError: unknown = null;
    while (Date.now() < deadline) {
        try {
            const msg = await latestMessage(match, notBefore);
            if (msg) return msg;
        } catch (err) {
            if (!isTransientSocketError(err)) throw err;
            lastError = err;
        }
        await delay(intervalMs);
    }
    const reason = lastError instanceof Error ? `; last error: ${lastError.message}` : "";
    throw new Error(`No MailPit message matching ${JSON.stringify(match)} after ${new Date(notBefore).toISOString()} within ${timeoutMs}ms${reason}`);
}

/**
 * Delete every captured message. Never call from tests running in parallel.
 * MailPit answers this one with text/plain "ok", not JSON.
 */
export async function deleteAllMessages(): Promise<void> {
    await request("/api/v1/messages", "DELETE");
}

/** Sleep for `ms` milliseconds. */
function delay(ms: number): Promise<void> {
    const { promise, resolve } = Promise.withResolvers<void>();
    setTimeout(resolve, ms);
    return promise;
}
