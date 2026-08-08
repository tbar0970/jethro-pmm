<?php
/**
 * @var int $id  Broadcast ID
 * @var array<string, mixed> $entry  Row from getSmsHistory()
 */
include_once 'urllinker.php';
require_once JETHRO_ROOT . '/include/jethro_sms.php';
$currentProviderKey = \Jethro\Sms\getCurrentSmsProviderKey();
$recipientCount = (int)($entry['recipient_count'] ?? 1);
$isMulti = $recipientCount > 1;
?>
<div class="history-entry well<?php if ($isMulti) echo ' sms-multi'; ?><?php if ($isFirst ?? false) echo ' latest-entry'; ?>"<?php
	$showExpr = '';
	if ($isMulti) $showExpr = '$showMultiSms && ';
	$s = $entry['delivery_status'] ?? '';
	if ($s !== '') $showExpr .= '$show' . preg_replace('/[^A-Za-z0-9]/', '', ucfirst($s)) . 'Sms';
	if ($showExpr !== '') echo ' data-show="' . $showExpr . '"';
?>
	 id="message_<?php echo $id; ?>"
	 data-message-id="<?php echo $id; ?>"
>

	<i class="<?php echo $isMulti ? 'icon-sms-multi' : 'icon-sms'; ?>"></i>
	<blockquote>
		<p class="content"><?php echo linkUrlsInTrustedHtml(nl2br(ents($entry['body']))); ?></p>
			<small class="message-attribution"<?php if (!empty($entry['delivery_id'])) echo ' id="sms-attribution-' . (int)$entry['delivery_id'] . '"'; ?>>
			<?php
			if (!empty($entry['sender_fn'])) {
				echo _('Sent by') . ' ' . ents($entry['sender_fn'] . ' ' . $entry['sender_ln']) . ' <span class="visible-desktop">(#' . (int)$entry['sender'] . ')</span>, ';
			} else {
				echo _('Sent by system') . ', ';
			}
			echo format_datetime($entry['created']);
			if (!empty($entry['delivery_id'])) {
					$status = $entry['delivery_status'] ?? '';
					$deliveredAt = !empty($entry['delivered_at']) ? strtotime($entry['delivered_at']) : null;
					$indicator = \Jethro\Sms\renderSmsDeliveryStatusIndicator(
						status: $status,
						deliveredAt: $deliveredAt ?: null,
						deliveryId: (int) $entry['delivery_id'],
						remoteId: $entry['remote_id'] ?? null,
						providerKey: $entry['provider'] ?? null,
						currentProviderKey: $currentProviderKey,
						scheduledAt: $entry['scheduled_send_at'] ?? null,
						sentAt: $entry['scheduled_send_at'] ?? $entry['created'] ?? null,
					);
					if ($indicator !== '') {
						echo ' &middot; ' . $indicator;
						// Always render the cancel span so status polls
						// (Call_SMS_Info) have a target to clear once the send
						// is no longer scheduled.
						$smsId = (int)$id;
						if ($status === 'scheduled' && \Jethro\Sms\canCancelScheduledDelivery(isset($entry['sender']) ? (int) $entry['sender'] : null)) {
							echo ' <span id="sms-cancel-' . $smsId . '">'
								. '<a href="#" data-on:click="@post(\'?call=sms_cancel&sms_id=' . $smsId . '\')"'
								. ' style="cursor:pointer;font-size:smaller;text-decoration:underline">'
								. '(' . _('Cancel') . ')</a></span>';
						} else {
							echo '<span id="sms-cancel-' . $smsId . '"></span>';
						}
					}
				}
			if (!empty($entry['note_id'])) {
				echo ' &middot; <a href="#note_' . (int)$entry['note_id'] . '">' . _('Associated note') . '</a>';
			}
			?>
		</small>
	</blockquote>

</div>
