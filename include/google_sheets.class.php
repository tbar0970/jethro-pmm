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

/**
 * Read-only Google Sheets access with a service account.
 *
 * Signs a JWT with the service-account key, exchanges it for a short-lived access token, and
 * reads one tab through the Sheets API. The spreadsheet can stay private: share it with the
 * service account's client_email as Viewer. Only the spreadsheets.readonly scope is requested.
 *
 * Used by scripts/import_persons.php --google-service-account. No Jethro bootstrap is needed,
 * so the pure helpers are unit-tested without a database.
 *
 * See docs/docs/developer/reference/import.md
 */
class Google_Sheets_Client
{
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const API_BASE = 'https://sheets.googleapis.com/v4';
	const SCOPE = 'https://www.googleapis.com/auth/spreadsheets.readonly';

	/** @var array<string,mixed> Parsed service-account JSON key */
	private $_credentials;

	/** @var string|null Cached access token for this process */
	private $_access_token = NULL;

	/**
	 * @param string $credentials_path Path to a service-account JSON key file
	 * @throws \RuntimeException when the file is missing or not a usable key
	 */
	public function __construct($credentials_path)
	{
		if (!is_string($credentials_path) || !strlen($credentials_path) || !is_readable($credentials_path)) {
			throw new \RuntimeException('Cannot read the Google service-account credentials file: '.$credentials_path);
		}
		$json = file_get_contents($credentials_path);
		if ($json === FALSE) {
			throw new \RuntimeException('Cannot read the Google service-account credentials file: '.$credentials_path);
		}
		$credentials = json_decode($json, TRUE);
		if (!is_array($credentials)) {
			throw new \RuntimeException($credentials_path.' is not valid JSON; download a service-account JSON key');
		}
		if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
			throw new \RuntimeException($credentials_path.' must contain client_email and private_key (a service-account JSON key)');
		}
		$this->_credentials = $credentials;
	}

	/**
	 * @return string The service account's client_email
	 */
	public function clientEmail()
	{
		return $this->_credentials['client_email'];
	}

	/**
	 * Fetch one tab as CSV.
	 *
	 * @param string $spreadsheet Share/edit URL or bare spreadsheet ID
	 * @param string|null $tab Tab name; NULL to use a gid in the URL, else the last visible tab
	 * @return string CSV with a header row
	 * @throws \RuntimeException on an API or credentials error
	 */
	public function fetchCsv($spreadsheet, $tab = NULL)
	{
		$id = self::spreadsheetId($spreadsheet);
		$gid = self::spreadsheetGid($spreadsheet);
		$title = $this->_sheetTitle($id, $tab, $gid);
		$range = "'".str_replace("'", "''", $title)."'!A1:ZZ";
		$url = self::API_BASE.'/spreadsheets/'.rawurlencode($id).'/values/'.rawurlencode($range)
			.'?majorDimension=ROWS&valueRenderOption=FORMATTED_VALUE&dateTimeRenderOption=FORMATTED_STRING';
		try {
			$data = $this->_apiGet($url);
		} catch (\RuntimeException $e) {
			$message = $e->getMessage();
			if (str_contains($message, 'HTTP 401') || str_contains($message, 'HTTP 403')) {
				$message .= ' Share the spreadsheet with '.$this->_credentials['client_email']
					.' as Viewer and enable the Google Sheets API in that project.';
			}
			throw new \RuntimeException($message);
		}
		if (empty($data['values']) || !is_array($data['values'])) {
			throw new \RuntimeException('The sheet tab "'.$title.'" is empty');
		}
		return self::rowsToCsv($data['values']);
	}

	/**
	 * Resolve a Google Sheets URL to its anonymous CSV export URL.
	 *
	 * Share/edit links are rewritten to the export endpoint. Without a gid in the link or an
	 * explicit tab name, the last tab is used, because Google's plain CSV export returns the
	 * first. Published links need a gid: they have no tab-name lookup.
	 *
	 * @param string $url
	 * @param string|null $tab Tab name; NULL to use the link's gid or the last tab
	 * @return array{url:string,last_tab:bool}
	 * @throws \RuntimeException for a non-Google URL or an unusable published link
	 */
	public static function csvUrl($url, $tab = NULL)
	{
		$parts = parse_url((string)$url);
		$path = (is_array($parts) && isset($parts['path'])) ? $parts['path'] : '';
		if (!is_array($parts) || empty($parts['host']) || strtolower($parts['host']) !== 'docs.google.com') {
			throw new \RuntimeException('Not a Google Sheets URL');
		}
		$gid = NULL;
		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
			if (isset($query['gid'])) $gid = $query['gid'];
		}
		if ($gid === NULL && !empty($parts['fragment']) && preg_match('/^gid=(\d+)$/', $parts['fragment'], $frag)) {
			$gid = $frag[1];
		}
		$gid_param = ($gid !== NULL && $gid !== '') ? '&gid='.rawurlencode($gid) : '';

		// Published-to-web sheet, e.g. /spreadsheets/d/e/<id>/pubhtml
		if (preg_match('#^/spreadsheets/d/e/([^/]+)/(pubhtml|pub)$#', $path, $pub)) {
			if ($tab !== NULL) {
				throw new \RuntimeException('A tab name is not supported for published links; use the tab\'s gid instead');
			}
			if ($gid_param === '') {
				throw new \RuntimeException('Published Google Sheets links need a gid; open the tab and copy its #gid= link');
			}
			return ['url' => 'https://docs.google.com/spreadsheets/d/e/'.$pub[1].'/pub?output=csv'.$gid_param, 'last_tab' => FALSE];
		}

		if (!preg_match('#^/spreadsheets/(?:u/\d+/)?d/([A-Za-z0-9_-]+)#', $path, $matches)) {
			throw new \RuntimeException('Not a Google Sheets URL');
		}
		$id = $matches[1];

		if ($tab !== NULL) {
			return [
				'url' => 'https://docs.google.com/spreadsheets/d/'.$id.'/gviz/tq?tqx=out:csv&sheet='.rawurlencode($tab),
				'last_tab' => FALSE,
			];
		}
		if (preg_match('#^/spreadsheets/(?:u/\d+/)?d/[A-Za-z0-9_-]+/(export|gviz|pub)(/|$)#', $path)) {
			return ['url' => $url, 'last_tab' => FALSE]; // explicit CSV endpoint; its own gid/range applies
		}
		if ($gid_param !== '') {
			return ['url' => 'https://docs.google.com/spreadsheets/d/'.$id.'/export?format=csv'.$gid_param, 'last_tab' => FALSE];
		}
		return [
			'url' => 'https://docs.google.com/spreadsheets/d/'.$id.'/export?format=csv&gid='.self::_lastTabGid($id),
			'last_tab' => TRUE,
		];
	}

	/**
	 * Fetch a CSV URL, rejecting HTML responses and oversized bodies.
	 *
	 * @param string $url
	 * @return string
	 * @throws \RuntimeException on a transport error or an unexpected response
	 */
	public static function fetchCsvUrl($url)
	{
		$body = self::_fetchAnonymous($url);
		if (substr(ltrim($body, "\xEF\xBB\xBF \t\r\n"), 0, 1) === '<') {
			throw new \RuntimeException('The URL returned HTML, not CSV. Use a Google Sheets share link or a CSV export URL.');
		}
		if (strlen($body) > 10 * 1024 * 1024) {
			throw new \RuntimeException('The CSV is larger than 10 MB');
		}
		return $body;
	}

	/**
	 * Find the gid of a spreadsheet's last tab.
	 *
	 * There is no anonymous API to list tabs, so read them from the public htmlview page, which has
	 * the same sharing requirement as the CSV export and lists tabs in order.
	 *
	 * @param string $id Spreadsheet ID
	 * @return string
	 * @throws \RuntimeException when the page cannot be parsed
	 */
	private static function _lastTabGid($id)
	{
		$html = self::_fetchAnonymous('https://docs.google.com/spreadsheets/d/'.$id.'/htmlview');
		if (preg_match_all('/gid:\s*"(\d+)"/', $html, $matches) && !empty($matches[1])) {
			return end($matches[1]);
		}
		throw new \RuntimeException('Could not determine the spreadsheet\'s last tab; use a link with a #gid=');
	}

	/**
	 * Perform an anonymous GET request.
	 *
	 * @param string $url
	 * @return string Response body
	 * @throws \RuntimeException on a transport or HTTP error
	 */
	private static function _fetchAnonymous($url)
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_USERAGENT => 'Jethro person importer',
			CURLOPT_MAXFILESIZE => 10 * 1024 * 1024,
		]);
		$body = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		if ($body === FALSE || $code < 200 || $code >= 300) {
			throw new \RuntimeException('Could not fetch the URL (HTTP '.$code.')'.($error ? ': '.$error : ''));
		}
		return $body;
	}

	/**
	 * Extract a spreadsheet ID from a Google Sheets URL or return a bare ID unchanged.
	 *
	 * @param string $url_or_id
	 * @return string
	 * @throws \RuntimeException for another host, a published-to-web URL, or an unparseable URL
	 */
	public static function spreadsheetId($url_or_id)
	{
		$value = trim((string)$url_or_id);
		if ($value !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
			return $value;
		}
		$parts = parse_url($value);
		if (!is_array($parts) || empty($parts['host']) || strtolower($parts['host']) !== 'docs.google.com') {
			throw new \RuntimeException('Not a Google Sheets URL or spreadsheet ID');
		}
		$path = isset($parts['path']) ? $parts['path'] : '';
		if (preg_match('#^/spreadsheets/d/e/#', $path)) {
			throw new \RuntimeException('Published Google Sheets links cannot be used with a service account; use the normal share/edit link');
		}
		if (!preg_match('#^/spreadsheets/(?:u/\d+/)?d/([A-Za-z0-9_-]+)#', $path, $matches)) {
			throw new \RuntimeException('Not a Google Sheets URL');
		}
		return $matches[1];
	}

	/**
	 * Extract a gid from a Google Sheets URL's query string or #gid= fragment.
	 *
	 * @param string $url
	 * @return string|null
	 */
	public static function spreadsheetGid($url)
	{
		$parts = parse_url((string)$url);
		if (!is_array($parts) || empty($parts['host'])) return NULL;
		$gid = NULL;
		if (!empty($parts['query'])) {
			parse_str($parts['query'], $query);
			if (isset($query['gid'])) $gid = $query['gid'];
		}
		if ($gid === NULL && !empty($parts['fragment']) && preg_match('/^gid=(\d+)$/', $parts['fragment'], $matches)) {
			$gid = $matches[1];
		}
		return ($gid !== NULL && $gid !== '') ? (string)$gid : NULL;
	}

	/**
	 * Build a signed RS256 JWT assertion for the OAuth token endpoint.
	 *
	 * @param array<string,mixed> $credentials Decoded service-account JSON
	 * @param int|null $issued_at Unix timestamp; defaults to now
	 * @return string
	 * @throws \RuntimeException when the private key cannot sign
	 */
	public static function assertion($credentials, $issued_at = NULL)
	{
		if (empty($credentials['client_email']) || empty($credentials['private_key'])) {
			throw new \RuntimeException('Service-account credentials need client_email and private_key');
		}
		$issued_at = ($issued_at === NULL) ? time() : (int)$issued_at;
		$header = ['alg' => 'RS256', 'typ' => 'JWT'];
		$claims = [
			'iss' => $credentials['client_email'],
			'scope' => self::SCOPE,
			'aud' => self::TOKEN_URL,
			'iat' => $issued_at,
			'exp' => $issued_at + 3600,
		];
		$unsigned = self::base64Url(json_encode($header)).'.'.self::base64Url(json_encode($claims));
		$signature = '';
		if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
			throw new \RuntimeException('Could not sign the service-account assertion; is the private_key valid?');
		}
		return $unsigned.'.'.self::base64Url($signature);
	}

	/**
	 * @param string $data
	 * @return string base64url without padding
	 */
	public static function base64Url($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Convert Sheets API rows to CSV, padding short rows so columns stay aligned.
	 *
	 * Google omits trailing empty cells, so a row can be shorter than the header.
	 *
	 * @param list<list<mixed>> $rows
	 * @return string
	 */
	public static function rowsToCsv($rows)
	{
		$width = 0;
		foreach ($rows as $row) {
			$width = max($width, is_array($row) ? count($row) : 1);
		}
		if ($width === 0) return '';
		$fp = fopen('php://temp', 'r+');
		foreach ($rows as $row) {
			$row = is_array($row) ? $row : [$row];
			fputcsv($fp, array_pad($row, $width, ''), ',', '"', '');
		}
		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp);
		return $csv;
	}

	/**
	 * Resolve which tab to read.
	 *
	 * @param string $spreadsheet_id
	 * @param string|null $tab Explicit tab name
	 * @param string|null $gid gid from the URL
	 * @return string
	 * @throws \RuntimeException when the tab cannot be resolved
	 */
	private function _sheetTitle($spreadsheet_id, $tab, $gid)
	{
		if ($tab !== NULL) return $tab;
		$data = $this->_apiGet(self::API_BASE.'/spreadsheets/'.rawurlencode($spreadsheet_id).'?fields=sheets.properties');
		$sheets = isset($data['sheets']) && is_array($data['sheets']) ? $data['sheets'] : [];
		if (empty($sheets)) {
			throw new \RuntimeException('The spreadsheet has no sheets');
		}
		if ($gid !== NULL) {
			foreach ($sheets as $sheet) {
				$properties = isset($sheet['properties']) ? $sheet['properties'] : [];
				if ((string)(isset($properties['sheetId']) ? $properties['sheetId'] : '') === $gid) {
					return $properties['title'];
				}
			}
			throw new \RuntimeException('No sheet has the gid '.$gid.' in that spreadsheet');
		}
		$last_title = NULL;
		foreach ($sheets as $sheet) {
			$properties = isset($sheet['properties']) ? $sheet['properties'] : [];
			if (!empty($properties['hidden'])) continue;
			$last_title = isset($properties['title']) ? $properties['title'] : NULL;
		}
		if ($last_title === NULL) {
			throw new \RuntimeException('The spreadsheet has no visible sheets');
		}
		return $last_title;
	}

	/**
	 * @return string Access token, cached for this process
	 * @throws \RuntimeException when Google rejects the assertion
	 */
	private function _accessToken()
	{
		if ($this->_access_token !== NULL) return $this->_access_token;
		$body = http_build_query([
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion' => self::assertion($this->_credentials),
		]);
		$response = $this->_request(
			self::TOKEN_URL,
			['Content-Type: application/x-www-form-urlencoded'],
			$body
		);
		$data = json_decode($response, TRUE);
		if (!is_array($data) || empty($data['access_token'])) {
			throw new \RuntimeException('Google did not return an access token');
		}
		$this->_access_token = $data['access_token'];
		return $this->_access_token;
	}

	/**
	 * @param string $url
	 * @return array<string,mixed> Decoded JSON
	 */
	private function _apiGet($url)
	{
		$response = $this->_request($url, ['Authorization: Bearer '.$this->_accessToken()]);
		$data = json_decode($response, TRUE);
		if (!is_array($data)) {
			throw new \RuntimeException('Google returned a non-JSON response');
		}
		return $data;
	}

	/**
	 * Perform one HTTP request, failing loudly with Google's error text.
	 *
	 * @param string $url
	 * @param list<string> $headers
	 * @param string|null $postBody When set, send a POST with this body
	 * @return string Response body
	 * @throws \RuntimeException on a transport or HTTP error
	 */
	private function _request($url, $headers = [], $postBody = NULL)
	{
		$ch = curl_init($url);
		$headers[] = 'Accept: application/json';
		$options = [
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_USERAGENT => 'Jethro person importer',
			CURLOPT_HTTPHEADER => $headers,
		];
		if ($postBody !== NULL) {
			$options[CURLOPT_POST] = TRUE;
			$options[CURLOPT_POSTFIELDS] = $postBody;
		}
		curl_setopt_array($ch, $options);
		$body = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		if ($body === FALSE || $code < 200 || $code >= 300) {
			$detail = '';
			$json = json_decode((string)$body, TRUE);
			if (is_array($json)) {
				if (!empty($json['error_description'])) {
					$detail = $json['error_description'];
				} else if (!empty($json['error']['message'])) {
					$detail = $json['error']['message'];
				} else if (!empty($json['error']) && is_string($json['error'])) {
					$detail = $json['error'];
				}
			}
			throw new \RuntimeException(
				'Google API request failed (HTTP '.$code.')'
				.($detail ? ': '.$detail : '')
				.($error ? ' ('.$error.')' : '')
			);
		}
		return $body;
	}
}
