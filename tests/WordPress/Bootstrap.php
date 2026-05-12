<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

$rootDir = dirname(__DIR__, 2);
$wpPhpUnit = $rootDir . '/vendor/wp-phpunit/wp-phpunit';

if (!is_dir($wpPhpUnit)) {
    fwrite(STDERR, "wp-phpunit/wp-phpunit not installed. Run `composer install`.\n");
    exit(1);
}

// SQLite drop-in: copy db.copy into wp-content/db.php on first run so
// WordPress boots against a SQLite database instead of MySQL.
$dbDropIn = $rootDir . '/web/wordpress/wp-content/db.php';
$sqlitePlugin = $rootDir . '/web/wordpress/wp-content/plugins/sqlite-database-integration';
if (!file_exists($dbDropIn) && file_exists($sqlitePlugin . '/db.copy')) {
    copy($sqlitePlugin . '/db.copy', $dbDropIn);
}

if (!file_exists($dbDropIn)) {
    fwrite(STDERR, "SQLite drop-in not found at {$dbDropIn}. Run `composer install`.\n");
    exit(1);
}

define('WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php');

require_once $rootDir . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $wpPhpUnit . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function () use ($rootDir): void {
    require $rootDir . '/vendor/autoload.php';
});

require $wpPhpUnit . '/includes/bootstrap.php';
