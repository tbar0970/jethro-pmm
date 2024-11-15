<?php
class Config_Manager {

	public static function init()
	{
		foreach (self::getSettings() as $symbol => $details) {
			if (empty($details['type'])) continue; // placeholder settings for age bracket etc
			if (defined($symbol)) {
				define($symbol.'_IN_CONFIG_FILE', 1);
				if (!ifdef('ALLOW_SETTINGS_IN_FILE')  && !self::allowSettingInFile($symbol)) {
					// NB *ALL* migration methods must be safe to run more than once
					$migrateMethod = 'migrate'.str_replace('_', '', $symbol);
					if (method_exists(__CLASS__, $migrateMethod)) {
						call_user_func(Array(__CLASS__, $migrateMethod));
					} else {
						self::saveSetting($symbol, constant($symbol));
						$savedFromFile = TRUE;
					}
					add_message("The setting ".$symbol." has now been migrated to the database and should be removed from conf.php");
				}
			} else {
				define($symbol, $details['value']);
			}
		}
		if (defined('AGE_BRACKET_OPTIONS')) {
			self::migrateAgeBracketOptions();
			add_message("The setting AGE_BRACKET_OPTIONS has now been migrated to the database and must now be removed from conf.php",'error');
		}
	}

	public static function allowSettingInFile($symbol)
	{
		if (0 === strpos($symbol, 'SMS_')) return TRUE;
		if (0 === strpos($symbol, '2FA_')) return TRUE;
		if (0 === strpos($symbol, 'SMTP')) return TRUE;
		return FALSE;
	}

	public static function getSettings()
	{
		$SQL = 'SELECT symbol, s.* from setting s ORDER BY `rank`';
		$res = Array();
		try {
			$res = $GLOBALS['db']->queryAll($SQL, NULL, NULL, TRUE);
		} catch (PDOException $e) {
			if (FALSE === strpos($e->getMessage(), "Base table or view not found")) {
				// We ignore "table not found" because that's expected during install
				throw $e;
			}
		}
		return $res;
	}

	public static function migrateEnabledFeatures()
	{
		$value = explode(',', ENABLED_FEATURES);
		$value = array_diff($value, Array('DATES'));
		self::saveSetting('ENABLED_FEATURES', implode(',', $value));

	}

	public static function migrateLockLength()
	{
		// used to be in strtotime format; now in minutes
		$secs = strtotime('+'.LOCK_LENGTH);
		self::saveSetting('LOCK_LENGTH', $secs/60);
	}

	public static function migrateAgeBracketOptions()
	{
		$db = $GLOBALS['db'];
		$res = NULL;
		try {
			$SQL = 'SELECT count(*) FROM _disused_person_age_brackets';
			$res = $db->queryAll($SQL);
		} catch (PDOException $e) {
			// No data to migrate
		}
		if (!$res) {
			// No data to migrate
			return;
		}

		$SQL = 'UPDATE _person SET age_bracketid = NULL';
		$res = $db->exec($SQL);

		$SQL = 'DELETE FROM age_bracket where 1';
		$res = $db->exec($SQL);

		$SQL = 'REPLACE INTO age_bracket (id, label, `rank`, is_adult, is_default)
				VALUES ';
		foreach(explode(',', AGE_BRACKET_OPTIONS) as $id => $label) {
			$is_adult = strtolower($label) == 'adult' ? 1 : 0;
			$sets[] = '('.(int)($id+1).', '.$db->quote($label).', '.(int)$id.', '.$is_adult.', '.$is_adult.')';
		}
		$SQL .= implode(",\n", $sets);
		$res = $db->exec($SQL);

		// Now we need to convert the zero-based to 1-based numbers
		$SQL = 'UPDATE _person p
				JOIN _disused_person_age_brackets dab ON p.id = dab.id
				JOIN age_bracket ab ON ab.`rank` = dab.age_bracket
				SET p.age_bracketid = ab.id';
		$res = $db->exec($SQL);

		// Update references to age brackets in action plans
		$SQL = 'SELECT id, actions FROM _disused_action_plan_backup';
		$plans = $db->queryAll($SQL);
		foreach ($plans as $row) {
			$actions = unserialize($row['actions']);
			if (!empty($actions['fields']['age_bracket'])) {
				$actions['fields']['age_bracketid'] = (int)$actions['fields']['age_bracket']+1;
				unset($actions['fields']['age_bracket']);
				$SQL = 'UPDATE action_plan SET actions = '.$db->quote(serialize($actions)).' WHERE id = '.(int)$row['id'];
				$res = $db->exec($SQL);
			}
		}

		// Update references to age brackets in person-report rules
		$SQL = 'SELECT id, params FROM _disused_person_query_backup';
		$queries = $db->queryAll($SQL);
		foreach ($queries as $row) {
			$params = unserialize($row['params']);
			if (!empty($params['rules']['p.age_bracket'])) {
				$params['rules']['p.age_bracketid'] = Array();
				foreach ($params['rules']['p.age_bracket'] as $k => $v) {
					$params['rules']['p.age_bracketid'][$k] = (int)$v+1;
				}
				unset($params['rules']['p.age_bracket']);
				$SQL = 'UPDATE person_query SET params = '.$db->quote(serialize($params)).' WHERE id = '.(int)$row['id'];
				$res = $db->exec($SQL);
			}
		}

	}

	public static function saveSetting($symbol, $value)
	{
		$db = $GLOBALS['db'];
		$SQL = 'UPDATE setting
				SET value = '.$db->quote($value).'
				WHERE symbol = '.$db->quote($symbol);
		$res = $db->exec($SQL);
		return TRUE;

	}

	public static function deleteSetting($symbol)
	{
		$db = JethroDB::get();
		$SQL = 'DELETE FROM setting WHERE symbol = '.$db->quote($symbol);
		return $db->exec($SQL);
	}
}
