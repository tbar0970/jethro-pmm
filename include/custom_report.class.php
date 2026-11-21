<?php
/**
 * Loader and runner for the custom reports directory (CUSTOM_REPORTS_DIR,
 * defaulting to JETHRO_ROOT/custom_reports).
 *
 * A custom report is a `.sql` file containing a single SELECT. Its title is the
 * filename in words ("new_members.sql" → "New Members"). Reports run with the
 * permissions of the system's database user, so the directory must only be
 * writable by administrators.
 */
class Custom_Report
{
	/**
	 * Directory the .sql report files are read from.
	 *
	 * @return string
	 */
	public static function getDirectory(): string
	{
		return ifdef('CUSTOM_REPORTS_DIR', JETHRO_ROOT.'/custom_reports');
	}

	/**
	 * All available reports, sorted by filename.
	 *
	 * @return array<string, string> filename => title
	 */
	public static function getAll(): array
	{
		$dir = self::getDirectory();
		if (!is_dir($dir)) return Array();

		$files = glob($dir.'/*.sql');
		if (empty($files)) return Array();

		$res = Array();
		foreach ($files as $f) {
			$f = basename($f);
			$res[$f] = substr(ucwords(str_replace('_', ' ', $f)), 0, -4);
		}
		return $res;
	}

	/**
	 * Run a report and return its rows.
	 *
	 * Returns null, after triggering an error, for an unknown filename, an
	 * unreadable file, or content that does not start with SELECT.
	 *
	 * @param string $file Filename as returned by getAll()
	 * @return array<int, array<string, mixed>>|null Rows, or null on failure
	 */
	public static function run(string $file): ?array
	{
		$reports = self::getAll();
		if (!isset($reports[$file])) {
			trigger_error("Invalid custom report filename");
			return NULL;
		}
		$fn = self::getDirectory().'/'.$file;
		if (!is_readable($fn)) {
			trigger_error("Custom report SQL file is not readable");
			return NULL;
		}

		$sql = @file_get_contents($fn);
		if ($sql === FALSE) {
			trigger_error("Could not open custom report SQL file");
			return NULL;
		}
		$sql = trim($sql);
		if (0 !== strpos(strtoupper($sql), 'SELECT')) {
			trigger_error("Custom report does not seem to start with SELECT; aborting");
			return NULL;
		}

		return $GLOBALS['db']->queryAll($sql);
	}
}
