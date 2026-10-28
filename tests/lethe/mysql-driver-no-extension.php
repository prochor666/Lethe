<?php

if (extension_loaded('mysqli') || function_exists('mysql_connect')) {
    throw new RuntimeException('Run this check without a MySQL extension.');
}

$root = dirname(__DIR__, 2);
$package = getenv('LETHE_PACKAGE_DIR') ?: (is_file($root . '/lib/Lethe/Mysqldb.php') ? $root : $root . '/vendor/prochor666/lethe');
define('__LETHE_ROOT__', dirname(__DIR__, 2));
define('__LETHE_LETHE__', $package);
$_SERVER = ['SERVER_PORT' => 80, 'REQUEST_URI' => '/mysql-driver-test/'];
require $package . '/init/boot.php';

set_error_handler(function ($level, $message, $file, $line) {
    if ((error_reporting() & $level) !== 0) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
    return false;
});

try {
    $config = ['host' => 'unused.invalid', 'db' => 'unused', 'user' => 'unused', 'password' => 'unused'];
    foreach ([$config, []] as $settings) {
        $driver = new Lethe\Mysqldb($settings);
        if ($driver->testConnection() !== false
            || !is_array($driver->query('SELECT 1'))
            || $driver->result('SELECT 1') !== []
            || $driver->sanitize('value') !== false
            || $driver->version() !== ''
            || $driver->getErrors() === []) {
            throw new RuntimeException('MySQL failure contract changed.');
        }
    }
    echo 'PASS: missing MySQL extension and invalid configuration; PHP ' . PHP_VERSION . PHP_EOL;
} finally {
    restore_error_handler();
}
