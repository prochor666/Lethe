<?php
/**
* Lethe base config sample
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
* @package Lethe
*/
use Lethe\Config;

// Filesystem basics
$config['system/public'] = '/public';
$config['system/publicAbs'] = __LETHE_ROOT__.'/public';
$config['system/cache'] = __LETHE_ROOT__.'/public/temp';

// Memcache
$config['system/memcacheEnabled'] = true;
$config['system/memcacheServer'] = "localhost";
$config['system/memcachePort'] = 11211;
$config['system/memcacheDriver'] = 'Memcached';

// Permissions
$config['system/filePermission'] = 0644;
$config['system/directoryPermission'] = 0755;

// Time limit
$config['system/sessionLifetime'] = 86400*7;

// System log
$config['store/log'] = [];


// Session handler and start
session_cache_limiter('nocache');

Config::cookieDomain($config['system/sessionLifetime']);

if($config['system/memcacheEnabled'] === true && $config['system/memcacheDriver'] == 'Memcache')
{
    ini_set('session.save_handler', mb_strtolower('memcache'));
    ini_set('session.save_path', 'tcp://'.$config['system/memcacheServer'].':'.$config['system/memcachePort']);
}

if($config['system/memcacheEnabled'] === true && $config['system/memcacheDriver'] == 'Memcached')
{
    ini_set('session.save_handler', mb_strtolower('memcached'));
    ini_set('session.save_path', $config['system/memcacheServer'].':'.$config['system/memcachePort']);
}

session_start();

// SESSION ID
$config['system/uid'] = session_id();

Config::configure($config);
