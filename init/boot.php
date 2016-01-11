<?php
/**
* Lethe bootstrap
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

if (version_compare(PHP_VERSION, '5.3.0', '<') )
{
	die('Now runing '.PHP_VERSION.'. You need PHP version 5.3.0 or later.');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');

/*
* *****************************************
*  MAIN CONFIGURATION + CORE LOAD   *
* *****************************************
*/
use Lethe\Lethe;
use Lethe\Autoloader;
use Lethe\Config;
use Lethe\Reg;
use Lethe\Tools;

if(	file_exists(__LETHE_LETHE__.'/lib/Lethe/Autoloader.php') )
{
	require_once __LETHE_LETHE__.'/lib/Lethe/Autoloader.php';

	try
	{
		ob_start('mb_output_handler');

		Autoloader::init()->register([
			__LETHE_LETHE__.'/interfaces/',
			__LETHE_LETHE__.'/lib/',
		]);

		Config::init();
		Reg::init();

		$buffer = ob_get_contents();
		ob_end_clean();

		echo trim($buffer);

	}catch(Exception $e)
	{
	    echo 'Fix me: '.$e->getMessage();
	}

}else{
	die('Lethe boot error, check core files.');
}
