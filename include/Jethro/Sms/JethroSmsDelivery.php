<?php

namespace Jethro\Sms;

use Sms\SmsDelivery;

/**
 * SmsDelivery decorator that adds Jethro-context fields: the person ID
 * and the smsdelivery database row ID.
 *
 * Produced by DbLoggingSmsProvider::send() so callers can build person→smsId
 * maps without a separate database query.
 */
final readonly class JethroSmsDelivery extends \Sms\SmsDelivery
{
	private ?int $recipientPersonId;
	private ?int $databaseId;
	public function __construct(
		\Sms\SmsDelivery $inner,
		?int $recipientPersonId,
		?int $databaseId,
	) {
		parent::__construct(
			recipient: $inner->recipient(),
			status: $inner->status(),
			remoteId: $inner->remoteId(),
			rawResponse: $inner->rawResponse(),
			message: $inner->message(),
			statusDetail: $inner->statusDetail(),
			deliveryTimestamp: $inner->deliveryTimestamp(),
			sendTimestamp: $inner->sendTimestamp(),
			segmentsUsed: $inner->segmentsUsed(),
			perSegmentCost: $inner->perSegmentCost(),
		);
		$this->recipientPersonId = $recipientPersonId;
		$this->databaseId = $databaseId;
	}

	/** The Jethro person ID of the recipient. */
	public function recipientPersonId(): ?int { return $this->recipientPersonId; }

	/** The smsdelivery.id database row for this delivery. */
	public function databaseId(): ?int { return $this->databaseId; }
}
