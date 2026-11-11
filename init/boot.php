<?php

/**
 * Lethe bootstrap
 * @author Jan Prochazka, prochor666 <prochor666@gmail.com>
 * @package Lethe
 */
use Lethe\Autoloader;
use Lethe\Config;
use Lethe\Reg;

(static function () {
    if (version_compare(PHP_VERSION, '7.2.0', '<')) {
        throw new RuntimeException('Lethe requires PHP 7.2.0 or later; currently running ' . PHP_VERSION . '.');
    }

    if (!extension_loaded('mbstring') || !function_exists('mb_regex_encoding')) {
        throw new RuntimeException('Lethe requires the mbstring extension with multibyte regex support.');
    }

    foreach (['__LETHE_LETHE__', '__LETHE_ROOT__'] as $constant) {
        if (!defined($constant) || !is_string(constant($constant)) || !is_dir(constant($constant))) {
            throw new RuntimeException('Lethe requires ' . $constant . ' to point to an existing directory.');
        }
    }

    $autoloader = __LETHE_LETHE__ . '/lib/Lethe/Autoloader.php';
    if (!is_file($autoloader) || !is_readable($autoloader)) {
        throw new RuntimeException('Lethe boot error: missing or unreadable Autoloader.php.');
    }

    $bufferLevel = ob_get_level();
    ob_start('mb_output_handler');

    try {
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
        mb_language('uni');
        mb_regex_encoding('UTF-8');

        require_once $autoloader;
        Autoloader::init()->register([
            __LETHE_LETHE__ . '/interfaces/',
            __LETHE_LETHE__ . '/lib/',
        ]);

        Config::init();
        Reg::init();

        $buffer = ob_get_contents();
    } finally {
        // Clean up on success and on any Throwable; keep the caller's buffers intact.
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
    }

    echo trim($buffer);
})();
