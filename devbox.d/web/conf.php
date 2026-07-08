<?php
# This conf.php is specific to the demo instance at http://127.0.0.1:8081. See DEVELOPMENT_DEVBOX.md
# Note 127.0.0.1 not 'localhost' so PHP doesn't try to use the default Unix socket (/run/mysqld/mysqld.sock) which is the wrong one in this Devbox instance
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
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
