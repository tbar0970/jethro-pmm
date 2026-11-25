<?php

/**
 * URLSHORTENER resolution (urlShortenerConfig()), shared by getUrlShortener()
 * and the SMS URL-shortener status panel.
 *
 * Regression: the panel accepted any http(s) base URL and reported "Operational"
 * while getUrlShortener() threw "Unknown URL shortener" for everything except
 * 'jethroau'/'https://jethro.au' — so URLs silently stopped being shortened.
 */

namespace Test\UrlShortenerConfig;

use function Test\{test, assert_eq, assert_true};

require_once __DIR__ . '/../../include/url_shortener.php';

test('blank means not configured', function () {
	$config = urlShortenerConfig('');
	assert_eq($config['configured'], false);
	assert_eq($config['supported'], false);
});

test('supported values, with slashes and in any case', function () {
	foreach (['jethroau', 'JethroAu', 'jethroau/', 'https://jethro.au', 'https://jethro.au/', ' HTTPS://JETHRO.AU/ '] as $value) {
		$config = urlShortenerConfig($value);
		assert_eq($config['supported'], true, "expected '$value' to be supported");
		assert_eq($config['base'], 'https://jethro.au', "base for '$value'");
	}
});

test('other URLs are configured but not supported', function () {
	$config = urlShortenerConfig('https://short.example.org');
	assert_eq($config['configured'], true);
	assert_eq($config['supported'], false);
	assert_eq($config['raw'], 'https://short.example.org');
});

test('the client builds the jethro.au shortener for a supported setting', function () {
	$shortener = getUrlShortener();
	assert_true($shortener instanceof \JethroAuUrlShortener);
});
