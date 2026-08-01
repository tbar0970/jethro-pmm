<?php

require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.40.0_fix_db_charset/dbcharsetutils.php';

class Charset_Fixer
{

	public static function runHTML()
	{
		echo '<pre class="alert alert-info">';
		echo "<b>FIXING DATABASE ENCODINGS FOR JETHRO v.2.39:</b>\n";
		self::run();
		if (!ifdef('NEEDS_UTF8MB4_UPGRADE')) {
			echo "ENCODING FIX COMPLETE \n";
		}
		echo '</pre>';
	}

	public static function run()
	{
		if (!ifdef('NEEDS_UTF8MB4_UPGRADE')) {
			echo "Encoding fix already completed \n";
			return;
		}

		// Before converting, check whether any latin1 columns contain raw
		// UTF-8 bytes (PHP < 5.3.6 bug). If found, warn but proceed — fix()
		// now handles these safely via a BLOB detour.
		$latin1check = DB_Charset_Utils::detectLatin1UTF8Bytes();
		if ($latin1check['risky']) {
			echo "\n";
			foreach ($latin1check['risky_columns'] as $rc) {
				echo 'Note: '.$rc['tbl'].'.'.$rc['col'].
					' contains '.$rc['utf8_seq_type'].' UTF-8 sequences '.
					'(sample: '.$rc['encoded_sample'].") — will fix via BLOB detour.\n";
			}
			echo "\n";
		}
		if (!empty($latin1check['error'])) {
			echo 'Note: could not check for latin1/UTF-8 mismatch: '.$latin1check['error']."\n";
		}

		try {
			$result = DB_Charset_Utils::fix();
		} catch (RuntimeException $e) {
			// MySQL requires SUPER privilege for ALTER DATABASE — the tables
			// were already converted, but the database default couldn't be set.
			echo $e->getMessage()."\n";
			Config_Manager::deleteSetting('NEEDS_UTF8MB4_UPGRADE');
			return;
		}

		if ($result['converted']) {
			echo count($result['converted']).' table(s) converted to utf8mb4_unicode_ci: '.implode(', ', $result['converted'])."\n";
		}
		if ($result['database_default_aligned']) {
			echo "Database default collation set to utf8mb4_unicode_ci.\n";
		}
		if ($result['errors']) {
			echo "Errors:\n";
			foreach ($result['errors'] as $error) {
				echo '  * '.$error."\n";
			}
		}
		if (empty($result['converted']) && empty($result['errors'])) {
			echo "No tables needed conversion.\n";
		}

		Config_Manager::deleteSetting('NEEDS_UTF8MB4_UPGRADE');
	}
}
