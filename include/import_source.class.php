<?php
/*
 * JETHRO PMM
 *
 * This file is part of Jethro PMM - https://github.com/tbar0970/jethro-pmm
 *
 * Jethro PMM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jethro PMM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jethro PMM.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once JETHRO_ROOT.'/include/google_sheets.class.php';

/**
 * A data source for the admin person importer.
 *
 * Each source renders its own fields on the begin screen and turns the submitted request into a
 * CSV file for Person_Importer. Adding a source (for example Wufoo) means adding a subclass here
 * and one line in getSources(); View_Admin__Import does not change.
 *
 * See docs/docs/developer/reference/import.md
 */
abstract class Import_Source
{
	/**
	 * @return string Value of the data_source radio
	 */
	abstract public function key();

	/**
	 * @return string Radio label
	 */
	abstract public function label();

	/**
	 * Print the source's form fields. They are shown only while this source is selected.
	 *
	 * @param array<string,mixed> $request $_REQUEST
	 * @return void
	 */
	abstract public function printFields($request);

	/**
	 * Turn the submitted request into a CSV file for Person_Importer::parse().
	 *
	 * @param array<string,mixed> $request $_REQUEST
	 * @param array<string,mixed> $files $_FILES
	 * @return array{path:string,separator:string}
	 * @throws \RuntimeException when the request is incomplete or the data cannot be fetched
	 */
	abstract public function fetch($request, $files);

	/**
	 * @return list<Import_Source> In menu order
	 */
	public static function getSources()
	{
		return [
			new Import_Source_Upload(),
			new Import_Source_Paste(),
			new Import_Source_Google(),
		];
	}

	/**
	 * @param string $key
	 * @return Import_Source|null
	 */
	public static function get($key)
	{
		foreach (self::getSources() as $source) {
			if ($source->key() === $key) return $source;
		}
		return NULL;
	}

	/**
	 * Print one radio per source, with that source's fields in a following div.
	 *
	 * The data-toggle="visible" handler shows the div whose data-source matches the checked
	 * radio; the inline display:none on the others sets the initial state.
	 *
	 * @param array<string,mixed> $request $_REQUEST
	 * @return void
	 */
	public static function printChooser($request)
	{
		$selected = isset($request['data_source']) ? $request['data_source'] : 'upload';
		foreach (self::getSources() as $source) {
			$key = $source->key();
			?>
			<label>
				<input type="radio" name="data_source" value="<?php echo ents($key); ?>"
					data-toggle="visible" data-target=".importsource" data-match-attr="data-source"
					<?php if ($selected === $key) echo 'checked="checked"'; ?> />
				<?php echo ents($source->label()); ?>
			</label>
			<div class="importsource" data-source="<?php echo ents($key); ?>"
				<?php if ($selected !== $key) echo 'style="display:none"'; ?>>
				<?php $source->printFields($request); ?>
			</div>
			<?php
		}
	}

	/**
	 * Write CSV text to a temporary file.
	 *
	 * @param string $csv
	 * @return string Path to the temporary file
	 * @throws \RuntimeException when the file cannot be written
	 */
	protected function writeTempCsv($csv)
	{
		$tmp = tempnam(sys_get_temp_dir(), 'jethroimport');
		if (($tmp === FALSE) || (file_put_contents($tmp, $csv) === FALSE)) {
			throw new \RuntimeException(_('Could not write a temporary CSV file'));
		}
		return $tmp;
	}
}

/**
 * A file uploaded from the browser.
 */
class Import_Source_Upload extends Import_Source
{
	public function key()
	{
		return 'upload';
	}

	public function label()
	{
		return 'Upload file';
	}

	public function printFields($request)
	{
		?>
		<div class="control-group">
			<input type="file" name="import" />
		</div>
		<?php
	}

	public function fetch($request, $files)
	{
		if (empty($files['import']) || empty($files['import']['tmp_name'])) {
			throw new \RuntimeException(_('You must upload a file'));
		}
		return ['path' => $files['import']['tmp_name'], 'separator' => ','];
	}
}

/**
 * Tab-separated text pasted into a textarea.
 */
class Import_Source_Paste extends Import_Source
{
	public function key()
	{
		return 'input';
	}

	public function label()
	{
		return 'Paste text...';
	}

	public function printFields($request)
	{
		?>
		<div class="control-group">
			<textarea name="importdata" style="width: 50ex; height: 6ex"
				placeholder="Paste Tab-separated text here"><?php echo ents(array_get($request, 'importdata', '')); ?></textarea>
		</div>
		<?php
	}

	public function fetch($request, $files)
	{
		$data = array_get($request, 'importdata', '');
		if (!strlen($data)) {
			throw new \RuntimeException(_('You must enter some import data'));
		}
		return ['path' => $this->writeTempCsv($data), 'separator' => "\t"];
	}
}

/**
 * A Google Sheet fetched from its share link, optionally through a service-account key.
 */
class Import_Source_Google extends Import_Source
{
	public function key()
	{
		return 'google';
	}

	public function label()
	{
		return 'From Google Spreadsheet';
	}

	public function printFields($request)
	{
		$saved = (isset($_SESSION['import_source_google']) && is_array($_SESSION['import_source_google']))
			? $_SESSION['import_source_google'] : [];
		$url = array_key_exists('google_url', $request)
			? $request['google_url']
			: array_get($saved, 'url', '');
		$tab = array_key_exists('google_spreadsheet_tab', $request)
			? $request['google_spreadsheet_tab']
			: array_get($saved, 'tab', '');
		?>
		<div class="control-group form-inline">
			<input type="text" name="google_url" class="input-xxlarge"
				value="<?php echo ents($url); ?>"
				placeholder="Paste the Google Sheets share link" />
			<label for="google_spreadsheet_tab">sheet name</label>
			<input type="text" id="google_spreadsheet_tab" name="google_spreadsheet_tab" class="input-medium"
				value="<?php echo ents($tab); ?>"
				placeholder="optional" />
		</div>
		<details>
			<summary>Service account</summary>
			<div class="indent-left">
				<div class="control-group">
					Service-account key (optional): <input type="file" name="google_service_account" accept=".json,application/json" />
				</div>
				<span class="smallprint">Without a key, the sheet must be readable by anyone with the link. With a key, share the sheet with the key's service account as Viewer; the key is used for this import only and is not stored.</span>
				<?php if (!empty($saved['client_email'])): ?>
					<br /><span class="smallprint">Last used service account: <?php echo ents($saved['client_email']); ?>. Upload the key again to use it.</span>
				<?php endif; ?>
			</div>
		</details>
		<?php
	}

	public function fetch($request, $files)
	{
		$url = trim(array_get($request, 'google_url', ''));
		if (!strlen($url)) {
			throw new \RuntimeException(_('You must paste a Google Sheets link'));
		}
		$tab = trim(array_get($request, 'google_spreadsheet_tab', ''));
		if ($tab === '') $tab = NULL;
		$_SESSION['import_source_google']['url'] = $url;
		$_SESSION['import_source_google']['tab'] = ($tab === NULL) ? '' : $tab;
		$key_path = NULL;
		if (isset($files['google_service_account']) && is_array($files['google_service_account'])) {
			$upload = $files['google_service_account'];
			$upload_error = isset($upload['error']) ? (int)$upload['error'] : UPLOAD_ERR_NO_FILE;
			if ($upload_error === UPLOAD_ERR_OK && !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
				$key_path = $upload['tmp_name'];
			} else if ($upload_error !== UPLOAD_ERR_NO_FILE) {
				throw new \RuntimeException(_('The service-account key file could not be uploaded'));
			}
		}
		if ($key_path !== NULL) {
			$client = new Google_Sheets_Client($key_path);
			$body = $client->fetchCsv($url, $tab);
			$_SESSION['import_source_google']['client_email'] = $client->clientEmail();
		} else {
			$resolved = Google_Sheets_Client::csvUrl($url, $tab);
			$body = Google_Sheets_Client::fetchCsvUrl($resolved['url']);
		}
		return ['path' => $this->writeTempCsv($body), 'separator' => ','];
	}
}
