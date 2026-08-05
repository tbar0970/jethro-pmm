<?php
/**
 * Database charset health checking and repair.
 *
 * Jethro's standard character set is utf8mb4 with the utf8mb4_unicode_ci collation.
 * Older installs (and some tables in newer ones) end up with utf8mb3 ("utf8") or
 * latin1 tables: utf8mb3 cannot store 4-byte characters, latin1 mangles anything
 * outside Western European text, and on MySQL 8 the default utf8mb4 collation
 * (utf8mb4_0900_ai_ci) refuses to coerce against utf8mb3 columns, breaking
 * comparisons. Newer installs can also end up on a non-standard utf8mb4 collation
 * (utf8mb4_general_ci, utf8mb4_uca1400_ai_ci or utf8mb4_0900_ai_ci) when the
 * database default collation differs from the server's default for utf8mb4.
 * These utilities find every table not on the standard collation and repair
 * them in place.
 *
 * Latin1 tables get special treatment: they may contain raw UTF-8 bytes (a
 * side-effect of PHP < 5.3.6 ignoring charset=utf8 in the PDO DSN). A plain
 * CONVERT TO CHARACTER SET would double-encode and corrupt them, so fix()
 * validates all rows then uses a BLOB detour: CONVERT TO binary (strips charset,
 * preserves bytes), then CONVERT TO utf8mb4 (reinterprets bytes as utf8mb4).
 * A pre-flight detector (detectLatin1UTF8Bytes) samples columns to warn about
 * this situation.
 *
 * The conversion is lossless for every character representable in utf8mb3 (the
 * Basic Multilingual Plane); 4-byte characters could not be stored in utf8mb3
 * tables in the first place, so there is nothing to lose. Tables still on the
 * legacy COMPACT/REDUNDANT row format are moved to DYNAMIC in the same ALTER,
 * because their indexes would otherwise exceed the 767-byte InnoDB key limit once
 * widened to utf8mb4 (eg the family table's composite name/address index is 1368
 * bytes as utf8mb4). Views need no rebuilding - their columns resolve to the base
 * tables' charsets at query time.
 */

class DB_Charset_Utils
{
	const TARGET_CHARSET = 'utf8mb4';
	const TARGET_COLLATION = 'utf8mb4_unicode_ci';

	/**
	 * Find every table in the current database whose collation is not the target one.
	 *
	 * @return array Each entry: array('name' =>, 'collation' =>, 'row_format' =>, 'needs_dynamic' => bool)
	 */
	private static function _findProblemTables()
	{
		$db = $GLOBALS['db'];
		$target = $db->quote(self::TARGET_COLLATION);
		$tables = $db->queryAll(
			"SELECT TABLE_NAME AS tbl, TABLE_COLLATION AS collation, ROW_FORMAT AS rowformat
			 FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_COLLATION IS NOT NULL
			   AND TABLE_COLLATION <> ".$target."
			 ORDER BY TABLE_NAME"
		);
		$result = Array();
		foreach ($tables as $t) {
			$result[] = Array(
				'name' => $t['tbl'],
				'collation' => $t['collation'],
				'row_format' => $t['rowformat'],
				'needs_dynamic' => in_array(strtoupper($t['rowformat']), Array('COMPACT', 'REDUNDANT')),
			);
		}
		return $result;
	}

	/**
	 * Detect whether we are connected to MySQL (as opposed to MariaDB).
	 * MariaDB always includes "MariaDB" in its VERSION() string; MySQL never does.
	 *
	 * @return bool True if the server is MySQL, false if MariaDB.
	 */
	private static function _isMySQL()
	{
		$db = $GLOBALS['db'];
		$version = $db->queryOne('SELECT VERSION()');
		return (stripos($version, 'MariaDB') === false);
	}

	/**
	 * Check the charset health of the database and all its tables.
	 *
	 * @return array{
	 *     healthy: bool,
	 *     problems: array,
	 *     problem_tables: array,
	 *     database_default: array{charset: string, collation: string}
	 * }
	 */
	public static function checkHealth()
	{
		$db = $GLOBALS['db'];
		$problems = Array();
		$problem_tables = self::_findProblemTables();

		if ($problem_tables) {
			$names = implode(', ', array_map(function ($t) {
				return $t['name'];
			}, $problem_tables));
			$problems[] = count($problem_tables).' table(s) do not use the standard '.self::TARGET_CHARSET.'/'.self::TARGET_COLLATION.' charset and collation: '.$names;
		}

		$db_default = $db->queryRow(
			"SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
			 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()"
		);
		if (empty($db_default) || $db_default['charset'] != self::TARGET_CHARSET || $db_default['collation'] != self::TARGET_COLLATION) {
			$actual = empty($db_default) ? '(unknown)' : $db_default['charset'].'/'.$db_default['collation'];
			$problems[] = 'The database default collation is '.$actual.'; expected '.self::TARGET_COLLATION.'. Tables created without an explicit charset (eg from future upgrades) would inherit the wrong one.';
		}

		return Array(
			'healthy' => empty($problems),
			'problems' => $problems,
			'problem_tables' => $problem_tables,
			'database_default' => $db_default,
		);
	}

	/**
	 * Column-level collation detail: every string column grouped by collation.
	 * Also returns summary counts and the database default.
	 *
	 * @return array{database_name: string, default_collation: string, collation_counts: array, columns: array}
	 */
	public static function getCollationDetail()
	{
		$db = $GLOBALS['db'];

		$schemaInfo = $db->queryRow(
			"SELECT SCHEMA_NAME, DEFAULT_COLLATION_NAME
			 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()"
		);

		$counts = $db->queryAll(
			"SELECT COLLATION_NAME AS collation, COUNT(*) AS cnt
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND COLLATION_NAME IS NOT NULL
			 GROUP BY COLLATION_NAME
			 ORDER BY COLLATION_NAME"
		);

		$columns = $db->queryAll(
			"SELECT c.TABLE_NAME AS tbl, c.COLUMN_NAME AS col,
			        c.COLLATION_NAME AS column_collation,
			        t.TABLE_COLLATION AS table_collation
			 FROM information_schema.COLUMNS c
			 JOIN information_schema.TABLES t
			   ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
			 WHERE c.TABLE_SCHEMA = DATABASE()
			   AND c.COLLATION_NAME IS NOT NULL
			 ORDER BY c.COLLATION_NAME, c.TABLE_NAME, c.COLUMN_NAME"
		);

		return Array(
			'database_name' => $schemaInfo['SCHEMA_NAME'],
			'default_collation' => $schemaInfo['DEFAULT_COLLATION_NAME'],
			'collation_counts' => $counts,
			'columns' => $columns,
		);
	}


	/**
	 * Detect whether any latin1 columns contain raw UTF-8 bytes that would
	 * be corrupted by ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4.
	 *
	 * Background: PHP < 5.3.6 silently ignored charset=utf8 in the PDO DSN.
	 * If Jethro ran on such PHP, UTF-8 strings (including emoji) went through
	 * a latin1 MySQL connection and were stored as raw bytes in latin1 columns.
	 * MySQL's CONVERT TO CHARACTER SET would then interpret each byte as a
	 * separate latin1 character and re-encode to UTF-8, double-encoding and
	 * corrupting the data.
	 *
	 * This method opens a temporary latin1 connection to read the raw bytes
	 * and checks whether they form valid UTF-8 multi-byte sequences — something
	 * impossible for genuine latin1 text.
	 *
	 * @param int $sampleLimit Maximum non-null rows to sample per column (default 100).
	 * @return array{
	 *     risky_columns: array<int, array{tbl: string, col: string, encoded_sample: string, utf8_seq_type: string}>,
	 *     risky: bool
	 * }
	 */
	public static function detectLatin1UTF8Bytes($sampleLimit = 100)
	{
		$db = $GLOBALS['db'];
		$risky = Array();

		// Find all latin1 text columns.
		$columns = $db->queryAll(
			"SELECT c.TABLE_NAME AS tbl, c.COLUMN_NAME AS col, c.DATA_TYPE AS dtype
			 FROM information_schema.COLUMNS c
			 WHERE c.TABLE_SCHEMA = DATABASE()
			   AND c.CHARACTER_SET_NAME LIKE 'latin1%'
			   AND c.DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'enum', 'set')
			 ORDER BY c.TABLE_NAME, c.COLUMN_NAME"
		);

		if (empty($columns)) {
			return Array('risky_columns' => Array(), 'risky' => false);
		}

		// Open a temporary latin1 connection to read raw bytes.
		$rawErr = null;
		$rawDb = self::_openRawConnection('latin1', $rawErr);
		if ($rawDb === null) {
			return Array(
				'risky_columns' => Array(),
				'risky' => false,
				'error' => 'Could not open latin1 connection for byte inspection'.($rawErr ? ': '.$rawErr : '.'),
			);
		}

		$foundTables = Array(); // deduplicate: one sample per table

		foreach ($columns as $col) {
			$tbl = $col['tbl'];
			$cname = $col['col'];

			if (isset($foundTables[$tbl])) continue; // already flagged this table

			$stmt = $rawDb->prepare(
				'SELECT `'.$cname.'` FROM `'.$tbl.'` WHERE `'.$cname.'` IS NOT NULL AND `'.$cname.'` <> \'\' LIMIT '.(int)$sampleLimit
			);
			$stmt->execute();

			while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
				$value = $row[0];
				if ($value === null || $value === '') continue;

				// Check whether the raw bytes form valid UTF-8 with
				// non-ASCII content (multi-byte sequences).
				if (self::_containsUTF8Multibyte($value)) {
					$seqType = self::_utf8SeqDescription($value);
					$risky[] = Array(
						'tbl' => $tbl,
						'col' => $cname,
						'encoded_sample' => self::_hexSample($value, 60),
						'utf8_seq_type' => $seqType,
					);
					$foundTables[$tbl] = true;
					break; // only report once per column
				}
			}
			$stmt->closeCursor();
		}

		// Close the temporary connection.
		$rawDb = null;

		return Array(
			'risky_columns' => $risky,
			'risky' => !empty($risky),
		);
	}

	/**
	 * Check whether a raw byte string contains valid UTF-8 multi-byte
	 * sequences (i.e., not pure ASCII or isolated high bytes).
	 *
	 * @param string $bytes Raw bytes from a latin1 column.
	 * @return bool
	 */
	private static function _containsUTF8Multibyte($bytes)
	{
		$len = strlen($bytes);
		$hasMultibyte = false;

		for ($i = 0; $i < $len; $i++) {
			$b = ord($bytes[$i]);

			if ($b < 0x80) {
				// ASCII — valid standalone, skip.
				continue;
			}

			// Determine UTF-8 sequence length and validate.
			$seqLen = 0;
			if (($b & 0xE0) === 0xC0) {
				$seqLen = 2;
			} elseif (($b & 0xF0) === 0xE0) {
				$seqLen = 3;
			} elseif (($b & 0xF8) === 0xF0) {
				$seqLen = 4;
			} else {
				// Invalid UTF-8 lead byte (0x80-0xBF, 0xF5-0xFF) — this is
				// either genuine latin1 high bytes or invalid data.
				// In either case, it's NOT valid UTF-8 stored as latin1,
				// so this column is safe to convert.
				return false;
			}

			// Check continuation bytes.
			if ($i + $seqLen > $len) return false;
			for ($j = 1; $j < $seqLen; $j++) {
				$cb = ord($bytes[$i + $j]);
				if (($cb & 0xC0) !== 0x80) return false;
			}

			// Validate overlong sequences and surrogates.
			if ($seqLen === 2) {
				$cp = (($b & 0x1F) << 6) | (ord($bytes[$i + 1]) & 0x3F);
				if ($cp < 0x80) return false; // overlong
			} elseif ($seqLen === 3) {
				$cp = (($b & 0x0F) << 12) | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
				if ($cp < 0x800) return false; // overlong
				if ($cp >= 0xD800 && $cp <= 0xDFFF) return false; // surrogate
			} elseif ($seqLen === 4) {
				$cp = (($b & 0x07) << 18) | ((ord($bytes[$i + 1]) & 0x3F) << 12) | ((ord($bytes[$i + 2]) & 0x3F) << 6) | (ord($bytes[$i + 3]) & 0x3F);
				if ($cp < 0x10000) return false; // overlong
				if ($cp > 0x10FFFF) return false; // beyond Unicode
			}

			$hasMultibyte = true;
			$i += $seqLen - 1; // skip continuation bytes
		}

		return $hasMultibyte;
	}

	/**
	 * Describe the types of UTF-8 multi-byte sequences found in a byte string.
	 * Returns a human-readable summary like "2-byte, 4-byte (emoji)".
	 *
	 * @param string $bytes Raw bytes.
	 * @return string
	 */
	private static function _utf8SeqDescription($bytes)
	{
		$found = Array();
		$len = strlen($bytes);
		$maxInspect = min($len, 200);

		for ($i = 0; $i < $maxInspect; $i++) {
			$b = ord($bytes[$i]);

			if ($b < 0x80) continue;

			$seqLen = 0;
			if (($b & 0xE0) === 0xC0) $seqLen = 2;
			elseif (($b & 0xF0) === 0xE0) $seqLen = 3;
			elseif (($b & 0xF8) === 0xF0) $seqLen = 4;
			else continue;

			// Quick continuation check.
			if ($i + $seqLen > $len) break;
			$valid = true;
			for ($j = 1; $j < $seqLen; $j++) {
				if ((ord($bytes[$i + $j]) & 0xC0) !== 0x80) { $valid = false; break; }
			}
			if (!$valid) continue;

			$found[$seqLen] = true;
			$i += $seqLen - 1;
		}

		$parts = Array();
		if (isset($found[2])) $parts[] = '2-byte';
		if (isset($found[3])) $parts[] = '3-byte';
		if (isset($found[4])) $parts[] = '4-byte (emoji)';

		return implode(', ', $parts);
	}

	/**
	 * Format a byte string as a hex sample for display.
	 *
	 * @param string $bytes Raw bytes.
	 * @param int $maxLen Maximum bytes to sample.
	 * @return string
	 */
	private static function _hexSample($bytes, $maxLen = 60)
	{
		$sample = substr($bytes, 0, $maxLen);
		$hex = strtoupper(bin2hex($sample));
		if (strlen($bytes) > $maxLen) $hex .= '...';
		return $hex;
	}

	/**
	 * Check whether a byte string is entirely valid UTF-8.
	 * Pure ASCII returns true — it is a valid UTF-8 subset.
	 *
	 * @param string $bytes Raw bytes.
	 * @return bool
	 */
	private static function _isValidUTF8($bytes)
	{
		$len = strlen($bytes);
		for ($i = 0; $i < $len; $i++) {
			$b = ord($bytes[$i]);
			if ($b < 0x80) continue;

			$seqLen = 0;
			if (($b & 0xE0) === 0xC0) $seqLen = 2;
			elseif (($b & 0xF0) === 0xE0) $seqLen = 3;
			elseif (($b & 0xF8) === 0xF0) $seqLen = 4;
			else return false;

			if ($i + $seqLen > $len) return false;
			for ($j = 1; $j < $seqLen; $j++) {
				if ((ord($bytes[$i + $j]) & 0xC0) !== 0x80) return false;
			}

			// Validate overlong sequences and surrogates.
			if ($seqLen === 2) {
				$cp = (($b & 0x1F) << 6) | (ord($bytes[$i + 1]) & 0x3F);
				if ($cp < 0x80) return false;
			} elseif ($seqLen === 3) {
				$cp = (($b & 0x0F) << 12) | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
				if ($cp < 0x800) return false;
				if ($cp >= 0xD800 && $cp <= 0xDFFF) return false;
			} elseif ($seqLen === 4) {
				$cp = (($b & 0x07) << 18) | ((ord($bytes[$i + 1]) & 0x3F) << 12) | ((ord($bytes[$i + 2]) & 0x3F) << 6) | (ord($bytes[$i + 3]) & 0x3F);
				if ($cp < 0x10000) return false;
				if ($cp > 0x10FFFF) return false;
			}

			$i += $seqLen - 1;
		}
		return true;
	}

	/**
	 * Open a raw PDO connection with a specific charset, independent of
	 * the main JethroDB connection. Used to read bytes without MySQL's
	 * automatic charset conversion.
	 *
	 * @param string $charset Connection charset (e.g. 'latin1').
	 * @param string|null &$error Filled with the PDO error message when the
	 *     return value is null, so callers can surface a useful diagnostic
	 *     instead of a bare "could not open" message.
	 * @return PDO|null PDO connection, or null on failure.
	 */
	private static function _openRawConnection($charset, &$error = null)
	{
		// Reuse the app's configured DSN when one is defined (eg the
		// unix-socket connections used by devbox/docker), swapping in the raw
		// charset. Fall back to host/port params for confs that set
		// DB_HOST/DB_PORT/DB_TYPE instead of DB_DSN.
		if (defined('DB_DSN')) {
			$dsn = preg_replace('/;charset=[^;]*/', '', DB_DSN).';charset='.$charset;
		} else {
			$type = ifdef('DB_TYPE', 'mysql');
			$host = ifdef('DB_HOST', 'localhost');
			$port = ifdef('DB_PORT', '');
			$portPart = strlen($port) ? ';port='.$port : '';
			$dsn = $type.':host='.$host.$portPart.';dbname='.DB_DATABASE.';charset='.$charset;
		}

		try {
			return new PDO($dsn, DB_USERNAME, DB_PASSWORD, Array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			));
		} catch (Exception $e) {
			$error = $e->getMessage();
			return null;
		}
	}

	/**
	 * Get all latin1 text columns for a specific table.
	 *
	 * @param string $tbl Table name.
	 * @return array<int, string> Column names.
	 */
	private static function _getLatin1TextColumns($tbl)
	{
		$db = $GLOBALS['db'];
		return $db->queryCol(
			"SELECT COLUMN_NAME
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = ".$db->quote($tbl)."
			   AND CHARACTER_SET_NAME LIKE 'latin1%'
			   AND DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'enum', 'set')
			 ORDER BY ORDINAL_POSITION"
		);
	}

	/**
	 * Get the full type definitions of all latin1 text columns in a table.
	 *
	 * Used to reconstruct column types after the BLOB detour: CONVERT TO
	 * CHARACTER SET binary turns VARCHAR into VARBINARY, TEXT into BLOB,
	 * etc., and a subsequent CONVERT TO CHARACTER SET utf8mb4 is a no-op
	 * on binary-typed columns. We snapshot the original types before the
	 * binary step, then MODIFY each column back individually.
	 *
	 * Returns separate type and trailing-attributes strings so the caller
	 * can insert CHARACTER SET / COLLATE between them:
	 *
	 *   MODIFY `col` <type> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci <trailing>
	 *
	 * @param string $tbl Table name.
	 * @return array<int, array{name: string, type: string, trailing: string}>
	 */
	private static function _getLatin1ColumnDefinitions($tbl)
	{
		$db = $GLOBALS['db'];
		$rows = $db->queryAll(
			"SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE, EXTRA
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = ".$db->quote($tbl)."
			   AND CHARACTER_SET_NAME LIKE 'latin1%'
			   AND DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext', 'enum', 'set')
			 ORDER BY ORDINAL_POSITION"
		);

		$result = Array();
		foreach ($rows as $row) {
			$trailing = '';
			if ($row['IS_NULLABLE'] === 'NO') {
				$trailing .= ' NOT NULL';
			}
			// COLUMN_DEFAULT from information_schema is already a SQL expression
			// (e.g. '', 0, NULL, current_timestamp()) — interpolate directly.
			if ($row['COLUMN_DEFAULT'] !== null) {
				$trailing .= ' DEFAULT '.$row['COLUMN_DEFAULT'];
			}
			if (!empty($row['EXTRA'])) {
				$trailing .= ' '.$row['EXTRA'];
			}
			$result[] = Array(
				'name' => $row['COLUMN_NAME'],
				'type' => $row['COLUMN_TYPE'],
				'trailing' => ltrim($trailing),
			);
		}
		return $result;
	}

	/**
	 * Validate that every row in every latin1 text column of a table
	 * contains valid UTF-8 bytes. Used as a gate before the BLOB-detour
	 * ALTER — if any row has genuine latin1 bytes, the BLOB-detour
	 * would fail or corrupt.
	 *
	 * @param PDO $rawDb A latin1-charset PDO connection.
	 * @param string $tbl Table name.
	 * @return array<int, string> Error messages; empty if all rows pass.
	 */
	private static function _validateTableAllRowsUTF8($rawDb, $tbl)
	{
		$errors = Array();
		$columns = self::_getLatin1TextColumns($tbl);

		foreach ($columns as $col) {
			$badSamples = Array();
			$badCount = 0;
			$maxBadSamples = 3;

			$stmt = $rawDb->prepare(
				"SELECT `".$col."` FROM `".$tbl."` WHERE `".$col."` IS NOT NULL AND `".$col."` <> ''"
			);
			$stmt->execute();

			while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
				$value = $row[0];
				if ($value === null || $value === '') continue;

				if (!self::_isValidUTF8($value)) {
					$badCount++;
					if (count($badSamples) < $maxBadSamples) {
						$badSamples[] = self::_hexSample($value, 40);
					}
				}
			}
			$stmt->closeCursor();

			if ($badCount > 0) {
				$msg = $col.': '.$badCount.' row(s) contain invalid UTF-8 bytes';
				if ($badSamples) {
					$msg .= ' (samples: '.implode(', ', $badSamples).')';
				}
				$errors[] = $msg;
			}
		}

		return $errors;
	}
	/**
	 * Repair the database charset: convert every table not using the standard
	 * database default collation. Safe to run repeatedly.
	 *
	 * @return array{converted: array, errors: array, database_default_aligned: bool}
	 */
	public static function fix()
	{
		$db = $GLOBALS['db'];
		$result = Array(
			'converted' => Array(),
			'errors' => Array(),
			'database_default_aligned' => FALSE,
		);

		// Separate latin1 tables: they may contain raw UTF-8 bytes
		// (PHP < 5.3.6 bug) that CONVERT TO CHARACTER SET would
		// double-encode. Use a BLOB detour instead: strip charset
		// with CONVERT TO binary, then reinterpret bytes as utf8mb4.
		$problemTables = self::_findProblemTables();
		$latin1Tables = Array();
		$otherTables = Array();
		foreach ($problemTables as $t) {
			if (strpos($t['collation'], 'latin1') === 0) {
				$latin1Tables[] = $t;
			} else {
				$otherTables[] = $t;
			}
		}

		// Fix latin1 tables via BLOB detour, after full-row validation.
		if ($latin1Tables) {
			$rawErr = null;
			$rawDb = self::_openRawConnection('latin1', $rawErr);
			if ($rawDb === null) {
				$result['errors'][] = 'Could not open latin1 connection for validation'.($rawErr ? ': '.$rawErr : '').' — skipping '.count($latin1Tables).' latin1 table(s).';
			} else {
				foreach ($latin1Tables as $t) {
					$validationErrors = self::_validateTableAllRowsUTF8($rawDb, $t['name']);
					// If every latin1 text column passes UTF-8 validation, the table
					// contains raw UTF-8 bytes (double-encoded via old PHP bug) and
					// needs the BLOB detour to avoid further corruption.  Mixed or
					// genuinely-latin1 tables still need converting — plain CONVERT
					// TO CHARACTER SET handles those safely because cp1252→utf8mb4
					// is a lossless encoding upgrade.
					$needsBlobDetour = empty($validationErrors);

					// Snapshot column types before binary conversion —
					// CONVERT TO CHARACTER SET binary turns VARCHAR→VARBINARY,
					// TEXT→BLOB, etc. A subsequent CONVERT TO utf8mb4 is a no-op
					// on binary-typed columns, so we must MODIFY each column
					// back individually.
					$columnDefs = self::_getLatin1ColumnDefinitions($t['name']);
					if ($needsBlobDetour) {
						$extra = $t['needs_dynamic'] ? ', ROW_FORMAT=DYNAMIC' : '';
						try {
							$db->exec('ALTER TABLE `'.$t['name'].'` CONVERT TO CHARACTER SET binary');
							$modifyParts = Array();
							foreach ($columnDefs as $cd) {
								$modifyParts[] = 'MODIFY `'.$cd['name'].'` '.$cd['type']
									.' CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION
									.($cd['trailing'] ? ' '.$cd['trailing'] : '');
							}
							$db->exec('ALTER TABLE `'.$t['name'].'` '.implode(', ', $modifyParts).$extra);
							// Set the table default charset back from binary to utf8mb4.
							// The MODIFY above fixes columns; this fixes the TABLE_COLLATION.
							$db->exec('ALTER TABLE `'.$t['name'].'` DEFAULT CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION);
							$result['converted'][] = $t['name'];
						} catch (Exception $e) {
							// If the binary→utf8mb4 conversion fails, the table
							// is left in a binary state. Roll back each column
							// to its original latin1 type via explicit MODIFY
							// (CONVERT TO CHARACTER SET is a no-op on binary columns).
							try {
								$origCollation = $t['collation'];
								$origCharset = explode('_', $origCollation)[0];
								$modifyParts = Array();
								foreach ($columnDefs as $cd) {
									$modifyParts[] = 'MODIFY `'.$cd['name'].'` '.$cd['type']
										.' CHARACTER SET '.$origCharset.' COLLATE '.$origCollation
										.($cd['trailing'] ? ' '.$cd['trailing'] : '');
								}
								$db->exec('ALTER TABLE `'.$t['name'].'` '.implode(', ', $modifyParts));
								$db->exec('ALTER TABLE `'.$t['name'].'` DEFAULT CHARACTER SET '.$origCharset.' COLLATE '.$origCollation);
							} catch (Exception $rollbackError) {
								// Rollback failed — table may be stuck as binary.
								$result['errors'][] = $t['name'].': '.$e->getMessage().' (rollback also failed: '.$rollbackError->getMessage().')';
								continue;
							}
							$result['errors'][] = $t['name'].': '.$e->getMessage().' (rolled back to '.$t['collation'].')';
						}
					} else {
						// Mixed or genuinely-latin1 data: plain CONVERT TO CHARACTER SET
						// correctly maps cp1252 bytes to their utf8mb4 equivalents.
						$extra = $t['needs_dynamic'] ? ', ROW_FORMAT=DYNAMIC' : '';
						try {
							$db->exec('ALTER TABLE `'.$t['name'].'` CONVERT TO CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION.$extra);
							$result['converted'][] = $t['name'];
							if ($validationErrors) {
								foreach ($validationErrors as $err) {
									$result['errors'][] = $t['name'].': '.$err.' — converted via CONVERT TO CHARACTER SET (not BLOB detour).';
								}
							}
						} catch (Exception $e) {
							$result['errors'][] = $t['name'].': '.$e->getMessage();
						}
					}
				}
				$rawDb = null;
			}
		}

		// Fix non-latin1 tables via CONVERT TO CHARACTER SET.
		foreach ($otherTables as $t) {
			$extra = $t['needs_dynamic'] ? ', ROW_FORMAT=DYNAMIC' : '';
			try {
				$db->exec('ALTER TABLE `'.$t['name'].'` CONVERT TO CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION.$extra);
				$result['converted'][] = $t['name'];
			} catch (Exception $e) {
				$result['errors'][] = $t['name'].': '.$e->getMessage();
			}
		}

		// Align the database default so future tables inherit the right collation.
		$dbname = $db->queryOne('SELECT DATABASE()');
		if (!empty($dbname)) {
			$current = $db->queryRow(
				"SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
				 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()"
			);
			$needsAlignment = (empty($current) || $current['charset'] != self::TARGET_CHARSET || $current['collation'] != self::TARGET_COLLATION);

			if ($needsAlignment) {
				if (self::_isMySQL()) {
					$cmd = 'ALTER DATABASE `'.$dbname.'` CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION.';';
					throw new RuntimeException(
						'Cannot set the database default charset/collation on MySQL without the SUPER privilege. '.
						'Please run the following command as a MySQL superuser (e.g. root):'."\n".
						$cmd
					);
				}

				try {
					$db->exec('ALTER DATABASE `'.$dbname.'` CHARACTER SET '.self::TARGET_CHARSET.' COLLATE '.self::TARGET_COLLATION);
					$result['database_default_aligned'] = TRUE;
				} catch (Exception $e) {
					$result['errors'][] = 'Could not set the database default collation: '.$e->getMessage();
				}
			}
		}

		return $result;
	}
}
