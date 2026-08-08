<?php

/**
 * Status panel for the SMS Gateway section of the system configuration page.
 * Fetched via AJAX from ?call=admin_statuspanel_sms.
 */

require_once __DIR__ . '/call_admin_statuspanel.class.php';

class Call_Admin_Statuspanel_Sms extends Call_Admin_Statuspanel
{
    /**
     * Sender-number entries behind the two "Sender Numbers (…)" detail rows,
     * filled by getStatus(): row label => list of
     * `['number' => string, 'registeredAt' => ?int]`.
     *
     * getStatus()'s detail values are plain text (renderer contract), so the
     * structured form rides on the instance and detailsTable() renders links
     * from it — no re-parsing of the joined strings.
     *
     * @var array<string, list<array{number: string, registeredAt: ?int}>>
     */
    private array $senderNumberRows = [];

    public function linkedFeature(): ?string
    {
        return 'SMS';
    }

    protected function isConfigured(): \Result
    {
        require_once JETHRO_ROOT . '/include/jethro_sms.php';
        $result = \Jethro\Sms\getSmsProvider();
        if ($result->isSuccess()) {
            return \Result::success(true);
        }
        $error = ['message' => $result->getError()];
        // Only a missing-constants failure has a list of constants to show.
        $help = \Jethro\Sms\renderNotConfiguredHelp();
        if ($help !== null) {
            $error['details'] = $help;
        }
        return \Result::failure($error);
    }

    protected function getHelpText(): string
    {
        return 'An external SMS provider may be configured to allow staff to send SMS messages.';
    }

    protected function getStatus(): array
    {
        $details = [];
        $testModeSuffix = filter_var(ifdef('SMS_TESTMODE', false), FILTER_VALIDATE_BOOLEAN)
            ? ' — test mode (no real SMSes sent)'
            : '';

        $providerResult = \Jethro\Sms\getSmsProvider();
        if ($providerResult->isFailure()) {
            return [
                'success' => false,
                'message' => 'SMS gateway is not available.',
                'details' => ['Error' => $providerResult->getError()],
            ];
        }

        /** @var \Sms\SmsProvider $provider */
        $provider = $providerResult->getValue();
        $details['Provider'] = $provider->getDescription();

        $operationalResult = $provider->isOperational();
        if ($operationalResult->isFailure() || $operationalResult->getValue() !== true) {
            $reason = $operationalResult->isFailure()
                ? $operationalResult->getError()
                : 'Provider did not respond';
            return [
                'success' => false,
                'message' => 'SMS gateway is not reachable — ' . $reason,
                'details' => ['Provider' => $provider->getDescription()],
            ];
        }

        $message = 'Operational' . $testModeSuffix;

        if ($provider->hasCapability(\Sms\SmsCapability::GET_BALANCE)) {
            $balance = \Jethro\Sms\getSmsBalance();
            if ($balance !== null) {
                $message = 'Operational' . $testModeSuffix . ', ' . $balance . ' SMSes remaining';
                $details['Credits remaining'] = (string) $balance;
            } else {
                $details['Credits remaining'] = 'Unknown';
            }
        } else {
            $details['Credits remaining'] = '(not supported by provider)';
        }

        if ($provider->hasCapability(\Sms\SmsCapability::GET_SENDER_IDS)) {
            $senderIdsResult = $provider->getSenderIds(getAll: true);
            if ($senderIdsResult->isSuccess()) {
                $senderIds = $senderIdsResult->getValue();
                if ($senderIds !== []) {
                    $approved = [];
                    $notApproved = [];
                    $unknown = [];
                    foreach ($senderIds as $s) {
                        $value = $s instanceof \Sms\SenderID ? $s->value : (string) $s;
                        if ($s instanceof \Sms\SenderID && $s->acmaApproved === true) {
                            $approved[] = $value;
                        } elseif ($s instanceof \Sms\SenderID && $s->acmaApproved === false) {
                            $notApproved[] = $value;
                        } else {
                            $unknown[] = $value;
                        }
                    }
                    if ($approved !== []) {
                        $details['Sender IDs'] = implode(', ', $approved);
                    }
                    if ($notApproved !== []) {
                        $details['Sender IDs (not approved)'] = implode(', ', $notApproved);
                    }
                    if ($unknown !== []) {
                        $details['Sender IDs (ACMA status unknown)'] = implode(', ', $unknown);
                    }
                    if ($approved === [] && $notApproved === [] && $unknown === []) {
                        $details['Sender IDs'] = 'None returned';
                    }
                } else {
                    $details['Sender IDs'] = 'None returned';
                }
            } else {
                $details['Sender IDs'] = 'Error: ' . $senderIdsResult->getError();
            }
        } else {
            $details['Sender IDs'] = '(not supported by provider)';
        }

        // Rich per-number records (status + registration date) when the
        // provider exposes them; the plain approved-number list otherwise.
        // The point of records here is surfacing stale pending entries — a
        // number attempted long ago that still sits upstream unapproved and
        // blocks re-registration — which the approved list alone hides.
        $recordsResult = $provider->getSenderNumberRecords();
        if ($recordsResult->isSuccess()) {
            $records = array_values(array_filter(
                $recordsResult->getValue(),
                static fn (\Sms\SenderNumberRecord $r) => $r->isPhoneNumber(),
            ));
            $approved = array_values(array_filter($records, static fn (\Sms\SenderNumberRecord $r) => $r->isApproved()));
            $unapproved = array_values(array_filter($records, static fn (\Sms\SenderNumberRecord $r) => !$r->isApproved()));

            $this->senderNumberRows = [
                'Registered Sender Numbers' => array_map(
                    static fn (\Sms\SenderNumberRecord $r) => ['number' => $r->value, 'registeredAt' => $r->registeredAt],
                    $approved,
                ),
                'Unregistered Sender Numbers' => array_map(
                    static fn (\Sms\SenderNumberRecord $r) => ['number' => $r->value, 'registeredAt' => $r->registeredAt],
                    $unapproved,
                ),
            ];

            $details['Registered Sender Numbers'] = $approved === []
                ? 'None returned'
                : implode(', ', array_map(static fn (\Sms\SenderNumberRecord $r) => $r->value, $approved));

            $details['Unregistered Sender Numbers'] = $unapproved === []
                ? 'None'
                : implode(', ', array_map(
                    static fn (\Sms\SenderNumberRecord $r) => $r->registeredAt !== null
                        ? $r->value . ' (attempted ' . date('Y-m-d', $r->registeredAt) . ')'
                        : $r->value,
                    $unapproved,
                ));
        } else {
            $senderNumbersResult = $provider->getSenderNumbers();
            if ($senderNumbersResult->isSuccess()) {
                $numbers = $senderNumbersResult->getValue();
                $this->senderNumberRows = [
                    'Registered Sender Numbers' => array_map(
                        static fn (\Sms\PhoneNumber $n) => ['number' => $n->value, 'registeredAt' => null],
                        $numbers,
                    ),
                ];
                if ($numbers !== []) {
                    $details['Registered Sender Numbers'] = implode(', ', array_map(
                        static fn (\Sms\PhoneNumber $n) => $n->value,
                        $numbers,
                    ));
                } else {
                    $details['Registered Sender Numbers'] = 'None returned';
                }
            } else {
                $details['Registered Sender Numbers'] = 'Error: ' . $senderNumbersResult->getError();
            }
        }

        return [
            'success' => true,
            'message' => $message,
            'details' => $details,
        ];
    }

    /**
     * Details table with person hyperlinks and per-number delete crosses.
     *
     * The parent escapes every value (see the getStatus() plain-text
     * contract), so markup can't ride inside a detail value.  This override
     * keeps the escaping and, for the sender-number rows, renders each
     * entry from the structured $senderNumberRows as a list item: every
     * matching person's name (each linked to their record, number on
     * hover), slash-joined when several persons share the mobile,
     * otherwise the raw number; unregistered entries carry their attempted
     * date; and, when the provider can delete, a red delete cross using the
     * chooser's `delete-chosen-person` icon.  That CSS is scoped to
     * `li div.delete-chosen-person`, hence the <ul>/<li> markup — a bare
     * div gets no icon at all.  The cross Datastar-@gets the
     * deleteSenderNumber confirmation form into the operations container,
     * which morphs by container ID from anywhere on the page.
     */
    protected function detailsTable(array $details): string
    {
        $providerResult = \Jethro\Sms\getSmsProvider();
        $deleteCapable = $providerResult->isSuccess()
            && $providerResult->getValue()->hasCapability(\Sms\SmsCapability::DELETE_SENDER_NUMBER);

        $html = '';
        foreach ($details as $label => $value) {
            $controls = ents($value);
            if (isset($this->senderNumberRows[$label]) && $this->senderNumberRows[$label] !== []) {
                $items = '';
                foreach ($this->senderNumberRows[$label] as $entry) {
                    $persons = $this->findPersonsByMobile($entry['number']);
                    if ($persons !== []) {
                        $links = [];
                        foreach ($persons as $person) {
                            // Person name as the display text, number on hover.
                            $links[] = '<a href="' . ents('?view=persons&personid=' . $person['id'])
                                . '" title="' . ents($entry['number']) . '">' . ents($person['name']) . '</a>';
                        }
                        $piece = implode(' / ', $links);
                    } else {
                        $piece = ents($entry['number']);
                    }
                    if ($label === 'Unregistered Sender Numbers' && $entry['registeredAt'] !== null) {
                        $piece .= ' ' . ents('(attempted ' . date('Y-m-d', $entry['registeredAt']) . ')');
                    }

                    // The cross follows the text with a single space.  The
                    // chooser CSS floats it right (`float:right`), which is
                    // right for its own list but wrong here, so float/position
                    // are neutralised inline; the icon, size and pointer
                    // cursor still come from the `delete-chosen-person` rule
                    // (which requires an <li> ancestor — hence the list).
                    $cross = '';
                    if ($deleteCapable) {
                        $url = ents('?call=admin_statuspanel_operation_sms&operation=deleteSenderNumber&number=' . $entry['number']);
                        $cross = '<div class="delete-chosen-person" title="Delete sender number"'
                            . ' style="float:none;display:inline-block;position:static;margin:0"'
                            . ' data-on:click="@get(\'' . $url . '\')"></div>';
                    }
                    $items .= '<li>' . $piece . ' ' . $cross . '</li>';
                }
                $controls = '<ul class="unstyled sms-sender-numbers">' . $items . '</ul>';
                if ($label === 'Unregistered Sender Numbers') {
                    $controls .= '<p class="help-block">'
                        . ents('A registration was attempted for these numbers, but the number was never verified, so it cannot currently be used as a sender. Re-registering only re-sends the verification link; deleting a stale entry clears it from the account.')
                        . '</p>';
                }
            }
            $html .= '<div class="control-group">'
                . '<label class="control-label">' . ents($label) . '</label>'
                . '<div class="controls">' . $controls . '</div>'
                . '</div>';
        }
        return $html;
    }

    /**
     * Find the non-archived persons whose mobile matches $number, in any of
     * the common formats — matching uses the same last-9-digits rule as SMS
     * recipients (PhoneNumber::sameNumberAs).  The mobile→persons map is
     * built once per request; several persons sharing a number all match —
     * the caller shows them all.
     *
     * @return list<array{id: int, name: string}>
     */
    private function findPersonsByMobile(string $number): array
    {
        static $byNumber = null;
        if ($byNumber === null) {
            $byNumber = [];
            $rows = $GLOBALS['system']->getDBObjectData('person', [
                '!mobile_tel' => '',
                '!(status' => \Person_Status::getArchivedIDs(),
            ], 'AND');
            foreach ($rows as $id => $row) {
                // mobile_tel is free-text; skip values with no digits at all
                // (PhoneNumber rejects them).
                if (!preg_match('/\d/', $row['mobile_tel'])) {
                    continue;
                }
                $num = new \Sms\PhoneNumber($row['mobile_tel']);
                $byNumber[$num->sameNumberKey()][] = [
                    'id' => (int) $id,
                    'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                ];
            }
        }
        $key = (new \Sms\PhoneNumber($number))->sameNumberKey();
        return $byNumber[$key] ?? [];
    }

    /** @return array<string, string>  method => label */
    protected function getOperations(): array
    {
        $providerResult = \Jethro\Sms\getSmsProvider();
        if ($providerResult->isFailure()) {
            return [];
        }

        $provider = $providerResult->getValue();
        $ops = [];

        if ($provider->hasCapability(\Sms\SmsCapability::REGISTER_SENDER_ID)) {
            $ops['registerSenderId'] = 'Register Sender ID';
        }

        if ($provider->hasCapability(\Sms\SmsCapability::DELETE_SENDER_NUMBER)) {
            $ops['deleteSenderNumber'] = 'Delete Sender Number';
        }


        // Sync history: bridge-level operation, offered only when the provider
        // can list account history.
        if ($provider->hasCapability(\Sms\SmsCapability::LIST_HISTORY)) {
            $ops['synchronizeHistory'] = 'Synchronize History';
        }

        return $ops;
    }
}
