<?php

namespace Jethro\Sms;

use Sms\PhoneNumber;
use Sms\SmsRecipient;


/**
 * A person (sender or recipient) with a phone number and Jethro person ID.
 *
 * $selectedPersonIds records who the sender actually picked when this person
 * is a recipient because of someone else: with "the adults in the selected
 * persons' families", the selected members of this person's family. It feeds
 * the %selected.firstname%, %selected.lastname% and %selected.fullname% tokens
 * (recipientTokens()). Empty when the person was selected directly; the
 * tokens then give the recipient's own names.
 *
 * @see getRecipientsFromPersonRecords()
 */
final readonly class JethroSmsRecipient implements SmsRecipient
{
	/**
	 * @param int              $personId           Jethro person ID of this person
	 * @param \Sms\PhoneNumber $number             The number to send to
	 * @param list<int>        $selectedPersonIds  IDs of the selected persons this person is a
	 *                                             recipient for; [] when selected directly
	 */
	public function __construct(
		public int              $personId,
		public \Sms\PhoneNumber $number,
		public array            $selectedPersonIds = [],
	)
	{}

    public function __toString(): string
    {
        return $this->number->value;
    }

	public function getPhoneNumber(): PhoneNumber
	{
		return $this->number;
	}
}
