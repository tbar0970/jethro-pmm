<?php

/**
 * jethro.au services (include/jethroau.php): which services a key may use,
 * read from GET /api/validate's `services` map. Jethro must not use a service
 * whose terms the key hasn't agreed to, or agreed to only in a version
 * jethro.au no longer accepts.
 *
 * See docs/docs/developer/reference/jethroau-services.md.
 */

namespace Test\JethroAu;

use function Test\{test, assert_eq};

require_once __DIR__ . '/../../include/general.php';
require_once __DIR__ . '/../../include/jethroau.php';

test('an accepted agreement enables a service, even when its terms have since been updated', function () {
	$validated = ['valid' => true, 'services' => [
		'url-shortener' => ['consented' => true, 'accepted' => true, 'current' => false],
	]];
	assert_eq(\JethroAu::acceptedServices($validated), ['url-shortener']);
});

test('an agreement to terms jethro.au no longer accepts does not', function () {
	$validated = ['valid' => true, 'services' => [
		'url-shortener' => ['consented' => true, 'accepted' => false, 'current' => false],
	]];
	assert_eq(\JethroAu::acceptedServices($validated), []);
});

test('services Jethro does not know are ignored', function () {
	$validated = ['valid' => true, 'services' => [
		'roster-solver' => ['consented' => true, 'accepted' => true, 'current' => true],
	]];
	assert_eq(\JethroAu::acceptedServices($validated), []);
});

test('a response without a services map enables nothing', function () {
	assert_eq(\JethroAu::acceptedServices(['valid' => true]), []);
});
