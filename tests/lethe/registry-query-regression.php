<?php

// No real session, bootstrap, HTTP or database: load only the actual Reg class.
$root = dirname(__DIR__, 2);
$package = getenv('LETHE_PACKAGE_DIR') ?: (is_file($root . '/lib/Lethe/Reg.php') ? $root : $root . '/vendor/prochor666/lethe');
require $package . '/lib/Lethe/Reg.php';
set_error_handler(function ($level, $message, $file, $line) {
    if ((error_reporting() & $level) !== 0) {
        throw new ErrorException($message, 0, $level, $file, $line);
    }
    return false;
});
$passed = 0;
$failed = 0;
function registryExpect($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}
function registryCase($name, $test)
{
    global $passed, $failed;
    $_SESSION = ['unrelated'=>'keep'];
    try {
        $test(); $passed++; echo 'PASS: ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failed++; echo 'FAIL: ' . $name . ': ' . $error->getMessage() . PHP_EOL;
    }
}
registryCase('Existing nested paths and path trimming retain values and types', function () {
    Lethe\Reg::setBlock(['store/nested/value'=>'found','store/zero'=>0,'store/false'=>false,
        'store/null'=>null,'store/empty'=>'','store/float'=>1.5,'store/list'=>['a','b']]);
    foreach (['store/nested/value'=>'found',' /store/nested/value/ '=>'found','store/zero'=>0,
        'store/false'=>false,'store/null'=>null,'store/empty'=>'','store/float'=>1.5,
        'store/list'=>['a','b'],'store/list/0'=>'a'] as $path=>$expected) {
        registryExpect(Lethe\Reg::query($path) === $expected, 'Changed value/type at ' . $path);
    }
});
registryCase('Scalar descendants return false without notices or exceptions', function () {
    $object = (object)['value'=>'object leaf'];
    Lethe\Reg::setBlock(['store/string'=>'text','store/int'=>42,'store/float'=>1.5,
        'store/bool'=>false,'store/null'=>null,'store/array'=>[],'store/object'=>$object]);
    foreach (['string','int','float','bool','null','array','object'] as $key) {
        registryExpect(Lethe\Reg::query('store/' . $key . '/value') === false, 'Invalid descendant: ' . $key);
    }
    registryExpect(Lethe\Reg::query('store/object') === $object, 'Terminal object must be preserved');
});
registryCase('A missing segment cannot resume lookup in an ancestor', function () {
    Lethe\Reg::set('store/value', 'must not leak');
    registryExpect(Lethe\Reg::query('store/missing/value') === false, 'Lookup resumed after a missing key');
    registryExpect(Lethe\Reg::query('missing/store/value') === false, 'Missing root');
});
registryCase('Read does not mutate existing session data', function () {
    Lethe\Reg::set('store/leaf', 'text');
    $before = $_SESSION;
    foreach (['store/leaf/value','store/absent','store','store/leaf'] as $path) { Lethe\Reg::query($path); }
    registryExpect($_SESSION === $before, 'Read mutated session contents');
    registryExpect($_SESSION['unrelated'] === 'keep', 'Unrelated session data changed');
});
registryCase('Default and empty queries remain false with normal registry contents', function () {
    registryExpect(Lethe\Reg::query() === false, 'Default query');
    registryExpect(Lethe\Reg::query('') === false, 'Empty query');
    registryExpect(Lethe\Reg::query(' / ') === false, 'Blank query');
    registryExpect(Lethe\Reg::query('not-found') === false, 'Absent key');
});
registryCase('Absent or malformed registry still uses the existing initialization', function () {
    foreach ([null, 'broken', [session_id()=>'broken']] as $initial) {
        $_SESSION = ['unrelated'=>'keep','__lethe_registry'=>$initial];
        registryExpect(Lethe\Reg::query('store/value') === false, 'Malformed registry lookup');
        registryExpect(is_array(Lethe\Reg::read()), 'Registry must be an array');
        registryExpect($_SESSION['unrelated'] === 'keep', 'Unrelated session key');
    }
});
restore_error_handler();
echo 'PHP ' . PHP_VERSION . ': ' . $passed . ' passed, ' . $failed . ' failed; no real session or DB.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);
