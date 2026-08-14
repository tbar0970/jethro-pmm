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
 *       "runtime_user": "jethro",
 *       "max_input_vars": 1000,
 *       "post_max_size": "8M",
 *       "upload_max_filesize": "2M",
 *       "db_charset_results": "utf8mb4",
 *       "session_gc_maxlifetime": 5400,
 *       "session_gc_probability": 1,
 *       "xdebug_loaded": false,
 *       "xdebug.mode": false,
 *       "xdebug.start_with_request": false,
 *       "php_incompatible_version": false,
 *       "php_extensions_installed": true,
 *       "request_id_added": true,
 *       "xdebug_performance_impact": false,
 *       "opcache_performance_impact": false,
 *       "session_gc_misconfigured": false,
 *       "logging_misconfigured": false,
 *       "warnings": ["max_input_vars", "post_max_size", "upload_max_filesize"]
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
 *     `runtime_user`              | "jethro"  | "root"                                       | Ensure PHP is running as the correct OS user (e.g. not root)
 *     `php_incompatible_version`  | false     | true                                         | Ensure the expected PHP version is used
 *     `php_extensions_installed`  | true      | "Missing extensions: exif, gd"               | Jethro requires `curl`, `gd`, `zip`, etc as documented in README.md
 *     `max_input_vars`            | 10000     | 1000                                         | Default 1000 is too few for large attendances (#6) or many custom fields (#152)
 *     `post_max_size`             | "20M"     | "8M"                                         | Default 8MB is too small; contact photos may exceed it (#949)
 *     `upload_max_filesize`       | "20M"     | "2M"                                         | Default 2MB is too small for common uploads
 *     `db_charset_results`        | `utf8mb4` | `utf8`, `latin1`                             | Old MySQL `utf8` isn't real UTF-8 — causes upgrade mess (#754, #1088)
 *     `opcache_performance_impact` | false     | "opcache JIT is enabled" / "opcache is not enabled" | JIT burns CPU on warmup; pointless for one-shot pages — false when opcache on + JIT off
 *     `session_gc_misconfigured`  | false     | "Session GC misconfigured: …"               | Debian/Ubuntu sessionclean trap: cron GC with low maxlifetime causes premature session expiry (#1088)
 *     `logging_misconfigured`     | false     | "log_errors=0"                               | log_errors must be On
 *     `request_id_added`          | true      | false                                        | Enable Apache `mod_unique_id` (or set `X-Request-Id` upstream) for log correlation
 *     `xdebug_performance_impact` | false     | "xdebug performance impact: mode=debug…"    | Xdebug overhead even when not debugging — false when safe, error string when impacting performance
 *     `warnings`                  | []        | ["max_input_vars", "post_max_size"]         | Array of fields currently in a warning state; empty when all nominal
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

// ── Raw observations ──
if (SHOW_JETHRO_VERSION) $result['jethroversion'] = JETHRO_VERSION;
$result['runtime_user'] = $_SERVER['USER'];
$result['max_input_vars'] = (int)ini_get('max_input_vars');
$result['post_max_size'] = ini_get('post_max_size');
$result['upload_max_filesize'] = ini_get('upload_max_filesize');
$result['db_charset_results'] =  $GLOBALS['db']->queryOne('select @@character_set_results');
$result['session_gc_maxlifetime'] = (int)ini_get('session.gc_maxlifetime');
$result['session_gc_probability'] = (int)ini_get('session.gc_probability');

// Xdebug runtime status — these settings affect production performance
$result['xdebug_loaded'] = extension_loaded('xdebug');
$result['xdebug.mode'] = ini_get('xdebug.mode');                    // e.g. 'off', 'develop', 'debug', 'trace', 'profile'
$result['xdebug.start_with_request'] = ini_get('xdebug.start_with_request'); // e.g. 'default', 'yes', 'no', 'trigger'

// ── Derived verdicts ──
// Return a bool. Reporting the actual PHP version would be an info-disclosure risk.
$result['php_incompatible_version'] = !version_compare(PHP_VERSION, '8.1', '>');

# Required PHP extensions per README.md
$required_extensions = ['gettext', 'zip', 'xmlwriter', 'gd', 'curl', 'exif'];
$missing = array_values(array_filter($required_extensions, fn($ext) => !extension_loaded($ext)));
$result['php_extensions_installed'] = empty($missing) ? true : "Missing extensions: " . join(', ', $missing);

$result['request_id_added'] = array_key_exists('HTTP_X_REQUEST_ID', $_SERVER);

// Derived: Xdebug only adds per-request overhead when actually engaged.
//   - 'develop' mode is always-on whenever listed (small overhead).
//   - 'debug'/'trace'/'profile' start every request only when start_with_request='yes'.
//     'no' never starts; 'trigger'/'default' arm on a cookie/param and cost nothing until
//     that trigger arrives — so they're treated as inactive here. (Attacker-triggerable
//     debug is a separate concern; production shouldn't load xdebug at all.)
$result['xdebug_performance_impact'] = (extension_loaded('xdebug')
    && ini_get('xdebug.mode') !== 'off'
    && ini_get('xdebug.mode') !== ''
    && (str_contains(ini_get('xdebug.mode'), 'develop')
        || ini_get('xdebug.start_with_request') === 'yes'))
    ? "xdebug performance impact: mode=" . ini_get('xdebug.mode') . ", start_with_request=" . ini_get('xdebug.start_with_request')
    : false;

$opcachestatus = opcache_get_status();
$result['opcache_performance_impact'] = ($opcachestatus === false)
    ? "opcache is not enabled"
    : (($opcachestatus['jit']['enabled'] ?? false) ? "opcache JIT is enabled" : false);

// Session GC config — check for Debian/Ubuntu sessionclean misconfiguration
// Ref: https://github.com/tbar0970/jethro-pmm/issues/1088#issuecomment-2436805398
$result['session_gc_misconfigured'] = ((int)ini_get('session.gc_probability') === 0)
    && ((int)ini_get('session.gc_maxlifetime') < (defined('SESSION_TIMEOUT_MINS') ? SESSION_TIMEOUT_MINS * 60 : PHP_INT_MAX))
    ? "Session GC misconfigured: gc_probability=0, gc_maxlifetime=" . ini_get('session.gc_maxlifetime') . "s < SESSION_TIMEOUT_MINS*60"
    : false;

$result['logging_misconfigured'] = filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN)
    ? false
    : "log_errors=" . (ini_get('log_errors') === '' ? 'Off' : ini_get('log_errors'));

// ── Aggregate warnings ──
// Raw-observation thresholds are hard-coded here to match scripts/check_http_jethrostatus
// defaults; the check plugin duplicates them so operators can override via CLI flags.
// If you change one, change both.
$warnings = [];
// Raw threshold breaches (same order as the field emission above).
if ($result['max_input_vars'] < 10000)               $warnings[] = 'max_input_vars';
if ((int)$result['post_max_size'] < 20)              $warnings[] = 'post_max_size';
if ((int)$result['upload_max_filesize'] < 20)        $warnings[] = 'upload_max_filesize';
if ($result['db_charset_results'] !== 'utf8mb4')     $warnings[] = 'db_charset_results';
// Derived verdicts.
if ($result['php_incompatible_version'] !== false)   $warnings[] = 'php_incompatible_version';
if ($result['php_extensions_installed'] !== true)    $warnings[] = 'php_extensions_installed';
if ($result['request_id_added'] !== true)            $warnings[] = 'request_id_added';
if ($result['xdebug_performance_impact'] !== false)  $warnings[] = 'xdebug_performance_impact';
if ($result['opcache_performance_impact'] !== false) $warnings[] = 'opcache_performance_impact';
if ($result['session_gc_misconfigured'] !== false)   $warnings[] = 'session_gc_misconfigured';
if ($result['logging_misconfigured'] !== false)      $warnings[] = 'logging_misconfigured';
$result['warnings'] = $warnings;
header('Content-Type: application/json');
print(json_encode($result, JSON_PRETTY_PRINT));
session_destroy(); // Don't accumulate sessions unnecessarily
?>
