<?php

/**
 * Unit tests for JethroDB::quoteLike().
 */

require_once __DIR__ . '/../../include/jethrodb.php';

use function Test\assert_eq;
use function Test\test;

/** A JethroDB with no connection: quote() stands in for PDO::quote(). */
final class FakeQuoteDB extends JethroDB
{
	public function __construct()
	{
	}

	public function quote($string, $paramtype = NULL)
	{
		return $string === NULL ? 'NULL' : "'".str_replace("'", "''", (string)$string)."'";
	}
}

test('quoteLike: leaves the caller\'s wildcards alone', function () {
	$db = new FakeQuoteDB();
	assert_eq($db->quoteLike('Calvin'), "'Calvin%' ESCAPE '|'");
	assert_eq($db->quoteLike('Calvin', '% '), "'% Calvin%' ESCAPE '|'");
	assert_eq($db->quoteLike('Calvin', '%', '%'), "'%Calvin%' ESCAPE '|'");
});

test('quoteLike: escapes %, _ and the escape character in the value', function () {
	$db = new FakeQuoteDB();
	assert_eq($db->quoteLike('50%_off|deal'), "'50|%|_off||deal%' ESCAPE '|'");
});
