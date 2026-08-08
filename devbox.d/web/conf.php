<?php
# This conf.php is specific to the demo instance at http://127.0.0.1:8081. See DEVELOPMENT_DEVBOX.md
# Uses unix socket so auth works with 'jethro'@'localhost'. MYSQL_UNIX_PORT is
# set by the devbox mariadb plugin (dev) and by docker/entrypoint.sh (Docker).

define('DB_DSN', 'mysql:unix_socket=' . getenv('MYSQL_UNIX_PORT') . ';dbname=jethro');
# TCP alternative (requires 'jethro'@'%' or 'jethro'@'127.0.0.1' grant):
# define('DB_HOST', '127.0.0.1');
# define('DB_PORT', '3307');
define('DB_DATABASE', 'jethro');
define('DB_USERNAME', "jethro");
define('DB_PASSWORD', 'jethro');
define('PREFILL_USERNAME', 'demo');
define('PREFILL_PASSWORD', 'qfntt7eYuwHs123');   # This qfntt7eYuwHs123 is not sensitive
define('PREFILL_MEMBER_EMAIL', 'mluther@wittenberg.edu.de');
define('PREFILL_MEMBER_PASSWORD', 'qfntt7eYuwHs123');  # This qfntt7eYuwHs123 password is not sensitive

# Define settings for all supported providers, but don't hardcode any so the choice is left to the user on the system config page.
#define('SMS_PROVIDER', '5centsmsv5');
define('SMS_5CENTSMS_URL', '/smsmockserver/5centsms');
define('SMS_5CENTSMS_APIKEY_ID', 'test-mock-key-id');
define('SMS_5CENTSMS_APIKEY', 'test-mock-secret');

define('SMS_CELLCAST_URL', '/smsmockserver/cellcast');
define('SMS_CELLCAST_APIKEY', 'test-mock-secret');

define('SMS_MOBILEMESSAGE_URL', '/smsmockserver/mobilemessage');
define('SMS_MOBILEMESSAGE_APIKEY_USERNAME', 'test-mock-username');
define('SMS_MOBILEMESSAGE_APIKEY_PASSWORD', 'test-mock-secret');

// The demo mock accounts already have this sender registered. MobileMessage
// does not expose ACMA approval in its sender list, so name it explicitly for
// pre-login sends.
define('2FA_SENDER_ID', 'StDemos');

// Email capture: send all mail into this checkout's MailPit unix socket
// (process-compose service `mailpit`), mirroring how SMS is faked via the
// smsmockserver above. php-fpm is given MAILPIT_SOCKET_DIR by
// devbox.d/php/php-fpm.conf; there is deliberately no fallback to a real
// sendmail, so if the variable or the socket is missing, mail fails.
// See DEVELOPMENT_FUNCTESTS.md "8. Email capture — MailPit (Unix domain sockets)".
define('SENDMAIL_COMMAND',
	escapeshellarg(dirname(JETHRO_ROOT, 2).'/.devbox/nix/profile/default/bin/mailpit')
	.' sendmail -S '.escapeshellarg('unix:'.getenv('MAILPIT_SOCKET_DIR').'/smtp.sock').' -bs');
