<?php

/**
 * SSE endpoint backing the Datastar-driven SMS bulk/modal composer.
 *
 * On debounced textarea input (and recipient/sms_type changes), the client
 * POSTs the form here. This Call recomputes the SMS cost/segment/preview
 * business logic SERVER-SIDE (the single source of truth) and streams back:
 *   - the rendered #sms-statusline-bulk / #sms-statusline element,
 *   - the rendered #sms-preview-panel element, and
 *   - the $smsSendBlocked / $smsBlockReason signals that gate the Send button.
 *
 * Only the live character count stays client-side (a trivial Datastar
 * signal). All maths lives in jethro-sms/src/sms_statusline.php.
 *
 * It also says which recipients a send would leave out (no mobile number,
 * archived; Jethro\Sms\leftOutNotice()), blocking Send when nobody is left.
 *
 * Sending itself is ?call=sms (calls/call_sms.class.php); this endpoint only
 * powers the live preview/statusline.
 *
 * See docs/docs/developer/sms/SMS_DATASTAR.md for the full
 * architecture and SSE protocol.
 *
 * @see \Sms\renderStatusline()
 * @see \Sms\renderPreviewPanel()
 * @see \Jethro\sseStart()
 */
class Call_SMS_Statusline extends Call
{
    public function run(): void
    {
        // Enforce PERM_SENDSMS exactly like Call_SMS.
        if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
            http_response_code(403);
            return;
        }

        require_once JETHRO_ROOT . '/include/jethro_sms.php';
        require_once JETHRO_ROOT . '/include/sse.php';

        $message = array_get($_POST, 'message', '');

        // Message overrides from the preview panel, by row key — the keys
        // previewRows() gives the rows below.
        $overrides = \Jethro\Sms\messageOverridesFromRequest();

        $smsReq = \Jethro\Sms\getRecipientsFromRequest();
        $shortenUrls = \Jethro\Sms\shortenUrlsFromRequest();
        $cfg = \Jethro\Sms\makeStatuslineConfig($shortenUrls);

        // Build the preview rows (token expansion + real recipient set, one
        // row per phone number) via the preview path. Empty when there is no
        // message or no sendable recipients — renderStatusline then falls
        // back to a raw-text estimate.
        $rows = [];
        if (trim((string)$message) !== '' && $smsReq->recipients) {
            $sender = \Jethro\Sms\getSenderFromRequest();
            if ($sender !== null) {
                $res = \Jethro\Sms\sendSms($message, $smsReq->recipients, sender: $sender, preview: true, shortenUrls: $shortenUrls);
                if ($res->isSuccess()) {
                    $rows = \Jethro\Sms\previewRows($res->getValue(), $smsReq->rawPersonRecords, (string)$message);
                }
            }
        }

        // Apply overrides into the rows used for the cost basis + panel: the
        // edited text is what will actually be sent to that row's number.
        foreach ($rows as &$row) {
            if (array_key_exists($row['key'], $overrides)) {
                $row['message'] = $overrides[$row['key']];
            }
        }
        unset($row);

        $actualCount = count($smsReq->recipients ?? []);
        $status = \Sms\renderStatusline((string)$message, $rows, $cfg, $actualCount);
        $panel = \Sms\renderPreviewPanel($rows, $overrides);

        // Say up front who the send would leave out (no mobile, archived),
        // rather than only after Send. With nobody left to send to, the notice
        // replaces the statusline and blocks Send.
        if (trim((string)$message) !== '') {
            $leftOut = \Jethro\Sms\leftOutNotice($actualCount, count($smsReq->blanks ?? []), count($smsReq->archived ?? []));
            if ($leftOut !== null && $leftOut['blocking']) {
                $status = ['html' => '<p>' . ents($leftOut['text']) . '</p>', 'blocked' => true, 'blockReason' => $leftOut['text']];
            } elseif ($leftOut !== null) {
                // Space-separated like renderStatusline()'s own paragraphs (displayed inline).
                $status['html'] .= ($status['html'] === '' ? '' : ' ') . '<p>' . ents($leftOut['text']) . '</p>';
            }
        }

        // Schedule time validation: if send_at exceeds the provider's max
        // deferred send delay, block the Send button with a reason.
        $sendAtRaw = !empty($_POST['send_at']) ? $_POST['send_at'] : null;
        if ($sendAtRaw !== null) {
            $sendAt = strtotime($sendAtRaw);
            if ($sendAt !== false && $sendAt > 0) {
                $providerResult = \Jethro\Sms\getSmsProvider();
                if ($providerResult->isSuccess()) {
                    $maxDelay = $providerResult->getValue()->getDeferredSendMaxDelay();
                    if ($maxDelay !== null && ($sendAt - time()) > $maxDelay) {
                        $status['blocked'] = true;
                        $status['blockReason'] = sprintf(
                            'Cannot schedule more than %s ahead',
                            \_formatDuration($maxDelay)
                        );
                    }
                }
            }
        }

        // The morph target ids depend on which form posted. The bulk form
        // uses the '-bulk' suffix; the modal uses none. The client tells us
        // via the 'statusline_id' / 'preview_id' fields (defaulting to bulk).
        $statuslineId = isset($_POST['statusline_id']) && is_string($_POST['statusline_id'])
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['statusline_id'])
            : 'sms-statusline-bulk';
        $previewId = isset($_POST['preview_id']) && is_string($_POST['preview_id'])
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['preview_id'])
            : 'sms-preview-panel';
        $shortenToggleId = isset($_POST['shorten_toggle_id']) && is_string($_POST['shorten_toggle_id'])
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['shorten_toggle_id'])
            : 'sms-shorten-toggle-bulk';

        \Jethro\sseStart();
        \Jethro\ssePatchElements(
            '<span id="' . $statuslineId . '" class="sms-statusline-cost">' . $status['html'] . '</span>'
        );
        \Jethro\ssePatchElements(
            '<div id="' . $previewId . '" class="sms-preview-panel"' . ($panel === '' ? ' style="display:none"' : '') . '>' . $panel . '</div>'
        );
        \Jethro\ssePatchElements(
            \Jethro\Sms\renderShortenUrlsToggle($shortenToggleId, (string)$message, $shortenUrls)
        );
        \Jethro\ssePatchSignals([
            'smsSendBlocked' => $status['blocked'],
            'smsBlockReason' => $status['blockReason'],
        ]);
    }
}
