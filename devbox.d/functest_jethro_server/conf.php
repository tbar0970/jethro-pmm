<?php

#define('DB_DSN', "mysql:unix_socket=/home/jethro/code/current/.devbox/virtenv/mariadb/run/mysql.sock;dbname=jethro_functest");
# Note 127.0.0.1 not 'localhost' so PHP doesn't try to use the default Unix socket (/run/mysqld/mysqld.sock) which is the wrong one in this Devbox instance
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_DATABASE', 'jethro_functest');
define('DB_USERNAME', "jethro_functest");
define('DB_PASSWORD', 'jethro_functest');
define('PREFILL_USERNAME', 'demo');
define('PREFILL_PASSWORD', 'qfntt7eYuwHs123');   # This qfntt7eYuwHs123 is not sensitive

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing filename (e.g. index.php) so that both
//   /tests/functional/sms/sms-2fa/
//   /tests/functional/sms/sms-2fa/index.php
// resolve to the same test-scenario conf file.
if (!str_ends_with($path, '/')) {
    $path = rtrim(dirname($path), '/') . '/';
}

if ($path !== '/') {
    $testConf = realpath( JETHRO_ROOT . '/../../' . rtrim($path, '/') . '.conf' );
    if ($testConf) {
        require_once $testConf;
        // Serve the app under the scenario prefix: with BASE_URL defined,
        // baseurl_relative() (and hence build_url(), redirects and resource
        // links) puts every generated URL under the prefix.  The functional
        // Caddyfile maps prefixed /resources/ requests back to the real files.
        if (!defined('BASE_URL')) define('BASE_URL', $path);
    }
    // Silently skip unknown paths — both test-scenario paths and bare-root /
    // requests can legitimately land here.
}
