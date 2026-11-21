<?php

/**
 * Unit tests for include/google_sheets.class.php — the pure helpers behind
 * scripts/import_persons.php --google-service-account.
 *
 * No network and no Jethro bootstrap: the JWT is signed with a key generated here and verified
 * with its public half; CSV conversion is checked by parsing the result back.
 */

require_once __DIR__ . '/../../include/google_sheets.class.php';

use function Test\assert_eq;
use function Test\assert_true;
use function Test\assert_throws;
use function Test\test;

test('spreadsheetId accepts share links, /u/N/ links and bare IDs', function () {
    assert_eq(Google_Sheets_Client::spreadsheetId('https://docs.google.com/spreadsheets/d/abc123/edit#gid=0'), 'abc123');
    assert_eq(Google_Sheets_Client::spreadsheetId('https://docs.google.com/spreadsheets/u/1/d/xyz789/edit'), 'xyz789');
    assert_eq(Google_Sheets_Client::spreadsheetId('  abcDEF_-123  '), 'abcDEF_-123');
});

test('spreadsheetId rejects other hosts and published links', function () {
    assert_throws(RuntimeException::class, function () {
        Google_Sheets_Client::spreadsheetId('https://example.com/spreadsheets/d/abc/edit');
    });
    assert_throws(RuntimeException::class, function () {
        Google_Sheets_Client::spreadsheetId('https://docs.google.com/spreadsheets/d/e/2PACX/pubhtml');
    });
});

test('spreadsheetGid reads query and fragment forms', function () {
    assert_eq(Google_Sheets_Client::spreadsheetGid('https://docs.google.com/spreadsheets/d/a/edit#gid=123'), '123');
    assert_eq(Google_Sheets_Client::spreadsheetGid('https://docs.google.com/spreadsheets/d/a/edit?gid=456'), '456');
    assert_eq(Google_Sheets_Client::spreadsheetGid('https://docs.google.com/spreadsheets/d/a/edit'), null);
});

test('base64Url is unpadded and URL-safe', function () {
    $encoded = Google_Sheets_Client::base64Url("\xfb\xff\xfe");
    assert_true(strpos($encoded, '+') === false);
    assert_true(strpos($encoded, '/') === false);
    assert_true(strpos($encoded, '=') === false);
    assert_eq(base64_decode(strtr($encoded, '-_', '+/')), "\xfb\xff\xfe");
});

test('assertion is a signed RS256 JWT with the readonly scope', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert_true($key !== false, 'could not create a test RSA key');
    $details = openssl_pkey_get_details($key);
    openssl_pkey_export($key, $pem);
    $credentials = [
        'client_email' => 'importer@example.iam.gserviceaccount.com',
        'private_key' => $pem,
    ];
    $assertion = Google_Sheets_Client::assertion($credentials, 1700000000);
    $parts = explode('.', $assertion);
    assert_eq(count($parts), 3);

    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    assert_eq($header['alg'], 'RS256');
    assert_eq($header['typ'], 'JWT');
    assert_eq($claims['iss'], $credentials['client_email']);
    assert_eq($claims['scope'], 'https://www.googleapis.com/auth/spreadsheets.readonly');
    assert_eq($claims['aud'], 'https://oauth2.googleapis.com/token');
    assert_eq($claims['iat'], 1700000000);
    assert_eq($claims['exp'], 1700003600);

    $signature = base64_decode(strtr($parts[2], '-_', '+/'));
    assert_eq(openssl_verify($parts[0].'.'.$parts[1], $signature, $details['key'], OPENSSL_ALGO_SHA256), 1);
});

test('rowsToCsv pads short rows and round-trips through fgetcsv', function () {
    $rows = [
        ['family_name', 'last_name', 'note'],
        ['Turner', 'Turner'],
        ['Quote " and'."\n".'newline', 'Comma, here'],
    ];
    $csv = Google_Sheets_Client::rowsToCsv($rows);
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $csv);
    rewind($fp);
    $parsed = [];
    while (($row = fgetcsv($fp, 0, ',', '"', '')) !== false) {
        $parsed[] = $row;
    }
    fclose($fp);
    assert_eq($parsed, [
        ['family_name', 'last_name', 'note'],
        ['Turner', 'Turner', ''],
        ['Quote " and'."\n".'newline', 'Comma, here', ''],
    ]);
    assert_eq(Google_Sheets_Client::rowsToCsv([]), '');
});

test('csvUrl rewrites share links, tabs, gids and published links', function () {
    $base = 'https://docs.google.com/spreadsheets/d/abc123';
    assert_eq(Google_Sheets_Client::csvUrl($base.'/edit#gid=42'), [
        'url' => $base.'/export?format=csv&gid=42',
        'last_tab' => false,
    ]);
    assert_eq(Google_Sheets_Client::csvUrl($base.'/edit', 'import'), [
        'url' => $base.'/gviz/tq?tqx=out:csv&sheet=import',
        'last_tab' => false,
    ]);
    $export = $base.'/export?format=csv&gid=7';
    assert_eq(Google_Sheets_Client::csvUrl($export), ['url' => $export, 'last_tab' => false]);
    $published = 'https://docs.google.com/spreadsheets/d/e/PUBID/pubhtml#gid=99';
    assert_eq(Google_Sheets_Client::csvUrl($published), [
        'url' => 'https://docs.google.com/spreadsheets/d/e/PUBID/pub?output=csv&gid=99',
        'last_tab' => false,
    ]);
});

test('csvUrl rejects other hosts and published links without a gid', function () {
    assert_throws(RuntimeException::class, function () {
        Google_Sheets_Client::csvUrl('https://example.com/spreadsheets/d/abc/edit');
    });
    assert_throws(RuntimeException::class, function () {
        Google_Sheets_Client::csvUrl('https://docs.google.com/spreadsheets/d/e/PUBID/pubhtml');
    });
});

test('constructor rejects missing and malformed key files', function () {
    assert_throws(RuntimeException::class, function () {
        new Google_Sheets_Client('/nonexistent/google-sheets.json');
    });
    $tmp = tempnam(sys_get_temp_dir(), 'gs');
    file_put_contents($tmp, '{}');
    try {
        assert_throws(RuntimeException::class, function () use ($tmp) {
            new Google_Sheets_Client($tmp);
        });
    } finally {
        unlink($tmp);
    }
});
