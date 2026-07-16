<?php

// Functional testing Jethro conf.php, with the ability to inspect the Jethro path and load custom PHP snippets on
// demand. E.g. /tests/functional/walkthrough/?view=... loads Jethro as usual, but also sources
// tests/functional/walkthrough.conf (or tests/functional/walkthrough/walkthrough.conf), which might load custom
// settings or point to a custom database.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing filename (e.g. index.php) so that both
//   /tests/functional/sms/sms-2fa/
//   /tests/functional/sms/sms-2fa/index.php
// resolve to the same test-scenario conf file.
if (!str_ends_with($path, '/')) {
    $path = rtrim(dirname($path), '/') . '/';
}


if (str_starts_with($path, '/tests/functional/')) {
    // e.g. $path = /tests/functional/walkthrough/
    // First look for /tests/functional/walkthrough.conf
    $conf1 = JETHRO_ROOT . '/../../' . rtrim($path, '/') . '.conf';
    $testConf = realpath( JETHRO_ROOT . '/../../' . rtrim($path, '/') . '.conf' );
    if (!$testConf = realpath($conf1)) {
        $last = basename(rtrim($path,'/'));
        // Second, look for /tests/functional/walkthrough/walkthrough.conf
        $conf2 = JETHRO_ROOT . '/../../' . rtrim($path, '/') . '/'. $last . '.conf';
        if ($testConf = realpath($conf2)) {
            require_once $testConf;
            // Serve the app under the scenario prefix: with BASE_URL defined,
            // baseurl_relative() (and hence build_url(), redirects and resource
            // links) puts every generated URL under the prefix.  The functional
            // Caddyfile maps prefixed /resources/ requests back to the real files.
            if (!defined('BASE_URL')) define('BASE_URL', $path);
            #exit;
        } else {
            if (str_ends_with($path, "/resources/less/")) {
                // The above assumes all Jethro views are triggered from /?view=... and thus the a path like /tests/functional/walkthrough/?view=..., but /resources is an exception, and does not need a custom conf.php loaded.
            } else {
                // Missing tests/functional/something.conf or tests/functional/something/something.conf
                http_response_code(403);
                echo "Path: $path <br>Missing config file. Looked for:<br> $conf1<br>$conf2";
                exit;
            }
        }
        #echo $testConf;
    }
    define('LOGIN_NOTE', "Config: ".substr($testConf, strlen(realpath(JETHRO_ROOT.'/../..'))));
}
# Note 127.0.0.1 not 'localhost' so PHP doesn't try to use the default Unix socket (/run/mysqld/mysqld.sock) which is the wrong one in this Devbox instance
# Default DB settings. A test-scenario conf (e.g. tests/functional/setupwizard.conf)
# is required above and may define its own DB_* constants first, to point this
# instance at a different database.
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3307');
if (!defined('DB_DATABASE')) define('DB_DATABASE', 'jethro_functest');
if (!defined('DB_USERNAME')) define('DB_USERNAME', "jethro");
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'jethro');

define('SHOW_ERROR_DETAILS', TRUE);

define('PREFILL_USERNAME', 'demo');
define('PREFILL_PASSWORD', 'qfntt7eYuwHs123');   # This qfntt7eYuwHs123 is not sensitive


// Set the version so that functests use compiled js/css, rather than evaluating less on every load.
// resources/js/jethro-$JETHRO_VERSION.js + resources/css/jethro-$JETHRO_VERSION.css are compiled by Playwright setup.
// The JETHRO_VERSION env var is set by Devbox, and passed through by devbox.d/php/php-fpm.conf
define('JETHRO_VERSION', getenv("JETHRO_VERSION"));
