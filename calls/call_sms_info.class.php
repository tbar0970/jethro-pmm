<?php

/**
 * AJAX handler returning delivery status HTML for a previously sent SMS.
 *
 * Called via ?call=sms_info&id=<smsdelivery_id>
 *
 * Returns HTML to replace the content of the #sms-delivery-status-N span:
 *   - Delivered: <span title="Delivery timestamp: ...">✓✓</span>
 *   - Status available but not delivered: "Status: <status_text>"
 *   - No status info available: empty string (caller leaves element as-is)
 */
class Call_SMS_Info extends Call
{
    function run(): void
    {
        $deliveryId = (int) ($_REQUEST['id'] ?? 0);
        if ($deliveryId === 0) {
            return;
        }

        // Batch-context pages (admin Messages view) render every sibling of a
        // send and pass &batch=1 so one poll can morph the whole batch by id.
        // Single-delivery pages (per-person Messages tab) omit the flag; their
        // sibling spans live on other pages, so we must not emit them here —
        // Datastar errors with PatchElementsNoTargetsFound when it can't find
        // a target element.
        $batch = !empty($_REQUEST['batch']);

        require_once JETHRO_ROOT . '/include/jethro_sms.php';

        // Load the send-time delivery from smsdelivery, including the sender
        // so non-PERM_VIEWSMS users can still poll status for their own sends.
        // sms_id and scheduled_count are used to render the batch cancel link;
        // created / scheduled_send_at give the send time polling counts from.
        // is_first_in_batch gates the thundering herd: only the lowest-ID
        // delivery per sms_id that may still change (not final) performs the
        // actual status lookup; all other calls return immediately as no-ops.
        $db = $GLOBALS['db'];
        $nonFinal = implode(',', array_map([$db, 'quote'], \Sms\SmsStatus::nonFinalValues()));
        $row = $db->queryRow(
            'SELECT sd.personid, sd.remote_id, sd.status, sd.provider, sd.delivered_at, p.mobile_tel, s.sender,'
            . ' s.id AS sms_id, s.scheduled_send_at, s.created,'
            . ' (SELECT COUNT(*) FROM smsdelivery WHERE sms_id = s.id AND status = \'scheduled\') AS scheduled_count,'
            . ' sd.id = (SELECT MIN(sd2.id) FROM smsdelivery sd2 WHERE sd2.sms_id = s.id AND sd2.status IN (' . $nonFinal . ')) AS is_first_in_batch'
            . ' FROM smsdelivery sd'
            . ' JOIN _person p ON p.id = sd.personid'
            . ' JOIN sms s ON s.id = sd.sms_id'
            . ' WHERE sd.id = ' . (int)$deliveryId
        );
        if (!$row || empty($row['mobile_tel'])) {
            return;
        }
        $rowFinal = \Sms\SmsStatus::fromMySql((string)$row['status'])->isFinal();

        // Thundering-herd gate (batch context only): the lowest-ID non-final
        // delivery per sms_id performs the actual status lookup; all other
        // batch calls return immediately and are updated when the first
        // delivery's response morphs sibling statuses via Datastar.  A delivery
        // already final in the DB is never gated: its span may be stale because
        // the poll that resolved it lost its response, and it must still be
        // able to render itself (below).  On a single-delivery page there is no
        // herd, so the polled delivery updates itself regardless of position.
        if ($batch && !$rowFinal && empty($row['is_first_in_batch'])) {
            return;
        }

        // Mirror getPersonSmsHistory(): PERM_VIEWSMS users see everything;
        // others see only deliveries they sent themselves.
        if (!$GLOBALS['user_system']->havePerm(PERM_VIEWSMS)) {
            $currentUserId = (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0);
            if (empty($row['sender']) || (int)$row['sender'] !== $currentUserId) {
                return;
            }
        }

        // Already final in the DB: render that (and clear any cancel link),
        // no provider call.
        if ($rowFinal) {
            echo \Jethro\Sms\renderSmsDeliveryStatusIcon(
                status: (string)$row['status'],
                deliveredAt: !empty($row['delivered_at']) ? strtotime((string)$row['delivered_at']) : null,
                scheduledAt: $row['scheduled_send_at'] ?? null,
                deliveryId: $deliveryId,
                remoteId: (string)($row['remote_id'] ?? ''),
                batch: $batch,
            );
            echo '<span id="sms-cancel-' . (int)($row['sms_id'] ?? 0) . '"></span>';
            return;
        }

        // Skip status lookup when the delivery's provider differs from the current one
        $currentKey = \Jethro\Sms\getCurrentSmsProviderKey();
        if ($currentKey === null || ($row['provider'] ?? null) !== $currentKey) {
            return;
        }

        // Only accounts with an SMS permission may trigger provider status
        // polling. The queries below (listRecentDeliveries / updateDelivery) are
        // upstream HTTP calls, so a no-SMS account holding a delivery id used to
        // burn provider API quota on every poll.
        if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS) && !$GLOBALS['user_system']->havePerm(PERM_VIEWSMS)) {
            return;
        }

        $smsId = (int)($row['sms_id'] ?? 0);

        // In batch context, fetch all sibling non-final deliveries so one poll
        // updates the whole batch.  Each sibling gets a fresh data-on-interval,
        // which Datastar morphs into the DOM — canceling the sibling's own
        // redundant timer.  Single-delivery pages have no sibling spans in the
        // DOM, so emitting them would trigger PatchElementsNoTargetsFound.
        $siblings = [];
        if ($batch && $smsId > 0) {
            $sibRows = $db->queryAll(
                'SELECT sd.id, sd.remote_id, sd.status, sd.provider, sd.delivered_at, p.mobile_tel
                 FROM smsdelivery sd
                 JOIN _person p ON p.id = sd.personid
                 WHERE sd.sms_id = ' . $smsId . '
                   AND sd.status IN (' . $nonFinal . ')
                   AND sd.id != ' . $deliveryId,
                null, null, false, true
            );
            if (is_array($sibRows)) {
                $siblings = $sibRows;
            }
        }

        // Process the requested delivery first, then siblings.
        $allIds = array_merge(
            [[
                'id' => $deliveryId,
                'remote_id' => $row['remote_id'],
                'status' => $row['status'],
                'delivered_at' => $row['delivered_at'] ?? null,
                'mobile_tel' => $row['mobile_tel'],
            ]],
            $siblings
        );

        // Release the PHP session lock before any upstream HTTP work.
        //
        // This handler is polled on an interval (data-on-interval, as often as
        // every 2s per batch — see smsDeliveryPollIntervalSecs()),
        // and the lookup below is an HTTP call to the provider. PHP's `files`
        // session handler holds an exclusive flock for the whole request, so
        // without this every poll for a given browser session queues behind the
        // previous one, each holding a php-fpm child for up to the 30s SMS HTTP
        // timeout. In the functional suite that convoy starves the pool and
        // stalls every other request on the server.
        //
        // Nothing below writes to $_SESSION ($_SESSION stays readable after
        // close; the provider's SessionSmsCache is only written by the
        // getSenderIds()/getBalance() paths, which this handler never calls).
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Batch status query — one upstream call instead of N.
        $statusByRemoteId = null;
        $providerResult = \Jethro\Sms\getSmsProvider();
        if ($providerResult->isSuccess()) {
            $provider = $providerResult->getValue();
            if ($provider->hasCapability(\Sms\SmsCapability::BATCH_DELIVERY_QUERY)) {
                // Everything still being polled: STATUS_POLL_WINDOW_SECS back.
                $cutoff = time() - \Jethro\Sms\STATUS_POLL_WINDOW_SECS;
                try {
                    $batchResult = $provider->listRecentDeliveries($cutoff);
                } catch (\Throwable $e) {
                    error_log('listRecentDeliveries failed: ' . $e->getMessage());
                    $batchResult = \Result::failure($e->getMessage());
                }
                if ($batchResult->isSuccess()) {
                    $statusByRemoteId = [];
                    foreach ($batchResult->getValue() as $bd) {
                        $rid = $bd->remoteId();
                        if ($rid !== null) {
                            $statusByRemoteId[$rid] = [
                                'status'  => $bd->status()->toMySql(),
                                'send_ts' => $bd->sendTimestamp(),
                            ];
                        }
                    }
                }
            }
        }

        // Delivery times recorded by the status lookup above: the batch query
        // persisted them before this loop, so the morphed "Delivered: <time>"
        // tooltip matches a page refresh. (The row's own delivered_at was read
        // before the lookup and is stale on the poll that sees the transition.)
        $deliveredAtById = [];
        $ids = implode(',', array_map(fn(array $d): string => (int)$d['id'], $allIds));
        if ($ids !== '') {
            foreach ((array)$db->queryAll('SELECT id, delivered_at FROM _smsdelivery WHERE id IN (' . $ids . ')') as $r) {
                if (!empty($r['delivered_at'])) {
                    $deliveredAtById[(int)$r['id']] = strtotime((string)$r['delivered_at']);
                }
            }
        }

        $output = '';
        $anyScheduled = false;
        foreach ($allIds as $d) {
            $did = (int)$d['id'];
            $remoteId = (string)($d['remote_id'] ?? '');

            $sendTs = null;
            if ($remoteId !== '' && isset($statusByRemoteId[$remoteId])) {
                $status = $statusByRemoteId[$remoteId]['status'];
                $sendTs = $statusByRemoteId[$remoteId]['send_ts'];
            } elseif ($statusByRemoteId !== null) {
                $status = (string)$d['status'];
            } else {
                $delivery = new \Sms\SmsDelivery(
                    recipient: new \Sms\PhoneNumber((string)$d['mobile_tel']),
                    status: \Sms\SmsStatus::fromMySql((string)$d['status']),
                    remoteId: $d['remote_id'],
                );
                $result = \Jethro\Sms\updateDelivery($delivery);
                if ($result->isFailure()) continue;
                $updated = $result->getValue();
                $status = $updated->status()->toMySql();
                $sendTs = $updated->sendTimestamp();
                // updateDelivery() has just persisted this status: read back the
                // delivery time it recorded (the provider may report none).
                $fresh = $db->queryRow('SELECT delivered_at FROM _smsdelivery WHERE id = ' . (int)$did);
                if (!empty($fresh['delivered_at'])) {
                    $deliveredAtById[(int)$did] = strtotime((string)$fresh['delivered_at']);
                }
            }

            if ($status === 'scheduled') {
                $anyScheduled = true;
            }

            $output .= \Jethro\Sms\renderSmsDeliveryStatusIcon(
                status: $status,
                deliveredAt: $deliveredAtById[(int)$did] ?? null,
                scheduledAt: $row['scheduled_send_at'] ?? (
                    $sendTs !== null ? date('Y-m-d H:i:s', $sendTs) : null
                ),
                deliveryId: $did,
                providerKey: $d['provider'] ?? $row['provider'] ?? null,
                currentProviderKey: $currentKey,
                remoteId: $remoteId,
                batch: $batch,
                sentAt: $row['scheduled_send_at'] ?? $row['created'] ?? null,
            );
        }

        if ($output !== '') {
            echo $output;
            // The pages always render #sms-cancel-<smsId>: keep the link while
            // something is still scheduled, clear it once the send has gone.
            if ($smsId > 0) {
                echo ' <span id="sms-cancel-' . $smsId . '">';
                if ($anyScheduled && \Jethro\Sms\canCancelScheduledDelivery(isset($row['sender']) ? (int) $row['sender'] : null)) {
                    echo '<a href="#" data-on:click="@post(\'?call=sms_cancel&sms_id=' . $smsId . '\')"'
                        . ' style="cursor:pointer;font-size:smaller;text-decoration:underline">'
                        . '(' . _('Cancel') . ')</a>';
                }
                echo '</span>';
            }
            return;
        }
    }

}
