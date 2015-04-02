<?php
/**
* Lethe framework configurator
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Config - Lethe framework configurator
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-03-01)
*/
class Config{
	
	/**
	* @ignore
	*/
	private static $instance = NULL;
	
	/**
	* @ignore
	*/
	private static $config = array();

	/**
	* @ignore
	*/
	private function __construct() {
	}

	/**
	* @ignore
	*/ 
	public function __clone() {
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}
 
 	/**
	* @ignore
	*/
	public function __wakeup() {
		trigger_error('Unserializing is not allowed.', E_USER_ERROR);
	}
 
 	/**
	* Config init, creating registry, use it once at boot
	* @param void
	* @return object 
	*/
	public static function init() {
		if (self::$instance == NULL) {
			self::$instance = new self();
			self::release();
		}
		return self::$instance;
	}

	/**
	* Read key/value pair
	* @param string $q 
	* @return mixed 
	*/
	public static function query($q = null){
		$origin = self::$config;
		$section = explode('/', trim($q, ' /'));
		$valid = array('user', 'db', 'mail', 'store', 'system', 'error' );
		$result = false; //array('status' => false, 'reason' => 'No value found');

		if(count($section)>0 && array_key_exists($section[0], $origin) && in_array($section[0], $valid) ){
			$lastRound = count($section)-1;
			
			foreach($section as $k => $s){
				if(array_key_exists($s, $origin)){
					$origin = $origin[$s];
					if($lastRound == $k){
						$result = $origin;
					}
				}
			}
		}

		return $result;
	}

	/**
	* Config array, key/value pairs, multiple
	* @param array $block
	* @return void
	*/
	public static function setBlock( $block = array() ){
		foreach($block as $k => $v){
			self::set($k, $v);
		}	
	}

	/**
	* Config array, key/value pairs, multiple, helper
	* Alias for Reg::setBlock
	* @param array $block
	* @return void
	*/
	public static function configure( $block = array() ){
		if(is_array($block)){
			self::setBlock($block);
		}
	}

	/**
	* Config pair, key/value pair
	* @param string $q = 'path/to'
	* @param mixed $value 
	* @return void
	*/
	public static function set($q = 'store/blind', $value = false){
		$branch = &self::$config;
		$section = explode('/', trim($q, ' /'));
		$valid = array('user', 'store', 'mail', 'db');
		
		if(count($section)>1 && in_array($section[0], $valid)){
			
			$lastRound = count($section)-1;
			
			foreach($section as $k => $s){
				$branch = &$branch[$s];
				if($lastRound == $k){
					$branch = $value;
				}
			}
			unset($branch);
		}
	}

	/**
	* Read whole registry, use for development
	* @param void
	* @return array 
	*/
	public static function read(){
		return self::$config;
	}

	/**
	* Reset registry, recreate core defaults, use for development
	* @param void
	* @return array
	*/
	public static function reset(){
		self::$config = array();
		self::init();
		return self::read();
	}

	/**
	* Initialize registry, core values, readonly system variables
	* @param void
	* @return void
	*/
	private static function release(){
		
		// Read only
		$config = array(); 
		$valid = array('db', 'mail', 'store', 'system');

		foreach($valid as $v){
			if(!Tools::chef(self::$config, $v) || !is_array(self::$config)){
				$config[$v] = array();
			}
		}



		// Server override
		$__SERVER_NAME = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME']: PHP_SAPI;
		$__REQUEST_URI = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']: '';
		
		// INIT defaults
		
		// SESSION ID 	
		$config['system']['uid'] = session_id();
		
		// Initial time
		$config['system']['start'] = microtime(true);

		// Initial max memory usage
		$config['system']['initMemTop'] = memory_get_usage(true);

		// Initial avg memory usage
		$config['system']['initMemPeakTop'] = memory_get_peak_usage(true);

		// Server root directory
		$config['system']['root'] = __LETHE_ROOT__;

		// Protocol
		$config['system']['protocol'] = isset($__HTTPS) && !empty($__HTTPS) ? 'https://': 'http://';

		// Port umber
		$config['system']['port'] = isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != '80' && $_SERVER["SERVER_PORT"] != '443' ? (int)$_SERVER["SERVER_PORT"]: 80;

		// Port used in URLs
		$config['system']['portPath'] = isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != '80' && $_SERVER["SERVER_PORT"] != '443' ? ':'.$_SERVER["SERVER_PORT"]: null;

		// Full site, domain and REQUEST_URI (only dirname)
		$config['system']['site'] = (string)( $config['system']['protocol'].$__SERVER_NAME.$config['system']['portPath'].$__REQUEST_URI );
		$config['system']['site'] = mb_substr((string)$config['system']['site'], -1, 1, 'UTF-8') === '/' ? mb_substr((string)$config['system']['site'], 0, -1, 'UTF-8'): $config['system']['site'];


		// Full site, domain and REQUEST_URI (only dirname), 
		// Mostly the same as site, but if you use some htaccess tricks:
		// EXAMPLE: 
		// ReWriteCond %{REQUEST_URI} public-fake-dir
		// ReWriteRule ^public-fake-dir/(.*)$ real/server/directory/$1 [L,QSA]
		// siteOrigin will point to 'real/server/directory'
		$config['system']['siteOrigin'] = (string)( $config['system']['protocol'].$__SERVER_NAME.$config['system']['portPath'].dirname($_SERVER['SCRIPT_NAME']) );
		$config['system']['siteOrigin'] = mb_substr((string)$config['system']['siteOrigin'], -1, 1, 'UTF-8') === '/' ? mb_substr((string)$config['system']['siteOrigin'], 0, -1, 'UTF-8'): $config['system']['siteOrigin'];

		// Domain used in system
		$config['system']['domain'] = (string)( $config['system']['protocol'].$__SERVER_NAME.$config['system']['portPath'] );

		// Clear domain name without www or http... 
		$config['system']['domainName'] = str_replace('www.', NULL, (string)$__SERVER_NAME);

		// Full address
		$config['system']['url'] = $config['system']['protocol'].$__SERVER_NAME.$config['system']['portPath'].$__REQUEST_URI;

		// Relative path
		$config['system']['rel'] = isset($_SERVER['REQUEST_URI']) ? $__REQUEST_URI: '/';

		// Browser address parsed
		$config['system']['urlArray'] = parse_url($config['system']['url']);

		// Browser query string parsed
		$_queryArray = array();
		if( array_key_exists('query', $config['system']['urlArray']) ){
			parse_str( $config['system']['urlArray']['query'], $_queryArray ); 
		}
		$config['system']['queryArray'] = $_queryArray;

		// Browser language
		$config['system']['lang'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2): 'en';
		
		// Browser type
		$config['system']['userAgent'] = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"]: 'Unknown';
		
		// OS type
		$config['system']['os'] = PHP_OS;
		
		// Server API type
		$config['system']['sapi'] = PHP_SAPI;

		// Core identification
		$config['system']['productName'] = 'Lethe';

		// Code name 
		$config['system']['productCodename'] = 'Rising Decay';
	
		// User defined config
		require_once __LETHE_LETHE__.'/init/config.php'; 
		
		// External config
		self::$config = $config;
	}
}
?>
