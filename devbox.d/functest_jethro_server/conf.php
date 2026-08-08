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
    $testConf = realpath($conf1);
    if (!$testConf) {
        // Second, look for /tests/functional/walkthrough/walkthrough.conf
        $last = basename(rtrim($path, '/'));
        $conf2 = JETHRO_ROOT . '/../../' . rtrim($path, '/') . '/' . $last . '.conf';
        $testConf = realpath($conf2);
    }
    if ($testConf) {
        require_once $testConf;
        // Serve the app under the scenario prefix: with BASE_URL defined,
        // baseurl_relative() (and hence build_url(), redirects and resource
        // links) puts every generated URL under the prefix.  The functional
        // Caddyfile maps prefixed /resources/ requests back to the real files.
        if (!defined('BASE_URL')) define('BASE_URL', $path);
        define('LOGIN_NOTE', "Config: ".substr($testConf, strlen(realpath(JETHRO_ROOT.'/../..'))));
    } elseif (!str_ends_with($path, "/resources/less/")) {
        // Missing tests/functional/something.conf or tests/functional/something/something.conf
        http_response_code(403);
        echo "Path: $path <br>Missing config file. Looked for:<br> $conf1<br>$conf2";
        exit;
    }
} else 
{
    // Identity marker for Playwright's global-setup: FUNCTEST_WEB_PORT is derived
    // per checkout path but not guaranteed unique, so global-setup fetches
    // functestinfo.php (served from this webroot) and refuses to run unless it
    // reports THIS checkout's path (a stale checkout's functest_jethro_server
    // can otherwise silently own the port). The LOGIN_NOTE below is human
    // information on the login page, not what global-setup checks.
    $instance_path = realpath(JETHRO_ROOT . '/../..');
    define('LOGIN_NOTE', "<details><summary>Functest info</summary>
        <p>Jethro instance path: <code data-functest-instance-path='$instance_path'>$instance_path</code>
        <p>This Jethro is used for functional tests.
        <p>It is configured to use database 'jethro_functest' (accessible via <tt>./devbox run mariadb jethro_functest</tt>), with a minimal conf.php at ./devbox.d/functest_jethro server/conf.php
        <p>Other functest-specific conf.php files may be loaded by specifying their directory prefix in the URL, e.g. <a href='/tests/functional/walkthrough/'>/tests/functional/walkthrough/</a> loads <tt>./tests/functional/walkthrough/walkthrough.conf</tt> which points to an initially empty database to test from the setup screen.
        <p>For development, prefer the <a href='http://localhost:8081/'>http://localhost:8081</a> Jethro. 
        <p>See <tt>docs/docs/developer/DEVELOPMENT_FUNCTESTS.md</tt> for more info.
</details>");
}
define('SHOW_ERROR_DETAILS', TRUE);

define('PREFILL_USERNAME', 'demo');
define('PREFILL_PASSWORD', 'qfntt7eYuwHs123');   # This qfntt7eYuwHs123 is not sensitive


// Set the version so that functests use compiled js/css, rather than evaluating less on every load.
// resources/js/jethro-$JETHRO_VERSION.js + resources/css/jethro-$JETHRO_VERSION.css are compiled by Playwright setup.
// The JETHRO_VERSION env var is set by Devbox, and passed through by devbox.d/php/php-fpm.conf
define('JETHRO_VERSION', getenv("JETHRO_VERSION"));

# Deliberately UNDEFINE these constants. The new SMS code should not need them, and only specific tests might turn them on - in which case defining them here causes defined-twice errors
#define('SMS_INTERNATIONAL_PREFIX', '61');
#define('SMS_LOCAL_PREFIX', '0');

# Default DB settings. A test-scenario conf (e.g. tests/functional/walkthrough.conf)
# loaded above may define its own DB_* constants first, to point this
# instance at a different database.
#
# Unix-socket auth (see devbox.d/web/conf.php): 'jethro'@'localhost' matches
# socket connections in MariaDB. MYSQL_UNIX_PORT is set by the devbox mariadb
# plugin and passed through by devbox.d/php/php-fpm.conf. DB_DATABASE is baked
# into the DSN because JethroDB ignores it when DB_DSN is defined (jethrodb.php).
if (!defined('DB_DATABASE')) define('DB_DATABASE', 'jethro_functest');
if (!defined('DB_HOST') && !defined('DB_DSN')) {
	define('DB_DSN', 'mysql:unix_socket=' . getenv('MYSQL_UNIX_PORT') . ';dbname=' . DB_DATABASE);
}
if (!defined('DB_USERNAME')) define('DB_USERNAME', "jethro");
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'jethro');

// Email capture: send all mail into this checkout's MailPit unix socket
// (process-compose service `mailpit`; see docs/docs/developer/DEVELOPMENT_FUNCTESTS.md
// "8. Email capture — MailPit (Unix domain sockets)"). Emailer runs this command via Symfony's sendmail
// transport ("-bs" = SMTP over the command's stdin), so no SMTP_SERVER is defined;
// a scenario conf that needs a real SMTP transport can define SMTP_SERVER itself
// (it is loaded before this point and takes precedence).
// MailPit's socket directory is $DEVBOX_RUNTIME_DIR/mailpit, configured in devbox.json
// and passed to php-fpm by devbox.d/php/php-fpm.conf; there is no fallback to a real
// sendmail, so mail fails if the variable or socket is missing.
// See docs/docs/developer/reference/JETHRO_UTILS.md.
define('SENDMAIL_COMMAND',
    escapeshellarg(dirname(JETHRO_ROOT, 2).'/.devbox/nix/profile/default/bin/mailpit')
    .' sendmail -S '.escapeshellarg('unix:'.getenv('MAILPIT_SOCKET_DIR').'/smtp.sock').' -bs');

