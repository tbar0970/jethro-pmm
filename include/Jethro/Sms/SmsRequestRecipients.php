<?php

namespace Jethro\Sms;


/**
 * Return value of {@see getRecipientsFromRequest()}.
 *
 * Splits the requested recipients into categories so callers can report
 * skips and failures alongside the sendable set.
 *
 * All `array` fields are person DB rows keyed by int person ID, with keys
 * like `id`, `first_name`, `last_name`, `mobile_tel`.  Only `$recipients`
 * is typed as {@see JethroSmsRecipient} objects.
 *
 * @see call_sms.class.php
 */
final readonly class SmsRequestRecipients
{
    /**
     * @param JethroSmsRecipient[] $recipients       Active people with a mobile — opted-out ones included; OptOutSmsProvider skips them at send time
     * @param array<int, array>    $blanks           Person rows with no mobile_tel, keyed by person ID
     * @param array<int, array>    $archived         Person rows that are archived, keyed by person ID
     * @param array<int, array>    $rawPersonRecords Person rows for the recipients, keyed by person ID — used for display/name lookup
     */
    public function __construct(
        public array $recipients,
        public array $blanks,
        public array $archived,
        public array $rawPersonRecords,
    ) {}
}