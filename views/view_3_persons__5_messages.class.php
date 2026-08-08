<?php
/**
 * Messages page — Send History with filters and per-recipient delivery status.
 *
 * Menu: Persons > SMS (requires PERM_VIEWSMS + SMS feature flag)
 */
class View_persons__messages extends View
{
    static function getMenuPermissionLevel()
    {
        return PERM_VIEWSMS;
    }

    static function getMenuRequiredFeature()
    {
        return 'SMS';
    }

    function getTitle()
    {
        return 'Messages';
    }

    function printView()
    {
        $GLOBALS['system']->includeDBClass('sms');
        $GLOBALS['system']->includeDBClass('smsdelivery');
        include_once 'urllinker.php';
        require_once JETHRO_ROOT . '/include/jethro_sms.php';
        $currentProviderKey = \Jethro\Sms\getCurrentSmsProviderKey();

        // Restricted accounts: the smsdelivery view only holds deliveries to
        // persons they may see (and to raw numbers), so the Sms aggregates
        // (recipient/status counts, cost) count only those, and sends with no
        // such delivery aren't listed (inner join). See smsdelivery.class.php.
        $sends = $GLOBALS['system']->getDBObjectData('sms', [], 'AND', 'created DESC');
        if (!$sends) {
            ?>
            <p><i>No SMS messages have been sent yet.</i></p>
            <?php
            return;
        }
        $deliveries = $GLOBALS['system']->getDBObjectData('smsdelivery', ['sms_id' => array_keys($sends)], 'AND', 'personid');

        // Group by sms_id.
        $deliveriesBySmsId = [];
        foreach ($deliveries as $id => $d) {
            $d['_id'] = (int)$id;
            $deliveriesBySmsId[(int)$d['sms_id']][] = $d;
        }

        ?>
            <div class="sms-history-layout">
                <div class="panel-sidebar" id="sms-history-filters">
            <h4 class="hidden-phone"><?php echo _('Filters'); ?></h4>
            <fieldset class="hidden-phone">
                <legend><?php echo _('Sender:'); ?></legend>
                <label class="checkbox sms-filter-noindent">
                    <input type="text" id="sms-filter-sender" data-bind="filterSender" style="width:130px" placeholder="Name..." data-on:input__debounce.150ms="updateSmsCostSum()" />
                </label>
                <legend><?php echo _('Recipient:'); ?></legend>
                <label class="checkbox sms-filter-noindent">
                    <input type="text" id="sms-filter-recipient" data-bind="filterRecipient" style="width:130px" placeholder="Name..." data-on:input__debounce.150ms="updateSmsCostSum()" />
                </label>
                <label class="checkbox">
                    <input type="checkbox" id="sms-filter-single-only" data-bind="filterSingleOnly" data-on:input="updateSmsCostSum()" /> <?php echo _('Single recipients only'); ?>
                </label>
                <legend><?php echo _('Body:'); ?></legend>
                <label class="checkbox sms-filter-noindent">
                    <input type="text" id="sms-filter-body" data-bind="filterBody" style="width:130px" placeholder="Text..." data-on:input__debounce.150ms="updateSmsCostSum()" />
                </label>
                <legend><?php echo _('Date:'); ?></legend>
                <label class="checkbox sms-filter-noindent sms-filter-dates">
                    <span class="sms-filter-date-row"><?php echo _('From'); ?> <input type="date" id="sms-filter-date-from" data-bind="filterDateFrom" style="width:110px" data-on:input="updateSmsCostSum()" /></span>
                    <span class="sms-filter-date-row"><?php echo _('To'); ?> <input type="date" id="sms-filter-date-to" data-bind="filterDateTo" style="width:110px" data-on:input="updateSmsCostSum()" /></span>
                </label>
                <button type="button" class="btn btn-mini" id="sms-filter-clear" style="margin-top:10px" data-on:click="$filterSender = ''; $filterRecipient = ''; $filterBody = ''; $filterDateFrom = ''; $filterDateTo = ''; $filterSingleOnly = false; updateSmsCostSum()"><?php echo _('Clear filters'); ?></button>
            </fieldset>
            <h4 class="hidden-phone">Show also:</h4>
            <fieldset class="hidden-phone">
                <label class="checkbox">
                    <input type="checkbox" id="sms-filter-show-cost" data-bind="showCost" /> <?php echo _('Cost'); ?>
                </label>
            </fieldset>
        </div>
        <table class="table table-sortable table-striped table-hover sms-history-table">
            <thead>
                <tr>
                    <th data-sort="string">Created</th>
                    <th data-sort="string-ins">Sender</th>
                    <th class="sms-history-recipients no-sort">Recipients</th>
                    <th data-sort="string">Body</th>
                    <th data-show="$showCost" style="display:none" data-sort="float">Cost<br><span id="sms-cost-total">$0.00</span></th>
                    <th class="">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ($sends as $smsId => $send) {
                $sendDeliveries = $deliveriesBySmsId[$smsId] ?? [];
                // Cancel link only for those Call_SMS_Cancel will let cancel it.
                $hasScheduled = (int)($send['scheduled_count'] ?? 0) > 0
                    && \Jethro\Sms\canCancelScheduledDelivery(isset($send['sender']) ? (int)$send['sender'] : null);
                $uniqueBodies = array_unique(array_map(fn(array $d): string => (string)($d['body'] ?? ''), $sendDeliveries));
                $hasTokens = count($uniqueBodies) > 1;
                $senderName = (!empty($send['sender_fn']) ? trim($send['sender_fn'] . ' ' . $send['sender_ln']) : 'system');

                $recipientNames = [];
                foreach ($sendDeliveries as $d) {
                    $recipientNames[] = $d['recipient_name'] ?? '';
                }

                $cost = (float)($send['cost'] ?? 0);
                ?>
                <tr data-sender="<?php echo ents($senderName); ?>"
                    data-recipients="<?php echo ents(implode(' ', $recipientNames)); ?>"
                    data-multi="<?php echo count($sendDeliveries) > 1 ? '1' : '0'; ?>"
                    data-body="<?php echo ents($send['body'] ?? ''); ?>"
                    data-created="<?php echo ents($send['created']); ?>"
                    data-cost="<?php echo $cost; ?>"
                    data-show="(!$filterSender || el.dataset.sender.toLowerCase().includes($filterSender.trim().toLowerCase()))
                      && (!$filterRecipient || el.dataset.recipients.toLowerCase().includes($filterRecipient.trim().toLowerCase()))
                      && (!$filterBody || el.dataset.body.toLowerCase().includes($filterBody.trim().toLowerCase()))
                      && (!$filterDateFrom || el.dataset.created.slice(0, 10) >= $filterDateFrom)
                      && (!$filterDateTo || el.dataset.created.slice(0, 10) <= $filterDateTo)
                      && (!$filterSingleOnly || el.dataset.multi === '0')"
                    id="message_<?php echo (int)$smsId; ?>">
                    <td class="" data-sort-value="<?php echo ents($send['created']); ?>">
                        <?php echo format_datetime($send['created']); ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($send['sender_fn'])) {
                            echo ents(trim($send['sender_fn'] . ' ' . $send['sender_ln']));
                        } else {
                            echo '<i>system</i>';
                        }
                        ?>
                    </td>
                    <td class="sms-history-recipients">
                        <div class="sms-history-recipients-scroll">
                        <?php
                        foreach ($sendDeliveries as $d) {
                            $name = $d['recipient_name'] ?: ('Person #' . ((int)$d['personid']));
                            $status = $d['status'] ?? 'unknown';
                            $did = (int)($d['_id'] ?? 0);
                            $pid = (int)$d['personid'];
                            $body = (string)($d['body'] ?? '');
                            $dprovider = $d['provider'] ?? null;
                            if ($pid && $did) {
                            echo \Jethro\Sms\renderSmsDeliveryStatusIcon(
                                status: $status,
                                scheduledAt: $send['scheduled_send_at'] ?? null,
                                deliveryId: $did ?: null,
                                providerKey: $dprovider,
                                currentProviderKey: $currentProviderKey,
                                batch: true,
                                sentAt: $send['scheduled_send_at'] ?? $send['created'] ?? null,
                            );
                                ?>
                                <a href="?view=persons&amp;personid=<?php echo $pid; ?>#message_<?php echo (int)$smsId; ?>"
                                <?php if ($hasTokens && $body !== ''): ?> title="<?php echo ents($body); ?>"<?php endif; ?>>
                                    <?php echo ents($name); ?>
                                </a>
                                <?php
                            } else {
                                echo ents($name);
                            }
                            ?><br />
                            <?php
                        }
                        ?>
                        </div>
                    </td>
                    <td>
                        <?php
                        $body = (string)($send['body'] ?? '');
                        echo linkUrlsInTrustedHtml(nl2br(ents($body)));
                        ?>
                    </td>
                    <td class="sms-history-cost" data-show="$showCost" style="display:none" data-sort-value="<?php echo number_format($cost, 2, '.', ''); ?>">
                        <?php echo '$' . number_format($cost, 2); ?>
                    </td>
                    <td class="sms-history-actions action-cell">
                        <?php // Always present, so status polls (Call_SMS_Info) can clear it once the send has gone. ?>
                        <span id="sms-cancel-<?php echo (int)$smsId; ?>">
                        <?php if ($hasScheduled): ?>
                            <a href="#"
                               data-on:click="@post('?call=sms_cancel&sms_id=<?php echo (int)$smsId; ?>')">
                                <?php echo count($sendDeliveries) > 1 ? 'Cancel all' : 'Cancel'; ?>
                            </a>
                        <?php endif; ?>
                        </span>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        </div><!-- /.sms-history-layout -->
        <?php
    }
}
