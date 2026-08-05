<?php
require_once JETHRO_ROOT.'/upgrades/upgradefixes/2.40.0_fix_db_charset/dbcharsetutils.php';

class View_Admin__Upgrade extends View
{
	private $_fix_result;

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function getTitle()
	{
		return 'Check for Jethro updates';
	}

	function processView()
	{
		if (!empty($_POST['fix_database_charset'])) {
			$this->_fix_result = DB_Charset_Utils::fix();
		}
	}

	function printView()
	{
		$this->_printUpdateChecker();
		$this->_printDatabaseHealth();
	}

	private function _printUpdateChecker()
	{
		?>
		<p id="message"></p>
		<script>
			$.ajax('https://api.github.com/repos/tbar0970/jethro-pmm/releases/latest', {
				dataType: 'json'
			}).done(function (data) {
				if (data.tag_name.replace('v', '') == '<?php echo JETHRO_VERSION; ?>') {
					$('#message').html('<i class="icon-ok"></i> Your system is up to date on version '+data.tag_name);
				} else if ('<?php echo JETHRO_VERSION; ?>' == 'DEV') {
					$('#message').html('<i class="icon-ok"></i> Your system is running Jethro in DEV mode.  The latest release is <a href="https://github.com/tbar0970/jethro-pmm/releases" target="_blank">'+data.tag_name + '</a>');
				} else {
					$('#message').html('<i class="icon-warning-sign"></i>Your system is running <?php echo JETHRO_VERSION; ?> but <a href="https://github.com/tbar0970/jethro-pmm/releases" target="_blank">'+data.tag_name + '</a> is available');
				}
			});
		</script>
		<?php
		if (defined('SYSADMIN_HREF') && strlen(SYSADMIN_HREF)) {
			echo '<p>For help, <a href="'.SYSADMIN_HREF.'">contact your system administrator</a></p>';
		}
	}
	private function _printDatabaseHealth()
	{
		$health = DB_Charset_Utils::checkHealth();
		$latin1check = DB_Charset_Utils::detectLatin1UTF8Bytes();
		?>
		<h3>Database health</h3>
		<?php
		if ($latin1check['risky']) {
			?>
			<div class="alert alert-error">
				<strong>WARNING: Data integrity risk detected</strong>
				<p><?php echo count($latin1check['risky_columns']); ?> column(s) in latin1 tables contain raw UTF-8 bytes
				(including multi-byte characters such as <?php echo ents($latin1check['risky_columns'][0]['utf8_seq_type']); ?>).
				This means Jethro was previously used with PHP &lt; 5.3.6, where the <code>charset=utf8</code> connection option was
				silently ignored, so UTF-8 data was stored as raw bytes in latin1 columns.</p>
				<p><strong>Fix database charset</strong> will handle this safely using a BLOB-detour method:
				<code>CONVERT TO binary</code> (strips the charset, preserves bytes) followed by
				<code>CONVERT TO utf8mb4</code> (reinterprets the preserved bytes as utf8mb4).
				All rows are validated first — any with invalid UTF-8 bytes will be reported and skipped.</p>
				<p><strong>Affected columns:</strong></p>
				<table class="table table-striped table-condensed table-min-width">
					<tr><th>Table</th><th>Column</th><th>UTF-8 sequences</th><th>Sample (hex)</th></tr>
					<?php foreach ($latin1check['risky_columns'] as $rc): ?>
					<tr>
						<td><?php echo ents($rc['tbl']); ?></td>
						<td><?php echo ents($rc['col']); ?></td>
						<td><?php echo ents($rc['utf8_seq_type']); ?></td>
						<td><code><?php echo ents($rc['encoded_sample']); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</table>
			</div>
			<?php
		}
		if (!empty($this->_fix_result)) {
			if ($this->_fix_result['converted']) {
				print_message(count($this->_fix_result['converted']).' table(s) converted to utf8mb4_unicode_ci: '.implode(', ', $this->_fix_result['converted']), 'success');
			}
			if ($this->_fix_result['database_default_aligned']) {
				print_message('The database default collation was set to utf8mb4_unicode_ci.', 'success');
			}
			foreach ($this->_fix_result['errors'] as $error) {
				print_message('Fix failed: '.$error, 'error');
			}
			if (empty($this->_fix_result['converted']) && empty($this->_fix_result['errors'])) {
				print_message('Nothing needed fixing.', 'success');
			}
		}
		if ($health['healthy']) {
			print_message('All tables use the utf8mb4 character set. No database problems detected.', 'success');
			$this->_printCollationDetail();
			return;
		}
		foreach ($health['problems'] as $problem) {
			print_message($problem, 'warning');
		}
		if ($health['problem_tables']) {
			?>
			<table class="table table-striped table-condensed table-min-width">
				<tr><th>Table</th><th>Collation</th><th>Row format</th></tr>
				<?php
				foreach ($health['problem_tables'] as $t) {
					$note = $t['needs_dynamic'] ? ' (will be moved to DYNAMIC)' : '';
					echo '<tr><td>'.ents($t['name']).'</td><td>'.ents($t['collation']).'</td><td>'.ents($t['row_format']).$note.'</td></tr>';
				}
				?>
			</table>
			<?php
		}
		$this->_printCollationDetail();
		?>
		<form method="post">
			<input type="submit" name="fix_database_charset" class="btn" value="Fix database charset"/>
		</form>
		<?php
	}

	private function _printCollationDetail()
	{
		$detail = DB_Charset_Utils::getCollationDetail();
		if (empty($detail['columns'])) return;

		$all_ok = count($detail['collation_counts']) <= 1
			&& ($detail['collation_counts'][0]['collation'] ?? '') === DB_Charset_Utils::TARGET_COLLATION;
		?>
		<h4>Column collation detail</h4>
		<p>Database: <strong><?php echo ents($detail['database_name']); ?></strong>.</p>
		<p>Database default: <strong><?php echo ents($detail['default_collation']); ?></strong>.
		   Target: <strong><?php echo ents(DB_Charset_Utils::TARGET_COLLATION); ?></strong>.</p>
		<?php if ($all_ok): ?>
		<details>
			<summary>All <?php echo count($detail['columns']); ?> columns match the target collation</summary>
		<?php endif; ?>
		<table class="table table-striped table-condensed table-min-width">
			<tr><th>Table</th><th>Column</th><th>Column collation</th><th>Table collation</th></tr>
			<?php
			foreach ($detail['columns'] as $c) {
				$mismatch = ($c['column_collation'] !== DB_Charset_Utils::TARGET_COLLATION);
				$style = $mismatch ? ' style="color: #c00"' : '';
				echo '<tr'.$style.'>'
					.'<td>'.ents($c['tbl']).'</td>'
					.'<td>'.ents($c['col']).'</td>'
					.'<td>'.ents($c['column_collation']).'</td>'
					.'<td>'.ents($c['table_collation']).'</td>'
					.'</tr>';
			}
			?>
		</table>
		<?php if ($all_ok): ?>
		</details>
		<?php endif; ?>
		<?php
	}
}
