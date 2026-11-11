<?php

// Isolated CLI processes, temporary fixtures only; no session, DB or network.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
$package = getenv('LETHE_PACKAGE_DIR') ?: (is_file($root . '/init/boot.php') ? $root : $root . '/vendor/prochor666/lethe');
$mode = isset($argv[1]) ? $argv[1] : null;

if ($mode === null) {
    if (!extension_loaded('mbstring')) {
        throw new RuntimeException('Run this regression with mbstring loaded.');
    }
    $cases = ['success', 'fixture-output', 'missing-mbstring', 'missing-root', 'invalid-root',
        'missing-package', 'missing-autoloader', 'require-error', 'exception', 'type-error', 'nested-error', 'parse-error'];
    $failed = 0;
    foreach ($cases as $case) {
        $command = escapeshellarg(PHP_BINARY) . ' -n';
        if ($case !== 'missing-mbstring') {
            $command .= ' -d ' . escapeshellarg('extension_dir=' . ini_get('extension_dir')) . ' -d extension=mbstring';
        }
        $command .= ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($case);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) { throw new RuntimeException('Could not start fixture process.'); }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0 || $output !== "PASS\n" || $errors !== '') {
            $failed++;
            echo 'FAIL: ' . $case . ' (exit ' . $status . ') ' . $output . $errors . PHP_EOL;
        } else {
            echo 'PASS: ' . $case . PHP_EOL;
        }
    }
    echo 'PHP ' . PHP_VERSION . ': ' . (count($cases) - $failed) . ' passed, ' . $failed . " failed.\n";
    exit($failed === 0 ? 0 : 1);
}

error_reporting(E_ALL);
set_error_handler(function ($level, $message, $file, $line) {
    throw new ErrorException($message, 0, $level, $file, $line);
});

function bootExpect($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}

$fixture = null;
$fixtureModes = ['fixture-output', 'missing-autoloader', 'require-error', 'exception', 'type-error', 'nested-error', 'parse-error'];
if (in_array($mode, $fixtureModes, true)) {
    $fixture = sys_get_temp_dir() . '/lethe-boot-' . bin2hex(random_bytes(8));
    mkdir($fixture . '/lib/Lethe', 0777, true);
    $fixtureCode = <<<'PHP'
<?php
namespace Lethe;
if ($GLOBALS['mode'] === 'require-error') {
    echo 'secret require output';
    throw $GLOBALS['bootFixtureError'];
}
class Autoloader {
    public static function init() { return new self(); }
    public function register($paths) {
        if ($GLOBALS['mode'] === 'exception') {
            echo 'secret register output';
            throw $GLOBALS['bootFixtureError'];
        }
    }
}
class Config {
    public static function init() {
        echo ' fixture output ';
        if ($GLOBALS['mode'] === 'type-error') { throw $GLOBALS['bootFixtureError']; }
    }
}
class Reg {
    public static function init() {
        if ($GLOBALS['mode'] === 'nested-error') {
            ob_start();
            echo 'secret nested output';
            throw $GLOBALS['bootFixtureError'];
        }
    }
}
PHP;
    if ($mode !== 'missing-autoloader') {
        file_put_contents($fixture . '/lib/Lethe/Autoloader.php', $mode === 'parse-error' ? '<?php invalid syntax here' : $fixtureCode);
    }
}

if ($mode !== 'missing-root') { define('__LETHE_ROOT__', $mode === 'invalid-root' ? 123 : $root); }
if ($mode !== 'missing-package') { define('__LETHE_LETHE__', $fixture ?: $package); }
$bootFixtureError = $mode === 'type-error' ? new TypeError('secret type failure')
    : ($mode === 'exception' ? new RuntimeException('secret exception') : new Error('secret error'));
$_SERVER = ['HTTP_HOST' => 'localhost', 'SERVER_PORT' => 80, 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php'];
$_SESSION = ['unrelated' => 'keep'];
$buffer = 'caller variable';
$initialLevel = ob_get_level();
ob_start();
echo 'caller output';
$callerLevel = ob_get_level();
$caught = null;

try {
    try {
        require $package . '/init/boot.php';
    } catch (Throwable $error) {
        $caught = $error;
    }
    bootExpect(ob_get_level() === $callerLevel, 'Caller buffer depth changed.');
    $output = ob_get_contents();
    bootExpect($buffer === 'caller variable', 'Boot overwrote a caller variable.');
    if ($mode === 'success' || $mode === 'fixture-output') {
        bootExpect($caught === null, 'Successful boot threw an exception.');
        bootExpect($output === 'caller output' . ($mode === 'fixture-output' ? 'fixture output' : ''), 'Successful output changed.');
        if ($mode === 'success') {
            $metadata = json_decode(file_get_contents($package . '/composer.json'), true);
            bootExpect(\Lethe\Config::query('system/version') === $metadata['version'], 'Runtime and Composer versions differ.');
            bootExpect(mb_internal_encoding() === 'UTF-8' && mb_http_output() === 'UTF-8' && mb_regex_encoding() === 'UTF-8', 'UTF-8 settings changed.');
            bootExpect(is_array(\Lethe\Reg::read()) && $_SESSION['unrelated'] === 'keep', 'Registry initialization failed.');
        }
    } else {
        bootExpect($caught instanceof Throwable, 'Boot failure was swallowed.');
        bootExpect($output === 'caller output', 'Partial output leaked or caller output was lost.');
        if (in_array($mode, ['require-error', 'exception', 'type-error', 'nested-error'], true)) {
            bootExpect($caught === $bootFixtureError, 'Original Throwable was replaced.');
        } elseif ($mode === 'parse-error') {
            bootExpect($caught instanceof ParseError, 'ParseError was not propagated.');
        } else {
            $expected = ['missing-mbstring' => 'mbstring', 'missing-root' => '__LETHE_ROOT__',
                'invalid-root' => '__LETHE_ROOT__', 'missing-package' => '__LETHE_LETHE__', 'missing-autoloader' => 'Autoloader.php'];
            bootExpect($caught instanceof RuntimeException && strpos($caught->getMessage(), $expected[$mode]) !== false, 'Missing prerequisite was not identified.');
        }
    }
} finally {
    while (ob_get_level() > $initialLevel) { ob_end_clean(); }
    if ($fixture !== null) {
        if (is_file($fixture . '/lib/Lethe/Autoloader.php')) { unlink($fixture . '/lib/Lethe/Autoloader.php'); }
        rmdir($fixture . '/lib/Lethe');
        rmdir($fixture . '/lib');
        rmdir($fixture);
    }
    restore_error_handler();
}
echo "PASS\n";
