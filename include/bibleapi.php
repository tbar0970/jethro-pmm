<?php
const APIBIBLE_NIV='78a9f6124f344018-01';
/**
 * Bible API library - provides functions to look up Bible passage text
 * via the API.Bible REST API.
 *
 * Requires BIBLE_API_APIKEY constant to be defined in conf.php.
 */


/**
 * Fetch the HTML content of a Bible passage from a specific translation.
 *
 * @param string $reference   Human-readable reference like "John 3:16-17" or "Jer 3:6-18"
 * @param string $bibleId Bible translation to use. This is an id as defined by https://api.bible's API (run 'php scripts/biblelookup.php --bibles' to see available)
 * @return ?array{string
 * , string} [passageHTML, attributionHTML] or null on failure
 */
function fetchBiblePassage(string $reference, string $bibleId = APIBIBLE_NIV): ?array
{
    if ($bibleId === '') throw new InvalidArgumentException('$bibleId must not be blank');
	$result = callBibleApi("/bibles/$bibleId/search", [
		'query' => $reference,
	]);

	$passage = $result['data']['passages'][0] ?? null;
	if (!$passage || empty($passage['content'])) {
		return null;
	}

	$content = $passage['content'];
	if (ifdef('BIBLE_API_PARSE', true)) {
		$parsed = parseBibleHtml($content);
		if ($parsed === null) {
			return null;
		}

		// Compare against a DOM round-trip without filtering.
		// If they differ, log both versions and use our parsed version.
		$doc = domParse($content);
		$canon = $doc !== null ? domSerialize($doc) : null;
		if ($parsed !== $canon) {
			error_log("Bible API returned HTML our parser couldn't handle.\nPARSED: " . $parsed . "\nUPSTREAM: " . $canon);
			$content = $parsed;
		} else {
			$content = $parsed;
		}
	}
	$content = '<div class="scripture-styles">' . $content . '</div>';

	$attribution = '';
	if (!empty($passage['copyright'])) {
		$short = shortenAttribution($bibleId, $passage['copyright']);
		$attribution = '<div style="font-size: smaller; text-align: left; margin-top: 1em;">'
			. ents($short)
			. '</div>';
	}

	return [$content, $attribution];
}

/**
 * Produce a space-constrained attribution like "NIV © 2011 Biblica, Inc. All rights reserved.", as permitted by https://api.bible/terms-and-conditions#appendix-a---formatting-for-detailed-copyright-information
 * @param copyright A long-winded copyright string e.g. "The Holy Bible, New International Version® NIV® Copyright © 1973, 1978, 1984, 2011 by Biblica, Inc.® Used by Permission of Biblica, Inc.® All rights reserved worldwide."
 */
function shortenAttribution(string $bibleId, string $copyright): string
{
	if (stripos($copyright, 'public domain') !== false) {
		return 'Public domain.';
	}

	// Extract the Bible abbreviation from the copyright string.
	// Expected format: "... Version® ABBREV® Copyright © ..."
	$abbrev = '';
	if (preg_match('/\b([A-Z]{2,5})®\s*Copyright\b/', $copyright, $m)) {
		$abbrev = $m[1];
	}
	// Fall back to looking up the abbreviation from available translations
	if ($abbrev === '') {
		$translations = getBibleTranslations();
		$abbrev = $translations[$bibleId]['abbreviation'] ?? $bibleId;
	}

	// Extract the last copyright year
	$year = '';
	if (preg_match('/©\s*(?:[\d,\s]+)?(\d{4})/', $copyright, $m)) {
		$year = $m[1];
	}

	// Extract organization name after "by" - stop at sentence end, "All rights", or "Used by"
	$org = '';
	if (preg_match('/\bby\s+(.+?)(?:\s*[.]\s+[A-Z]|\s+All\s+rights|\s+Used\s+by|\s*$)/s', $copyright, $m)) {
		$org = trim($m[1]);
		// Strip trailing punctuation and registered symbols
		$org = preg_replace('/[®TM,.]+$/', '', $org);
		$org = trim($org);
	}

	if ($year && $org) {
		return "$abbrev © $year $org. All rights reserved.";
	}
	if ($year) {
		return "$abbrev © $year. All rights reserved.";
	}
	return "$abbrev. All rights reserved.";
}

// ===========================================================================
// API communication
// ===========================================================================

/**
 * Call the API.Bible REST API.
 *
 * @param string $path   API path (e.g. '/bibles/{id}/passages/{id}')
 * @param array<string, string> $params Query parameters
 * @return array<string, mixed> Decoded JSON response
 */
function callBibleApi(string $path, array $params = []): array
{
	if (!defined('BIBLE_API_APIKEY') || BIBLE_API_APIKEY === '') {
		return [];
	}

    defined('BIBLE_API_URL') or define('BIBLE_API_URL', 'https://rest.api.bible/v1');
	$url = BIBLE_API_URL . $path;
	if ($params !== []) {
		$url .= '?' . http_build_query($params);
	}

	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => [
			'api-key: ' . BIBLE_API_APIKEY,
			'Accept: application/json',
		],
		CURLOPT_TIMEOUT => ifdef('BIBLE_API_TIMEOUT', 10),
	]);

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if ($response === false || $httpCode >= 400) {
		$error = curl_error($ch);
		$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);
		error_log("bibleapi: HTTP $httpCode for $path" . ($error ? " ($error)" : ""));
		error_log("bibleapi: replicate with: curl -sv " . escapeshellarg($effectiveUrl) . " -H 'api-key: \$BIBLE_API_APIKEY'");
		return [];
	}

	curl_close($ch);

	$data = json_decode($response, true);
	return is_array($data) ? $data : [];
}

// ===========================================================================
// HTML parsing
// ===========================================================================

/**
 * Parse scripture HTML from the upstream API, emitting only safe elements
 * and attributes.  Everything else is dropped.
 *
 * Allowed: <p>, <span>, any class, and any data-* attribute (inert by definition).
 *
 * @return ?string Safe HTML, or null on empty/invalid
 */
function parseBibleHtml(string $html): ?string
{
	$html = trim($html);
	if ($html === '') {
		return null;
	}

	$doc = domParse($html);
	if ($doc === null) {
		return null;
	}

	// Strip unknown attributes: keep only class and data-*
	$xpath = new \DOMXPath($doc);
	foreach ($xpath->query('//*') as $el) {
		foreach (iterator_to_array($el->attributes) as $attr) {
			if ($attr->name !== 'class' && !str_starts_with($attr->name, 'data-')) {
				$el->removeAttribute($attr->name);
			}
		}
	}

	return domSerialize($doc);
}

/**
 * Parse an HTML fragment as XML, stripping unknown elements first.
 * @return ?\DOMDocument
 */
function domParse(string $html): ?\DOMDocument
{
	$html = trim(strip_tags($html, '<p><span>'));
	if ($html === '') {
		return null;
	}

	// Named HTML entities (e.g. &nbsp; &mdash; &ldquo;) are not valid XML entities.
	// Decode them to their actual Unicode characters, which are valid XML content.
	$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	$prev = libxml_use_internal_errors(true);
	$doc = new \DOMDocument();
	if (!$doc->loadXML('<root>' . $html . '</root>', \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
		libxml_use_internal_errors($prev);
		return null;
	}
	libxml_use_internal_errors($prev);

	return $doc;
}

/**
 * Serialise the children of a document's root element via saveHTML().
 * @return ?string
 */
function domSerialize(\DOMDocument $doc): ?string
{
	$out = '';
	foreach ($doc->documentElement->childNodes as $child) {
		$out .= $doc->saveHTML($child);
	}

	return $out !== '' ? $out : null;
}

// ===========================================================================
// Translation lookup
// ===========================================================================

/**
 * Resolve a translation abbreviation (e.g. 'WBBE') to a specific Bible ID.
 *
 * Some translations exist in multiple editions (e.g. 7142879509583d59-01 through
 * 7142879509583d59-04). This function groups matching IDs by their base ID and
 * picks the lexicographically greatest (latest edition).
 *
 * @param string $name Translation abbreviation (case-insensitive)
 * @return ?string Bible ID, or null if not found
 */
function resolveBibleId(string $name): ?string
{
	$translations = getBibleTranslations();

	// 1. Direct ID match (e.g. '78a9f6124f344018-01' or '78a9f6124f344018')
	if (isset($translations[$name])) {
		return $name;
	}
	// Partial ID match: if they pass just the base (e.g. '78a9f6124f344018'),
	// pick the lexicographically greatest edition
	$baseIds = [];
	foreach ($translations as $id => $info) {
		if (str_starts_with($id, $name.'-')) {
			$baseIds[] = $id;
		}
	}
	if ($baseIds !== []) {
		rsort($baseIds, \SORT_STRING);
		return $baseIds[0];
	}

	// 2. Match by abbreviation or name (case-insensitive)
	$matching = [];
	foreach ($translations as $id => $info) {
		if (strcasecmp($info['abbreviation'], $name) === 0 || strcasecmp($info['name'], $name) === 0) {
			// Group by base ID (the part before the -NN suffix)
			$base = preg_replace('/-\d+$/', '', $id);
			$matching[$base][] = $id;
		}
	}

	if ($matching !== []) {
		// For each base group, pick the lexicographically greatest (latest edition)
		$candidates = [];
		foreach ($matching as $ids) {
			rsort($ids, \SORT_STRING);
			$candidates[] = $ids[0];
		}
		return $candidates[0];
	}

	return null;
}

/**
 * Get available Bible translations from the API, filtered to English.
 *
 * @return array<string, array{name: string, abbreviation: string}> Bible ID => {name, abbreviation}
 */
function getBibleTranslations(): array
{
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}

	$result = callBibleApi('/bibles', ['language' => 'eng']);
	$cache = [];
    // Out of the 36 English bibles, testing shows these 5 don't actually work
	$brokenBibles = [
		'685d1470fe4d5c3b-01',
		'6bab4d6c61b31b80-01',
		'65bfdebd704a8324-01',
		'bf8f1c7f3f9045a5-01',
		'ec290b5045ff54a5-01',
	];
	foreach (($result['data'] ?? []) as $bible) {
		if (in_array($bible['id'], $brokenBibles)) {
			continue;
		}
		$cache[$bible['id']] = [
			'name'         => $bible['name'] ?? '',
			'abbreviation' => $bible['abbreviation'] ?? '',
		];
	}

	// Put preferred translations first
	$preferred = [
		'78a9f6124f344018-01',
		'a761ca71e0b3ddcf-01',
		'a556c5305ee15c3f-01',
		'de4e12af7f28f599-02',
	];
	$sorted = [];
	foreach ($preferred as $id) {
		if (isset($cache[$id])) {
			$sorted[$id] = $cache[$id];
		}
	}
	foreach ($cache as $id => $info) {
		if (!isset($sorted[$id])) {
			$sorted[$id] = $info;
		}
	}
	$cache = $sorted;

	return $cache;
}

