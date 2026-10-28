<?php

// Run only against the disposable local server created for this audit.
$port = (int)getenv('LETHE_MYSQL_TEST_PORT');
if ($port < 1 || $port === 3306) {
    throw new RuntimeException('An explicit disposable-server port is required.');
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

$originalReportMode = (new mysqli_driver())->report_mode;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$admin = new mysqli('127.0.0.1', 'root', '', '', $port);
echo 'Test server: ' . $admin->server_info . PHP_EOL;
$suffix = bin2hex(random_bytes(5));
$database = 'lethe_driver_test_' . $suffix;
$otherDatabase = $database . '_other';
$user = 'lethe_audit_' . $suffix;
$password = bin2hex(random_bytes(16));
$failures = [];
$passed = 0;
$createdDatabases = [];
$createdUser = false;

function expectDriver($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function driverCase($name, $test)
{
    global $passed, $failures;
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $test();
        $passed++;
        echo 'PASS: ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures[] = $name;
        // Do not print connection credentials or SQL from exception messages.
        echo 'FAIL: ' . $name . ' (' . get_class($error) . ')' . PHP_EOL;
    }
}

try {
    foreach ([$database, $otherDatabase] as $name) {
        $admin->query('CREATE DATABASE `' . $name . '` CHARACTER SET utf8');
        $createdDatabases[] = $name;
    }
    $admin->query("CREATE USER '" . $user . "'@'127.0.0.1' IDENTIFIED BY '" . $password . "'");
    $createdUser = true;
    foreach ($createdDatabases as $name) {
        $admin->query("GRANT ALL ON `" . $name . "`.* TO '" . $user . "'@'127.0.0.1'");
    }
    $admin->select_db($database);
    $admin->query('CREATE TABLE items (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200)) ENGINE=InnoDB');
    $config = [
        'engine' => 'Mysqldb',
        'host' => '127.0.0.1',
        'port' => $port,
        'db' => $database,
        'user' => $user,
        'password' => $password,
    ];

    driverCase('CRUD, escaping, result formats, wrapper and legacy ID contract', function () use ($config) {
        $driver = new Lethe\Mysqldb($config);
        $title = "O'Reilly \\ Český katalog";
        expectDriver($driver->query("INSERT INTO items (title) VALUES ('" . $driver->sanitize($title) . "')") === true, 'Insert');
        $rows = $driver->result('SELECT id, title FROM items');
        expectDriver(count($rows) === 1 && $rows[0]['title'] === $title, 'Escaped round trip');
        $id = (int)$rows[0]['id'];
        expectDriver($driver->getLastId('items') === $id, 'Existing ID contract');
        expectDriver($driver->result('SELECT title FROM items', 'row')[0][0] === $title, 'Row format');
        expectDriver($driver->result('SELECT title FROM items', 'array')[0]['title'] === $title, 'Array format');
        expectDriver($driver->result('SELECT title FROM items', 'object')->title === $title, 'Object format');
        expectDriver($driver->query("UPDATE items SET title = 'updated' WHERE id = " . $id) === true, 'Update');
        $wrapperRows = Lethe\Db::result(['driver' => $config, 'query' => 'SELECT title FROM items']);
        expectDriver($wrapperRows[0]['title'] === 'updated', 'Wrapper forwarding');
        expectDriver($driver->query('DELETE FROM items WHERE id = ' . $id) === true, 'Delete');
        expectDriver($driver->result('SELECT * FROM items') === [], 'Empty result');
        expectDriver(is_string($driver->version()) && $driver->version() !== '', 'Server version');
    });

    driverCase('MySQLi report mode is preserved for both strict and disabled callers', function () use ($config) {
        foreach ([MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT, MYSQLI_REPORT_OFF] as $mode) {
            mysqli_report($mode);
            $driver = new Lethe\Mysqldb($config);
            $driver->result('SELECT 1 AS value');
            $driver->sanitize('quote');
            $driver->version();
            expectDriver((new mysqli_driver())->report_mode === $mode, 'Report mode changed');
            expectDriver(is_array($driver->query('SELECT * FROM missing_table')), 'Legacy error-array contract');
            expectDriver((new mysqli_driver())->report_mode === $mode, 'Report mode changed after error');
        }
    });

    driverCase('SQL errors keep existing return types and allow reuse of the same driver', function () use ($config) {
        $driver = new Lethe\Mysqldb($config);
        expectDriver(is_array($driver->query('SELECT * FROM missing_table')), 'Error array');
        expectDriver($driver->getErrors() !== [], 'Error history');
        expectDriver($driver->result('SELECT * FROM missing_table') === [], 'Failed result');
        $rows = $driver->result('SELECT 7 AS value');
        expectDriver(count($rows) === 1 && (int)$rows[0]['value'] === 7, 'Recovery after SQL error');
    });

    driverCase('result called with a non-row statement stays an array', function () use ($config) {
        $driver = new Lethe\Mysqldb($config);
        expectDriver($driver->result('UPDATE items SET title = title WHERE 1 = 0') === [], 'No result set');
    });

    driverCase('Connection failures retain existing failure types without exceptions', function () use ($config) {
        $invalid = $config;
        $invalid['password'] = 'deliberately-wrong';
        $driver = new Lethe\Mysqldb($invalid);
        expectDriver($driver->testConnection() === false, 'Failed connection');
        expectDriver(is_array($driver->query('SELECT 1')), 'Failed query');
        expectDriver($driver->result('SELECT 1') === [], 'Failed result');
        expectDriver($driver->sanitize('value') === false, 'Failed escaping');
        expectDriver($driver->version() === '', 'Failed version');
        expectDriver($driver->getErrors() !== [], 'Connection errors recorded');
        expectDriver((new mysqli_driver())->report_mode === (MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT), 'Report mode after connection error');
    });

    driverCase('Persistent links reuse only the same configuration and preserve charset', function () use ($config, $otherDatabase) {
        $persistent = $config + ['persistent' => true];
        $one = (new Lethe\Mysqldb($persistent))->testConnection();
        $two = (new Lethe\Mysqldb($persistent))->testConnection();
        expectDriver($one instanceof mysqli && $one === $two, 'Same persistent connection');
        $other = $persistent;
        $other['db'] = $otherDatabase;
        $three = (new Lethe\Mysqldb($other))->testConnection();
        expectDriver($three instanceof mysqli && $three !== $one, 'Database isolation');
        $charset = $one->character_set_name();
        expectDriver(in_array($charset, ['utf8', 'utf8mb3'], true), 'Existing utf8 charset');
        $wrong = $persistent;
        $wrong['password'] = 'deliberately-wrong';
        expectDriver((new Lethe\Mysqldb($wrong))->testConnection() === false, 'Password isolation');
    });

    driverCase('Persistence stays disabled when missing or explicitly false', function () use ($config) {
        foreach ([$config, $config + ['persistent' => false]] as $settings) {
            $one = (new Lethe\Mysqldb($settings))->testConnection();
            $two = (new Lethe\Mysqldb($settings))->testConnection();
            expectDriver($one instanceof mysqli && $two instanceof mysqli && $one !== $two, 'Independent connections');
            expectDriver(mysqli_thread_id($one) !== mysqli_thread_id($two), 'Independent server sessions');
            mysqli_close($one);
            mysqli_close($two);
        }
    });

    driverCase('A lost persistent link is discarded without replaying the failed statement', function () use ($config, $admin) {
        $driver = new Lethe\Mysqldb($config + ['persistent' => true]);
        $link = $driver->testConnection();
        $admin->query('KILL CONNECTION ' . mysqli_thread_id($link));
        expectDriver(is_array($driver->query('SELECT 1')), 'Failed statement reported');
        $rows = $driver->result('SELECT 9 AS value');
        expectDriver(count($rows) === 1 && (int)$rows[0]['value'] === 9, 'Next operation reconnects');
    });

    driverCase('An explicitly closed cached link can be replaced', function () use ($config) {
        $persistent = $config + ['persistent' => true];
        $link = (new Lethe\Mysqldb($persistent))->testConnection();
        mysqli_close($link);
        $replacement = (new Lethe\Mysqldb($persistent))->testConnection();
        expectDriver($replacement instanceof mysqli && $replacement !== $link, 'Closed link replaced');
        expectDriver(mysqli_thread_id($replacement) > 0, 'Replacement usable');
    });
} finally {
    mysqli_report(MYSQLI_REPORT_OFF);
    foreach ($createdDatabases as $name) {
        $admin->query('DROP DATABASE `' . $name . '`');
    }
    if ($createdUser) {
        $admin->query("DROP USER '" . $user . "'@'127.0.0.1'");
    }
    $admin->close();
    mysqli_report($originalReportMode);
    restore_error_handler();
}

echo 'PHP ' . PHP_VERSION . ': ' . $passed . ' passed, ' . count($failures) . ' failed.' . PHP_EOL;
exit($failures === [] ? 0 : 1);
