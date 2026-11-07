<?php

class Call_SMS extends Call
{
    function run(): void
    {
        $datastar = !empty($_SERVER['HTTP_DATASTAR_REQUEST']);
        if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
            $refusal = ['error' => 'You do not have permission to send SMS messages.'];
            if ($datastar) {
                self::_respondDatastar($refusal);
            } else {
                echo json_encode($refusal);
            }
            return;
        }
        try {
            $result = $this->_send();
        } catch (\Throwable $e) {
            if (!$datastar) throw $e;
            // A Datastar request shows only what the SSE response patches in,
            // so an uncaught error would otherwise vanish without a trace.
            error_log('Call_SMS: ' . $e);
            $result = ['error' => 'Error sending SMS: ' . $e->getMessage()];
        }
        // Whether the result may link to Persons → Messages ("SMS - view all").
        $result['can_view_history'] = $GLOBALS['user_system']->havePerm(PERM_VIEWSMS);
        if ($datastar) {
            self::_respondDatastar($result);
        } else {
            echo json_encode($result);
        }
    }

    /**
     * Send the SMS described by the request ($_POST: personid / personid[],
     * sms_type, message, message_overrides, sender, send_at, saveasnote,
     * note_subject, note_action_date, note_type) and return the outcome.
     * The caller has checked PERM_SENDSMS.
     *
     * @return array<string, mixed> 'error' (string) when nothing was sent;
     *     otherwise any of 'sent', 'failed', 'failed_blank', 'failed_archived',
     *     'failed_opted_out' (each ['count' => int, 'recipients' => person ID =>
     *     person row]) and 'note_saved'. See Jethro\Sms\presentSendResult().
     */
    private function _send(): array
    {

        require_once JETHRO_ROOT . '/include/jethro_sms.php';

        $smsReq = \Jethro\Sms\getRecipientsFromRequest();
        $recips   = $smsReq->recipients;
        $blanks   = $smsReq->blanks;
        $archived = $smsReq->archived;
        $rawRecips = $smsReq->rawPersonRecords;

        $ajax = [];

        if (!empty($archived)) {
            $ajax['failed_archived']['count'] = count($archived);
            $ajax['failed_archived']['recipients'] = $archived;
        }
        if (!empty($blanks)) {
            $ajax['failed_blank']['count'] = count($blanks);
            $ajax['failed_blank']['recipients'] = $blanks;
        }

        if (!empty($recips)) {
            $message = array_get($_POST, 'message', '');

            // Per-person message overrides from the preview panel. A row
            // shared by people on one mobile (key '140_142', see previewRows())
            // overrides the one message they get, so it applies to each.
            $messageOverrides = [];
            foreach (\Jethro\Sms\messageOverridesFromRequest() as $rowKey => $msg) {
                foreach (explode('_', (string)$rowKey) as $pid) {
                    $messageOverrides[(int)$pid] = $msg;
                }
            }

            // Validate the template and each override against SMS_MAX_LENGTH
            $maxLen = (int) (ifdef('SMS_MAX_LENGTH') ?: 160);
            $tooLong = [];
            if (mb_strlen((string)$message) > $maxLen) {
                $tooLong[] = 'template message';
            }
            foreach ($messageOverrides as $pid => $msg) {
                if (mb_strlen($msg) > $maxLen) {
                    $tooLong[] = $rawRecips[$pid]['first_name'] ?? "person #$pid";
                }
            }

            if (empty($message)) {
                $ajax['error'] = "Empty message";
            } elseif ($tooLong !== []) {
                $ajax['error'] = 'Message too long (limit ' . $maxLen . ' characters) for: '
                    . implode(', ', $tooLong);
            } else {
                $sender = \Jethro\Sms\getSenderFromRequest();
                if ($sender === null) {
                    $ajax['error'] = 'No valid sender configured. Check SMS_SENDER_OPTIONS, SMS_SENDER, or your mobile number.';
                    return $ajax;
                }
                $senderError = \Jethro\Sms\senderNotVerifiedError($sender);
                if ($senderError !== null) {
                    $ajax['error'] = $senderError;
                    return $ajax;
                }
                $sendAtRaw = !empty($_POST['send_at']) ? $_POST['send_at'] : null;
                $sendAt = ($sendAtRaw !== null) ? strtotime($sendAtRaw) : null;
                $sendAt = ($sendAt !== false && $sendAt > 0) ? $sendAt : null;

                set_time_limit(300);

                // Build entries: one entry per distinct message — the template
                // for everyone not overridden, then one per override text.
                // Grouping overrides by text keeps the people of a shared-mobile
                // preview row in one entry, so DeduplicatingSmsProvider still
                // sends them one message.
                $standard = [];
                $overridden = [];
                foreach ($recips as $r) {
                    $pid = $r instanceof \Jethro\Sms\JethroSmsRecipient ? $r->personId : null;
                    if ($pid !== null && isset($messageOverrides[$pid])) {
                        $text = $messageOverrides[$pid];
                        $overridden[$text] ??= ['message' => $text, 'recipients' => []];
                        $overridden[$text]['recipients'][] = $r;
                    } else {
                        $standard[] = $r;
                    }
                }
                $entries = array_values($overridden);
                if ($standard !== []) {
                    array_unshift($entries, ['message' => $message, 'recipients' => $standard]);
                }
                // Deliveries come back in entry order.
                $orderedRecips = array_merge(...array_column($entries, 'recipients'));

                // Enforce the unicode policy on what will actually be sent:
                // token expansion can add characters the template lacks, and
                // each override is its own message. A preview pass expands
                // without calling the gateway.
                $statuslineConfig = \Jethro\Sms\makeStatuslineConfig();
                $previewResult = \Jethro\Sms\sendSms($entries, [], sender: $sender, preview: true, shortenUrls: \Jethro\Sms\shortenUrlsFromRequest());
                if ($previewResult->isSuccess()) {
                    foreach ($previewResult->getValue()->deliveries as $d) {
                        if ($d->status() === \Sms\SmsStatus::OPTED_OUT) {
                            continue;
                        }
                        $violation = \Sms\unicodePolicyViolation((string) $d->message(), $statuslineConfig);
                        if ($violation !== null) {
                            $ajax['error'] = $violation;
                            return $ajax;
                        }
                    }
                }

                $result = \Jethro\Sms\sendSms($entries, [], sender: $sender, sendAt: $sendAt, shortenUrls: \Jethro\Sms\shortenUrlsFromRequest());
                if ($result->isFailure()) {
                    $ajax['error'] = "Unable to send SMS\n" . $result->getError();
                    return $ajax;
                }

                /** @var \Jethro\Sms\JethroSmsDeliveryBatch|\Sms\SmsDeliveryBatch $smsBatch */
                $smsBatch = $result->getValue();
                /** @var \Jethro\Sms\JethroSmsDelivery[] $smsDeliveries */
                $smsDeliveries = $smsBatch->deliveries;

                // Opted-out people (OptOutSmsProvider) were never sent to:
                // report them on their own, and keep them out of the send
                // summary and the notes.  Deliveries pair with $orderedRecips
                // by position.
                $optedOut = [];
                $sentDeliveries = [];
                $sentRecips = [];
                foreach ($smsDeliveries as $i => $delivery) {
                    if ($delivery->status() === \Sms\SmsStatus::OPTED_OUT) {
                        $pid = $delivery->recipientPersonId();
                        if ($pid !== null) {
                            $optedOut[$pid] = $rawRecips[$pid] ?? ['first_name' => "Person #$pid", 'last_name' => ''];
                        }
                        continue;
                    }
                    $sentDeliveries[] = $delivery;
                    if (isset($orderedRecips[$i])) {
                        $sentRecips[] = $orderedRecips[$i];
                    }
                }
                if ($optedOut !== []) {
                    $ajax['failed_opted_out']['count'] = count($optedOut);
                    $ajax['failed_opted_out']['recipients'] = $optedOut;
                }

                // Build person→deliveryId map from JethroSmsDelivery objects
                $broadcastMap = [];
                foreach ($sentDeliveries as $delivery) {
                    $pid = $delivery->recipientPersonId();
                    $did = $delivery->databaseId();
                    if ($pid !== null && $did !== null) {
                        $broadcastMap[(string)$pid] = $did;
                    }
                }

                // Pass recipients in entry order so that delivery positions
                // align with recipient positions, enabling sendSummary()'s
                // positional matching for shared-mobile correctness.
                $summary = $sentDeliveries === [] ? null : \Sms\sendSummary($sentDeliveries, $sentRecips);

                // Server-side note creation — eliminates the data-loss window
                // that existed when the browser fired a second AJAX for notes.
                if (!empty($_POST['saveasnote'])) {
                    $noteType = $_POST['note_type'] ?? 'person';
                    $deliveryMessageByPid = [];
                    foreach ($smsDeliveries as $d) {
                        $dpid = $d->recipientPersonId();
                        $dmsg = $d->message();
                        if ($dpid !== null && $dmsg !== null) {
                            $deliveryMessageByPid[(int)$dpid] = $dmsg;
                        }
                    }

                    $noteSubject = !empty($_POST['note_subject'])
                        ? $_POST['note_subject']
                        : ((string) ifdef('SMS_SAVE_TO_NOTE_SUBJECT', '') ?: 'SMS follow-up');
                    $noteActionDate = !empty($_POST['note_action_date'])
                        ? $_POST['note_action_date']
                        : date('Y-m-d');

                    if ($noteType === 'family') {
                        $GLOBALS['system']->includeDBClass('family_note');
                        // Group person IDs by familyid so multiple recipients
                        // in the same family produce one family note, not N copies.
                        $families = [];
                        foreach ($broadcastMap as $personId => $deliveryId) {
                            $familyId = (int)($rawRecips[$personId]['familyid'] ?? 0);
                            if ($familyId) {
                                $families[$familyId][] = (int)$personId;
                            }
                        }
                        foreach ($families as $familyId => $pids) {
                            $firstPid = $pids[0];
                            $noteText = $messageOverrides[$firstPid]
                                ?? ($deliveryMessageByPid[$firstPid] ?? $message);
                            $note = new \Family_Note();
                            $note->setValue('familyid', $familyId);
                            $note->setValue('subject', $noteSubject);
                            $note->setValue('details', $noteText);
                            $note->setValue('status', 'pending');
                            $note->setValue('action_date', $noteActionDate);
                            // Family_Note::readyToCreate() blocks
                            // single-member families (≥2 required).
                            // Silently skip those — the family is too
                            // small for a family-scoped note.
                            if ($note->create()) {
                                // sms_note currently requires a
                                // person_note FK, so we skip the link
                                // for family notes. The note is already
                                // discoverable via the family record.
                            }
                        }
                    } else {
                        // Default: person-level notes (backward compatible).
                        $GLOBALS['system']->includeDBClass('person_note');
                        foreach ($broadcastMap as $personId => $deliveryId) {
                            $noteText = $messageOverrides[(int)$personId]
                                ?? ($deliveryMessageByPid[(int)$personId] ?? $message);
                            $note = new \Person_Note();
                            $note->setValue('personid', (int)$personId);
                            $note->setValue('subject', $noteSubject);
                            $note->setValue('details', $noteText);
                            $note->setValue('status', 'pending');
                            $note->setValue('action_date', $noteActionDate);

                            if ($note->create()) {
                                $db = $GLOBALS['db'];
                                $db->query(
                                    'INSERT IGNORE INTO sms_note (note_personid, note_id, smsdelivery_id) VALUES ('
                                    . $db->quote((string)$personId) . ', '
                                    . $db->quote((string)$note->id) . ', '
                                    . $db->quote((string)$deliveryId)
                                    . ')'
                                );
                            }
                        }
                    }
                    $ajax['note_saved'] = true;
                }

                // Convert to AJAX response.  No summary when everyone opted
                // out: failed_opted_out above is the whole result.
                if ($summary instanceof \Sms\AllSent) {
                    $ajax['sent']['count'] = count($summary->recipients);
                    $ajax['sent']['recipients'] = self::_recipientNames($summary->recipients, $rawRecips);
                    $ajax['sent']['confirmed'] = true;
                    $ajax['sent']['msgid'] = $broadcastMap;
                    $ajax['sent']['scheduled'] = ($sendAt !== null);
                } elseif ($summary instanceof \Sms\PartialSuccess) {
                    $ajax['sent']['count'] = count($summary->successes);
                    $ajax['sent']['recipients'] = self::_recipientNames($summary->successes, $rawRecips);
                    $ajax['sent']['confirmed'] = true;
                    $ajax['sent']['msgid'] = $broadcastMap;
                    $ajax['sent']['scheduled'] = ($sendAt !== null);
                    $ajax['failed']['count'] = count($summary->failures);
                    $ajax['failed']['recipients'] = self::_recipientNames($summary->failures, $rawRecips);
                } elseif ($summary instanceof \Sms\Failed) {
                    $ajax['error'] = "Unable to send SMS\n" . $summary->error;
                } elseif ($summary !== null) {
                    $ajax['error'] = "Unexpected send result type";
                }
            }
        }
        return $ajax;
    }

    /**
     * Reply to a send from one of the Datastar Send buttons (printSmsModal(),
     * printBulkSmsForm()) with SSE: the error box and results box of the form
     * named by $_POST['sms_form'] ('modal' or 'bulk'), rendered by
     * Jethro\Sms\presentSendResult(), then a script that applies the outcome
     * to the page (JethroSMS.applySendOutcome() in resources/js/jethro-sms.js):
     * row classes, closing the modal, reloading a visible Messages tab.
     *
     * @param array<string, mixed> $result _send()'s result plus 'can_view_history'
     * @see docs/docs/developer/sms/send-pipeline.mdx
     */
    private static function _respondDatastar(array $result): void
    {
        require_once JETHRO_ROOT . '/include/jethro_sms.php';
        require_once JETHRO_ROOT . '/include/sse.php';

        $bulk = ($_POST['sms_form'] ?? '') === 'bulk';
        $suffix = $bulk ? '-bulk' : '';
        $shown = \Jethro\Sms\presentSendResult($result, !empty($result['can_view_history']));
        $error = $shown['error'];
        // The modal closes once one person has been sent to and nobody left
        // out; the results are then not shown.
        $closeModal = !$bulk && $error === null && !$shown['keepOpen'];

        \Jethro\sseStart();
        \Jethro\ssePatchElements('<div id="sms-send-response' . $suffix . '" class="alert alert-error"'
            . ($error === null ? ' style="display:none">' : '>' . nl2br(ents($error))) . '</div>');
        $resultsHtml = $closeModal ? '' : $shown['html'];
        \Jethro\ssePatchElements($bulk
            ? '<div class="control-group" id="bulk-sms-results">' . $resultsHtml . '</div>'
            : '<div class="results" id="sms-results">' . $resultsHtml . '</div>');

        $msgIds = $closeModal ? ($result['sent']['msgid'] ?? []) : [];
        $outcome = [
            'rows' => (object) $shown['rowClasses'],
            'closeModal' => $closeModal,
            // Show the new message on a person's Messages tab, if the page has one.
            'reloadMessages' => (int) ($result['sent']['count'] ?? 0) > 0 && ($bulk || $closeModal),
            'messageId' => $msgIds === [] ? null : (int) reset($msgIds),
        ];
        \Jethro\ssePatchElements('<script>JethroSMS.applySendOutcome('
            . json_encode($outcome, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ');document.currentScript.remove();</script>', 'append', 'body');
    }

    /**
     * Build a recipient name map keyed by person ID from JethroSmsRecipients
     * and the raw person records that were already fetched.
     *
     * @param \Jethro\Sms\JethroSmsRecipient[] $smsRecipients
     * @param array $rawRecips Raw person records keyed by person ID
     * @return array
     */
    private static function _recipientNames(array $smsRecipients, array $rawRecips): array
    {
        $result = [];
        foreach ($smsRecipients as $recip) {
            $pid = $recip->personId;
            $result[$pid] = [
                'first_name' => $rawRecips[$pid]['first_name'] ?? "Person #$pid",
                'last_name' => $rawRecips[$pid]['last_name'] ?? '',
            ];
        }
        return $result;
    }

}
