<?php

namespace Jethro\Sms\Providers;

use Jethro\Sms\JethroSmsDelivery;
use Jethro\Sms\JethroSmsDeliveryBatch;
use Jethro\Sms\JethroSmsRecipient;
use Sms\ContactPhoneNumber;
use Sms\DecoratingSmsProvider;
use Sms\PhoneNumber;
use Sms\RegistrationStep;
use Sms\SmsDelivery;
use Sms\SmsDeliveryBatch;
use Sms\SmsProvider;
use Sms\SmsSender;
use function Jethro\Sms\getAvailableTokens;
use function Jethro\Sms\insertSms;

/**
 * SmsProvider decorator that logs sends to the sms/smsdelivery tables
 * and caches updateDelivery()/cancel() results in smsdelivery.
 *
 * On send(), inserts a sms row and per-recipient smsdelivery rows,
 * then wraps SmsDelivery objects in JethroSmsDelivery with person IDs
 * and database IDs for downstream callers.
 *
 * On updateDelivery(), records the latest status (and the provider's status
 * JSON in smsdelivery.raw_response — never overwriting it with an empty value).
 * Subsequent calls for the same delivery skip the upstream API.
 *
 * Reads and writes the _smsdelivery table, not the restricted smsdelivery
 * view: logging and status caching are system-level, whoever triggers them
 * (see db_objects/smsdelivery.class.php).
 */
final class DbLoggingSmsProvider extends \Sms\DecoratingSmsProvider
{
		public function __construct(\Sms\SmsProvider $inner)
	{
		parent::__construct($inner);
	}

	public function send(
		array $entries,
		\Sms\SmsSender $sender,
		?int $sendAt = null,
		bool $preview = false,
	): \Result
	{
		// Pre-flight: if any entry's message contains known %tokens%,
		// every recipient in that entry must be a JethroSmsRecipient so
		// we can resolve person data.
		foreach ($entries as $entry) {
			if (\Sms\messageHasTokens($entry['message'], getAvailableTokens())) {
				foreach ($entry['recipients'] as $r) {
					if (!$r instanceof JethroSmsRecipient) {
						return \Result::failure(
							'Token expansion requires JethroSmsRecipient recipients. '
							. 'Got ' . get_debug_type($r) . '. '
							. 'Use person IDs instead of raw phone numbers, or avoid %tokens% in the message.'
						);
					}
				}
			}
		}

		$result = parent::send($entries, $sender, $sendAt, $preview);
		if ($result->isFailure()) {
			return $result;
		}

		/** @var \Sms\SmsDeliveryBatch $innerBatch */
		$innerBatch = $result->getValue();
		$deliveries = $innerBatch->deliveries;

		// Flatten all entries into one recipient list. Deliveries arrive in
		// entry order, so positional pairing (improvement 39) matches correctly.
		$allRecipients = [];
		$allPersonIds  = [];
		foreach ($entries as $entry) {
			foreach ($entry['recipients'] as $r) {
				$allRecipients[] = $r;
				$allPersonIds[] = ($r instanceof JethroSmsRecipient) ? $r->personId : null;
			}
		}

		if ($preview) {
			$wrapped = [];
			foreach ($deliveries as $i => $d) {
				$pid = $allPersonIds[$i] ?? null;
				$wrapped[] = new JethroSmsDelivery(
					inner: $d,
					recipientPersonId: $pid,
					databaseId: null,
				);
			}
			return \Result::success(new \Sms\SmsDeliveryBatch(null, $wrapped));
		}

		// Opted-out recipients (OptOutSmsProvider) were never sent to: they
		// stay in the returned batch for reporting but are not recorded —
		// and when nobody was sent to, there is no sms row at all.
		$logDeliveries = [];
		$logRecipients = [];
		foreach ($deliveries as $i => $d) {
			if ($d->status() !== \Sms\SmsStatus::OPTED_OUT) {
				$logDeliveries[] = $d;
				if (isset($allRecipients[$i])) {
					$logRecipients[] = $allRecipients[$i];
				}
			}
		}

		// One sms row for the entire batch.
		$senderId = $GLOBALS['user_system']->getCurrentUser()['id'] ?? null;
		$insertResult = $logDeliveries === [] ? ['smsId' => 0, 'deliveries' => []] : insertSms(
			$entries[0]['template'] ?? $entries[0]['message'] ?? '',
			$senderId,
			$logDeliveries,
			$logRecipients,
			$sendAt,
			$this->getKey(),
			(string) $sender,
		);
		$personToDeliveryId = $insertResult['deliveries'];

		$wrapped = [];
		foreach ($deliveries as $i => $d) {
			$pid = $allPersonIds[$i] ?? null;
			$wrapped[] = new JethroSmsDelivery(
				inner: $d,
				recipientPersonId: $pid,
				databaseId: $pid !== null ? ($personToDeliveryId[(string)$pid] ?? null) : null,
			);
		}
		$smsId = $insertResult['smsId'] ?? 0;
		if ($smsId > 0) {
			return \Result::success(new JethroSmsDeliveryBatch((string)$smsId, $wrapped, $senderId === null ? null : (int)$senderId));
		}
		return \Result::success(new \Sms\SmsDeliveryBatch(null, $wrapped));
	}

	public function updateDelivery(\Sms\SmsDelivery $delivery): \Result
	{
		$remoteId = $delivery->remoteId();
		if ($remoteId === null) {
			return \Result::failure('No remote ID on delivery');
		}

		$db = $GLOBALS['db'];

		// Check if already final — return early without an upstream round-trip.
		try {
			$row = $db->queryRow(
				'SELECT status, delivered_at FROM _smsdelivery WHERE remote_id = ' . $db->quote($remoteId) . $this->providerScopeSql()
			);

			if ($row) {
				$status = \Sms\SmsStatus::fromMySql((string) ($row['status'] ?? ''));
				if ($status->isFinal()) {
					$delTs = !empty($row['delivered_at']) ? strtotime($row['delivered_at']) : null;
					return \Result::success($delivery->with(
						status: $status,
						deliveryTimestamp: $delTs ?: null,
					));
				}
			}
		} catch (\PDOException $e) {
			// Table doesn't exist — skip cache, fetch from upstream
		}

		// Fetch from upstream
		$result = parent::updateDelivery($delivery);
		if ($result->isFailure()) {
			return $result;
		}

		/** @var \Sms\SmsDelivery $updated */
		$updated = $result->getValue();

		$status = $updated->status()->toMySql();
		$rawResponse = $updated->rawResponse();

		try {
			$db->exec(
				'UPDATE _smsdelivery SET status = ' . $db->quote($status)
				. ($rawResponse !== '' ? ', raw_response = ' . $db->quote($rawResponse) : '')
				. self::deliveredAtAssignment($updated, $status)
				. self::costAssignments($updated)
				. ' WHERE remote_id = ' . $db->quote($remoteId) . $this->providerScopeSql()
			);
		} catch (\PDOException $e) {
			// Table doesn't exist — skip cache write
		}

		return \Result::success($updated);
	}

	/**
	 * List the provider's recent deliveries, recording their statuses in _smsdelivery.
	 *
	 * @return \Result<\Sms\SmsDelivery[], string>
	 */
	public function listRecentDeliveries(?int $since = null): \Result
	{
		$result = parent::listRecentDeliveries($since);
		if ($result->isSuccess()) {
			$this->persistStatuses($result->getValue());
		}
		return $result;
	}

	/**
	 * Persist delivery status changes to smsdelivery so that page refreshes
	 * show the correct status without waiting for AJAX polling.
	 *
	 * For each delivery with a non-null remoteId, updates smsdelivery.status,
	 * raw_response, and delivered_at.  PDOException on any individual update
	 * is silently swallowed (table may not exist in all environments).
	 *
	 * @param \Sms\SmsDelivery[] $deliveries
	 */
	private function persistStatuses(array $deliveries): void
	{
		$db = $GLOBALS['db'];
		foreach ($deliveries as $delivery) {
			$remoteId = $delivery->remoteId();
			if ($remoteId === null) {
				continue;
			}
			try {
				$status = $delivery->status()->toMySql();
				$rawResponse = $delivery->rawResponse();

				$db->exec(
					'UPDATE _smsdelivery SET status = ' . $db->quote($status)
					. ($rawResponse !== '' ? ', raw_response = ' . $db->quote($rawResponse) : '')
					. self::deliveredAtAssignment($delivery, $status)
					. self::costAssignments($delivery)
					. ' WHERE remote_id = ' . $db->quote($remoteId) . $this->providerScopeSql()
				);
			} catch (\PDOException $e) {
				// Table doesn't exist — skip cache write
			}
		}
	}

	/**
	 * When to record a delivery as delivered.
	 *
	 * Uses the carrier's timestamp when the provider reports one. MobileMessage
	 * reports none (MobileMessageSmsProvider::parseDeliveryResponse(): a
	 * DELIVERED status alone is authoritative), so the time of the status check
	 * that first saw it delivered is recorded instead.
	 *
	 * @param \Sms\SmsDelivery $delivery  Latest status from the provider
	 * @param int|null $now  Unix time of this status check (defaults to now)
	 * @return string|null  MySQL datetime, or null when the delivery isn't delivered
	 * @see deliveredAtAssignment()
	 */
	public static function observedDeliveredAt(\Sms\SmsDelivery $delivery, ?int $now = null): ?string
	{
		if ($delivery->status() !== \Sms\SmsStatus::DELIVERED) {
			return null;
		}
		return date('Y-m-d H:i:s', $delivery->deliveryTimestamp() ?? ($now ?? time()));
	}

	/**
	 * SQL SET fragment recording when a delivery was confirmed delivered.
	 *
	 * COALESCE keeps the first value: batch polls revisit every delivery of the
	 * last STATUS_POLL_WINDOW_SECS, so a later poll must not move the timestamp.
	 *
	 * @param string $status  MySQL status value the delivery is being set to
	 * @see observedDeliveredAt()
	 */
	private static function deliveredAtAssignment(\Sms\SmsDelivery $delivery, string $status): string
	{
		if ($status !== \Sms\SmsStatus::DELIVERED->toMySql()) {
			return '';
		}
		$deliveredAt = self::observedDeliveredAt($delivery);
		return ', delivered_at = COALESCE(delivered_at, ' . $GLOBALS['db']->quote($deliveredAt) . ')';
	}

	/**
	 * SQL SET fragment recording the segments/price a provider reported for a
	 * delivery. Only reported values are written, so a status poll that
	 * doesn't include them never erases what the send recorded.
	 * Both fields are guarded on cost_bearer: recipients sharing a number
	 * share the delivery's remote_id, so this UPDATE matches every row of the
	 * group and only the bearer row (which carries the charge) may take the
	 * provider's count — the non-bearer rows must keep their explicit 0.
	 * See docs/docs/developer/sms/database.mdx "Cost tracking".
	 */
	private static function costAssignments(\Sms\SmsDelivery $delivery): string
	{
		$sql = '';
		if ($delivery->segmentsUsed() !== null) {
			$sql .= ', segments_used = IF(cost_bearer, ' . (int) $delivery->segmentsUsed() . ', segments_used)';
		}
		if ($delivery->perSegmentCost() !== null) {
			$sql .= ', per_segment_cost = IF(cost_bearer, ' . number_format($delivery->perSegmentCost(), 5, '.', '') . ', per_segment_cost)';
		}
		return $sql;
	}

	/**
	 * SQL predicate restricting a remote_id match to rows this provider sent:
	 * remote IDs are only unique per upstream API, so after a provider switch
	 * another provider's row could share the ID.
	 *
	 * Rows are logged with getKey() (NULL when the API is unknown — see
	 * insertSms()). NULL also covers deliveries logged before the column
	 * existed: those belong to whichever provider is polling them, so they
	 * stay in scope rather than losing status updates after the upgrade.
	 */
	private function providerScopeSql(): string
	{
		$key = $this->getKey();
		return $key === null
			? ' AND provider IS NULL'
			: ' AND (provider = ' . $GLOBALS['db']->quote($key) . ' OR provider IS NULL)';
	}

	/**
	 * Cancel the deliveries in the batch, then record the outcome in _smsdelivery.
	 *
	 * Only deliveries the provider reports a new status for are written
	 * (cancelled, or e.g. sent when the cancel came too late). A refused cancel
	 * returns the delivery unchanged — still SCHEDULED — and writing that back
	 * could overwrite a newer status a concurrent poll has just stored (SMS
	 * review Q7); it is left for the next status poll.
	 *
	 * @return \Result<\Sms\SmsDeliveryBatch, string>
	 */
	public function cancel(\Sms\SmsDeliveryBatch $batch): \Result
	{
		// Recipients sharing a number share the group's remote_id — cancelling
		// the same upstream message once is enough. Deduplicate by remote_id
		// for the upstream call, then fan each outcome back out so the
		// returned batch matches the input delivery-for-delivery.
		$first = [];
		$outcomeIndex = [];
		foreach ($batch->deliveries as $delivery) {
			$remoteId = $delivery->remoteId();
			if ($remoteId !== null && isset($outcomeIndex[$remoteId])) {
				continue;
			}
			if ($remoteId !== null) {
				$outcomeIndex[$remoteId] = count($first);
			}
			$first[] = $delivery;
		}

		$result = parent::cancel(new \Sms\SmsDeliveryBatch($batch->batchId, $first));
		if ($result->isFailure()) {
			return $result;
		}

		/** @var \Sms\SmsDeliveryBatch $cancelledBatch */
		$cancelledBatch = $result->getValue();
		$merged = [];
		foreach ($batch->deliveries as $delivery) {
			$remoteId = $delivery->remoteId();
			$merged[] = ($remoteId !== null && isset($outcomeIndex[$remoteId]))
				? $cancelledBatch->deliveries[$outcomeIndex[$remoteId]]
				: $delivery;
		}
		$this->persistStatuses(array_filter(
			$merged,
			static fn(\Sms\SmsDelivery $d) => $d->status() !== \Sms\SmsStatus::SCHEDULED,
		));
		return \Result::success(new \Sms\SmsDeliveryBatch($cancelledBatch->batchId, $merged));
	}

	/**
	 * Whether $number is verified as a sender for the current user.
	 *
	 * A number belongs to the first person verified for it
	 * (sms_registered_sender.personid), so a user who sets their own mobile to
	 * someone else's verified number can't send as them (SMS review Q9):
	 *  - owned by the current user → verified;
	 *  - owned by someone else → not verified, whatever the provider says;
	 *  - recorded without an owner (before ownership existed) → claimed;
	 *  - not recorded → the provider decides (session cache or upstream), and
	 *    if it says verified, the current user claims the number.
	 */
	public function verifySenderNumber(\Sms\PhoneNumber $number): \Result
	{
		$db = $GLOBALS['db'];
		$me = self::currentPersonId();
		try {
			$owner = $db->queryOne(
				'SELECT COALESCE(personid, 0) FROM sms_registered_sender WHERE ' . self::sameNumberSql('phone', $number->value)
			);
		} catch (\PDOException $e) {
			return $this->inner->verifySenderNumber($number);	// table doesn't exist yet
		}

		if ($owner !== false && $owner !== null) {
			if ((int) $owner === 0 && $me > 0) {
				return \Result::success($this->claim($number->value, $me));
			}
			return \Result::success((int) $owner === $me && $me > 0);
		}

		$result = $this->inner->verifySenderNumber($number);
		if ($result->isSuccess() && $result->getValue() === true && $me > 0) {
			return \Result::success($this->claim($number->value, $me));
		}
		return $result;
	}

	public function registerSenderNumber(?\Sms\ContactPhoneNumber $contact = null, ?array $validationParams = null): \Result
	{
		$result = $this->inner->registerSenderNumber($contact, $validationParams);
		if ($result->isSuccess()) {
			$step = $result->getValue();
			// Record the current user as the number's owner once registration is
			// complete (Phase 2 or immediate) — unless someone else owns it.
			$phone = $contact?->phoneNumber->value ?? $step->number ?? null;
			$me = self::currentPersonId();
			if ($step instanceof \Sms\RegistrationStep && $step->registered && $phone !== null && $me > 0) {
				try {
					$this->claim($phone, $me);
				} catch (\PDOException $e) {
					// Table doesn't exist — session cache still applies
				}
			}
		}
		return $result;
	}

	/**
	 * Record $personId as the owner of $phone unless another person owns it
	 * (first person wins). Returns whether $personId owns it afterwards.
	 */
	private function claim(string $phone, int $personId): bool
	{
		$db = $GLOBALS['db'];
		// Numbers are matched by their last nine digits (the providers' own
		// comparison), so "0491…" and "61491…" are the same handset. Reuse an
		// existing row in another format rather than adding a second one.
		$existing = $db->queryOne(
			'SELECT phone FROM sms_registered_sender WHERE ' . self::sameNumberSql('phone', $phone)
		);
		if (is_string($existing) && $existing !== '') {
			$db->exec('UPDATE sms_registered_sender SET personid = ' . $personId
				. ' WHERE phone = ' . $db->quote($existing) . ' AND personid IS NULL');
		} else {
			$db->exec('INSERT IGNORE INTO sms_registered_sender (phone, personid) VALUES ('
				. $db->quote($phone) . ', ' . $personId . ')');
		}
		return (int) $db->queryOne(
			'SELECT COALESCE(personid, 0) FROM sms_registered_sender WHERE ' . self::sameNumberSql('phone', $phone)
		) === $personId;
	}

	/**
	 * SQL predicate matching a stored number by the same key the providers use
	 * (last nine digits; mirrors PhoneNumber::sameNumberKey()).
	 */
	private static function sameNumberSql(string $column, string $number): string
	{
		$digits = preg_replace('/\D/', '', $number);
		$key = strlen($digits) < 9 ? $digits : substr($digits, -9);
		return 'SUBSTRING(' . $column . ', -9) = ' . $GLOBALS['db']->quote($key);
	}

	/** The logged-in user's person ID, or 0 (CLI, cron, not logged in). */
	private static function currentPersonId(): int
	{
		return isset($GLOBALS['user_system']) ? (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0) : 0;
	}
}
