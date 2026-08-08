<?php
/**
 * SMS send — one row per send operation.
 *
 * Table: `sms` (created by upgrades/2026-upgrade-to-2.40.sql)
 */
class Sms extends DB_Object
{
	protected static function _getFields()
	{
		return [
			'body' => [
				'type' => 'text',
				'label' => 'Message',
				'allow_empty' => false,
			],
			'sender' => [
				'type' => 'reference',
				'label' => 'Sent by',
				'references' => 'staff_member',
				'allow_empty' => true,
			],
			'created' => [
				'type' => 'datetime',
				'label' => 'Created',
				'default' => 'CURRENT_TIMESTAMP',
			],
			'scheduled_send_at' => [
				'type' => 'datetime',
				'label' => 'Scheduled send',
				'allow_empty' => true,
			],
		'wire_sender' => [
			'type' => 'text',
			'label' => 'Wire sender',
			'allow_empty' => true,
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
			CREATE TABLE `sms` (
				`id` INT NOT NULL AUTO_INCREMENT,
				`body` TEXT NOT NULL,
				`sender` INT NULL DEFAULT NULL COMMENT 'Person ID of the sender, or NULL if a script',
				`wire_sender` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Sender identity (alphanumeric ID or phone number) actually used on the wire',
				`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`scheduled_send_at` DATETIME NULL DEFAULT NULL COMMENT 'Scheduled delivery time, or NULL for immediate',
				PRIMARY KEY (`id`),
				INDEX `sender` (`sender`),
				INDEX `created` (`created`)
			) ENGINE=InnoDB;";
	}

	public function getInstancesQueryComps($params, $logic, $order)
	{
		$res = parent::getInstancesQueryComps($params, $logic, $order);
		$res['from'] = 'sms';
		// Sender name
		$res['select'][] = 'COALESCE(_person.first_name, \'\') AS sender_fn';
		$res['select'][] = 'COALESCE(_person.last_name, \'\') AS sender_ln';
		$res['from'] .= ' LEFT JOIN _person ON _person.id = sms.sender';
		// Recipient count and status aggregates
		$res['select'][] = 'COUNT(smsdelivery.id) AS recipient_count';
		$res['select'][] = 'SUM(smsdelivery.status IN (\'queued\',\'sent\',\'delivered\',\'test-message\')) AS delivered_count';
		$res['select'][] = 'SUM(smsdelivery.status IN (\'failed\',\'cancelled\')) AS failed_count';
		$res['select'][] = 'SUM(smsdelivery.status = \'scheduled\') AS scheduled_count';
		// Cost: sum of segments_used × per_segment_cost over accepted deliveries ({@see costSqlExpr()}).
		$res['select'][] = self::costSqlExpr('smsdelivery');
		$res['from'] .= ' JOIN smsdelivery ON smsdelivery.sms_id = sms.id';
		$res['group_by'] = 'sms.id';
		return $res;
	}

	public function toString()
	{
		$body = (string) $this->getValue('body');
		return mb_strlen($body) > 60 ? mb_substr($body, 0, 57).'...' : $body;
	}

	/**
	 * SQL expression for total cost of accepted SMS deliveries, grouped by sms.id.
	 *
	 * Counts deliveries where smsdelivery.status is in {@see \Sms\SmsStatus::ACCEPTED_STATUSES}
	 * — the canonical set of statuses where the gateway accepted the message for delivery.
	 * Each delivery costs segments_used × per_segment_cost, both recorded when the
	 * delivery was sent/polled/imported. When segments_used is unknown it counts as 1;
	 * when per_segment_cost is unknown the default of the provider that sent that row
	 * is used — never the currently configured provider, so switching providers
	 * doesn't reprice history.
	 * See docs/docs/developer/sms/database.mdx "Cost tracking".
	 *
	 * @param string $tableAlias Table name or alias for smsdelivery (e.g. 'smsdelivery' or 'rd')
	 * @return string SQL expression aliased AS cost
	 */
	public static function costSqlExpr(string $tableAlias = 'smsdelivery'): string
	{
		require_once JETHRO_ROOT . '/include/jethro_sms.php';
		$defaultPrice = 'CASE ' . $tableAlias . '.provider';
		foreach (array_keys(\Jethro\Sms\providerClassesByKey()) as $key) {
			$defaultPrice .= " WHEN '" . $key . "' THEN " . number_format(\Jethro\Sms\defaultSegmentCost($key), 5, '.', '');
		}
		$defaultPrice .= ' ELSE 0 END';
		$accepted = "'" . implode("','", \Sms\SmsStatus::ACCEPTED_STATUSES) . "'";

		return "COALESCE(SUM(CASE WHEN {$tableAlias}.status IN ({$accepted}) THEN
			COALESCE({$tableAlias}.segments_used, 1) * COALESCE({$tableAlias}.per_segment_cost, {$defaultPrice})
		ELSE 0 END), 0) AS cost";
	}
}
