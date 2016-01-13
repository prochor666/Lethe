<?php
/**
* Lethe base config
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

// Filesystem basics
$config['system']['public'] = '/public';
$config['system']['publicAbs'] = __LETHE_ROOT__.'/public';
$config['system']['cache'] = __LETHE_ROOT__.'/public/temp';

// Memcache
$config['system']['memcacheEnabled'] = true;
$config['system']['memcacheServer'] = "localhost";
$config['system']['memcachePort'] = 11211;
$config['system']['memcacheDriver'] = 'Memcached';

// Permissions
$config['system']['filePermission'] = 0644;
$config['system']['directoryPermission'] = 0755;

// Time limit
$config['system']['sessionLifetime'] = 86400*7;

// Version
$config['system']['version'] = '0.7.5';

// System log
$config['store']['log'] = [];
