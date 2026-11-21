<?php
/**
 * SMS delivery — one row per recipient within a send.
 *
 * Table: `_smsdelivery` (created by upgrades/2026-upgrade-to-2.40.sql), read
 * through the `smsdelivery` view, which applies the current user's
 * congregation/group restrictions as `person` does: pages read `smsdelivery`;
 * writers and system-level code (logging, status caching, history sync, CLI)
 * use `_smsdelivery`. See docs/docs/developer/sms/database.mdx.
 *
 * Not to be confused with \Sms\SmsDelivery (the pure-layer value object in jethro-sms/src/SmsDelivery.php).
 */
class SmsDelivery extends DB_Object
{
	protected static function _getFields()
	{
		return [
			'sms_id' => [
				'type' => 'reference',
				'label' => 'Send',
				'references' => 'sms',
				'allow_empty' => false,
			],
			'personid' => [
				'type' => 'reference',
				'label' => 'Recipient',
				'references' => 'person',
				'allow_empty' => true,
			],
			'remote_id' => [
				'type' => 'text',
				'label' => 'Provider message ID',
				'allow_empty' => true,
			],
			'raw_response' => [
				'type' => 'text',
				'label' => 'Raw provider response',
				'allow_empty' => true,
			],
			'body' => [
				'type' => 'text',
				'label' => 'Expanded message',
				'allow_empty' => true,
			],
			'delivered_at' => [
				'type' => 'datetime',
				'label' => 'Delivered at',
				'allow_empty' => true,
			],
			'status' => [
				'type' => 'text',
				'label' => 'Delivery status',
				'default' => 'sending',
            ],
            'provider' => [
                'type' => 'text',
                'label' => 'Provider key',
                'allow_empty' => true,
            ],
            'segments_used' => [
                'type' => 'int',
                'label' => 'Segments used',
                'allow_empty' => true,
            ],
            'per_segment_cost' => [
                'type' => 'text',
                'label' => 'Cost per segment ($)',
                'allow_empty' => true,
            ],
            'cost_bearer' => [
                'type' => 'bool',
                'label' => 'Bears message cost',
                'default' => 1,
                'allow_empty' => false,
                'show_in_summary' => false,
            ],
    ];
    }
	/**
	 * Column definitions must match upgrades/2026-upgrade-to-2.40.sql exactly;
	 * tests/unit/test_sms_schema.php enforces this.
	 */
	public function getInitSQL($table_name = NULL)
	{
		return "
			CREATE TABLE `_smsdelivery` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`sms_id` INT NOT NULL,
				`personid` INT NULL DEFAULT NULL COMMENT 'Person ID of the recipient, or NULL if sent to a raw mobile number',
				`remote_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Upstream SMS provider message ID for this recipient',
				`raw_response` TEXT NULL DEFAULT NULL COMMENT 'Raw upstream provider response for this recipient (send response or status query)',
				`body` TEXT NULL DEFAULT NULL COMMENT 'Expanded message body for this recipient, or NULL if the batch message was used',
				`provider` ENUM('5centsmsv5','5centsmsv4','cellcastv1','mobilemessagev1') NULL DEFAULT NULL COMMENT 'Upstream SMS API (company + version) that sent this delivery, so raw_response can be interpreted; NULL if unknown',
				`delivered_at` DATETIME NULL DEFAULT NULL COMMENT 'When delivery was confirmed: the carrier timestamp when the provider reports one, else when a status check first saw it delivered',
				`status` ENUM('queued','sent','delivered','failed','in-progress','scheduled','cancelled','sending','test-message','unknown') NOT NULL DEFAULT 'sending' COMMENT 'Delivery status',
				`segments_used` INT NULL DEFAULT NULL COMMENT 'SMS segments charged, as reported by the provider; NULL if not reported',
				`per_segment_cost` DECIMAL(10,5) NULL DEFAULT NULL COMMENT 'Dollars per segment at send time (provider-reported, else SMS_SEGMENT_COST or provider default)',
				`cost_bearer` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'TRUE if this row bears the segments/cost its (possibly shared) message was charged; 0 on the secondary persons of a shared number',
				PRIMARY KEY (`id`),
				INDEX `sms_id` (`sms_id`),
				INDEX `personid` (`personid`),
				INDEX `remote_id` (`remote_id`)
			) ENGINE=InnoDB;";
	}

	/**
	 * The deliveries the current user may see: those to persons visible through
	 * the restricted person view, plus those to raw numbers. Synced with
	 * upgrades/2026-upgrade-to-2.40.sql.
	 */
	public function getViewSQL()
	{
		return "
			CREATE VIEW smsdelivery AS
			SELECT sd.* FROM _smsdelivery sd
			WHERE sd.personid IS NULL OR sd.personid IN (SELECT p.id FROM person p)
		";
	}

	protected function _getInsertTableName()
	{
		return '_smsdelivery';
	}

	public function getForeignKeys()
	{
		return Array(
			'_smsdelivery.sms_id' => '`sms`(`id`) ON DELETE CASCADE',
			'_smsdelivery.personid' => '`_person`(`id`) ON DELETE CASCADE',
		);
	}
	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = 'smsdelivery';
		// Drop columns never used in views: raw_response (TEXT), remote_id, delivered_at
		$res['select'] = array_values(array_filter(
			$res['select'],
			fn(string $col): bool => !str_ends_with($col, '.raw_response')
				&& !str_ends_with($col, '.remote_id')
				&& !str_ends_with($col, '.delivered_at')
		));
		// Recipient name
		$res['select'][] = "CONCAT(COALESCE(_person.first_name, ''), ' ', COALESCE(_person.last_name, '')) AS recipient_name";
		$res['from'] .= ' LEFT JOIN _person ON _person.id = smsdelivery.personid';
		return $res;
	}

	public function toString()
	{
		return 'SMS delivery #'.$this->id.' ('.((string) $this->getValue('status')).')';
	}
}
