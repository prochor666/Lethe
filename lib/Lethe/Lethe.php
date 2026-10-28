<?php
namespace Lethe;

/**
* Base class, initalizes config and registry, provide debug messaging
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
*/
class Lethe implements ISystem, IDebug
{
    /**
    * Debug info
    */
    protected   $debug;

    /**
    * Debug info, human readable
    */
    protected   $debugHuman;

    /**
    * Lethe class contructor
    * @param void
    * @return void
    */
    public function __construct()
    {
        $this->debug = [];
        $this->debugHuman = [];
    }

    /**
    * Set debug message
    * @param string|int $code
    * @param string $message
    * @return void
    */
    public function debugSet($code, $message)
    {
        $t = microtime(true);
        $tt = explode('.', (string)$t);
        $message = (int)$code < 1000 ? 'MESSAGE::'.$message: 'ERROR::'.$message;
        $th = count($tt)>1 ? date('Y-m-d-H-i-s', $tt[0]).'-'.$tt[1]: date('Y-m-d-H-i-s', $tt[0]);
        $this->debug[] = ['time' => $th, 'code' => $code, 'message' => $message];
    }

    /**
    * Read debug
    * @param void
    * @return array
    */
    public function debugRead()
    {
        return $this->debug;
    }

    /**
    * Clear debug
    * @param void
    * @return void
    */
    public function debugClear()
    {
        $this->debug = [];
    }

    /**
    * Query configuration
    * @param string $query
    * @return mixed
    */
    public function config($query)
    {
        return Config::query($query);
    }

    /**
    * Store log record (LIFO)
    * @param array $record
    * @return array
    */
    public function log($record = [])
    {
        $log = Config::query('store/log');
        $log = is_array($log) ? $log : [];

        if(
            array_key_exists( 'case', $record ) && array_key_exists( 'message', $record )
        ){
            $log[] = ['timestamp' => time(), 'case' => $record['case'], 'message' => $record['message']];
        }

        Config::set('store/log', $log);
    }

    /**
    * Query SESSION based registry
    * @param string $query
    * @return mixed
    */
    public function reg($query)
    {
        return Reg::query($query);
    }

    /**
    * i18n ISO 639-1 JSON source
    * @param void
    * @return array
    */
    public function langConfig()
    {
        return json_decode(Storage::getFileData(__LETHE_LETHE__.'/init/lang.json'), true);
    }

    /**
    * Create random hexadecimal token
    * @param int $bytes
    * @return string
    */
    protected function randomHex($bytes = 32)
    {
        return Tools::randomHex($bytes);
    }

    /**
    * Build throttle identifier from client IP and normalized target value
    * @param mixed $target
    * @return string
    */
    public function throttleIdentifier($target = null)
    {
        $parts = [trim((string)Tools::clientIp())];

        if (is_array($target)) {
            foreach ($target as $item) {
                $item = $this->normalizeThrottleValue($item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            }
        } else {
            $target = $this->normalizeThrottleValue($target);
            if ($target !== '') {
                $parts[] = $target;
            }
        }

        return implode('|', $parts);
    }

    /**
    * Normalize throttle target part
    * @param mixed $value
    * @return string
    */
    protected function normalizeThrottleValue($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value === true ? '1': '0';
        }

        if (is_scalar($value)) {
            $value = trim((string)$value);
            if ($value === '') {
                return '';
            }

            $value = preg_replace('/\s+/u', ' ', $value);
            return mb_strtolower($value, 'UTF-8');
        }

        return Tools::hash(json_encode($value), 'sha256');
    }

    /**
    * Convert throttle result to UI/API friendly metadata
    * @param array $throttle
    * @param string $dateFormat
    * @return array
    */
    public function throttleInfo($throttle, $dateFormat = 'd.m.Y H:i:s')
    {
        $blockedUntil = (int)Tools::chef($throttle, 'blocked_until', 0);
        $retryAfter = (int)Tools::chef($throttle, 'retry_after', 0);
        $timeout = (int)Tools::chef($throttle, 'timeout', 0);

        return [
            'retry_after' => $retryAfter,
            'retry_after_human' => $this->formatDurationMmSs($retryAfter),
            'blocked_until' => $blockedUntil,
            'next_try_at' => $blockedUntil > 0 ? date($dateFormat, $blockedUntil): null,
            'timeout' => $timeout,
            'timeout_human' => $this->formatDurationMmSs($timeout),
        ];
    }

    /**
    * Format seconds as mm:ss
    * @param int $seconds
    * @return string
    */
    protected function formatDurationMmSs($seconds)
    {
        $seconds = max(0, (int)$seconds);
        $minutes = (int)floor($seconds / 60);
        $rest = $seconds % 60;
        return str_pad((string)$minutes, 2, '0', STR_PAD_LEFT).':'.str_pad((string)$rest, 2, '0', STR_PAD_LEFT);
    }

    /**
    * Basic IP-based throttle persisted in cache files
    * @param string $scope
    * @param int $limit
    * @param int $window
    * @param int $blockFor
    * @param string|null $identifier
    * @return array
    */
    public function throttle($scope, $limit = 5, $window = 300, $blockFor = 900, $identifier = null)
    {
        $scope = trim((string)$scope);
        $identifier = trim((string)($identifier === null ? Tools::clientIp(): $identifier));
        $limit = max(1, (int)$limit);
        $window = max(60, (int)$window);
        $blockFor = max($window, (int)$blockFor);
        $now = time();

        $baseDir = rtrim((string)$this->config('system/cache'), '/').'/auth-throttle';
        if (!Storage::isDir($baseDir)) {
            Storage::makeDir($baseDir);
        }

        $file = $baseDir.'/'.Tools::hash($scope.'|'.$identifier, 'sha256').'.json';
        $state = [
            'count' => 0,
            'first' => $now,
            'blocked_until' => 0,
            'block_for' => $blockFor,
        ];

        if (Storage::isFile($file)) {
            $loaded = json_decode((string)Storage::getFileData($file), true);
            if (is_array($loaded)) {
                $state = array_merge($state, $loaded);
            }
        }

        if ((int)$state['blocked_until'] > $now) {
            return [
                'allowed' => false,
                'retry_after' => (int)$state['blocked_until'] - $now,
                'blocked_until' => (int)$state['blocked_until'],
                'timeout' => (int)Tools::chef($state, 'block_for', $blockFor),
                'remaining' => 0,
            ];
        }

        if ((int)$state['blocked_until'] > 0 && (int)$state['blocked_until'] <= $now) {
            $state['count'] = 0;
            $state['first'] = $now;
            $state['blocked_until'] = 0;
            $state['block_for'] = $blockFor;
        } elseif ((int)$state['first'] < ($now - $window)) {
            $state['count'] = 0;
            $state['first'] = $now;
            $state['blocked_until'] = 0;
            $state['block_for'] = $blockFor;
        }

        $state['count'] = (int)$state['count'] + 1;

        if ((int)$state['count'] > $limit) {
            $state['blocked_until'] = $now + $blockFor;
            $state['block_for'] = $blockFor;
            Storage::putFile($file, json_encode($state));
            return [
                'allowed' => false,
                'retry_after' => $blockFor,
                'blocked_until' => (int)$state['blocked_until'],
                'timeout' => $blockFor,
                'remaining' => 0,
            ];
        }

        Storage::putFile($file, json_encode($state));
        return [
            'allowed' => true,
            'retry_after' => 0,
            'blocked_until' => 0,
            'timeout' => 0,
            'remaining' => max(0, $limit - (int)$state['count']),
        ];
    }

    /**
    * Clear throttle state
    * @param string $scope
    * @param string|null $identifier
    * @return void
    */
    public function throttleReset($scope, $identifier = null)
    {
        $scope = trim((string)$scope);
        $identifier = trim((string)($identifier === null ? Tools::clientIp(): $identifier));
        $baseDir = rtrim((string)$this->config('system/cache'), '/').'/auth-throttle';
        $file = $baseDir.'/'.Tools::hash($scope.'|'.$identifier, 'sha256').'.json';

        if (Storage::isFile($file)) {
            Storage::deleteFile($file);
        }
    }
}
