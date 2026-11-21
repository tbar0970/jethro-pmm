<?php

/**
 * AJAX handler to cancel all scheduled deliveries for a previously sent SMS.
 *
 * POST ?call=sms_cancel&sms_id=<sms_id>
 *
 * Requires PERM_SENDSMS.  Only the original sender (or PERM_SYSADMIN) may
 * cancel.  Script-initiated sends (sms.sender IS NULL) are admin-only.
 *
 * Returns HTML indicating the cancellation result.
 *
 * No SQL queries in this file — all DB access is via loadSmsBatch() and
 * cancelSms() in include/jethro_sms.php.
 */
class Call_SMS_Cancel extends Call
{
    function run(): void
    {
        if (!$GLOBALS['user_system']->havePerm(PERM_SENDSMS)) {
            echo 'Permission denied';
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo 'Method not allowed';
            return;
        }

        require_once JETHRO_ROOT . '/include/jethro_sms.php';

        $isAdmin = $GLOBALS['user_system']->havePerm(PERM_SYSADMIN);
        $currentUserId = (int) ($GLOBALS['user_system']->getCurrentUser('id') ?? 0);

        $smsId = (int) ($_REQUEST['sms_id'] ?? 0);
        if ($smsId === 0) {
            echo 'Missing sms_id';
            return;
        }

        // Load the full batch from DB
        try {
            $batchResult = \Jethro\Sms\loadSmsBatch($smsId);
        } catch (\Throwable $e) {
            $batchResult = \Result::failure('Could not load SMS send: ' . $e->getMessage());
        }
        if ($batchResult->isFailure()) {
            echo '<span id="sms-cancel-' . $smsId . '">' . ents($batchResult->getError()) . '</span>';
            return;
        }

        /** @var \Jethro\Sms\JethroSmsDeliveryBatch $batch */
        $batch = $batchResult->getValue();

        // Ownership: only the original sender (or sysadmin) may cancel.
        // senderPersonId null = script-initiated send → admin-only.
        if (!$isAdmin) {
            if ($batch->senderPersonId === null || $batch->senderPersonId !== $currentUserId) {
                echo '<span id="sms-cancel-' . $smsId . '">Permission denied</span>';
                return;
            }
        }

        // Filter to SCHEDULED deliveries only
        $scheduled = array_values(array_filter(
            $batch->deliveries,
            static fn(\Sms\SmsDelivery $d) => $d->status() === \Sms\SmsStatus::SCHEDULED,
        ));

        if ($scheduled === []) {
            // Already cancelled — clear the Cancel link and update all status
            // indicators immediately so the caller doesn't wait for the next poll.
            echo '<span id="sms-cancel-' . $smsId . '"></span>';
            $currentKey = \Jethro\Sms\getCurrentSmsProviderKey();
            foreach ($batch->deliveries as $d) {
                if (!($d instanceof \Jethro\Sms\JethroSmsDelivery) || $d->databaseId() === null) {
                    continue;
                }
                echo \Jethro\Sms\renderSmsDeliveryStatusIndicator(
                    status: $d->status()->toMySql(),
                    deliveryId: $d->databaseId(),
                    remoteId: $d->remoteId(),
                    providerKey: null,
                    currentProviderKey: null,
                );
            }
            return;
        }

        $cancelBatch = new \Sms\SmsDeliveryBatch($batch->batchId, $scheduled);
        // The message goes out unless this cancel lands, so an exception from
        // the gateway must surface as a visible failure, not a broken response.
        try {
            $result = \Jethro\Sms\cancelSms($cancelBatch);
        } catch (\Throwable $e) {
            $result = \Result::failure($e->getMessage());
        }
        if ($result->isFailure()) {
            echo '<span id="sms-cancel-' . $smsId . '">Cancel failed: ' . ents($result->getError()) . '</span>';
            return;
        }

        /** @var \Sms\SmsDeliveryBatch $cancelled */
        $cancelled = $result->getValue();

        $cancelledCount = 0;
        $failedCount = 0;
        $failureReasons = [];
        foreach ($cancelled->deliveries as $d) {
            if ($d->status() === \Sms\SmsStatus::CANCELLED) {
                $cancelledCount++;
            } else {
                $failedCount++;
                if ($d->statusDetail() !== null) {
                    $failureReasons[] = $d->statusDetail();
                }
            }
        }

        $msg = 'Cancelled ' . $cancelledCount . ' deliver' . ($cancelledCount !== 1 ? 'ies' : 'y');
        if ($failedCount > 0) {
            $msg .= ', ' . $failedCount . ' failed';
            $failureReasons = array_unique($failureReasons);
            if ($failureReasons !== []) {
                $msg .= ' (' . implode('; ', $failureReasons) . ')';
            }
        }
        $msg .= '.';
        echo '<span id="sms-cancel-' . $smsId . '">' . ents($msg) . '</span>';
        foreach ($scheduled as $i => $d) {
            if (!($d instanceof \Jethro\Sms\JethroSmsDelivery) || $d->databaseId() === null) {
                continue;
            }
            // Only morph in a 'cancelled' span when this delivery really was
            // cancelled — a refused cancel keeps its existing polling span.
            $outcome = $cancelled->deliveries[$i] ?? null;
            if ($outcome === null || $outcome->status() !== \Sms\SmsStatus::CANCELLED) {
                continue;
            }
            echo \Jethro\Sms\renderSmsDeliveryStatusIndicator(
                status: 'cancelled',
                deliveryId: $d->databaseId(),
                remoteId: $d->remoteId(),
                providerKey: null,
                currentProviderKey: null,
            );
        }
    }
}
