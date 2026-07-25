<?php
/**
 * Jethro runtime health endpoint — returns JSON describing PHP/Database/Apache
 * configuration suitable for monitoring systems (e.g. Nagios).
 *
 * Companion Nagios check plugin: scripts/check_http_jethrostatus
 *
 * Example JSON output:
 *
 *     {
 *       "user": "jethro",
 *       "php_compatible_version": true,
 *       "max_input_vars": 1000,
 *       "xdebug_loaded": false,
 *       "xdebug.mode": false,
 *       "xdebug.start_with_request": false,
 *       "xdebug_performance_impact": true,
 *       "opcache_jit_impact": true,
 *       "post_max_size": 8,
 *       "upload_max_filesize": 2,
 *       "db_charset_results": "utf8mb4",
 *       "session_gc_maxlifetime": 5400,
 *       "session_gc_probability": 1,
 *       "session_gc_misconfigured": true,
 *       "php_extensions_installed": true,
 *       "mod_unique_id_loaded": true,
 *       "log_errors": "0"
 *     }
 *
 * For the above JSON, check_http_jethrostatus would report:
 *
 *     WARNING - max_input_vars is 1000, must be >= 10000;
 *              post_max_size is 8M, must be >= 20M;
 *              upload_max_filesize is 2M, must be >= 20M
 *
 * There are many ways to mess up a Jethro installation, particularly across
 * upgrades. Key fields to watch:
 *
 *     Field                       | Expected  | Problem                                      | Explanation
 *     ----------------------------|-----------|----------------------------------------------|--------------------------------------------------------------------------
 *     `user`                      | "jethro"  | "root"                                       | Ensure PHP is running as the correct OS user (e.g. not root)
 *     `php_compatible_version`    | true      | "PHP 8.0.30 is not > 8.1"                    | Ensure the expected PHP version is used
 *     `php_extensions_installed`  | true      | "Missing extensions: exif, gd"               | Jethro requires `curl`, `gd`, `zip`, etc as documented in README.md
 *     `max_input_vars`            | 10000     | 1000                                         | Default 1000 is too few for large attendances (#6) or many custom fields (#152)
 *     `post_max_size`             | 20        | 8                                            | Default 8MB is too small; contact photos may exceed it (#949)
 *     `upload_max_filesize`       | 20        | 2                                            | Default 2MB is too small for common uploads
 *     `db_charset_results`        | `utf8mb4` | `utf8`, `latin1`                             | Old MySQL `utf8` isn't real UTF-8 — causes upgrade mess (#754, #1088)
 *     `opcache_jit_impact`        | true      | "opcache JIT is enabled"                     | JIT burns CPU on warmup; pointless for short-lived PHP processes — returns true when JIT is off (good)
 *     `session_gc_misconfigured`  | true      | "Session GC misconfigured: …"               | Debian/Ubuntu sessionclean trap: cron GC with low maxlifetime causes premature session expiry (#1088)
 *     `mod_unique_id_loaded`      | true      | "mod_unique_id Apache module not enabled"    | Apache unique request IDs for log correlation and request tracing
 *     `xdebug_performance_impact` | true      | "xdebug performance impact: mode=debug…"    | Xdebug overhead even when not debugging — returns true when safe, error string when impacting performance
 *
 * @see scripts/check_http_jethrostatus
 */
define('JETHRO_ROOT', dirname($_SERVER['SCRIPT_FILENAME']));
require_once JETHRO_ROOT.'/conf.php';
require_once JETHRO_ROOT.'/include/init.php';
define('SHOW_JETHRO_VERSION', false);

// If we can't connect to the database or something else is majorly wrong, we would have returned a 200 HTML error page to the caller by now.
// I have considered setting a custom error handler with set_error_handler() and returning HTTP 500 with a json response, but the caller should
// be treating any failure to return JSON as critical anyway.
$result = [];
if (SHOW_JETHRO_VERSION) $result['jethroversion'] = JETHRO_VERSION;
$result['user'] = $_SERVER['USER'];
$result['php_compatible_version'] = version_compare(PHP_VERSION, '8.1', '>') ? true : "PHP " . PHP_VERSION . " is not > 8.1";
$result['max_input_vars'] = (int)ini_get('max_input_vars');
// Xdebug runtime status — these settings affect production performance
$result['xdebug_loaded'] = extension_loaded('xdebug');
$result['xdebug.mode'] = ini_get('xdebug.mode');                    // e.g. 'off', 'develop', 'debug', 'trace', 'profile'
$result['xdebug.start_with_request'] = ini_get('xdebug.start_with_request'); // e.g. 'default', 'yes', 'no', 'trigger'
// Derived: xdebug is "hot" (may slow things down) if loaded *and* mode is not off/empty *and* start_with_request isn't 'no'
$result['xdebug_performance_impact'] = (extension_loaded('xdebug')
    && ini_get('xdebug.mode') !== 'off'
    && ini_get('xdebug.mode') !== ''
    && ini_get('xdebug.start_with_request') !== 'no')
    ? "xdebug performance impact: mode=" . ini_get('xdebug.mode') . ", start_with_request=" . ini_get('xdebug.start_with_request')
    : true;
$result['opcache_jit_impact'] = opcache_get_status()['jit']['enabled'] ? "opcache JIT is enabled" : true;
$result['post_max_size'] = (int)ini_get('post_max_size');
$result['upload_max_filesize'] = (int)ini_get('upload_max_filesize');
$result['db_charset_results'] =  $GLOBALS['db']->queryOne('select @@character_set_results');
// Session GC config — check for Debian/Ubuntu sessionclean misconfiguration
// Ref: https://github.com/tbar0970/jethro-pmm/issues/1088#issuecomment-2436805398
$result['session_gc_maxlifetime'] = (int)ini_get('session.gc_maxlifetime');
$result['session_gc_probability'] = (int)ini_get('session.gc_probability');
$result['session_gc_misconfigured'] = ((int)ini_get('session.gc_probability') === 0)
    && ((int)ini_get('session.gc_maxlifetime') < (defined('SESSION_TIMEOUT_MINS') ? SESSION_TIMEOUT_MINS * 60 : PHP_INT_MAX))
    ? "Session GC misconfigured: gc_probability=0, gc_maxlifetime=" . ini_get('session.gc_maxlifetime') . "s < SESSION_TIMEOUT_MINS*60"
    : true;
# Required PHP extensions per README.md
$required_extensions = ['gettext', 'zip', 'xmlwriter', 'gd', 'curl', 'exif'];
$missing = array_values(array_filter($required_extensions, fn($ext) => !extension_loaded($ext)));
$result['php_extensions_installed'] = empty($missing) ? true : "Missing extensions: " . join(', ', $missing);
$result['mod_unique_id_loaded'] = array_key_exists('HTTP_X_REQUEST_ID', $_SERVER) ? true : "mod_unique_id Apache module not enabled";
$result['log_errors'] = ini_get('log_errors');
header('Content-Type: application/json');
print(json_encode($result, JSON_PRETTY_PRINT));
session_destroy(); // Don't accumulate sessions unnecessarily
?>
