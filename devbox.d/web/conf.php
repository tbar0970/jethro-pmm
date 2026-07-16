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

# Required
define('SMS_INTERNATIONAL_PREFIX', '61');
define('SMS_LOCAL_PREFIX', '0');
