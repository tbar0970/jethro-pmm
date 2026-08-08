<?php

namespace Jethro\Sms;

use Sms\SmsDeliveryBatch;


/**
 * SmsDeliveryBatch with Jethro context: batchId is always the sms.id of the
 * persisted send, deliveries are JethroSmsDelivery objects, and
 * $senderPersonId is sms.sender (null for script-initiated sends, in which
 * case only admins may cancel).
 */
final readonly class JethroSmsDeliveryBatch extends \Sms\SmsDeliveryBatch
{
	public function __construct(
		string $batchId,
		/** @var JethroSmsDelivery[] */
		array $deliveries,
		public ?int $senderPersonId,
	) {
		parent::__construct($batchId, $deliveries);
	}
}
