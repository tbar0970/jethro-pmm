<?php


/**
 * Jethro ↔ jethro-sms bridge — the Jethro-specific part of the SMS subsystem.
 *
 * The pure SMS engine (providers, value objects, templater, statusline maths)
 * lives in the `jethro-sms/` package at `Sms\` and `Sms\Providers\`.  This
 * file bridges it into Jethro: database persistence, session caching, person
 * resolution, permission checks, and HTML rendering.
 *
 * ── Structure ───────────────────────────────────────────────────────────
 *
 *  Domain types
 *    {@see JethroSmsRecipient}       — person + phone number, implements SmsRecipient
 *    {@see SmsRequestRecipients}     — categorised recipients from a request
 *    {@see JethroSmsDelivery}        — SmsDelivery with Jethro personId + databaseId
 *    {@see JethroSmsDeliveryBatch}   — SmsDeliveryBatch keyed by Jethro batch ID
 *    {@see DbLoggingSmsProvider}     — decorator that persists sends to the DB
 *    {@see FailureCachingSmsProvider} — decorator that briefly caches failed provider lookups
 *    {@see SessionSmsCache}          — SmsCache backed by $_SESSION
 *
 *  Provider factory
 *    {@see getSmsProvider()}         — build the configured provider chain
 *    {@see renderNotConfiguredHelp()} — HTML table of each provider's required constants
 *
 *  Send pipeline
 *    {@see sendSms()}               — send to recipients, with token expansion + DB logging
 *
 *  Request parsing
 *    {@see getRecipientsFromRequest()}      — resolve recipients from $_REQUEST params
 *    {@see getRecipientsFromPersonRecords()} — person DB rows → JethroSmsRecipient[]
 *    {@see getSenderFromRequest()}          — resolve sender, enforcing closed vocabulary
 *    {@see messageOverridesFromRequest()}   — preview-panel message overrides, by row key
 *    {@see getLegitimateSenderStrings()}    — build the closed vocabulary
 *    {@see getCurrentUserMobileNumber()}    — resolve _USER_MOBILE_ sender
 *
 *  Delivery lifecycle
 *    {@see updateDelivery()}               — poll upstream for status changes
 *    {@see cancelSms()}                    — cancel a scheduled send
 *    {@see canCancelScheduledDelivery()}   — permission + capability gate
 *    {@see loadSmsBatch()}                 — reconstruct a SmsDeliveryBatch from the DB
 *
 *  Opt-out handling
 *    {@see getOptedOutPhoneNumbers()}       — upstream opt-out list, session-cached
 *    {@see isPersonOptedOut()}             — check a single Person
 *
 *  Display / HTML
 *    {@see formatSendSummary()}            — human-readable send result
 *    {@see presentSendResult()}            — Call_SMS result → alerts, row classes, keep modal open?
 *    {@see previewRows()}                  — preview-panel rows, one per phone number
 *    {@see renderSmsDeliveryStatusIndicator()} — status column for the SMS log
 *    {@see renderSmsDeliveryStatusIcon()}      — status icon for delivery tracking
 *    {@see renderRegistrationStepHtml()}       — sender registration form
 *    {@see printSmsModal()}                — the main send-SMS modal dialog
 *    {@see printBulkSmsForm()}             — bulk-action SMS form on list pages
 *    {@see printSenderDropdown()}          — sender selection control
 *    {@see printSaveAsNoteCheckbox()}      — "Create Note" checkbox
 *    {@see printNoteSubjectField()}        — note subject input
 *    {@see printNoteActionDateField()}     — note action date picker
 *    {@see printTextbox()}                 — message textarea with statusline + preview
 *
 *  Utilities
 *    {@see getSmsBalance()}                — account balance, cached per request
 *    {@see getCurrentSmsProviderKey()}     — short key of the current provider
 *    {@see getAvailableTokens()}           — person tokens for message personalisation
 *    {@see makeStatuslineConfig()}         — config snapshot for statusline maths
 *    {@see SmsStatusIcon}                  — canonical icons for delivery statuses
 *    {@see insertSms()}                    — write an SMS + deliveries to the DB
 *    {@see classifySmsStatus()}            — aggregate status for the SMS log
 *    {@see getPersonSmsHistory()}          — SMS log entries for a person
 *    {@see isUsable()} / {@see isConfigured()} / {@see isFeatureEnabled()}
 *    {@see usesUserMobile()}               — does the provider use phone-number senders
 *
 * ── Provider chain (outermost first) ─────────────────────────────────────
 *
 *   1. OverridingSmsProvider   — SMS_SENDER enforcement + send-cooloff delay
 *   2. DbLoggingSmsProvider    — persists sends + deliveries to the DB
 *   3. TokenExpandingSmsProvider — per-recipient %token% expansion
 *   4. FailureCachingSmsProvider — remembers failed balance/sender/opt-out lookups for a minute
 *   5. Raw provider            — FiveCentSmsV5Provider, CellcastSmsProvider, etc.
 *
 * @see jethro-sms/docs/reference/SMS_ARCHITECTURE.md
 */
namespace Jethro\Sms;

// Pure SMS engine (providers, value objects, templater, statusline maths)
// lives in the jethro-sms package; this loader brings in all of it.
require_once dirname(__DIR__) . '/vendor/jethro/jethro-sms/src/load.php';
require_once __DIR__ . '/Jethro/Sms/Providers/DbLoggingSmsProvider.php';
require_once __DIR__ . '/Jethro/Sms/Providers/FailureCachingSmsProvider.php';
require_once __DIR__ . '/Jethro/Sms/SmsStatusIcon.php';
require_once __DIR__ . '/Jethro/Sms/JethroSmsRecipient.php';
require_once __DIR__ . '/Jethro/Sms/SmsRequestRecipients.php';
require_once __DIR__ . '/Jethro/Sms/JethroSmsDelivery.php';
require_once __DIR__ . '/Jethro/Sms/JethroSmsDeliveryBatch.php';
require_once __DIR__ . '/Jethro/Sms/SessionSmsCache.php';
require_once __DIR__ . '/url_shortener.php';

use Sms\AllSent;
use Sms\Failed;
use Sms\PartialSuccess;
use Sms\PhoneNumber;
use Sms\SenderID;
use Sms\SmsProvider;
use Sms\SmsRecipient;
use Sms\SmsSender;
use Sms\SmsStatus;
use Sms\Providers\TemplateSmsProvider;

/**
 * Build the configured SMS provider chain.
 *
 * Auto-detects the raw provider class from constants if SMS_PROVIDER is
 * not set:
 *   1. SMS_5CENTSMS_APIKEY_ID + SMS_5CENTSMS_APIKEY → FiveCentSmsV5Provider
 *   2. SMS_CELLCAST_APIKEY → CellcastSmsProvider
 *   3. SMS_HTTP_URL → FiveCentSmsV4Provider
 *
 * The returned provider chain is (outermost first):
 *   OverridingSmsProvider → DbLoggingSmsProvider → TokenExpandingSmsProvider → FailureCachingSmsProvider → RawProvider
 *
 * @param bool $logToDb  When true, wraps the chain in DbLoggingSmsProvider.
 * @return \Result<SmsProvider, string>  Success with the provider chain, or failure with a
 *     plain-text error message. For a not-configured failure, {@see renderNotConfiguredHelp()}
 *     supplies the accompanying HTML for callers that can render it.
 */
function getSmsProvider(bool $logToDb = true): \Result
{
	// Raw-provider selection (SMS_PROVIDER / auto-detection) lives in the
	// jethro-sms package — single source for this bridge and the standalone CLI.
	$classResult = \Sms\resolveRawProviderClass();
	if ($classResult->isFailure()) {
		$error = $classResult->getError();
		$message = empty($error['notConfigured'])
			? $error['message']
			: 'SMS not configured.';
		// Plain text only: this string is concatenated into JSON responses, CLI
		// output, 2FA logs and ents()-escaped HTML. The HTML "which constants do
		// I need" table lives in renderNotConfiguredHelp(), for callers that can
		// render markup.
		return \Result::failure($message);
	}
	$class = $classResult->getValue();

	// BASE_URL_ABSOLUTE (scheme://host[/path]) is defined in init.php alongside BASE_URL.
	// Providers extract only the origin from it to resolve bare-path SMS_*_URL constants.
	$baseUrl = defined('BASE_URL_ABSOLUTE') ? BASE_URL_ABSOLUTE : null;

	try {
		// Build the chain: raw provider → token expansion → (optional) DB logging.
		// Compute a config fingerprint so that cached sender IDs and balances
		// are invalidated when provider settings change (provider class,
		// API keys, URLs, etc.) — the SessionSmsCache treats fingerprint
		// mismatches as cache misses.
		$fingerprint = hash('xxh3', $class . ':' . implode(',', array_map(
			fn($c) => $c[0] . '=' . (string) ifdef($c[0], ''),
			$class::getConstants(),
		)));
		$cache = new SessionSmsCache('sms_cache_', $fingerprint);
		$provider = $class::fromSettings(new \Sms\ConstantSettings(), baseUrl: $baseUrl)->withCache($cache);
		if ($provider instanceof \Sms\Providers\TemplateSmsProvider) {
			// The package can't see Jethro's user system; supply the email the
			// _USER_EMAIL_ keyword substitutes.
			$provider = $provider->withUserEmail(getCurrentUserEmail());
		}
		// Balance, sender and opt-out lookups run on ordinary page loads; don't
		// let an unreachable gateway time out on every one of them.
		$provider = new \Jethro\Sms\Providers\FailureCachingSmsProvider($provider, $cache);
	} catch (\Throwable $e) {
		return \Result::failure($e->getMessage());
	}

	$personCache = [];
	$templater = new \Sms\Templater();
	$templater->registerFunction('concat', fn(string ...$parts): string => implode('', $parts));
	$provider = new \Sms\TokenExpandingSmsProvider($provider, function (\Sms\SmsRecipient $r) use (&$personCache): array {
		if (!$r instanceof JethroSmsRecipient) {
			throw new \RuntimeException(
				'Token expansion requires JethroSmsRecipient recipients. '
				. 'Got ' . get_debug_type($r) . '. '
				. 'Use person IDs instead of raw phone numbers, or avoid %tokens% in the message.'
			);
		}
		return recipientTokens($r, function (int $pid) use (&$personCache): array {
			if (!isset($personCache[$pid]) && isset($GLOBALS['system'])) {
				$res = $GLOBALS['system']->getDBObjectData('person', ['id' => $pid]);
				$personCache[$pid] = $res[$pid] ?? [];
			}
			return $personCache[$pid] ?? [];
		});
	}, $templater, getAvailableTokens(),
		shortenFn: fn(string $url): string => getUrlShortener()->shorten($url),
		previewShortenFn: fn(string $url): string => getUrlShortener()->previewShorten($url));

	if ($logToDb) {
		// Order contract (see docs/docs/developer/sms/smsarchitecture.md):
		// Deduplicating must sit INSIDE DbLoggingSmsProvider — logging needs
		// per-person results so insertSms()'s positional pairing stays 1:1 —
		// and OUTSIDE TokenExpandingSmsProvider — per-group token expansion
		// reads the logicalRecipients key the decorator attaches.  OptOut sits
		// between DbLogging and Deduplicating: opted-out numbers are dropped
		// before grouping; DbLogging drops their placeholders when logging.  It is
		// paired with DbLogging, so unlogged system sends (2FA) skip it.
		// Do not reorder.
		$provider = new \Sms\DeduplicatingSmsProvider($provider);
		$provider = new \Sms\OptOutSmsProvider($provider);
		$provider = new \Jethro\Sms\Providers\DbLoggingSmsProvider($provider);
	}

	// OverridingSmsProvider sits outside DbLoggingSmsProvider so the DB layer
	// sees the cooloff-modified $sendAt.  Only web sends by a user are
	// user-initiated: system sends (2FA, CLI scripts such as roster
	// reminders) skip the cooloff undo window even though they are logged.
	// userMobile is what the _USER_MOBILE_ sender token resolves to.
	$provider = new \Sms\OverridingSmsProvider($provider, userInitiated: $logToDb && PHP_SAPI !== 'cli', userMobile: getCurrentUserMobileNumber());

	// Not memoized — takes ~50 µs and called ~8 times in a worst-case page load.
	return \Result::success($provider);
 }

 
/**
 * HTML help listing the constants each SMS provider needs, with a tick or
 * cross against each one, for display beside the settings that define them.
 *
 * Split out of {@see getSmsProvider()} so that its failure message stays plain
 * text: that string reaches JSON responses, CLI scripts, 2FA logs and
 * ents()-escaped HTML, none of which can render markup. Only callers that are
 * emitting HTML ask for this.
 *
 * @return string|null HTML, or NULL when the failure is not a missing-constants
 *                     problem (a provider is selectable but broken) and there
 *                     is therefore nothing useful to list.
 */
function renderNotConfiguredHelp(): ?string
{
	$classResult = \Sms\resolveRawProviderClass();
	if (!$classResult->isFailure()) {
		return null;
	}
	$error = $classResult->getError();
	if (empty($error['notConfigured'])) {
		return null;
	}

	$html = 'Set <code>SMS_PROVIDER</code> or define the required constants for one of these providers:<br><br>';
	foreach (\Sms\providerCandidates() as [$candidate, $label]) {
		if ($candidate::usagePreference() < 0) {
			continue;
		}
		// We used to display ents($candidate) here ('Sms\FiveCentSmsV5Privider') but it seemed too techie
		$html .= '<b>' . ents($label) . '</b><br>';
		foreach ($candidate::getConstants() as [$key, $required, $purpose]) {
			$defined = (string) ifdef($key, '') !== '';
			$html .= '&nbsp;&nbsp;<span class="status-icon-' . ($defined ? 'yes' : 'no') . '">'
				. '<a href="#' . ents($key) . '"><code>' . ents($key) . '</code></a> '
				. '<span class="sms-constant-' . ($required === 'required' ? 'required' : 'optional') . '">'
				. ents($required) . '</span>'
				. ' &mdash; ' . ents($purpose) . '</span><br>';
		}
		$html .= '<br>';
	}
	return $html;
}



// ---------------------------------------------------------------------------
// Main send pipeline
// ---------------------------------------------------------------------------


/**
 * Send an SMS message.
 *
 * Accepts either the single-message form or the entries form:
 *   sendSms('Hello', $recips, $sender)
 *   sendSms([['message' => 'Hi', 'recipients' => [$r]]], [], $sender)
 *
 * @param string|array<int, array{message: string, recipients: SmsRecipient[]}> $messageOrEntries
 * @param SmsRecipient[] $recipients  Only used in the single-message form. Pass [] for entries form.
 * @param bool $logToDb  When false, skips DB logging (use for system-initiated sends with no logged-in user context, e.g. 2FA OTP).
 * @param bool $shortenUrls  Auto-shorten bare URLs over 26 chars. Only the SMS form turns this on (its
 *        "Shorten URLs" checkbox, via {@see shortenUrlsFromRequest()}); other senders (cron reminders,
 *        2FA) never auto-shorten. Ignored when URLSHORTENER is not a supported
 *        shortener ({@see \urlShortenerConfig()}). Explicit %(shorten url)% tokens
 *        are always expanded. See docs/docs/developer/sms/smsarchitecture.md.
 * @return \Result<\Sms\SmsDeliveryBatch, string>
 */
function sendSms(
	string|array $messageOrEntries,
	array        $recipients,
	SmsSender    $sender,
	?int         $sendAt = null,
	bool         $preview = false,
	bool         $logToDb = true,
	bool         $shortenUrls = false,
): \Result
{
	$entries = is_string($messageOrEntries)
		? [['message' => $messageOrEntries, 'recipients' => $recipients]]
		: $messageOrEntries;

	if ($entries === []) {
		return \Result::failure('No entries to send');
	}

	// Send
	$providerResult = getSmsProvider(logToDb: $logToDb);
	if ($providerResult->isFailure()) {
		return \Result::failure($providerResult->getError());
	}
	$provider = $providerResult->getValue();

	// Auto-shorten bare https?:// URLs: wrap them in %(shorten "...")%
	// Gated by $shortenUrls (the form's checkbox) — explicit %(shorten url)% tokens always work.
	if ($shortenUrls && \urlShortenerConfig()['supported']) {
		foreach ($entries as $i => $entry) {
			$msg = $entry['message'];
			// Don't double-wrap: explicit %(shorten ...)% tokens skip auto-shorten.
			if (!str_contains($msg, '%(shorten')) {
				$entries[$i]['message'] = preg_replace_callback(
					'{(https?://[^\s"\')\][<>]+)}',
					function (array $m): string {
						if (strlen($m[0]) > 26) {
							return '%(shorten "' . $m[1] . '")%';
						}
						return $m[0];
					},
					$msg,
				);
			}
		}
	}

	$outcome = $provider->send(
		entries: $entries,
		sender: $sender,
		sendAt: $sendAt,
		preview: $preview,
	);
	if ($outcome->isFailure()) {
		return \Result::failure($outcome->getError());
	}

	$batch = $outcome->getValue();


    return \Result::success($batch);
}


// ---------------------------------------------------------------------------
// Request parsing
// ---------------------------------------------------------------------------

/**
 * Resolve SMS recipients from the current HTTP request.
 *
 * Reads `$_REQUEST` params set by various SMS-initiating UI flows:
 *
 *   - `queryid`   — a saved person query
 *   - `groupid`   — a person group
 *   - `roster_view` + `start_date`/`end_date` — roster view assignees
 *   - `personid`  — comma-separated or array of person IDs
 *
 * When `sms_type` is `'family'`, selected persons are expanded to all
 * adults in their families (using Age_Bracket and Family lookups). Each such
 * recipient records which selected persons of its family it is sent the
 * message for (JethroSmsRecipient::$selectedPersonIds, for the
 * %selected.*% tokens), taken from the selected rows already loaded here.
 *
 * Opted-out numbers stay in the recipient list: OptOutSmsProvider (in the
 * send chain) skips them and returns an OPTED_OUT delivery for each, so the
 * preview can show them and the send logs them.
 *
 * @return SmsRequestRecipients  Categorised recipients ready for sending and UI display
 * @see getRecipientsFromPersonRecords()
 * @see call_sms.class.php
 */
function getRecipientsFromRequest(): SmsRequestRecipients
{
	$blanks = $archived = $rawRecips = $selectedByFamily = [];

	if (!empty($_REQUEST['queryid'])) {
		$query = $GLOBALS['system']->getDBObject('person_query', (int)$_REQUEST['queryid']);
		$personids = $query->getResultPersonIDs();
		$rawRecips = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '!mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
		$blanks = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, 'mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
		$archived = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '(status' => \Person_Status::getArchivedIDs()], 'AND');
	} else if (!empty($_REQUEST['groupid'])) {
		$group = $GLOBALS['system']->getDBObject('person_group', (int)$_REQUEST['groupid']);
		$personids = array_keys($group->getMembers());
		$rawRecips = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '!mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
		$blanks = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, 'mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
		$archived = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '(status' => \Person_Status::getArchivedIDs()], 'AND');
	} else if (!empty($_REQUEST['roster_view'])) {
		$rawRecips = [];
		foreach ((array)$_REQUEST['roster_view'] as $viewid) {
			$view = $GLOBALS['system']->getDBObject('roster_view', (int)$viewid);
			$rawRecips += $view->getAssignees($_REQUEST['start_date'], $_REQUEST['end_date']);
		}
	} else {
		if (empty($_REQUEST['personid'])) {
			$rawRecips = $blanks = $archived = [];
		} else {
			// Normalise: a comma-separated string (from roster "SMS all") or an array
			$personids = \is_array($_REQUEST['personid']) ? $_REQUEST['personid'] : explode(',', $_REQUEST['personid']);
			$rawRecips = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '!mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, 'mobile_tel' => '', '!(status' => \Person_Status::getArchivedIDs()], 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', ['(id' => $personids, '(status' => \Person_Status::getArchivedIDs()], 'AND');
		}
	}

	// Family mode: expand selected persons to all adults in their families.
	// Use $rawRecips + $blanks as seed so persons without mobiles still contribute their family.
	if (($rawRecips !== [] || $blanks !== []) && (array_get($_REQUEST, 'sms_type', 'person') === 'family')) {
		$personids = array_keys($rawRecips + $blanks);
		// Who each family's adults are sent the message for (%selected.*% tokens).
		$selectedRows = $rawRecips + $blanks;
		ksort($selectedRows);
		foreach ($selectedRows as $pid => $row) {
			$selectedByFamily[(int)$row['familyid']][] = (int)$pid;
		}
		$GLOBALS['system']->includeDBClass('family');
		$GLOBALS['system']->includeDBClass('age_bracket');
		$families = \Family::getFamilyDataByMemberIDs($personids);
		$familyIds = array_keys($families);
		if ($familyIds !== []) {
			$adultBrackets = \Age_Bracket::getAdults();
			$rawRecips = $GLOBALS['system']->getDBObjectData('person', [
				'(age_bracketid' => $adultBrackets,
				'(familyid' => $familyIds,
				'!mobile_tel' => '',
				'!(status' => \Person_Status::getArchivedIDs(),
			], 'AND');
			$blanks = $GLOBALS['system']->getDBObjectData('person', [
				'(age_bracketid' => $adultBrackets,
				'(familyid' => $familyIds,
				'mobile_tel' => '',
				'!(status' => \Person_Status::getArchivedIDs(),
			], 'AND');
			$archived = $GLOBALS['system']->getDBObjectData('person', [
				'(age_bracketid' => $adultBrackets,
				'(familyid' => $familyIds,
				'(status' => \Person_Status::getArchivedIDs(),
			], 'AND');
		} else {
			$rawRecips = $blanks = $archived = [];
		}
	}

	// A mobile with no digits (e.g. "-") can't be sent to: report it with the
	// blank mobiles rather than sending it or silently dropping it.
	foreach ($rawRecips as $pid => $data) {
		if (!preg_match('/\d/', (string) ($data['mobile_tel'] ?? ''))) {
			$blanks[$pid] = $data;
			unset($rawRecips[$pid]);
		}
	}

	return new SmsRequestRecipients(
		recipients: getRecipientsFromPersonRecords($rawRecips, $selectedByFamily),
		blanks: $blanks,
		archived: $archived,
		rawPersonRecords: $rawRecips,
	);
}

/**
 * Convert person records from the database into JethroSmsRecipient objects.
 *
 * @param array<int, array<string, mixed>> $personRecords  Person rows KEYED BY PERSON ID (the key is used as personId);
 *        each must have 'mobile_tel', and 'familyid' when $selectedByFamily is given
 * @param array<int, list<int>> $selectedByFamily  Family ID => IDs of the selected persons in that family,
 *        for recipients sent the message because of them (family mode of getRecipientsFromRequest());
 *        each recipient gets its own family's list as JethroSmsRecipient::$selectedPersonIds.
 *        Omit when every recipient was selected directly.
 * @return JethroSmsRecipient[]
 */
function getRecipientsFromPersonRecords(array $personRecords, array $selectedByFamily = []): array
{
	$recipients = [];
	foreach ($personRecords as $personid => $persondata) {
		if (!empty($persondata['mobile_tel'])) {
			$recipients[] = new JethroSmsRecipient(
				personId: (int)$personid,
				number: new \Sms\PhoneNumber($persondata['mobile_tel']),
				selectedPersonIds: $selectedByFamily[(int)($persondata['familyid'] ?? 0)] ?? [],
			);
		}
	}
	return $recipients;
}

/**
 * Read the SMS form's "Shorten URLs" checkbox from the request.
 *
 * The form posts a hidden `shorten_urls=0` before the checkbox (`=1`), so
 * an unticked box arrives as "0". Absent (no usable shortener, so no
 * checkbox) also means false.
 *
 * @see sendSms()
 * @see makeStatuslineConfig()
 */
function shortenUrlsFromRequest(): bool
{
	return ($_REQUEST['shorten_urls'] ?? null) === '1';
}

/**
 * The preview panel's message overrides from the request: row key => text.
 *
 * A row key names the people one preview row's message goes to: their
 * person IDs joined by '_' ('140', or '140_142_143' for people sharing a
 * mobile; see previewRows()).  Each row posts its override as
 * message_overrides[KEY] (Sms\renderPreviewPanel()).  Malformed keys and
 * empty texts are dropped.  PHP turns a single-ID key like '140' into int 140.
 *
 * @return array<int|string, string>
 * @see docs/docs/developer/sms/SMS_DATASTAR.md
 */
function messageOverridesFromRequest(): array
{
	$raw = $_POST['message_overrides'] ?? [];
	$overrides = [];
	if (is_array($raw)) {
		foreach ($raw as $key => $msg) {
			if (is_string($msg) && $msg !== '' && preg_match('/^\d+(?:_\d+)*$/', (string)$key)) {
				$overrides[$key] = $msg;
			}
		}
	}
	return $overrides;
}

/**
 * Preview-panel rows for a preview send: ONE row per phone number, the unit
 * the gateway sends and charges.  People sharing a mobile get one message
 * between them (Sms\DeduplicatingSmsProvider), so they share a row: names
 * one per line ("\n"), the combined message they will receive, and a key listing
 * their person IDs in recipient order ('140_142_143').  The key names the
 * row's override field, which the send path applies to everyone in it
 * (Call_SMS).  A row sits where its first person sat in the recipient list.
 *
 * A preview is one message, so grouping by number alone matches what
 * DeduplicatingSmsProvider does per entry.
 *
 * @param array<int, array<string, mixed>> $rawPersonRecords person rows keyed by ID ({@see SmsRequestRecipients::$rawPersonRecords})
 * @param string $message fallback text for deliveries that carry none
 * @return list<array{key: string, name: string, message: string, status: string}>
 * @see messageOverridesFromRequest()
 * @see \Sms\renderPreviewPanel()
 */
function previewRows(\Sms\SmsDeliveryBatch $batch, array $rawPersonRecords, string $message): array
{
	/** @var list<non-empty-list<\Sms\SmsDelivery>> $groups */
	$groups = [];
	$groupOfNumber = [];
	foreach ($batch->deliveries as $d) {
		$g = $groupOfNumber[$d->recipient()->sameNumberKey()] ??= count($groups);
		$groups[$g][] = $d;
	}

	$rows = [];
	foreach ($groups as $deliveries) {
		$pids = [];
		$names = [];
		foreach ($deliveries as $d) {
			$pid = $d instanceof JethroSmsDelivery ? $d->recipientPersonId() : null;
			$person = $pid !== null ? ($rawPersonRecords[$pid] ?? null) : null;
			if ($pid !== null) {
				$pids[] = $pid;
			}
			$names[] = $person
				? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''))
				: $d->recipient()->value;
		}
		$rows[] = [
			'key' => implode('_', $pids),
			'name' => implode("\n", $names),
			'message' => $deliveries[0]->message() ?? $message,
			'status' => $deliveries[0]->status()->value,
		];
	}
	return $rows;
}

/**
 * Resolve the SMS sender from the HTTP request.
 *
 * Only accepts legitimate sender values from the closed vocabulary defined
 * by {@see getLegitimateSenderStrings()}: the symbolic `_USER_MOBILE_` token
 * (resolved from the session user), the trusted `SMS_SENDER` constant, and
 * sender IDs in `SMS_SENDER_OPTIONS` or discovered from the provider.
 *
 * Arbitrary raw phone numbers and unrecognised strings are rejected —
 * the dropdown never produces them and they represent potential sender
 * spoofing (see docs/security/09_sms_sender_spoofing_via_request.md).
 *
 * Returns null if no valid sender can be determined.
 */
function getSenderFromRequest(): ?SmsSender
{
    $rawSender = $_REQUEST['sender'] ?? null;

    // Resolve through the trust hierarchy:
    //   request param → SMS_SENDER constant → session user's mobile
    $sender = null;
    if ($rawSender !== null && $rawSender !== '') {
        $sender = $rawSender;
    } elseif ((string) ifdef('SMS_SENDER', '') !== '') {
        $sender = SMS_SENDER;
    } else {
        $sender = getCurrentUserMobileNumber();
    }

    if ($sender === null) {
        return null;
    }

    // _USER_MOBILE_ always resolves to the session user's actual number.
    if ($sender === '_USER_MOBILE_') {
        return getCurrentUserMobileNumber();
    }

    // Fallback path — no request sender specified, no SMS_SENDER.
    // getCurrentUserMobileNumber() returns a PhoneNumber directly.
    if ($sender instanceof SmsSender) {
        return $sender;
    }

    // SMS_SENDER constant: trusted configuration, accept as-is.
    if ((string) ifdef('SMS_SENDER', '') !== '' && $sender === SMS_SENDER) {
        return \Sms\senderFromString((string) $sender);
    }

    // Explicit request value (not _USER_MOBILE_, not SMS_SENDER, not a
    // fallback object).  Only accept if it appears in the legitimate
    // sender vocabulary — SmsSenderIDs in the admin allowlist or
    // discovered from the provider.  Raw numbers and random strings fail
    // here and return null (no valid sender).
    $legitimate = getLegitimateSenderStrings();
    if (\in_array($sender, $legitimate, true)) {
        return new SenderID((string) $sender);
    }

    return null;
}

/**
 * Collect the full set of legitimate sender string values from the
 * SMS_SENDER_OPTIONS allowlist and provider-discovered sender IDs.
 *
 * Statically cached — one provider lookup on first call, free afterwards.
 *
 * @return string[]
 */
function getLegitimateSenderStrings(): array
{
    static $legitimate = null;
    if ($legitimate !== null) {
        return $legitimate;
    }

    $legitimate = [];

    // 1. SMS_SENDER_OPTIONS allowlist (minus special tokens: _USER_MOBILE_ is
    //    handled by getSenderFromRequest(); _SENDER_IDS_ expands to real IDs
    //    via the provider call in step 2 below).
    if ((string) ifdef('SMS_SENDER_OPTIONS', '') !== '') {
        foreach (array_map('trim', explode(',', SMS_SENDER_OPTIONS)) as $id) {
            if ($id !== '' && $id !== '_USER_MOBILE_' && $id !== '_SENDER_IDS_') {
                $legitimate[] = $id;
            }
        }
    }

    // 2. Provider-discovered sender IDs (session-cached by the provider,
    //    and getSmsProvider() is cheap on repeated calls).
    $providerResult = getSmsProvider();
    if ($providerResult->isSuccess()) {
        $idsResult = $providerResult->getValue()->getSenderIds();
        if ($idsResult->isSuccess()) {
            foreach ($idsResult->getValue() as $senderId) {
                $legitimate[] = $senderId->value;
            }
        }
    }

    return $legitimate;
}

/**
 * Why the current user may not send as $sender, or null if they may.
 *
 * Sending as their own mobile, on a provider that verifies sender numbers
 * (REGISTER_SENDER_NUMBER: Cellcast, 5CentSMS v5), needs the number verified
 * *for them*: a number belongs to the first person verified for it
 * (DbLoggingSmsProvider::verifySenderNumber()), so a user who sets their
 * mobile to someone else's verified number can't send as them (SMS review
 * Q9). If the check itself fails (provider unreachable), the send isn't
 * blocked: it would fail on its own, and a number owned by someone else is
 * refused from the database before the provider is asked.
 */
function senderNotVerifiedError(\Sms\SmsSender $sender): ?string
{
    $own = getCurrentUserMobileNumber();
    if ($own === null || !$sender instanceof \Sms\PhoneNumber || !$sender->sameNumberAs($own)) {
        return null;
    }
    $providerResult = getSmsProvider();
    if ($providerResult->isFailure()) {
        return null;
    }
    $provider = $providerResult->getValue();
    if (!$provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_NUMBER)) {
        return null;
    }
    $verified = $provider->verifySenderNumber($own);
    if ($verified->isSuccess() && $verified->getValue() === false) {
        return 'Your mobile number ' . $own->value . ' isn\'t verified as a sender for you: use "verify now" next to the sender, then click the verification link (or enter the code) the provider texts to your mobile.';
    }
    return null;
}

/**
 * The current (logged-in) user's mobile number — what the `_USER_MOBILE_`
 * sender token resolves to.  Null when nobody is logged in (CLI, cron,
 * pre-2FA login) or the user has no mobile (a value with no digits counts
 * as none, as in isPersonOptedOut()).
 */
function getCurrentUserMobileNumber(): ?\Sms\PhoneNumber
{
    $currentUser = isset($GLOBALS['user_system']) ? $GLOBALS['user_system']->getCurrentUser() : null;
    $mobile = (string) ($currentUser['mobile_tel'] ?? '');
    if (preg_match('/\d/', $mobile)) {
		return new \Sms\PhoneNumber($mobile);
	}
	return null;
}


// ---------------------------------------------------------------------------
// Delivery lifecycle
// ---------------------------------------------------------------------------

/**
 * Fetch delivery status for a previously sent SMS.
 *
 * Caching (smsdelivery) is handled transparently by DbLoggingSmsProvider
 * wired into getSmsProvider().
 *
 * @return \Result<\Sms\SmsDelivery, string>
 */
function updateDelivery(\Sms\SmsDelivery $delivery): \Result
{
	$providerResult = getSmsProvider();
	if ($providerResult->isFailure()) {
		return \Result::failure($providerResult->getError());
	}

	return $providerResult->getValue()->updateDelivery($delivery);
}

/**
 * Cancel the deliveries in a batch.
 *
 * Delegates to the provider's cancel(). DB persistence is handled transparently
 * by DbLoggingSmsProvider wired into getSmsProvider().
 *
 * @return \Result<\Sms\SmsDeliveryBatch, string>
 */
function cancelSms(\Sms\SmsDeliveryBatch $batch): \Result
{
	$providerResult = getSmsProvider();
	if ($providerResult->isFailure()) {
		return \Result::failure($providerResult->getError());
	}

	return $providerResult->getValue()->cancel($batch);
}
/**
 * Whether the current user can cancel a scheduled SMS sent by $senderPersonId
 * (null: sent by a script) — so Cancel links are shown only to those
 * Call_SMS_Cancel will let cancel it: "SMS - send", a provider that can
 * cancel, and the original sender or a system administrator.
 * Does not check delivery status — call only for SCHEDULED deliveries.
 */
function canCancelScheduledDelivery(?int $senderPersonId): bool
{
    $user = $GLOBALS['user_system'];
    if (!$user->havePerm(PERM_SENDSMS)) {
        return false;
    }
    if (!$user->havePerm(PERM_SYSADMIN)
        && ($senderPersonId === null || $senderPersonId !== (int) ($user->getCurrentUser('id') ?? 0))) {
        return false;
    }
    $providerResult = getSmsProvider();
    if ($providerResult->isFailure()) {
        return false;
    }
    return $providerResult->getValue()->hasCapability(\Sms\SmsCapability::DEFERRED_SEND_CANCEL);
}

/**
 * Placeholder recipient for a logged delivery whose number is unknown — see
 * loadSmsBatch(). Never sent to: it only fills SmsDelivery's required recipient.
 */
const UNKNOWN_RECIPIENT_NUMBER = '0';

/**
 * Load a persisted SMS send as a batch, for cancellation/inspection.
 *
 * Deliveries are JethroSmsDelivery objects hydrated with statuses, remote IDs
 * and database IDs.  senderPersonId carries sms.sender for ownership checks
 * (null = script-initiated send).
 *
 * _smsdelivery doesn't store the destination number, so each recipient is the
 * person's *current* mobile.  Deliveries to raw numbers (personid NULL) and to
 * persons whose mobile has since been cleared get a placeholder number
 * ({@see UNKNOWN_RECIPIENT_NUMBER}): cancellation and status polling work by
 * remote_id, so every delivery stays in the batch.
 *
 * @return \Result<JethroSmsDeliveryBatch, string>  Failure if no such sms row.
 */
function loadSmsBatch(int $smsId): \Result
{
	$db = $GLOBALS['db'];

	$smsRow = $db->queryRow(
		'SELECT sender FROM sms WHERE id = ' . (int)$smsId
	);
	if (!$smsRow) {
		return \Result::failure('SMS send not found');
	}

	$senderPersonId = isset($smsRow['sender']) && $smsRow['sender'] !== null
		? (int)$smsRow['sender']
		: null;

	$rows = $db->queryAll(
		'SELECT sd.id, sd.personid, sd.remote_id, sd.status, sd.body, p.mobile_tel'
		. ' FROM _smsdelivery sd'
		. ' LEFT JOIN _person p ON p.id = sd.personid'
		. ' WHERE sd.sms_id = ' . (int)$smsId
	);

	$deliveries = [];
	foreach ((array)$rows as $row) {
		$mobile = (string) ($row['mobile_tel'] ?? '');
		$deliveries[] = new JethroSmsDelivery(
			inner: new \Sms\SmsDelivery(
				recipient: new \Sms\PhoneNumber(preg_match('/\d/', $mobile) ? $mobile : UNKNOWN_RECIPIENT_NUMBER),
				status: \Sms\SmsStatus::fromMySql((string)$row['status']),
				remoteId: $row['remote_id'],
				message: $row['body'],
			),
			recipientPersonId: $row['personid'] !== null ? (int)$row['personid'] : null,
			databaseId: (int)$row['id'],
		);
	}

	return \Result::success(new JethroSmsDeliveryBatch(
		batchId: (string)$smsId,
		deliveries: $deliveries,
		senderPersonId: $senderPersonId,
	));
}


// formatDeliveryLine() and formatDeliveryLines() are defined in
// jethro-sms/src/functions.php (namespace Sms) — shared with the CLI.

/**
 * Compare upstream provider deliveries with local database records.
 *
 * Calls listRecentDeliveries($since) on the provider, formats the result,
 * queries smsdelivery+sms for the same period, formats that, and diffs them.
 *
 * @param int|null $since Unix timestamp, or null for last 24 hours
 * @return string  Diff output (empty if synchronized)
 */
function checkSynchronized(?int $since = null): string
{
	$since = $since ?? time() - 86400;
	$sinceDate = date('Y-m-d H:i:s', $since);

	// --- Upstream ---
	$providerResult = getSmsProvider(logToDb: false);
	if ($providerResult->isFailure()) {
		return "ERROR: Cannot get SMS provider: " . $providerResult->getError() . "\n";
	}
	$provider = $providerResult->getValue();

	$upstreamResult = $provider->listRecentDeliveries($since);
	if ($upstreamResult->isFailure()) {
		return "ERROR: listRecentDeliveries failed: " . $upstreamResult->getError() . "\n";
	}
	/** @var \Sms\SmsDelivery[] $upstreamDeliveries */
	$upstreamDeliveries = $upstreamResult->getValue();

	$upstreamText = \Sms\formatDeliveryLines($upstreamDeliveries);
	// --- Local DB ---
	$db = $GLOBALS['db'];
	$dbRows = $db->queryAll(
		'SELECT sd.remote_id, sd.status, sd.delivered_at, sd.body,'
		. ' p.mobile_tel, s.created AS send_time'
		. ' FROM _smsdelivery sd'
		. ' JOIN sms s ON s.id = sd.sms_id'
		. ' LEFT JOIN _person p ON p.id = sd.personid'
		. ' WHERE s.created >= ' . $db->quote($sinceDate)
		. ' ORDER BY s.created'
	);

	$dbDeliveries = [];
	foreach ($dbRows as $row) {
		$status = \Sms\SmsStatus::fromMySql((string)$row['status']);
		$sendTs = $row['send_time'] !== null ? strtotime((string)$row['send_time']) : null;
		$delTs = $row['delivered_at'] !== null ? strtotime((string)$row['delivered_at']) : null;
		$dbDeliveries[] = new \Sms\SmsDelivery(
			recipient: new \Sms\PhoneNumber((string)($row['mobile_tel'] ?: '0000000000')),
			status: $status,
			remoteId: $row['remote_id'] !== null ? (string)$row['remote_id'] : null,
			sendTimestamp: $sendTs ?: null,
			deliveryTimestamp: $delTs ?: null,
			message: (string)($row['body'] ?? ''),
		);
	}

	$dbText = \Sms\formatDeliveryLines($dbDeliveries);

	// --- Diff ---
	$upstreamFile = tempnam(sys_get_temp_dir(), 'sms-up-');
	$dbFile = tempnam(sys_get_temp_dir(), 'sms-db-');
	file_put_contents($upstreamFile, $upstreamText);
	file_put_contents($dbFile, $dbText);

	$diff = shell_exec('/usr/bin/diff ' . escapeshellarg($upstreamFile) . ' ' . escapeshellarg($dbFile) . ' 2>&1');

	unlink($upstreamFile);
	unlink($dbFile);

	$upCount = count($upstreamDeliveries);
	$dbCount = count($dbDeliveries);
	$header = "Upstream ($upCount) vs local DB ($dbCount) since $sinceDate:\n\n";

	if ($diff === null || $diff === '') {
		return $header . "(in sync — no differences)\n";
	}

	return $header . $diff;
}

/**
 * Import SMS history from the upstream provider.
 *
 * Fetches deliveries from listRecentDeliveries($since), stages them
 * verbatim in scratch tables (sms_new + smsdelivery_new), refines the
 * staged rows with SQL — feed dedup, batch grouping, personid resolution —
 * then deletes the staged rows already present locally and bulk-inserts
 * the rest.  See docs/docs/developer/sms/history-sync.md for a
 * step-by-step description of the pipeline.
 *
 * Existing rows cannot be assumed to carry a remote_id, so duplicate
 * detection is heuristic:
 *  - a batch matches an sms row with the same body created within 10 minutes;
 *  - a delivery matches an smsdelivery row under that batch by remote_id
 *    when both sides have one, otherwise by recipient phone number
 *    (via personid or the raw_response destination), compared in
 *    international format.
 * New deliveries for a partially-imported batch are attached to the existing
 * sms row rather than creating a duplicate batch.
 *
 * The scratch tables are real (not TEMPORARY — MySQL cannot reference a
 * temporary table twice in one query, which the SQL refinement needs), so
 * concurrent runs are serialised with a named lock, and the CREATE/DROP
 * TABLE statements implicitly commit: do not call this function inside a
 * transaction whose atomicity you need to keep.
 *
 * @param int|null $since Unix timestamp, or null for last 24 hours
 * @return array{deleted: int, imported: int, batches: int, skipped: int}
 *   deleted  = staged deliveries discarded as duplicates of local rows,
 *   imported = smsdelivery rows inserted,
 *   batches  = sms rows created,
 *   skipped  = upstream batches whose deliveries were all already present.
 */
function synchronizeHistory(?int $since = null): array
{
	$since = $since ?? time() - 86400;
	// A full-history fetch pages through the provider's entire retention
	// (~10k deliveries took ~12s on a busy instance); don't let the 30s web
	// max_execution_time kill it halfway.
	set_time_limit(0);

	$db = $GLOBALS['db'];

	// --- Fetch upstream ---
	$providerResult = getSmsProvider(logToDb: false);
	if ($providerResult->isFailure()) {
		throw new \RuntimeException('Cannot get SMS provider: ' . $providerResult->getError());
	}
	$provider = $providerResult->getValue();

	if (!$provider->hasCapability(\Sms\SmsCapability::LIST_HISTORY)) {
		throw new \RuntimeException('This SMS provider (' . $provider->getDescription() . ') does not support importing history.');
	}

	$upstreamResult = $provider->listRecentDeliveries($since);
	if ($upstreamResult->isFailure()) {
		throw new \RuntimeException('listRecentDeliveries failed: ' . $upstreamResult->getError());
	}
	/** @var \Sms\SmsDelivery[] $deliveries */
	$deliveries = $upstreamResult->getValue();
	$providerKey = $provider->getKey();
	if ($providerKey === null) {
		throw new \RuntimeException('This SMS provider (' . $provider->getDescription() . ') has no known upstream API, so its history cannot be matched.');
	}

	// SQL fragment: reduce a phone expression to its comparison key — the
	// last 9 digits, or all digits if fewer — so upstream numbers,
	// _person.mobile_tel and raw_response destinations match whether stored
	// as '0401…' or '61401…'.  Mirrors PhoneNumber::sameNumberAs().
	$phoneKeySql = function (string $expr): string {
		$digits = "REGEXP_REPLACE(CONVERT(COALESCE($expr, '') USING utf8mb4), '[^0-9]', '')";
		// The explicit COLLATE lets the result compare against the staging
		// tables regardless of the source column's own collation.
		return "(CASE WHEN CHAR_LENGTH($digits) > 9 THEN RIGHT($digits, 9) ELSE $digits END) COLLATE utf8mb4_unicode_ci";
	};

	// Ranking used whenever several staged records describe the same
	// delivery (multipart segments, a 'sent' record followed by a
	// 'delivered' record): keep the most final status, preferring records
	// that carry a delivery time, then the latest record.
	$keepBestOrder = "FIELD(status, 'delivered', 'failed', 'cancelled', 'sent', 'sending', 'in-progress', 'queued', 'scheduled', 'test-message', 'unknown'), delivered_at IS NULL, id DESC";

	// Scratch tables are shared real tables, so serialise concurrent syncs.
	$locked = (int)$db->queryOne("SELECT GET_LOCK(CONCAT(DATABASE(), '.sms_sync'), 5)");
	if ($locked !== 1) {
		throw new \RuntimeException('Another SMS history synchronization is already running');
	}

	$ownTransaction = false;
	try {
		// (Re)build the scratch tables first: CREATE/DROP TABLE implicitly
		// commits, so all DDL happens before our transaction opens.
		$db->exec('DROP TABLE IF EXISTS smsdelivery_new, sms_new');
		$db->exec(
			'CREATE TABLE smsdelivery_new ('
			. ' id INT AUTO_INCREMENT PRIMARY KEY,'
			. " phone_key VARCHAR(32) NOT NULL DEFAULT ''," // last 9 digits (as PhoneNumber::sameNumberAs()), '' if unknown
			. ' personid INT NULL,'
			. ' remote_id VARCHAR(255) NULL,'
			. ' raw_response TEXT NULL,'
			. ' body TEXT NULL,'
			. ' send_at DATETIME NULL,'
			. ' delivered_at DATETIME NULL,'
			. ' status VARCHAR(20) NOT NULL,'
			. ' segments_used INT NULL,'
			. ' per_segment_cost DECIMAL(10,5) NULL,'
			. ' grp INT NULL,'      // session number within body (1-hour gap rule)
			. ' batch_id INT NULL,' // sms_new.id after batch grouping
			. ' sms_id INT NULL'    // destination sms.id once the batch is resolved
			. ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);
		$db->exec(
			'CREATE TABLE sms_new ('
			. ' id INT AUTO_INCREMENT PRIMARY KEY,'
			. ' body TEXT NOT NULL,'
			. ' created DATETIME NOT NULL,'
			. ' grp INT NOT NULL,'
			. ' sms_id INT NULL'    // matching existing sms.id, if any
			. ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);

		$ownTransaction = !$db->inTransaction();
		if ($ownTransaction) {
			$db->beginTransaction();
		}
		// --- Stage the upstream feed verbatim ---
		// Imports always come from the current provider, so its configured
		// price is the right fallback when the feed doesn't report one.
		$configuredPrice = configuredSegmentCost($providerKey);
		$values = [];
		foreach ($deliveries as $d) {
			$digits = preg_replace('/\D/', '', $d->recipient()->value);
			$phoneKey = \strlen($digits) > 9 ? substr($digits, -9) : $digits;
			$values[] = '('
				. $db->quote($phoneKey) . ', '
				. ($d->remoteId() !== null ? $db->quote($d->remoteId()) : 'NULL') . ', '
				. $db->quote($d->rawResponse()) . ', '
				. ($d->message() !== null ? $db->quote($d->message()) : 'NULL') . ', '
				. ($d->sendTimestamp() !== null ? $db->quote(date('Y-m-d H:i:s', $d->sendTimestamp())) : 'NULL') . ', '
				. ($d->deliveryTimestamp() !== null ? $db->quote(date('Y-m-d H:i:s', $d->deliveryTimestamp())) : 'NULL') . ', '
				. $db->quote($d->status()->toMySql()) . ', '
				. ($d->segmentsUsed() !== null ? (int) $d->segmentsUsed() : 'NULL') . ', '
				. number_format($d->perSegmentCost() ?? $configuredPrice, 5, '.', '')
				. ')';
		}
		foreach (array_chunk($values, 200) as $chunk) {
			$db->exec(
				'INSERT INTO smsdelivery_new (phone_key, remote_id, raw_response, body, send_at, delivered_at, status, segments_used, per_segment_cost)'
				. ' VALUES ' . implode(', ', $chunk)
			);
		}

		// --- Refine: group into batches — same body, deliveries within 1 hour ---
		// Gap-based sessions per body: a >1-hour silence starts a new batch
		// (matches the migration logic in upgrades/2026-upgrade-to-2.40-sms.sql;
		// we don't have sender from upstream, so we cluster by body alone).
		// Deliveries without a send time join the batch they sort next to.
		$db->exec(
			'UPDATE smsdelivery_new d JOIN ('
			. ' SELECT id, SUM(brk) OVER (PARTITION BY body_key ORDER BY send_at IS NULL, send_at, id) AS grp'
			. ' FROM ('
			. "  SELECT id, COALESCE(body, '') AS body_key, send_at,"
			. '   CASE WHEN TIMESTAMPDIFF(SECOND,'
			. "    LAG(send_at) OVER (PARTITION BY COALESCE(body, '') ORDER BY send_at IS NULL, send_at, id),"
			. '    send_at) > 3600 THEN 1 ELSE 0 END AS brk'
			. '  FROM smsdelivery_new'
			. ' ) gaps'
			. ') g ON g.id = d.id'
			. ' SET d.grp = g.grp'
		);
		$db->exec(
			'INSERT INTO sms_new (body, created, grp)'
			. " SELECT COALESCE(body, ''), COALESCE(MIN(send_at), NOW()), grp"
			. ' FROM smsdelivery_new'
			. " GROUP BY COALESCE(body, ''), grp"
		);
		$db->exec(
			'UPDATE smsdelivery_new d'
			. " JOIN sms_new b ON b.body = COALESCE(d.body, '') AND b.grp = d.grp"
			. ' SET d.batch_id = b.id'
		);

		// --- Refine: collapse the feed to one record per delivery ---
		// Paged report fetches can return the same record twice (records shift
		// across page boundaries between fetches), and one recipient can have
		// several report entries within a batch.  Dedup by remote_id when
		// present, and by recipient phone within each batch.
		$db->exec(
			'DELETE FROM smsdelivery_new WHERE id IN ('
			. ' SELECT id FROM ('
			. '  SELECT id, ROW_NUMBER() OVER (PARTITION BY remote_id ORDER BY ' . $keepBestOrder . ') AS rn'
			. "  FROM smsdelivery_new WHERE COALESCE(remote_id, '') <> ''"
			. ' ) ranked WHERE rn > 1'
			. ')'
		);
		$db->exec(
			'DELETE FROM smsdelivery_new WHERE id IN ('
			. ' SELECT id FROM ('
			. '  SELECT id, ROW_NUMBER() OVER (PARTITION BY batch_id, phone_key ORDER BY ' . $keepBestOrder . ') AS rn'
			. "  FROM smsdelivery_new WHERE phone_key <> ''"
			. ' ) ranked WHERE rn > 1'
			. ')'
		);

		// --- Refine: resolve personid from the recipient phone number ---
		$db->exec(
			'UPDATE smsdelivery_new d JOIN ('
			. ' SELECT ' . $phoneKeySql('mobile_tel') . ' AS phone_key, MIN(id) AS personid'
			. ' FROM _person GROUP BY phone_key'
			. ') p ON p.phone_key = d.phone_key'
			. " SET d.personid = p.personid WHERE d.phone_key <> ''"
		);

		// --- Match batches against existing sms rows ---
		// 1. By provider message ID: a batch with any delivery already logged
		//    (same provider, same remote_id) belongs to that delivery's message.
		$db->exec(
			'UPDATE sms_new b SET b.sms_id = ('
			. ' SELECT sd.sms_id FROM smsdelivery_new d'
			. ' JOIN _smsdelivery sd ON sd.remote_id = d.remote_id COLLATE utf8mb4_unicode_ci AND sd.provider = ' . $db->quote($providerKey)
			. " WHERE d.batch_id = b.id AND d.remote_id <> '' LIMIT 1"
			. ')'
		);
		// 2. Otherwise by the same body within ±10 minutes of the *send* time
		//    (scheduled time, else creation): existing rows may predate
		//    remote_id logging, and a scheduled send is created long before it
		//    is sent, which the provider reports.
		$db->exec(
			'UPDATE sms_new b SET b.sms_id = ('
			. ' SELECT s.id FROM sms s'
			. ' WHERE s.body = b.body COLLATE utf8mb4_unicode_ci'
			. '  AND COALESCE(s.scheduled_send_at, s.created) BETWEEN b.created - INTERVAL 10 MINUTE AND b.created + INTERVAL 10 MINUTE'
			. ' ORDER BY ABS(TIMESTAMPDIFF(SECOND, COALESCE(s.scheduled_send_at, s.created), b.created)) LIMIT 1'
			. ') WHERE b.sms_id IS NULL'
		);
		$db->exec('UPDATE smsdelivery_new d JOIN sms_new b ON b.id = d.batch_id SET d.sms_id = b.sms_id');

		// --- Delete staged deliveries already present under their matched sms
		// row: by remote_id when both sides have one …
		$deleted = (int)$db->exec(
			'DELETE d FROM smsdelivery_new d'
			. ' JOIN _smsdelivery sd ON sd.sms_id = d.sms_id AND sd.remote_id = d.remote_id COLLATE utf8mb4_unicode_ci'
			. " WHERE d.remote_id <> ''"
		);
		// … otherwise by recipient phone (via personid or the raw_response
		// destination).
		$deleted += (int)$db->exec(
			'DELETE d FROM smsdelivery_new d'
			. ' JOIN _smsdelivery sd ON sd.sms_id = d.sms_id'
			. ' LEFT JOIN _person p ON p.id = sd.personid'
			. " WHERE d.phone_key <> '' AND d.phone_key IN ("
			. $phoneKeySql('p.mobile_tel') . ', '
			. $phoneKeySql("CASE WHEN JSON_VALID(sd.raw_response) THEN JSON_UNQUOTE(JSON_EXTRACT(sd.raw_response, '\$.destination')) ELSE NULL END")
			. ')'
		);

		// --- Counts and final inserts ---
		$skipped = (int)$db->queryOne(
			'SELECT COUNT(*) FROM sms_new b'
			. ' WHERE b.sms_id IS NOT NULL'
			. ' AND NOT EXISTS (SELECT 1 FROM smsdelivery_new d WHERE d.batch_id = b.id)'
		);

		// Create sms rows for genuinely new batches that still have deliveries
		$newBatches = $db->queryAll(
			'SELECT b.id, b.body, b.created FROM sms_new b'
			. ' WHERE b.sms_id IS NULL'
			. ' AND EXISTS (SELECT 1 FROM smsdelivery_new d WHERE d.batch_id = b.id)'
		);
		foreach ($newBatches as $batch) {
			$db->exec(
				'INSERT INTO sms (body, created, scheduled_send_at) VALUES ('
				. $db->quote((string)$batch['body']) . ', '
				. $db->quote((string)$batch['created']) . ', NULL)'
			);
			$db->exec(
				'UPDATE sms_new SET sms_id = ' . (int)$db->queryOne('SELECT LAST_INSERT_ID()')
				. ' WHERE id = ' . (int)$batch['id']
			);
		}
		$batches = count($newBatches);
		$db->exec('UPDATE smsdelivery_new d JOIN sms_new b ON b.id = d.batch_id SET d.sms_id = b.sms_id');

		$imported = (int)$db->exec(
			'INSERT INTO _smsdelivery (sms_id, personid, remote_id, raw_response, body, provider, delivered_at, status, segments_used, per_segment_cost)'
			. " SELECT d.sms_id, d.personid, NULLIF(d.remote_id, ''), d.raw_response, d.body, "
			. $db->quote($providerKey) . ', d.delivered_at, d.status, d.segments_used, d.per_segment_cost'
			. ' FROM smsdelivery_new d WHERE d.sms_id IS NOT NULL'
		);

		if ($ownTransaction) {
			$db->commit();
		}
	} catch (\Throwable $e) {
		if ($ownTransaction) {
			$db->rollBack();
		}
		throw $e;
	} finally {
		$db->exec('DROP TABLE IF EXISTS smsdelivery_new, sms_new');
		$db->queryOne("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '.sms_sync'))");
	}

	return [
		'deleted' => $deleted,
		'imported' => $imported,
		'batches' => $batches,
		'skipped' => $skipped,
	];
}


// ---------------------------------------------------------------------------
// Opt-out helpers
// ---------------------------------------------------------------------------

/**
 * Get the set of opted-out phone numbers from the upstream provider.
 *
 * Normalises all numbers to international format (e.g. "61491570157").
 * Memoised for the request — failures included, so a list page doesn't call
 * an unreachable gateway once per mobile number — and session-cached across
 * requests by the provider (failures briefly, by FailureCachingSmsProvider).
 *
 * @return string[]  International-format phone numbers (no "+" prefix)
 */
function getOptedOutPhoneNumbers(): array
{
	static $numbers = null;
	if ($numbers !== null) {
		return $numbers;
	}
	$numbers = [];

	// Installs that switched SMS off (but kept their keys) never ask upstream.
	if (!isFeatureEnabled()) {
		return $numbers;
	}

	$providerResult = getSmsProvider(logToDb: false);
	if ($providerResult->isFailure()) {
		return $numbers;
	}

	$provider = $providerResult->getValue();
	if (!$provider->hasCapability(\Sms\SmsCapability::LIST_OPT_OUTS)) {
		return $numbers;
	}

	$result = $provider->listOptOuts();
	if ($result->isFailure()) {
		return $numbers;
	}

	foreach ($result->getValue() as $entry) {
		$numbers[] = $entry->number->value;
	}
	return $numbers;
}

/**
 * Check whether a person's mobile number has opted out upstream.
 *
 * Fails open — returns false when the provider is unreachable,
 * doesn't support opt-out listing, or the person has no mobile.
 *
 * @param \Person $person
 * @return bool
 */
function isPersonOptedOut(\Person $person): bool
{
	$mobile = (string) $person->getValue('mobile_tel');
	if (!preg_match('/\d/', $mobile)) {
		return false;
	}

	return phoneNumberInList(new \Sms\PhoneNumber($mobile), getOptedOutPhoneNumbers());
}

/**
 * Whether $number is the same phone as any of $numbers (digit strings), in
 * either local or international form. See \Sms\PhoneNumber::sameNumberAs().
 *
 * @param string[] $numbers
 */
function phoneNumberInList(\Sms\PhoneNumber $number, array $numbers): bool
{
	foreach ($numbers as $candidate) {
		if (preg_match('/\d/', (string) $candidate) && $number->sameNumberAs(new \Sms\PhoneNumber((string) $candidate))) {
			return true;
		}
	}
	return false;
}



// ---------------------------------------------------------------------------
// Result formatting and post-send logging
// ---------------------------------------------------------------------------

/**
 * Format a send result into a human-readable summary string.
 *
 * Useful for scripts that need to report what happened (e.g. roster reminders).
 *
 * @param \Sms\SendSummary $sendResult The result from send()
 * @param array<int, string> $nameLookup Person ID => "First Last" map (may be empty)
 * @param string $contextLabel e.g. "roster" or "notification"
 * @return string Human-readable summary
 */
function formatSendSummary(\Sms\SendSummary $sendResult, array $nameLookup, string $contextLabel = 'SMS'): string
{
	if ($sendResult instanceof \Sms\Failed) {
		return "Unable to send {$contextLabel}\n\n".$sendResult->error."\n";
	}

	$nameOf = fn(JethroSmsRecipient $r) => $nameLookup[$r->personId] ?? 'Person #'.$r->personId;

	if ($sendResult instanceof \Sms\AllSent) {
		$out = "Sent {$contextLabel} successfully to:\n";
		return $out.implode(', ', array_map($nameOf, $sendResult->recipients)).".\n\n";
	}

	if ($sendResult instanceof \Sms\PartialSuccess) {
		$out = "Sent {$contextLabel} successfully to:\n";
		$out .= implode(', ', array_map($nameOf, $sendResult->successes)).".\n\n";
		$out .= "Failed to send {$contextLabel} to:\n";
		$out .= implode(', ', array_map($nameOf, $sendResult->failures)).".\n\n";
		return $out;
	}

	return "Unknown send result for {$contextLabel}.\n";
}

/**
 * Present a Call_SMS send result for the page that sent it: the alerts shown
 * under the Send button, the classes to add to recipients' list rows, and
 * whether the send modal should stay open.
 *
 * The wording is what users see after every send (and what the functional
 * tests check). One recipient in a category is named in the sentence; several
 * are counted, then listed by name.
 *
 * Row classes: 'sms-success' for those sent to, 'sms-failure' for everyone not
 * sent to (no mobile, archived, opted out, failed), and 'note-success' for
 * those sent to when a note was saved (styles in resources/less/jethro.less.php).
 *
 * @param array{
 *     error?: string,
 *     sent?: array{count: int, recipients: array<int|string, array<string, mixed>>, scheduled?: bool},
 *     failed?: array{count: int, recipients: array<int|string, array<string, mixed>>},
 *     failed_blank?: array{count: int, recipients: array<int|string, array<string, mixed>>},
 *     failed_archived?: array{count: int, recipients: array<int|string, array<string, mixed>>},
 *     failed_opted_out?: array{count: int, recipients: array<int|string, array<string, mixed>>},
 *     note_saved?: bool,
 * } $result  Call_SMS's result; each recipients map is person ID => person row (first_name, last_name)
 * @param bool $canViewHistory  Whether to link the success alert to Persons → Messages (PERM_VIEWSMS)
 * @return array{error: ?string, html: string, rowClasses: array<int, list<string>>, keepOpen: bool}
 *     error: shown on its own instead of html; html: the alert divs; keepOpen: FALSE only when exactly
 *     one person was sent to and nobody was left out
 * @see calls/call_sms.class.php
 * @see docs/docs/developer/sms/send-pipeline.mdx
 */
function presentSendResult(array $result, bool $canViewHistory): array
{
	if (isset($result['error'])) {
		return ['error' => (string) $result['error'], 'html' => '', 'rowClasses' => [], 'keepOpen' => true];
	}

	$nameOf = static fn(array $p): string => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
	$alert = static function (string $class, string $html, array $recipients, int $count) use ($nameOf): string {
		if ($count > 1) {
			$html .= '<p>' . implode(', ', array_map(static fn($p) => ents($nameOf($p)), $recipients)) . '</p>';
		}
		return '<div class="alert' . ($class !== '' ? ' ' . $class : '') . '">' . $html . '</div>';
	};
	$rowClasses = [];
	$mark = static function (array $recipients, string $class) use (&$rowClasses): void {
		foreach (array_keys($recipients) as $pid) {
			$rowClasses[(int) $pid][] = $class;
		}
	};
	$count = static fn(string $key): int => (int) ($result[$key]['count'] ?? 0);
	$recipients = static fn(string $key): array => $result[$key]['recipients'] ?? [];
	$first = static fn(string $key): string => $nameOf((array) (array_values($recipients($key))[0] ?? []));

	$html = '';
	$sent = $count('sent');
	if ($sent > 0) {
		$verb = !empty($result['sent']['scheduled']) ? 'scheduled for sending to' : 'successfully sent to';
		$text = 'Message ' . $verb . ' ' . ($sent === 1 ? ents($first('sent')) : $sent . ' recipients');
		if ($canViewHistory) {
			$text .= ' <a href="?view=persons__messages">(SMS History Log)</a>';
		}
		$html .= $alert('alert-success', $text, $recipients('sent'), $sent);
		$mark($recipients('sent'), 'sms-success');
		if (!empty($result['note_saved'])) {
			$mark($recipients('sent'), 'note-success');
		}
	}

	$leftOut = [
		'failed_blank' => ['', 'was not sent the message because they have no mobile number',
			'%d recipients were not sent the message because they have no mobile number'],
		'failed_archived' => ['', 'was not sent the message because they are archived',
			'%d archived persons were not sent the message'],
		'failed_opted_out' => ['', 'has opted out of receiving SMS',
			'%d recipients have opted out of receiving SMS'],
	];
	foreach ($leftOut as $key => [$class, $one, $many]) {
		$n = $count($key);
		if ($n === 0) continue;
		$text = $n === 1 ? ents($first($key)) . ' ' . $one : sprintf($many, $n);
		$html .= $alert($class, $text, $recipients($key), $n);
		$mark($recipients($key), 'sms-failure');
	}

	$failed = $count('failed');
	if ($failed > 0) {
		$text = 'SMS sending failed for ' . ($failed === 1 ? ents($first('failed')) : $failed . ' recipients');
		$html .= $alert('alert-error', $text, $recipients('failed'), $failed);
		$mark($recipients('failed'), 'sms-failure');
	}

	$keepOpen = $sent !== 1 || $failed > 0
		|| $count('failed_blank') > 0 || $count('failed_archived') > 0 || $count('failed_opted_out') > 0;

	return ['error' => null, 'html' => $html, 'rowClasses' => $rowClasses, 'keepOpen' => $keepOpen];
}

/**
 * Explain, before sending, which recipients a send would leave out: those with
 * no mobile number and archived persons, whom getRecipientsFromRequest()
 * filters out. Shown in the statusline (Call_SMS_Statusline), so the sender
 * learns this while composing rather than only after Send.
 *
 * Examples:
 *   (0, 5, 0) "No SMS can be sent: none of the 5 recipients has a mobile number."
 *   (0, 1, 0) "No SMS can be sent: the recipient has no mobile number."
 *   (0, 4, 1) "No SMS can be sent: of the 5 recipients, 4 have no mobile number and 1 is archived."
 *   (1, 1, 0) "1 of the 2 recipients will not be sent this: 1 has no mobile number."
 *
 * @param int $sendable  Recipients who will be sent the message
 * @param int $noMobile  Recipients left out for having no mobile number
 * @param int $archived  Recipients left out for being archived
 * @return array{text: string, blocking: bool}|null  NULL when nobody is left out;
 *     'blocking' when nobody can be sent the message at all
 */
function leftOutNotice(int $sendable, int $noMobile, int $archived): ?array
{
	$leftOut = $noMobile + $archived;
	if ($leftOut === 0) {
		return null;
	}
	$total = $sendable + $leftOut;
	$reasons = [];
	if ($noMobile > 0) {
		$reasons[] = $noMobile . ($noMobile === 1 ? ' has' : ' have') . ' no mobile number';
	}
	if ($archived > 0) {
		$reasons[] = $archived . ($archived === 1 ? ' is' : ' are') . ' archived';
	}

	if ($sendable > 0) {
		return [
			'text' => $leftOut . ' of the ' . $total . ' recipients will not be sent this: ' . implode(' and ', $reasons) . '.',
			'blocking' => false,
		];
	}
	if ($total === 1) {
		$why = $noMobile === 1 ? 'has no mobile number' : 'is archived';
		$text = 'No SMS can be sent: the recipient ' . $why . '.';
	} elseif ($archived === 0) {
		$text = 'No SMS can be sent: none of the ' . $total . ' recipients has a mobile number.';
	} elseif ($noMobile === 0) {
		$text = 'No SMS can be sent: all ' . $total . ' recipients are archived.';
	} else {
		$text = 'No SMS can be sent: of the ' . $total . ' recipients, ' . implode(' and ', $reasons) . '.';
	}
	return ['text' => $text, 'blocking' => true];
}

/**
 * How long after its send time a delivery that hasn't reached a final status
 * (\Sms\SmsStatus::isFinal()) keeps being polled: 24 hours.  Carriers that
 * never return a delivery receipt would otherwise leave a 'sent' message
 * polling forever.  Also the window Call_SMS_Info asks the provider's
 * delivery list for.
 *
 * @see smsDeliveryPollIntervalSecs()
 * @see docs/docs/developer/explanation/sms/delivery-polling.mdx
 */
const STATUS_POLL_WINDOW_SECS = 86400;

/**
 * Render the delivery status indicator for an SMS delivery (per-person
 * Messages tab).
 *
 * Returns an HTML <span> with tick(s) or status text — no surrounding markup.
 *
 *   ✓✓  = delivered (with delivery timestamp tooltip)
 *   ✓   = sent (gateway accepted, not yet delivered)
 *   text = other final status (failed, cancelled)
 *   icon + ref = other non-final status (scheduled with a countdown, queued, …)
 *
 * Any delivery not yet final — 'sent' included — carries Datastar polling
 * (?call=sms_info) while the provider matches and it is within
 * STATUS_POLL_WINDOW_SECS of its send time; past its send time it also fetches
 * once on render.  Outside that it renders statically.
 *
 * @param string $status              MySQL status value (e.g. 'delivered', 'sent')
 * @param int|null $deliveredAt       Unix timestamp of delivery, for tooltip
 * @param int|null $deliveryId        smsdelivery.id, for the polling span
 * @param string|null $remoteId       Remote message ID from the gateway
 * @param string|null $providerKey    Provider that sent this delivery
 * @param string|null $currentProviderKey  Currently configured provider (null = no polling possible)
 * @param string|null $scheduledAt  MySQL datetime of a scheduled send, for the title and countdown
 * @param string|null $sentAt       MySQL datetime the message is or was sent (scheduled_send_at, else sms.created); drives polling — null = don't poll
 * @return string  HTML <span> element
 */
function renderSmsDeliveryStatusIndicator(
    string $status,
    ?int $deliveredAt = null,
    ?int $deliveryId = null,
    ?string $remoteId = null,
    ?string $providerKey = null,
    ?string $currentProviderKey = null,
    ?string $scheduledAt = null,
    ?string $sentAt = null,
): string
{
	$smsStatus = \Sms\SmsStatus::fromMySql($status);
    $icon      = SmsStatusIcon::fromStatus($smsStatus)->icon();
    $ref       = ($remoteId !== null && $remoteId !== '') ? ents($remoteId) : '';

    $outerAttrs  = [];
    $statusAttrs = ['class' => 'message-status', 'data-message-attribution' => $status];

    if ($deliveryId !== null) {
        $outerAttrs['id'] = 'sms-delivery-status-' . $deliveryId;
    }

    if ($smsStatus === \Sms\SmsStatus::DELIVERED) {
        $statusAttrs['title'] = $deliveredAt !== null && $deliveredAt > 0
            ? 'Delivery timestamp: ' . date('j M Y g:ia', $deliveredAt)
            : 'delivered';
        $content = $icon;
    } elseif ($smsStatus === \Sms\SmsStatus::SENT) {
        $statusAttrs['title'] = 'sent';
        $content = $icon;
    } elseif ($smsStatus->isFinal()) {
        $statusAttrs['title'] = $status;
        $content = $icon !== '' ? $icon . ' ' . $status : $status;
    } else {
        $statusAttrs['title'] = $status . ($scheduledAt !== null ? ': ' . format_datetime($scheduledAt) : '');
        $content = trim($icon . ' ' . $ref);
        if ($smsStatus === \Sms\SmsStatus::SCHEDULED && $scheduledAt !== null) {
            $seconds = strtotime($scheduledAt) - time();
            $content .= ' ' . ($seconds > 0 ? 'in ' . _formatDuration($seconds) : format_datetime($scheduledAt));
        }
    }

    // Thundering-herd gate in call_sms_info makes per-delivery jitter
    // unnecessary (batch pages); here every delivery polls itself.
    $pollable = $currentProviderKey !== null && $providerKey === $currentProviderKey && $deliveryId !== null;
    $pollSecs = $pollable && !$smsStatus->isFinal() ? smsDeliveryPollIntervalSecs($sentAt) : null;
    if ($pollSecs !== null) {
        $get = "@get('?call=sms_info&id={$deliveryId}')";
        $outerAttrs["data-on-interval__duration.{$pollSecs}s"] = $get;
        // Past its send time the provider may already have moved on (e.g.
        // scheduled → sent → delivered): fetch once now, not just on the timer.
        if (strtotime((string) $sentAt) <= time()) {
            $outerAttrs['data-init'] = $get;
        }
    }

    $outerAttrStr = '';
    foreach ($outerAttrs as $name => $value) {
        $outerAttrStr .= ' ' . $name . '="' . ents($value) . '"';
    }
    $statusAttrStr = '';
    foreach ($statusAttrs as $name => $value) {
        $statusAttrStr .= ' ' . $name . '="' . ents($value) . '"';
    }
    return '<span' . $outerAttrStr . '><span' . $statusAttrStr . '>' . $content . '</span></span>';
}

/**
 * Datastar poll interval for a delivery that hasn't reached a final status.
 *
 * Counted from the send time: approaching it the interval shrinks (a tenth
 * of the time remaining, floor 2s, cap 300s); once it passes it grows again
 * at the same rate, so deliveries the provider is slow to resolve back off
 * instead of hammering ?call=sms_info at the 2s floor.  STATUS_POLL_WINDOW_SECS
 * after the send time polling stops altogether.
 *
 * See docs/docs/developer/sms/SMS_DATASTAR.md.
 *
 * @param string|null $sentAt MySQL datetime the message is or was sent (scheduled_send_at, else sms.created); null = unknown, don't poll
 * @return int|null Seconds between polls (2–300), or null to stop polling
 */
function smsDeliveryPollIntervalSecs(?string $sentAt): ?int
{
    if ($sentAt === null) {
        return null;
    }
    $remaining = (int)(strtotime($sentAt) - time());
    if ($remaining < -STATUS_POLL_WINDOW_SECS) {
        return null;
    }
    return max(2, min(300, (int)ceil(abs($remaining) / 10)));
}

/**
 * Render a delivery-status icon with title rollover (admin Messages page).
 *
 * Always returns a <span> with the status icon and a descriptive title.
 * Like renderSmsDeliveryStatusIndicator(), any delivery not yet final polls
 * `?call=sms_info&id=N` (plus `&batch=1` when $batch is true) while the
 * provider matches and it is within STATUS_POLL_WINDOW_SECS of its send time,
 * fetching once on render when past its send time.
 *
 * @param string $status       MySQL status value (e.g. 'delivered', 'scheduled')
 * @param int|null $deliveredAt   Unix timestamp of delivery, for the title tooltip
 * @param string|null $scheduledAt  MySQL datetime of a scheduled send, for the title and countdown
 * @param int|null $deliveryId    When provided, stamps id="sms-delivery-status-N" for Datastar morph
 * @param string|null $providerKey  Provider that sent this delivery
 * @param string|null $currentProviderKey  Currently configured provider key
 * @param string $remoteId     Provider-side message ID shown next to the icon
 * @param bool $batch          True when every sibling of this delivery is also
 *                             rendered on the page (admin Messages view), so the
 *                             poll may morph the whole batch by id.
 * @param string|null $sentAt  MySQL datetime the message is or was sent (scheduled_send_at, else sms.created); drives polling — null = don't poll
 * @return string  HTML <span> element
 */
function renderSmsDeliveryStatusIcon(
    string $status,
    ?int $deliveredAt = null,
    ?string $scheduledAt = null,
    ?int $deliveryId = null,
    ?string $providerKey = null,
    ?string $currentProviderKey = null,
    string $remoteId = '',
    bool $batch = false,
    ?string $sentAt = null,
): string
{
    $smsStatus = \Sms\SmsStatus::fromMySql($status);
    $icon      = SmsStatusIcon::fromStatus($smsStatus)->icon();

    $title = $status;
    if ($smsStatus === \Sms\SmsStatus::DELIVERED && $deliveredAt !== null && $deliveredAt > 0) {
        $title = 'Delivered: ' . date('j M Y g:ia', $deliveredAt);
    } elseif ($scheduledAt !== null) {
        $title .= ': ' . format_datetime($scheduledAt);
    }

    $content = $icon;
    if ($smsStatus === \Sms\SmsStatus::SCHEDULED && $scheduledAt !== null) {
        $seconds = strtotime($scheduledAt) - time();
        $content = trim($icon . ' ' . ents($remoteId));
        $content .= ' ' . ($seconds > 0 ? 'in ' . _formatDuration($seconds, short: true) : format_datetime($scheduledAt));
    }

    // Thundering-herd gate in call_sms_info handles coalescing.
    $outerAttrs = '';
    $pollable = $deliveryId !== null && $providerKey !== null && $currentProviderKey === $providerKey;
    $pollSecs = $pollable && !$smsStatus->isFinal() ? smsDeliveryPollIntervalSecs($sentAt) : null;
    if ($pollSecs !== null) {
        $get = '@get(\'?call=sms_info&amp;id=' . $deliveryId . ($batch ? '&amp;batch=1' : '') . '\')';
        $outerAttrs = ' data-on-interval__duration.' . $pollSecs . 's="' . $get . '"';
        if (strtotime((string) $sentAt) <= time()) {
            $outerAttrs .= ' data-init="' . $get . '"';
        }
    }

    $idAttr = $deliveryId !== null ? ' id="sms-delivery-status-' . $deliveryId . '"' : '';
    return '<span class="message-attribution"' . $idAttr . $outerAttrs . '><span class="message-status" title="' . ents($title) . '">' . $content . '</span></span>';
}

/**
 * Format a duration in seconds as a human-readable string.
 *
 * Resolution depends on magnitude:
 *   - < 1 day    → shows minutes: "1 hour 12 minutes"
 *   - < 7 days   → rounds to nearest hour: "23 hours"
 *   - ≥ 7 days   → rounds to nearest day: "8 days"
 *
 * @return string e.g. "45 minutes", "2 hours 30 minutes", "23 hours", "8 days"
 */
function _formatDuration(int $seconds, bool $short = false): string
{
    $perDay    = 86400;
    $perHour   = 3600;
    $perMinute = 60;

    if ($short) {
        if ($seconds >= 7 * $perDay) {
            $n = (int)round($seconds / $perDay);
            return $n . 'd';
        }
        if ($seconds >= $perDay) {
            $n = (int)round($seconds / $perHour);
            return $n . 'h';
        }
        // < 1 day: hours + minutes, short
        $hours   = intdiv($seconds, $perHour);
        $rem     = $seconds % $perHour;
        $minutes = intdiv($rem, $perMinute);
        $secs    = $rem % $perMinute;

        // Show seconds when under 2 minutes — same as the long format
        if ($hours === 0 && $minutes < 2) {
            $totalSecs = $minutes * 60 + $secs;
            if ($totalSecs < 60) {
                return $totalSecs . 's';
            }
            $parts = [];
            if ($minutes > 0) $parts[] = $minutes . 'm';
            if ($secs > 0)    $parts[] = $secs . 's';
            return implode(' ', $parts);
        }

        if ($secs >= 30) {
            $minutes++;
            if ($minutes === 60) { $hours++; $minutes = 0; }
        }
    }

    if ($seconds >= 7 * $perDay) {
        $n = (int)round($seconds / $perDay);
        return $n === 1 ? _('1 day') : sprintf(_('%d days'), $n);
    }

    if ($seconds >= $perDay) {
        $n = (int)round($seconds / $perHour);
        return $n === 1 ? _('1 hour') : sprintf(_('%d hours'), $n);
    }

    // < 1 day: show minutes resolution using integer arithmetic
    $hours   = intdiv($seconds, $perHour);
    $rem     = $seconds % $perHour;
    $minutes = intdiv($rem, $perMinute);
    $secs    = $rem % $perMinute;

    // Sub-minute resolution when the countdown is under 2 minutes —
    // matters for progressive N/10 polling which goes down to 2s intervals.
    if ($hours === 0 && $minutes < 2) {
        $totalSecs = $minutes * 60 + $secs;
        if ($totalSecs < 60) {
            return $totalSecs === 1 ? _('1 second') : sprintf(_('%d seconds'), $totalSecs);
        }
        $parts = [];
        if ($minutes > 0) {
            $parts[] = $minutes === 1 ? _('1 minute') : sprintf(_('%d minutes'), $minutes);
        }
        if ($secs > 0) {
            $parts[] = $secs === 1 ? _('1 second') : sprintf(_('%d seconds'), $secs);
        }
        return implode(' ', $parts);
    }

    if ($secs >= 30) {
        $minutes++;
        if ($minutes === 60) {
            $hours++;
            $minutes = 0;
        }
    }

    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours === 1 ? _('1 hour') : sprintf(_('%d hours'), $hours);
    }
    if ($minutes > 0 || $parts === []) {
        $parts[] = $minutes === 1 ? _('1 minute') : sprintf(_('%d minutes'), $minutes);
    }
    return implode(' ', $parts);
}


/**
 * Render a RegistrationStep as an HTML snippet.
 *
 * Dispatches on {@see RegistrationStep::isComplete()}:
 *   - **Not complete** (fields !== []): renders an input form from the field
 *     schema — the admin fills this out and submits it to advance the state
 *     machine (formerly {@see Call_Admin_Statuspanel_Operation::buildFormHtml()}).
 *   - **Complete** (fields === []): renders the summary — message, instructions,
 *     contact link, and a table of submitted values.
 *
 * The CLI renders the same structure as text via
 * {@see renderRegistrationStepText()} in scripts/sms.php.
 *
 * @param string|null $method  Operation method name for the form element IDs
 *                             and data-operation attribute (e.g. 'registerSenderId').
 *                             Only used when rendering a form.
 */
function renderRegistrationStepHtml(\Sms\RegistrationStep $step, ?string $method = null, string $submitPostUrl = ''): string
{
	$html = '';

	// Message and instructions display in every state — Phase 1 schemas
	// often carry guidance text alongside the fields. Both are plain text,
	// partly from the provider's API responses, so they are escaped.
	if ($step->message !== '') {
		$cls = $step->registered ? ' text-success' : '';
		$html .= '<p class="reg-result-message' . $cls . '"><strong>' . nl2br(ents($step->message)) . '</strong></p>';
	}

	if ($step->instructions !== '') {
		$html .= '<p>' . nl2br(ents($step->instructions)) . '</p>';
	}

	if (!$step->isComplete()) {
		$html .= _renderRegistrationFormHtml($step->fields, $method ?? '', $submitPostUrl);
		return $html;
	}

	if ($step->contact !== '') {
		if (str_starts_with($step->contact, 'mailto:')) {
			$qPos = strpos($step->contact, '?');
			$display = $qPos !== false ? substr($step->contact, 7, $qPos - 7) : substr($step->contact, 7);
			$html .= '<p><a href="' . ents($step->contact) . '">' . ents($display) . '</a></p>';
		} else {
			$html .= '<p><a href="mailto:' . ents($step->contact) . '">' . ents($step->contact) . '</a></p>';
		}
	}

	if ($step->form !== []) {
		$html .= '<table style="border-collapse:collapse">';
		foreach ($step->form as $row) {
			$html .= '<tr>'
				. '<td style="padding:4px 8px;font-weight:bold;vertical-align:top">' . ents((string) ($row['label'] ?? '')) . ':</td>'
				. '<td style="padding:4px 8px">' . ents((string) ($row['value'] ?? '')) . '</td>'
				. '</tr>';
		}
		$html .= '</table>';
	}

	return $html;
}

/**
 * Render field schema as an input form.
 *
 * When $submitPostUrl is provided (statuspanel Datastar context), the wrapper
 * is a <form onsubmit="return false"> and the submit button carries a Datastar
 * @post attribute.  The status-panel-op-result div is omitted — the whole
 * container is morphed by Datastar on POST.
 *
 * When $submitPostUrl is empty (legacy / sendernum context), the original
 * <div> wrapper and type="submit" button are used unchanged.
 *
 * @param \Sms\FormField[] $fields
 * @internal  Called by {@see renderRegistrationStepHtml()} when the step is not complete.
 */
function _renderRegistrationFormHtml(array $fields, string $method, string $submitPostUrl = ''): string
{
	$datastar = $submitPostUrl !== '';
	$tag      = $datastar ? 'form' : 'div';
	$extra    = $datastar ? ' onsubmit="return false"' : '';

	$html  = '<' . $tag . $extra . ' class="status-panel-op-form form-horizontal" data-operation="' . ents($method) . '">';
	$html .= '<input type="hidden" name="operation" value="' . ents($method) . '">';

	foreach ($fields as $f) {
		$name     = $f->name;
		$label    = $f->label;
		$type     = $f->type;
		$required = $f->required ? ' required' : '';
		$id       = 'spop_' . $method . '_' . $name;

		$html .= '<div class="control-group" id="' . ents($id) . '">';

		if ($type === 'checkbox') {
			$html .= '<div class="controls">'
				. '<label class="checkbox">'
				. '<input type="checkbox" name="' . ents($name) . '" id="' . ents($id) . '" value="1"' . $required . '>'
				. ents($label)
				. '</label>';
		} else {
			$html .= '<label class="control-label" for="' . ents($id) . '">' . ents($label) . '</label>';
			$html .= '<div class="controls">';

			if ($type === 'select' && $f->options !== null) {
				$html .= '<select name="' . ents($name) . '" id="' . ents($id) . '"' . $required . '>';
				foreach ($f->options as $opt) {
					$html .= '<option value="' . ents($opt) . '">' . ents($opt) . '</option>';
				}
				$html .= '</select>';
			} else {
				$inputType = match ($type) {
					'email' => 'email',
					'tel'   => 'tel',
					'url'   => 'url',
					default => 'text',
				};
				$html .= '<input type="' . $inputType . '" name="' . ents($name) . '" id="' . ents($id) . '" value="" class="" size="60"' . $required . '>';
			}
		}

		if ($f->description !== null && $f->description !== '') {
			$html .= '<p class="help-inline">' . ents($f->description) . '</p>';
		}

		$html .= '</div>'; // .controls or .control-group for checkbox
		$html .= '</div>'; // .control-group
	}

	$html .= '<div class="control-group"><div class="controls">';
	if ($datastar) {
		$escapedUrl = ents($submitPostUrl);
		$html .= '<button type="button" class="btn"'
			. " data-on:click=\"@post('{$escapedUrl}', {contentType: 'form'})\">Submit";
	} else {
		$html .= '<button type="submit" class="btn">Submit';
	}
	$testMode = filter_var(ifdef('SMS_TESTMODE', false), FILTER_VALIDATE_BOOLEAN);
	if ($testMode) $html .= ' (test mode - not really sent)';
	$html .= '</button>';
	$html .= '</div></div>';

	if (!$datastar) {
		$html .= ' <div class="status-panel-op-result"></div>';
	}

	$html .= '</' . $tag . '>';

	return $html;
}


/**
 * Print the SMS send modal dialog.
 * This is reused in the person view, person list view, roster 'SMS all assignees' and runsheet personnel sections.
 *
 * Send posts #sms-modal-form to ?call=sms through Datastar; Call_SMS answers
 * with SSE that fills #sms-send-response / #sms-results (see Call_SMS::_respondDatastar()).
 * The modal-open handler in resources/js/jethro-sms.js fills in the recipients.
 */
function printSmsModal(): void
{
	$noteType = ($_REQUEST['view'] ?? '') === 'families' ? 'family' : 'person';
	$post = "@post('?call=sms', {contentType: 'form', selector: '#sms-modal-form'})";
	?>
	<div id="send-sms-modal" class="modal sms-modal hide" role="dialog" aria-hidden="true">
		<div class="modal-header">
			<h4>Send SMS to <span class="sms_recipients"></span></h4>
		</div>
		<form id="sms-modal-form" class="modal-body form-horizontal" onsubmit="return false">
			<?php // personid is filled in by the modal-open handler in jethro-sms.js. ?>
			<input type="hidden" name="personid" value="" />
			<input type="hidden" name="sms_type" value="person" />
			<input type="hidden" name="note_type" value="<?php echo ents($noteType); ?>" />
			<input type="hidden" name="sms_form" value="modal" />
			<?php _printSmsFormFields(); ?>
		</form>
		<div class="modal-footer">
			<div class="results" id="sms-results"></div>
			<button type="button" class="btn sms-submit" accesskey="s"
			        data-on:click="<?php echo ents($post); ?>"
			        data-indicator:smssending
			        data-attr:disabled="$smsSendBlocked || $smssending"
			        data-text="$smssending ? 'Sending...' : 'Send'">Send</button>
			<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
			<div class="alert alert-error sms-disabled-reason" data-text="$smsBlockReason" data-show="$smsSendBlocked" style="display:none"></div>
		</div>
	</div>
	<?php
}

/**
 * Print the inline bulk-SMS form shown on list views.
 *
 * Send posts the enclosing bulk-action form (the ticked personid[] boxes
 * included) to ?call=sms through Datastar; Call_SMS answers with SSE that
 * fills #sms-send-response-bulk / #bulk-sms-results.
 *
 * @param string $noteType 'person' (default) or 'family' — controls
 *                         whether notes created via "Save as Note" are
 *                         person_notes or family_notes.
 */
function printBulkSmsForm(string $noteType = 'person'): void
{
	// With nobody ticked, ask whether to send to everyone listed, as the
	// other bulk actions do (JethroSMS.confirmBulkRecipients()).
	$send = "JethroSMS.confirmBulkRecipients(el) && @post('?call=sms', {contentType: 'form'})";
	?>
	<div class="bulk-action well form-horizontal" id="smshttp" data-signals:smstype="'person'">
		<input type="hidden" name="note_type" value="<?php echo ents($noteType); ?>" />
		<input type="hidden" name="sms_form" value="bulk" />
		<div class="control-group">
			<label class="control-label"><?php echo _('To:')?></label>
			<div class="controls">
				<label class="radio">
					<input class="compulsory" type="radio" name="sms_type" value="person" id="sms_type_person" checked="checked"
					       data-bind:smstype
					       data-on:change="@post('?call=sms_statusline', {contentType: 'form'})" />
					<?php echo _('the selected persons')?>
				</label>
				<label class="radio">
					<input type="radio" name="sms_type" value="family" id="sms_type_family"
					       data-bind:smstype
					       data-on:change="@post('?call=sms_statusline', {contentType: 'form'})" />
					<?php echo _('the adults in the selected persons\' families')?>
				</label>
			</div>
		</div>
		<?php _printSmsFormFields('-bulk'); ?>
		<div class="control-group">
			<div class="controls">
				<input type="button" class="btn bulk-sms-submit" value="Send"
				       data-on:click="<?php echo ents($send); ?>"
				       data-indicator:smssendingbulk
				       data-attr:disabled="$smsSendBlocked || $smssendingbulk"
				       data-attr:value="$smssendingbulk ? 'Sending...' : 'Send'" />
				<div class="help-block alert alert-error sms-disabled-reason" data-text="$smsBlockReason" data-show="$smsSendBlocked" style="display:none"></div>
			</div>
		</div>
		<div class="control-group" id="bulk-sms-results"></div>
	</div>
	<?php
}

/**
 * Print the Sender dropdown for SMS forms.
 */
function printSenderDropdown(string $idSuffix = ''): void
{
	?>
	<div class="control-group">
		<label class="control-label<?php if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) echo ' config-help'; ?>"<?php if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) echo ' title="Available sender options can be set in config — (SMS_SENDER, SMS_SENDER_OPTIONS, SMS_SENDER_DEFAULT) setting"'; ?>>Sender:</label>
		<div class="controls">
			<?php _printSenderDropdownControl($idSuffix); ?>
		</div>
	</div>
	<?php
}

/**
 * Print the "Create Note" checkbox for SMS forms.
 */
function printSaveAsNoteCheckbox(string $idSuffix = ''): void
{
	if (!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) {
		return;
	}
	$checked = ifdef('SMS_SAVE_TO_NOTE_BY_DEFAULT', false) ? 'checked="checked"' : '';
	$sig = 'saveasnote' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
	?>
	<label class="checkbox">
		<input type="checkbox" name="saveasnote" class="saveasnote" data-bind:<?php echo ents($sig); ?> <?php echo $checked; ?> />
		Create Note&hellip;
	</label>
	<?php
}

/**
 * Print the shared SMS form fields used by both the modal and the inline bulk form:
 * sender dropdown, message textarea, note fields, optional schedule toggle, and the
 * AJAX response container.
 *
 * @param string $idSuffix  Appended to element IDs/suffixes — '' for the modal, '-bulk' for the bulk form.
 */
function _printSmsFormFields(string $idSuffix = ''): void
{
	printSenderDropdown($idSuffix);
	?>
	<div class="control-group">
    	<label class="control-label">Message:</label>

		<div class="controls">
			<?php printTextbox($idSuffix); ?>
		</div>
	</div>
	<?php echo renderShortenUrlsToggle('sms-shorten-toggle' . $idSuffix, '', null); ?>
	<div class="control-group">
		<div class="controls">
			<?php
			printSaveAsNoteCheckbox($idSuffix);
			printNoteSubjectField($idSuffix);
			printNoteActionDateField($idSuffix);
			?>
		</div>
	</div>
	<?php
	$smsSchedResult = getSmsProvider();
	$schedSig = '';
	$maxAttr = '';
	if ($smsSchedResult->isSuccess()):
		$provider = $smsSchedResult->getValue();
		if ($provider->hasCapability(\Sms\SmsCapability::DEFERRED_SEND)):
			$idClean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
			$schedSig = 'schedulesend' . $idClean;
			$maxDelay = $provider->getDeferredSendMaxDelay();
			if ($maxDelay !== null) {
				$maxAttr = ' max="' . date('Y-m-d\TH:i', time() + $maxDelay) . '"';
			}
		endif;
	endif;
	if ($schedSig !== ''): ?>
	<div class="control-group">
		<div class="controls">
			<label class="checkbox">
				<input type="checkbox" class="sms-schedule-toggle" data-bind:<?php echo ents($schedSig); ?> /> <?php echo _('Schedule Send&hellip;');?>
			</label>
			<div class="sms-schedule-picker" data-show="$<?php echo ents($schedSig); ?>">
				<label><?php echo _('Send at:');?>
					<input type="datetime-local" name="send_at" class="sms-schedule-datetime"<?php echo $maxAttr; ?> data-on:change="@post('?call=sms_statusline', {contentType: 'form'})" />
				</label>
			</div>
		</div>
	</div>
	<?php endif; ?>
	<div id="sms-send-response<?php echo $idSuffix; ?>" class="alert alert-error" style="display:none"></div>
	<?php
}


/**
 * Print the Subject field for SMS-save-as-note.
 * Pre-filled with SMS_SAVE_TO_NOTE_SUBJECT, or 'SMS follow-up' if that is unset or empty.
 */
function printNoteSubjectField(string $idSuffix = ''): void
{
    if (!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) return;

	$GLOBALS['system']->includeDBClass('person_note');
	$note = new \Person_Note();
	$defaultSubject = (string) ifdef('SMS_SAVE_TO_NOTE_SUBJECT', '') ?: 'SMS follow-up';
	$sig = 'saveasnote' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
	?>
	<div class="control-group sms-note-field" data-show="$<?php echo ents($sig); ?>">
		<label class="control-label<?php if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) echo ' config-help'; ?>"<?php if ($GLOBALS['user_system']->havePerm(PERM_SYSADMIN)) echo ' title="Subject default can be set in config — SMS_SAVE_TO_NOTE_SUBJECT setting"'; ?>><?php echo ents($note->getFieldLabel('subject')); ?></label>
        <div class="controls">
			<?php print_widget('note_subject', $note->fields['subject'], $defaultSubject); ?>
		</div>
	</div>
	<?php
}

/**
 * Print the Action Date field for SMS-save-as-note.
 */
function printNoteActionDateField(string $idSuffix = ''): void
{
    if (!$GLOBALS['user_system']->havePerm(PERM_EDITNOTE)) return;

	$GLOBALS['system']->includeDBClass('person_note');
	$note = new \Person_Note();
	$defaultDate = date('Y-m-d');
	$sig = 'saveasnote' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
	?>
	<div class="control-group sms-note-field" data-show="$<?php echo ents($sig); ?>">
		<label class="control-label"><?php echo ents($note->getFieldLabel('action_date')); ?></label>
		<div class="controls">
			<?php print_widget('note_action_date', $note->fields['action_date'], $defaultDate); ?>
		</div>
	</div>
	<?php
}

/**
 * Print the SMS text input widget.
 *
 * The "Personalise with:" hint lists the %selected.*% tokens only on the bulk
 * form, and only while "the adults in the selected persons' families" is
 * chosen (the $smstype signal of printBulkSmsForm()'s sms_type radios): only
 * then do they differ from the plain tokens.
 *
 * @param string $idSuffix  '' for the modal, '-bulk' for the bulk form (see _printSmsFormFields())
 */
function printTextbox(string $idSuffix = ''): void
{
	$cfg = makeStatuslineConfig();
	$segmentCost = $cfg->segmentCost;

	// Datastar signal names cannot contain hyphens; derive a JS-safe suffix
	// so the bulk and modal composers have independent signals. The suffix
	// must stay lowercase: data-bind:<name> is an HTML attribute name, and
	// browsers always lowercase attribute names, so an embedded uppercase
	// letter (e.g. a camelCase 'Bulk') would never match the '$sig'-derived
	// signal name used in data-text="$sig..." below — the textarea's value
	// would silently never reach the signal. Keep both sides lowercase.
	//   bulk  ('-bulk') -> $smsmessagebulk
	//   modal ('')      -> $smsmessage
	// NOTE: Datastar separates the plugin name from its key with a COLON, not
	// a hyphen (data-on:input, data-bind:sig, data-attr:disabled). A hyphen
	// makes the whole string the plugin name (e.g. "on-input"), matching no
	// registered plugin, so it is silently skipped and no listener attaches.
	$sig = 'smsmessage' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
	$statuslineId = 'sms-statusline' . $idSuffix;
	$previewId = 'sms-preview-panel' . $idSuffix;
	$shortenToggleId = 'sms-shorten-toggle' . $idSuffix; // rendered by _printSmsFormFields()
	// Per-form signal toggled by the "Message Preview" checkbox (data-bind);
	// the panel wrapper's data-show follows it. Lowercased for the same
	// reason as $sig (browsers lowercase attribute names).
	$previewSig = 'smspreview' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));

	// Initial server-rendered statusline so the page is correct before the
	// first keystroke (empty message -> empty statusline). All cost/segment
	// maths is server-owned now; see jethro-sms/src/sms_statusline.php and
	// docs/docs/developer/sms/SMS_DATASTAR.md.
	$initialStatus = \Sms\renderStatusline('', [], $cfg);

	// {contentType:'form'} submits the enclosing <form> so sender, sms_type,
	// personid[] and message_overrides ride along. The hidden id fields tell
	// the SSE endpoint which elements to morph.
	$post = "@post('?call=sms_statusline', {contentType: 'form'})";
	?>
    <input type="hidden" name="statusline_id" value="<?php echo ents($statuslineId); ?>" />
    <input type="hidden" name="preview_id" value="<?php echo ents($previewId); ?>" />
    <input type="hidden" name="shorten_toggle_id" value="<?php echo ents($shortenToggleId); ?>" />
    <textarea class="sms-message span4" name="message"
              data-bind:<?php echo ents($sig); ?>
              data-on:input__debounce.300ms="<?php echo ents($post); ?>"
              data-is-sysadmin="<?php echo $GLOBALS['user_system']->havePerm(PERM_SYSADMIN); ?>"
              rows="5" cols="30" maxlength="<?php echo ifdef('SMS_MAX_LENGTH') ?: 160; ?>"></textarea>
    <?php // One combined counter line: the live client-side char count
          // ($<sig>.length — the only client-side maths left) immediately
          // followed by the server-rendered segment/cost/recipient figures,
          // morphed into the #<statuslineId> span via SSE. A CSS ::before on the
          // non-empty statusline draws the " · " separator, so there is exactly
          // one line and the character count is never shown twice. ?>
    <div class="smscharactercount soft">
        <span class="sms-charcount-instant" data-show="$<?php echo ents($sig); ?>.length > 0" data-text="$<?php echo ents($sig); ?>.length + ' chars'"></span><span id="<?php echo ents($statuslineId); ?>" class="sms-statusline-cost"><?php echo $initialStatus['html']; ?></span>
    </div>
	<?php
	// %selected.*% only differ from the plain tokens with "the adults in the
	// selected persons' families": shown while the bulk form's $smstype is 'family'.
	$code = fn (string $t): string => '<code>%' . $t . '%</code>';
	// Mouseover text for %selected.*%: which name it gives, and of whom.
	$selectedCode = function (string $t): string {
		$field = substr($t, strlen('selected.'));
		$label = ['firstname' => 'first name', 'lastname' => 'last name', 'fullname' => 'full name'][$field] ?? $field;
		$title = 'The ' . $label . ' of the selected person(s) in the recipient\'s family, not of the adult receiving the SMS.'
			. ' E.g. select two children: each parent\'s SMS names both, joined with " & ".';
		return '<code title="' . ents($title) . '">%' . $t . '%</code>';
	};
	$isSelected = fn (string $t): bool => str_starts_with($t, 'selected.');
	$plainTokens = array_filter(getAvailableTokens(), fn (string $t) => !$isSelected($t));
	$selectedTokens = $idSuffix === '-bulk' ? array_filter(getAvailableTokens(), $isSelected) : [];
	if ($plainTokens !== []) {
		?>
		<div id="sms-token-hint<?php echo $idSuffix; ?>" class="soft" style="margin-top:2px">
			<span class="icon-info-sign"></span>
			Personalise with: <?php echo implode(', ', array_map($code, $plainTokens)); ?><?php if ($selectedTokens !== []): ?><span class="sms-selected-tokens" data-show="$smstype === 'family'" style="display:none">, <?php echo implode(', ', array_map($selectedCode, $selectedTokens)); ?></span><?php endif; ?>
		</div>
		<?php
	}
	?>
		<?php if (getAvailableTokens() !== []): ?>
		<label class="checkbox sms-preview-toggle">
			<input type="checkbox" class="sms-preview-checkbox" data-bind:<?php echo ents($previewSig); ?> />
			Message Preview
		</label>
		<?php endif; ?>
		<?php // Preview panel. Visibility follows the "Message Preview" checkbox
		     // via a Datastar signal on this wrapper, which the SSE never morphs;
		     // the inner #<previewId> is the SSE morph target for the server-
		     // rendered content. ?>
		<div class="sms-preview-wrap" style="display:none" data-show="$<?php echo ents($previewSig); ?>">
			<div id="<?php echo ents($previewId); ?>" class="sms-preview-panel" style="display:none;min-height:0"></div>
		</div>
	<?php
	}

/**
 * Render the "Shorten URLs" form row — a .control-group that is a Datastar
 * morph target, re-rendered by Call_SMS_Statusline on every composer post
 * and once (empty message) by _printSmsFormFields().
 *
 * The checkbox only shows when the shortener is usable (URLSHORTENER is a
 * supported shortener, {@see \urlShortenerConfig()}, and URLSHORTENER_API_KEY
 * is set) and $message has a bare URL that auto-shortening
 * would actually wrap (same >26-char rule as sendSms(), via
 * \Sms\shortenUrlsInText()), and no explicit %(shorten …)% token (which
 * disables auto-shortening). Otherwise the row is display:none and the
 * user's choice is carried in a hidden field so it survives the URL being
 * deleted and re-added. When the shortener is unusable the row is empty, so
 * the request omits shorten_urls.
 *
 * See docs/docs/developer/sms/smsarchitecture.md "URL auto-shortening".
 *
 * @param string    $id           Element id (sms-shorten-toggle + form suffix).
 * @param string    $message      Composer text.
 * @param bool|null $shortenUrls  Posted choice ({@see shortenUrlsFromRequest()}); null (initial page
 *        render) = SMS_SHORTEN_URLS, which is only the checkbox's default.
 * @return string  HTML <div id="$id" class="control-group">.
 */
function renderShortenUrlsToggle(string $id, string $message, ?bool $shortenUrls): string
{
	$hidden = '<div id="' . ents($id) . '" class="control-group" style="display:none">';
	if (!\urlShortenerConfig()['supported'] || ifdef('URLSHORTENER_API_KEY', '') === '') {
		return $hidden . '</div>';
	}
	$on = $shortenUrls ?? (bool)ifdef('SMS_SHORTEN_URLS', false);
	$shortenable = !str_contains($message, '%(shorten') && \Sms\shortenUrlsInText($message) !== $message;
	if (!$shortenable) {
		return $hidden . '<input type="hidden" name="shorten_urls" value="' . ($on ? '1' : '0') . '" /></div>';
	}
	// Hidden "0" first: an unticked box then posts 0 rather than nothing.
	$post = "@post('?call=sms_statusline', {contentType: 'form'})";
	return '<div id="' . ents($id) . '" class="control-group"><div class="controls">'
		. '<input type="hidden" name="shorten_urls" value="0" />'
		. '<label class="checkbox"><input type="checkbox" name="shorten_urls" value="1"'
		. ' data-on:change="' . ents($post) . '"' . ($on ? ' checked="checked"' : '') . ' /> Shorten URLs</label>'
		. '</div></div>';
}



// ---------------------------------------------------------------------------
// UI
// ---------------------------------------------------------------------------

/**
 * Print the Sender <select>/status markup (no surrounding label/control-group).
 * Split out of printSenderDropdown() so modal and bulk-form callers share one
 * .control-group/.control-label wrapper instead of duplicating it.
 *
 * SMS_SENDER_OPTIONS controls which options appear and in what order.  When the
 * constant is undefined or set to the empty string, defaults to
 * `_USER_MOBILE_,_SENDER_IDS_` so an out-of-the-box install still offers the
 * user's mobile and any upstream-registered sender IDs
 * ({@see \Sms\senderOptionTokens()}).
 * Recognised tokens:
 *   `_SENDER_IDS_`  — expands to every sender ID the provider reports
 *   `_USER_MOBILE_` — expands to the current user's mobile number
 * Empty segments from duplicate commas are silently discarded.
 *
 * `_printRegisterStatus()` is emitted exactly once (inside a hidden
 * `.sms-register-status-wrapper` revealed by JS when the user selects the
 * `_USER_MOBILE_` option and that number still needs verifying).
 */
function _printSenderDropdownControl(string $idSuffix = ''): void
{
	// ── Hardcoded SMS_SENDER — no dropdown ──────────────────────────────────
	if ((string) ifdef('SMS_SENDER', '') !== '') {
		$senderAvailable = true;
		if (SMS_SENDER === '_USER_MOBILE_') {
			$userMobile = getCurrentUserMobileNumber();
			if ($userMobile === null) {
				$senderAvailable = false;
				?>
				<span class="text-error">Your mobile number is not set. Add it to your profile to send SMS.</span>
				<?php
			} else {
				$needsReg = false;
				$label = '';
				$providerResult = getSmsProvider();
				if ($providerResult->isSuccess()) {
					$provider = $providerResult->getValue();
					if ($provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_NUMBER)) {
						$verifyResult = $provider->verifySenderNumber($userMobile);
						if ($verifyResult->isSuccess()) {
							$needsReg = !$verifyResult->getValue();
						} else {
							$verifyError = $verifyResult->getError();
							$needsReg = true;
						}
						$currentUserId = (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0);
						$label = 'person_' . $currentUserId;
					}
				}
				?>
				<?php echo ents('My mobile ('.$userMobile->value.')'); ?>
				<?php if ($needsReg): ?>
					<?php _printRegisterStatus($userMobile, $label); ?>
				<?php endif; ?>
				<?php if (isset($verifyError)): ?>
					<span class="text-warning" style="font-size:0.85em">
						<i class="icon-warning-sign"></i>
						Could not verify sender: <?php echo ents($verifyError); ?>
					</span>
				<?php endif; ?>
				<?php
			}
		} else {
			?>
			<div class="controls-text"><?php echo ents(SMS_SENDER); ?></div>
			<?php
		}
		// No <select> exists for a hardcoded sender, so checkSenderAvailability()
		// (JS) can't infer availability from option counts — this marker tells
		// it directly whether Send should be enabled.
		?>
		<span class="sms-sender-static" data-sender-available="<?php echo $senderAvailable ? '1' : '0'; ?>" style="display:none"></span>
		<?php
		return;
	}

	// ── Dropdown path ────────────────────────────────────────────────────────
	// Parse configured options (or default) into an ordered token list;
	// empty segments from duplicate commas are silently discarded.  An empty
	// SMS_SENDER_OPTIONS is treated the same as undefined — the constant lives
	// in the `setting` table now, where its seeded default was once blank, and
	// blank there should still produce a usable dropdown.
	$tokens = \Sms\senderOptionTokens((string) ifdef('SMS_SENDER_OPTIONS', ''));

	// Resolve the provider once; getSenderIds() handles SMS_SENDER_OPTIONS
	// internally (including _SENDER_IDS_ expansion and duplicate-comma cleanup).
	// Only _USER_MOBILE_ configured: there are no provider sender IDs to show
	// or vet, so skip that provider call.  Literal IDs still need it —
	// OverridingSmsProvider::getSenderIds() shows only those registered upstream.
	$needsSenderIds = array_diff($tokens, ['_USER_MOBILE_']) !== [];
	$senderIds     = [];
	$senderIdsError = null;
	$provider      = null;
	$providerResult = getSmsProvider();
	if ($providerResult->isSuccess()) {
		$provider  = $providerResult->getValue();
		$idsResult = $needsSenderIds ? $provider->getSenderIds() : \Result::success([]);
		if ($idsResult->isFailure()) {
			$senderIdsError = $idsResult->getError();
		} else {
			$senderIds = $idsResult->getValue();
		}
	}

	// Resolve _USER_MOBILE_ and check registration status in one pass.
	$userMobile      = null;
	$needsRegistration = false;
	$label           = '';
	if (\in_array('_USER_MOBILE_', $tokens, true)) {
		$userMobile = getCurrentUserMobileNumber();
		if ($userMobile !== null && $provider !== null
			&& $provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_NUMBER)
		) {
			$verifyResult = $provider->verifySenderNumber($userMobile);
			if ($verifyResult->isSuccess()) {
				if (!$verifyResult->getValue()) {
					$needsRegistration = true;
				}
			} else {
				$verifyError = $verifyResult->getError();
				$needsRegistration = true;
			}
			$currentUserId = (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0);
			$label = 'person_' . $currentUserId;
		}
	}

	// Nothing to show at all — surface the most useful error.
	if ($senderIds === [] && $userMobile === null) {
		if ($senderIdsError !== null) {
			?>
			<span class="text-error"><?php echo ents($senderIdsError); ?></span>
			<?php
		} else {
			?>
			<span class="text-error">No senders available. Set SMS_SENDER_OPTIONS or add a mobile number to your profile (log out/log in to take effect).</span>
			<?php
		}
		return;
	}

	if ($senderIdsError !== null || isset($verifyError)) {
		if ($senderIdsError !== null) {
			?>
			<span class="text-error"><?php echo ents($senderIdsError); ?></span>
			<?php
		}
		if (isset($verifyError)) {
			?>
			<span class="text-warning" style="font-size:0.85em">
				<i class="icon-warning-sign"></i>
				Could not verify sender: <?php echo ents($verifyError); ?>
			</span>
			<?php
		}
	}

	$defaultSender = (string) ifdef('SMS_SENDER_DEFAULT', '_USER_MOBILE_');

	// Track emitted values to avoid duplicate options when a literal token and
	// _SENDER_IDS_ would otherwise produce the same entry.
	$renderedValues = [];
	$sig = 'smssender' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $idSuffix));
	?>
	<select name="sender" id="sms_sender<?php echo $idSuffix; ?>" class="span4" data-bind:<?php echo ents($sig); ?>>
		<?php foreach ($tokens as $token):
			if ($token === '_USER_MOBILE_') {
				if ($userMobile !== null && !\in_array('_USER_MOBILE_', $renderedValues, true)) {
					$renderedValues[] = '_USER_MOBILE_';
					?>
					<option value="_USER_MOBILE_"
						<?php if ($defaultSender === '_USER_MOBILE_') echo 'selected'; ?>
						<?php if ($needsRegistration) echo 'data-needs-registration="1"'; ?>
						data-number="<?php echo ents($userMobile->value); ?>"
						data-label="<?php echo ents($label); ?>"
					><?php echo ents('My mobile: '.$userMobile->value); ?></option>
					<?php
				}
			} elseif ($token === '_SENDER_IDS_') {
				foreach ($senderIds as $senderId) {
					if (!\in_array($senderId->value, $renderedValues, true)) {
						$renderedValues[] = $senderId->value;
						?>
						<option value="<?php echo ents($senderId->value); ?>"
							<?php if ((string) $defaultSender === $senderId->value) echo 'selected'; ?>
						><?php echo ents($senderId->value); ?></option>
						<?php
					}
				}
			} else {
				if (!\in_array($token, $renderedValues, true)) {
					$renderedValues[] = $token;
					?>
					<option value="<?php echo ents($token); ?>"
						<?php if ((string) $defaultSender === $token) echo 'selected'; ?>
					><?php echo ents($token); ?></option>
					<?php
				}
			}
		endforeach; ?>
	</select>
	<?php if ($needsRegistration && $userMobile !== null): ?>
	<span class="sms-register-status-wrapper" data-show="$<?php echo ents($sig); ?> == '_USER_MOBILE_'">
		<?php _printRegisterStatus($userMobile, $label); ?>
	</span>
	<?php endif; ?>
	<?php
}

/**
 * Print the sender-number registration status block.
 *
 * Shows a "needs verifying" prompt and inline OTP verification UI.
 * Used when REGISTER_SENDER_NUMBER capability is present and the
 * user's mobile has not yet been verified with the upstream provider.
 */
function _printRegisterStatus(\Sms\PhoneNumber $userMobile, string $label): void
{
	$number = ents($userMobile->value);
	$labelE = ents($label);
	?>
	<div id="sms-register-<?php echo $labelE; ?>" class="sms-register-status" style="margin-top: 4px; font-size: 0.9em;">
		<span class="sms-register-prompt">
			<?php echo _('(your mobile number needs verifying to send &mdash;'); ?>
			<a href="#" class="sms-register-number"
			   data-on:click="@post('?call=sms_sendernum&action=register&number=<?php echo $number; ?>&label=<?php echo $labelE; ?>')"
			><?php echo _('verify now'); ?></a>)
		</span>
	<div class="sms-register-otp" style="display:none">
			<?php echo _('Verification code sent &mdash; enter code:'); ?>
			<input type="text" name="otp" data-bind:otp<?php echo $labelE; ?> class="sms-otp-input input-small" maxlength="6" style="width:70px; margin:0 4px" />
			<button type="button" class="btn btn-mini sms-otp-submit"
				data-on:click="@post('?call=sms_sendernum&action=validate&number=<?php echo $number; ?>&label=<?php echo $labelE; ?>&otp=' + $otp<?php echo $labelE; ?>)"
			><?php echo _('Verify'); ?></button>
			<span class="sms-register-error" style="display:none; color:#c00; margin-left:4px"></span>
	</div>
	</div>
	<?php
}



// ---------------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------------

/**
 * Get the current SMS account balance.
 *
 * Has a TWO-LEVEL cache:
 *   1. Per-request static variable (avoids multiple provider calls within
 *      the same PHP request — e.g. both printTextbox() and printBulkSmsForm()
 *      calling getSmsBalance())
 *   2. Cross-request SmsCache (SessionSmsCache, wired in getSmsProvider) —
 *      avoids HTTP calls on every page load; invalidated after send
 *
 * Returns null (not 0) when the balance is unavailable, to distinguish
 * "provider doesn't support balance" from "balance is zero".
 *
 * @return int|null The balance as an integer, or null if unavailable or unsupported
 */
function getSmsBalance(): ?int
{
	static $balance = false;

	if ($balance !== false) {
		return $balance;
	}

	$balance = null;

	$providerResult = getSmsProvider();
	if ($providerResult->isSuccess()) {
		$result = $providerResult->getValue()->getBalance();
		if ($result->isSuccess()) {
			$balance = $result->getValue();
		}
	}

	return $balance;
}

/**
 * Get the upstream API key (e.g. 'cellcastv1') of the currently configured
 * SMS provider; see \Sms\SmsProvider::getKey().
 *
 * Returns null when no provider is configured, an error occurs, or the
 * provider's upstream API is unknown (generic HTTP template).
 * Cheap to call repeatedly — chain construction is pure constant reads.
 *
 * @return string|null
 */
function getCurrentSmsProviderKey(): ?string
{
	$result = getSmsProvider();
	if ($result->isFailure()) {
		return null;
	}
	return $result->getValue()->getKey();
}

/**
 * Get the list of available %token% names for SMS message personalisation:
 *   firstname, lastname, fullname            the recipient (personTokens());
 *   selected.firstname, selected.lastname, selected.fullname
 *       the selected person(s) the recipient is sent the message for, e.g. a
 *       child when sending to "the adults in the selected persons' families";
 *       several are joined " & ". When the recipient was selected directly,
 *       the recipient's own values (recipientTokens()).
 *
 * These tokens are resolved per-recipient at send time using person data.
 * If the message contains no %, the message is sent as a single batch.
 * Otherwise each recipient receives an individual message with their own data.
 *
 * @return list<string>  Token names (without the % delimiters)
 * @see docs/docs/developer/sms/engine/token-expansion.mdx
 */
function getAvailableTokens(): array
{
	$base = array_keys(personTokens([]));
	return [...$base, ...array_map(fn(string $name): string => 'selected.' . $name, $base)];
}

/**
 * The person tokens for one person row: firstname, lastname, fullname.
 * Missing fields give ''.
 *
 * @param array<string, mixed> $person  Person row (first_name, last_name), or [] if unknown
 * @return array{firstname: string, lastname: string, fullname: string}
 * @see getAvailableTokens()
 */
function personTokens(array $person): array
{
	$first = (string)($person['first_name'] ?? '');
	$last = (string)($person['last_name'] ?? '');
	return [
		'firstname' => $first,
		'lastname'  => $last,
		'fullname'  => trim($first . ' ' . $last),
	];
}

/**
 * All token values for one recipient: personTokens() of the recipient, plus
 * 'selected.<name>' for each of them: the values of the selected persons the
 * recipient is sent the message for (JethroSmsRecipient::$selectedPersonIds),
 * joined " & ", or the recipient's own value when it was selected directly.
 *
 * @param callable(int): array<string, mixed> $personRow  Person ID => person row ([] if unknown)
 * @return array<string, string>  Token name => value, for every getAvailableTokens() name
 * @see getSmsProvider()  whose token resolver this is
 */
function recipientTokens(JethroSmsRecipient $recipient, callable $personRow): array
{
	$own = personTokens($personRow($recipient->personId));
	$selected = $recipient->selectedPersonIds === []
		? [$own]
		: array_map(fn(int $id): array => personTokens($personRow($id)), $recipient->selectedPersonIds);
	$tokens = $own;
	foreach (array_keys($own) as $name) {
		$values = array_filter(array_column($selected, $name), fn(string $v): bool => $v !== '');
		$tokens['selected.' . $name] = implode(' & ', $values);
	}
	return $tokens;
}

/**
 * Snapshot Jethro state into the pure statusline config value object.
 *
 * This is the bridge-side factory for \Sms\SmsStatuslineConfig: it reads the
 * provider chain (segment cost), balance, config constants and the current
 * user's sysadmin bit — none of which the pure statusline layer may touch.
 * (Formerly SmsStatuslineConfig::fromConstants(); moved here during the
 * jethro-sms extraction — see jethro-sms/docs/extraction.md §4.)
 *
 * @param bool $shortenUrls  Per-request "Shorten URLs" choice (see
 *        {@see shortenUrlsFromRequest()}).
 */
function makeStatuslineConfig(bool $shortenUrls = false): \Sms\SmsStatuslineConfig
{
    $segmentCost = 0.0;
    $balanceBlocksSend = true;
    $providerResult = \Jethro\Sms\getSmsProvider();
    if ($providerResult->isSuccess()) {
        $provider = $providerResult->getValue();
        $segmentCost = $provider->getSegmentCost() / 100000;
        $balanceBlocksSend = !$provider->hasCapability(\Sms\SmsCapability::OVERDRAFT);
    }

    $smsUnicode = ifdef('SMS_UNICODE_PERMITTED', 'when_free');
    if ($smsUnicode === 'when_free' || $smsUnicode === 2 || $smsUnicode === '2') {
        $unicodeMode = 'when_free';
    } elseif (filter_var($smsUnicode, FILTER_VALIDATE_BOOLEAN)) {
        $unicodeMode = 'enabled';
    } else {
        $unicodeMode = 'disabled';
    }

    return new \Sms\SmsStatuslineConfig(
        maxLength: (int) (ifdef('SMS_MAX_LENGTH') ?: 160),
        segmentLength: 160,
        ucs2SegmentLength: 70,
        segmentCost: $segmentCost,
        // The statusline warns and blocks Send when a send needs more segments
        // than the account has (only warns when the balance is advisory,
        // SmsCapability::OVERDRAFT). Providers cache the balance for 300s, so
        // typing does not call the provider's API every time.
        balance: getSmsBalance(),
        balanceBlocksSend: $balanceBlocksSend,
        shortenUrls: $shortenUrls && \urlShortenerConfig()['supported'],
        testMode: filter_var(ifdef('SMS_TESTMODE', false), FILTER_VALIDATE_BOOLEAN),
        unicodeMode: $unicodeMode,
        isSysadmin: isset($GLOBALS['user_system']) && $GLOBALS['user_system']->havePerm(PERM_SYSADMIN),
    );
}

/**
 * The time a deferred send will actually go out.
 *
 * Jethro asks the provider for $requested, but a provider may defer further:
 * MobileMessage's API silently downgrades a `scheduled_for` ≤60 s ahead to an
 * immediate send, so that provider clamps the request to now+90 s (see
 * MobileMessageSmsProvider::MIN_SCHEDULE_SECONDS) and the API echoes the
 * clamped time back as `scheduled_for`, which its deliveries carry as
 * SmsDelivery::sendTimestamp(). Recording that time keeps the countdown, the
 * status polling and the cancel window in step with the gateway.
 *
 * @param int|null $requested  Unix time Jethro requested, or null for an immediate send
 * @param \Sms\SmsDelivery[] $deliveries  What the provider returned for that send
 * @return int|null  The provider's reported time when it gives one, else $requested;
 *                   null for immediate sends whatever the deliveries carry
 * @see insertSms()
 */
function effectiveScheduledSendAt(?int $requested, array $deliveries): ?int
{
	if ($requested === null) {
		return null;
	}
	foreach ($deliveries as $delivery) {
		if ($delivery instanceof \Sms\SmsDelivery && $delivery->sendTimestamp() !== null) {
			return $delivery->sendTimestamp();
		}
	}
	return $requested;
}

/**
 * Insert a broadcast record and its per-recipient rows.
 *
 * Sets status (JSON from per-recipient SmsDelivery data) and isdelivered (0).
 * Per-recipient remote IDs are stored in smsdelivery.remoteId.
 * For failed sends where results is empty, status is left NULL.
 *
 * `$scheduledSendAt` is what Jethro requested, but a provider may defer
 * further: MobileMessage's API silently downgrades a `scheduled_for` ≤60 s
 * ahead to an immediate send, so that provider clamps the request to now+90 s
 * and the API echoes that time back. Its deliveries carry that actual time
 * (SmsDelivery::sendTimestamp()), which is recorded here when present, so the
 * countdown, status polling and cancel window match the gateway.
 *
 * On DB failure, returns the sentinel `['smsId' => 0, 'deliveries' => []]`
 * and logs loudly via error_log(). The gateway send has already happened;
 * callers must still treat the send as successful (messages were delivered).
 * DbLoggingSmsProvider never caches a non-positive smsId so subsequent batch
 * calls will retry insertSms() rather than inheriting the failure.
 *
 * @param string $messageText
 * @param int|null $senderId
 * @param \Sms\SmsDelivery[] $results
 * @param \Sms\SmsRecipient[] $recipients  Original recipients
 * @param int|null $scheduledSendAt  Unix time the send was requested for (pre-clamp); see above
 * @param string|null $provider  Upstream API key logged in smsdelivery.provider ({@see \Sms\SmsProvider::getKey()}); null if unknown
 * @return array{smsId: int, deliveries: array<string, int>}  smsId + person ID → smsdelivery ID map; smsId=0 on DB failure
 */
function insertSms(string $messageText, ?int $senderId, array $results, array $recipients, ?int $scheduledSendAt = null, ?string $provider = null, string $wireSender = ''): array
{
	$db = $GLOBALS['db'];

	$scheduledSendAt = effectiveScheduledSendAt($scheduledSendAt, $results);

	$summary = \Sms\sendSummary($results, $recipients);

	// Re-index so positional checks are reliable.
	$results    = array_values($results);
	$recipients = array_values($recipients);

	// Detect positional pairing: same strategy as sendSummary() uses.
	// When counts match and every result phone equals the recipient phone,
	// we can pair by position — correctly handles shared mobile numbers.
	$usePositional = false;
	if (count($results) === count($recipients)) {
		$usePositional = true;
		foreach ($results as $i => $r) {
			if ($r->recipient()->value !== $recipients[$i]->getPhoneNumber()->value) {
				$usePositional = false;
				break;
			}
		}
	}

	// Insert all recipients on PartialSuccess so the audit trail records every attempt, not just successes
	$recipientsToInsert = match (true) {
		$summary instanceof \Sms\AllSent        => $summary->recipients,
		$summary instanceof \Sms\PartialSuccess  => array_merge($summary->successes, $summary->failures),
		$summary instanceof \Sms\Failed          => [],
		default                                   => [],
	};

	// Insert the sms body row via DB_Object
	$GLOBALS['system']->includeDBClass('sms');
	$sms = new \Sms();
	$sms->setValue('body', $messageText);
	$sms->setValue('sender', $senderId);
	$sms->setValue('created', date('Y-m-d H:i:s', time()));
	if ($wireSender !== '') {
		$sms->setValue('wire_sender', $wireSender);
	}
	if ($scheduledSendAt !== null) {
		$sms->setValue('scheduled_send_at', date('Y-m-d H:i:s', $scheduledSendAt));
	}
	if (!$sms->create()) {
		$recipientCount = count($recipients);
		$scheduledNote  = $scheduledSendAt !== null ? ', scheduledSendAt=' . date('Y-m-d H:i:s', $scheduledSendAt) : '';
		error_log(
			"[Jethro SMS] AUDIT FAILURE: insertSms() Sms::create() failed — "
			. "audit row NOT written. Gateway send already completed. "
			. "recipientCount={$recipientCount}, senderId=" . ($senderId ?? 'null')
			. ", provider=" . ($provider ?? 'unknown') . $scheduledNote
		);
		return ['smsId' => 0, 'deliveries' => []];
	}
	$smsId = (int)$sms->id;

	// Insert per-recipient delivery rows.
	// When positional pairing holds, iterate (recipient, result) pairs directly
	// so shared-phone recipients each get their own correct delivery row.
	// When falling back to phone-keyed matching, find the first result whose
	// phone matches each recipient (pre-fix behaviour, unchanged).
	$personToBroadcast  = [];
	$personToDeliveryId = [];
	$recipStmt = $db->prepare('INSERT INTO _smsdelivery (sms_id, personid, remote_id, raw_response, status, body, provider, segments_used, per_segment_cost, cost_bearer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$configuredPrice = configuredSegmentCost($provider);

	if ($usePositional) {
		// Positional: pair each original recipient with its result by index.
		// recipientsToInsert is derived from the summary and may differ in
		// count/identity from $recipients (on Failed it's empty; on AllSent /
		// PartialSuccess the summary objects are the same objects as the
		// original recipients but sourced via sendSummary's output).
		// For correctness with shared phones we bypass recipientsToInsert and
		// drive directly off the ($recipients, $results) positional pairs —
		// but only for AllSent/PartialSuccess (Failed inserts nothing).
		if (!($summary instanceof \Sms\Failed)) {
			foreach ($recipients as $i => $recip) {
				$recipientResult = $results[$i];
				$remoteId        = $recipientResult->remoteId();
				$remoteId        = ($remoteId !== null && $remoteId !== '') ? $remoteId : null;
				$rawResponse     = $recipientResult->rawResponse();
				$status          = $recipientResult->status()->toMySql();
				$personId        = $recip instanceof JethroSmsRecipient ? $recip->personId : null;
				$recipStmt->execute([$smsId, $personId, $remoteId, $rawResponse, $status, $recipientResult->message(), $provider,
					$recipientResult->segmentsUsed(), $recipientResult->perSegmentCost() ?? $configuredPrice,
					$recipientResult->costBearer() ? 1 : 0]);
				$personToBroadcast[(string)$personId]  = $smsId;
				$personToDeliveryId[(string)$personId] = (int)$db->lastInsertId();
			}
		}
	} else {
		// Phone-keyed fallback: pre-fix behaviour, byte-identical for the
		// common non-shared-phone case.
		$destRemoteIdMap = [];
		foreach ($results as $r) {
			$rid = $r->remoteId();
			if ($rid !== null && $rid !== '') {
				$destRemoteIdMap[$r->recipient()->value] = $rid;
			}
		}
		foreach ($recipientsToInsert as $recip) {
			$phone           = $recip->getPhoneNumber()->value;
			$recipientResult = null;
			foreach ($results as $r) {
				if ($r->recipient()->value === $phone) {
					$recipientResult = $r;
					break;
				}
			}
			$rawResponse = $recipientResult ? $recipientResult->rawResponse() : '';
			$status      = $recipientResult ? $recipientResult->status()->toMySql() : \Sms\SmsStatus::SENDING->toMySql();
			$personId    = $recip instanceof JethroSmsRecipient ? $recip->personId : null;
			$recipStmt->execute([$smsId, $personId, $destRemoteIdMap[$phone] ?? null, $rawResponse, $status, $recipientResult?->message(), $provider,
				$recipientResult?->segmentsUsed(), $recipientResult?->perSegmentCost() ?? $configuredPrice,
				($recipientResult?->costBearer() ?? true) ? 1 : 0]);
			$personToBroadcast[(string)$personId]  = $smsId;
			$personToDeliveryId[(string)$personId] = (int)$db->lastInsertId();
		}
	}

	return ['smsId' => $smsId, 'deliveries' => $personToDeliveryId];
}

/**
 * Provider class for each upstream API key that smsdelivery.provider can hold
 * ({@see \Sms\SmsProvider::getKey()}). Single source for pricing old rows.
 * See docs/docs/developer/sms/database.mdx "Provider column".
 *
 * @return array<string, class-string<\Sms\SmsProvider>>
 */
function providerClassesByKey(): array
{
	return [
		'5centsmsv5' => \Sms\Providers\FiveCentSmsV5Provider::class,
		'5centsmsv4' => \Sms\Providers\FiveCentSmsV4Provider::class,
		'cellcastv1' => \Sms\Providers\CellcastSmsProvider::class,
		'mobilemessagev1' => \Sms\Providers\MobileMessageSmsProvider::class,
	];
}

/**
 * The provider's built-in dollars-per-segment (one credit = one SMS segment),
 * from its SEGMENT_COST_MILLICENTS constant; 0.0 for an unknown or null key.
 *
 * Keyed by smsdelivery.provider so each row is priced by the provider that
 * sent it, not whichever provider is configured now.
 * See docs/docs/developer/sms/database.mdx "Cost tracking".
 */
function defaultSegmentCost(?string $providerKey): float
{
	$class = providerClassesByKey()[$providerKey ?? ''] ?? null;
	return $class !== null ? $class::SEGMENT_COST_MILLICENTS / 100000 : 0.0;
}

/**
 * Dollars per segment to record for a delivery sent now via $providerKey when
 * the provider didn't report a price: SMS_SEGMENT_COST if set, else the
 * provider's default ({@see defaultSegmentCost()}).
 */
function configuredSegmentCost(?string $providerKey): float
{
	$setting = trim((string) ifdef('SMS_SEGMENT_COST', ''));
	return is_numeric($setting) ? (float) $setting : defaultSegmentCost($providerKey);
}

/**
 * Classify an aggregate of delivery status counts into a single log-file label.
 *
 * Used by the export-smslog CLI can
 * reconstruct the same status strings from the sms + smsdelivery tables.
 *
 * @param int $okCount    Number of successful deliveries (delivered, sent, etc.)
 * @param int $failCount  Number of failed/cancelled deliveries
 * @param int $totalCount Total delivery count for this send
 * @return string  'sent', 'partial', 'failed', or 'unknown'
 */
function classifySmsStatus(int $okCount, int $failCount, int $totalCount): string
{
    if ($totalCount === 0) {
        return 'unknown';
    }
    if ($okCount === $totalCount) {
        return 'sent';
    }
    if ($failCount === $totalCount) {
        return 'failed';
    }
    return 'partial';
}

/**
 * Get SMS delivery history for a person.
 * Viewers lacking PERM_VIEWSMS only see messages sent by themselves.
 *
 * A send has at most one delivery per person (recipients are deduplicated
 * per person), so rows are keyed by sms ID — the templates use the key as the
 * message ID — with the delivery's own ID in 'delivery_id'.
 *
 * @param int $personId
 * @return array<int, array<string, mixed>>  Deliveries keyed by sms ID, ordered by created DESC
 */
function getPersonSmsHistory(int $personId): array
{
	$db = $GLOBALS['db'];
	$SQL = 'SELECT s.id, COALESCE(sd.body, s.body) AS body, s.sender, s.created,'
		. ' (sd.status IN (\'queued\',\'sent\',\'delivered\',\'test-message\')) AS isdelivered,'
		. ' sd.id AS delivery_id, sd.remote_id, COALESCE(NULLIF(sd.status, ""), "unknown") AS delivery_status,'
		. ' p.first_name AS sender_fn, p.last_name AS sender_ln,'
		. ' (SELECT COUNT(*) FROM smsdelivery WHERE sms_id = s.id) AS recipient_count,'
			. ' sn.note_id, sd.provider, sd.delivered_at, s.scheduled_send_at'
		. ' FROM sms s'
		. ' JOIN smsdelivery sd ON sd.sms_id = s.id'
		. ' LEFT JOIN _person p ON p.id = s.sender'
		. ' LEFT JOIN sms_note sn ON sn.smsdelivery_id = sd.id AND sn.note_personid = sd.personid'
		. ' WHERE sd.personid = ' . (int)$personId;
	if (!$GLOBALS['user_system']->havePerm(PERM_VIEWSMS)) {
		$currentUserId = (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0);
		$SQL .= ' AND s.sender = ' . $currentUserId;
	}
	$SQL .= ' ORDER BY s.created DESC';
	return $db->queryAll($SQL, null, null, true);
}


/**
 * Check whether SMSes are both enabled and correctly configured, and Jethro is thus able to send SMSes.
 * Shortcut for `isFeatureEnabled() && isConfigured()`
 */
function isUsable(): bool
{
    return isFeatureEnabled() && isConfigured();
}

/**
 * Whether the current user may send SMS: they hold PERM_SENDSMS, the feature
 * is on and a gateway is configured.
 *
 * UI code (send links, bulk actions, the compose modal, the mobile-number
 * dropdown flag) must use this rather than isUsable(), which takes no account
 * of the viewer. Code that runs without a user (cron, CLI) keeps isUsable().
 *
 * The members portal installs Member_User_System, which has no permission
 * levels (no havePerm()); members can never send SMS. The permission check
 * runs first so viewers who cannot send never trigger provider construction.
 */
function canSendSms(): bool
{
    $userSystem = $GLOBALS['user_system'] ?? null;
    if ($userSystem === null || !method_exists($userSystem, 'havePerm') || !$userSystem->havePerm(PERM_SENDSMS)) {
        return false;
    }
    return isUsable();
}

/**
 * Check whether the default SMS gateway is configured with all necessary settings.
 * Most callers should prefer {@link isUsable()}.
 *
 * Cached for the request: callers include per-mobile-number rendering on list
 * pages and the page head, and provider construction is not free.
 */
function isConfigured(): bool
{
    static $configured = null;
    if ($configured === null) {
        $configured = getSmsProvider()->isSuccess();
    }
    return $configured;
}

/**
 * Check if the SMS feature is enabled in the system settings.
 * Most callers should prefer {@link isUsable()}.
 */
function isFeatureEnabled(): bool
{
    // ENABLED_FEATURES is defined by Config_Manager::init() before
    // User_System is constructed, so we can read it directly here.
    // We cannot rely on $GLOBALS['system'] being available: it is only
    // assigned after a successful login (index.php), not during the
    // login-form POST itself.
    if (!isset($GLOBALS['system'])) {
        $features = array_map('strtoupper', explode(',', ifdef('ENABLED_FEATURES', '')));
        return in_array('SMS', $features);
    }
    return $GLOBALS['system']->featureEnabled('SMS');
}

/**
 * Whether conf.php still defines the removed dedicated 2FA gateway settings
 * (2FA_SMS_URL and friends).
 *
 * Earlier Jethro versions could send 2FA codes through a separate gateway
 * configured with 2FA_SMS_* constants. 2FA now always uses the main SMS
 * gateway, so these constants have no effect; callers warn the administrator
 * rather than letting them believe a gateway is in use.
 *
 * Only non-empty constants count: 2.39 activated them with ifdef($name, ''),
 * and its sample conf.php offered `//define('2FA_SMS_URL', '');`, so an
 * uncommented-but-blank legacy block was inert then and must stay inert now.
 */
function legacyTfaGatewayConstantsDefined(): bool
{
	static $defined = null;
	if ($defined === null) {
		$defined = false;
		// The settings FiveCentSmsV5Provider / CellcastSmsProvider /
		// MobileMessageSmsProvider / TemplateSmsProvider used to read as 2FA_*
		$names = [
			'2FA_SMS_URL', '2FA_SMS_URL_BALANCE', '2FA_SMS_POST_TEMPLATE', '2FA_SMS_HEADER_TEMPLATE',
			'2FA_SMS_RESPONSE_OK_REGEX', '2FA_SMS_RESPONSE_ERROR_REGEX', '2FA_SMS_RESPONSE_ID_REGEX',
			'2FA_SMS_CELLCAST_APIKEY',
			'2FA_SMS_5CENTSMS_URL', '2FA_SMS_5CENTSMS_APIKEY_ID', '2FA_SMS_5CENTSMS_APIKEY',
			'2FA_SMS_MOBILEMESSAGE_URL', '2FA_SMS_MOBILEMESSAGE_APIKEY_USERNAME', '2FA_SMS_MOBILEMESSAGE_APIKEY_PASSWORD',
		];
		foreach ($names as $name) {
			if (defined($name) && (string) constant($name) !== '') {
				$defined = true;
				break;
			}
		}
	}
	return $defined;
}

/**
 * The current (logged-in) user's email — what the `_USER_EMAIL_` template
 * keyword resolves to. Null when nobody is logged in (CLI, cron, pre-2FA
 * login) or the user has no email.
 */
function getCurrentUserEmail(): ?string
{
	$currentUser = isset($GLOBALS['user_system']) ? $GLOBALS['user_system']->getCurrentUser() : null;
	return !empty($currentUser['email']) ? (string) $currentUser['email'] : null;
}

/**
 * Check whether the configured SMS provider's POST_TEMPLATE uses the _USER_MOBILE_ keyword.
 */
function usesUserMobile(): bool
{
	$providerResult = getSmsProvider();
	if ($providerResult->isFailure()) return false;
	$provider = $providerResult->getValue();
	return $provider instanceof \Sms\Providers\TemplateSmsProvider && $provider->usesUserMobile();
}
