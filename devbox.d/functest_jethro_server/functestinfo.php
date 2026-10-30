<?php

// Identity endpoint for the functional test suite: tests/functional/global-setup.ts
// fetches /functestinfo.php and refuses to run unless the serving checkout is the
// checkout the tests are running from (FUNCTEST_WEB_PORT is derived per checkout path
// but not guaranteed unique, so a stale server from another checkout can own the port).
//
// Deliberately standalone: no conf.php/init.php, so it needs no database and has no
// side effects. nginx passes $realpath_root to php-fpm, so __DIR__ is the real path
// <checkout>/devbox.d/functest_jethro_server even though this webroot's other entries
// are symlinks into the checkout — hence dirname(__DIR__, 2) is the checkout root.

header('Content-Type: application/json');
print json_encode(['instance_path' => dirname(__DIR__, 2)]);
