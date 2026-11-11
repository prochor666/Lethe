<?php
// Standalone deterministic cache/delete regression. No application, DB or network.
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace Lethe {
    class Lethe {
        public function __construct() {}
        public function config($key) { return 0644; }
    }
    class DeleteFixture { public static $mode = 'normal'; }
    function unlink($path) {
        if (DeleteFixture::$mode === 'race') { \unlink($path); }
        if (DeleteFixture::$mode === 'failure') { return \unlink(dirname($path)); }
        if (DeleteFixture::$mode === 'throw') { throw new \RuntimeException('fixture-delete'); }
        return \unlink($path);
    }
}
namespace {
    ob_start();
    $root = dirname(__DIR__, 2);
    $package = getenv('LETHE_PACKAGE_DIR') ?: (is_file($root . '/lib/Lethe/Storage.php') ? $root : $root . '/vendor/prochor666/lethe');
    require $package . '/lib/Lethe/Storage.php';
    require $package . '/lib/Lethe/Cache.php';
    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
        echo 'PASS: ' . $message . "\n";
    }
    $directory = sys_get_temp_dir() . '/warp-cache-errors-' . bin2hex(random_bytes(6));
    mkdir($directory);
    $path = $directory . '/fixture.lcache';
    $log = $directory . '/errors.log';
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $log);
    error_reporting(E_ALL);
    $warnings = [];
    $handler = function ($level, $message) use (&$warnings) { $warnings[] = [$level, $message]; return true; };
    set_error_handler($handler);
    try {
        file_put_contents($path, '[]');
        check(\Lethe\Storage::deleteFile($path) === true && !file_exists($path), 'normal deletion');
        check(\Lethe\Storage::deleteFile($path) === false && !$warnings, 'already missing file is quiet');
        file_put_contents($path, '[]');
        \Lethe\DeleteFixture::$mode = 'race';
        check(\Lethe\Storage::deleteFile($path) === false && !$warnings, 'file disappearing immediately before unlink creates no warning');
        $cache = new \Lethe\Cache();
        $cache->storage = $directory;
        $cache->keepalive = 1;
        file_put_contents($path, '[]');
        touch($path, time() - 30);
        clearstatcache(true, $path);
        check($cache->getKey('fixture') === false && !$warnings, 'expired cache race becomes an ordinary cache miss');
        \Lethe\DeleteFixture::$mode = 'normal';
        file_put_contents($path, '[1,2]');
        clearstatcache(true, $path);
        check($cache->getKey('fixture') === [1, 2], 'fresh cache still reads normally');
        \Lethe\DeleteFixture::$mode = 'failure';
        check(\Lethe\Storage::deleteFile($path) === false && count($warnings) === 1
            && $warnings[0][0] === E_WARNING && file_exists($path), 'other filesystem failure reaches original warning handler');
        \Lethe\DeleteFixture::$mode = 'throw';
        try { \Lethe\Storage::deleteFile($path); throw new \LogicException('expected fixture exception'); }
        catch (\RuntimeException $e) { check($e->getMessage() === 'fixture-delete', 'unexpected exceptions are preserved'); }
        $probe = set_error_handler($handler);
        restore_error_handler();
        check($probe === $handler, 'previous handler restored even after an exception');
        \Lethe\DeleteFixture::$mode = 'normal';
    } finally { restore_error_handler(); }

    // Without a custom handler, real failures still use PHP's normal log path.
    \Lethe\DeleteFixture::$mode = 'failure';
    ob_start();
    $result = \Lethe\Storage::deleteFile($path);
    $output = ob_get_clean();
    check($result === false && $output === '' && is_file($log), 'unhandled filesystem failure is logged without response output');
    \Lethe\DeleteFixture::$mode = 'normal';

    foreach ([$path, $log] as $file) { if (file_exists($file)) { \unlink($file); } }
    rmdir($directory);
    ob_end_flush();
}
