<?php
namespace Lethe;

use Lethe\Tools;

/**
* Lethe\Config - Lethe configurator
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.0
*/
class Config{

	/**
	* @ignore
	*/
	private static $instance = NULL;

	/**
	* @ignore
	*/
	private static $config = [];

	/**
	* @ignore
	*/
	private function __construct()
	{
	}

	/**
	* @ignore
	*/
	public function __clone()
	{
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}

 	/**
	* @ignore
	*/
	public function __wakeup()
	{
		trigger_error('Unserializing is not allowed.', E_USER_ERROR);
	}

 	/**
	* Config init, creating registry, use it once at boot
	* @param void
	* @return object
	*/
	public static function init($config = [])
	{
		if (self::$instance == NULL)
		{
			self::$instance = new self();
			self::release($config);
		}
		return self::$instance;
	}

	/**
	* Read key/value pair
	* @param string $q
	* @return mixed
	*/
	public static function query($q = null)
	{
		$origin = self::$config;
		$section = explode('/', trim($q, ' /'));
		//$valid = ['user', 'db', 'mail', 'store', 'system', 'error'];
		$result = false;

		if(count($section)>0 && array_key_exists($section[0], $origin)/* && in_array($section[0], $valid)*/ )
		{
			$lastRound = count($section)-1;

			foreach($section as $k => $s)
			{
				if(array_key_exists($s, $origin))
				{
					$origin = $origin[$s];
					if($lastRound == $k)
					{
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
	public static function setBlock( $block = [] )
	{
		foreach($block as $k => $v)
		{
			self::set($k, $v);
		}
	}

	/**
	* Config array, key/value pairs, multiple, helper
	* Alias for Reg::setBlock
	* @param array $block
	* @return void
	*/
	public static function configure( $block = [] )
	{
		if(is_array($block))
		{
			self::setBlock($block);
		}
	}

	/**
	* Config pair, key/value pair
	* @param string $q = 'path/to'
	* @param mixed $value
	* @return void
	*/
	public static function set($q = 'store/blind', $value = false)
	{
		$branch = &self::$config;
		$section = explode('/', trim($q, ' /'));

		if(count($section)>1)
		{
			$lastRound = count($section)-1;

			foreach($section as $k => $s)
			{
				if(is_object($branch))
                {
                    $branch = &$branch->$s;

                }else{

                    $branch = &$branch[$s];
                }

                if($lastRound == $k)
                {
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
	public static function read()
	{
		return self::$config;
	}

	/**
	* Reset registry, recreate core defaults, use for development
	* @param void
	* @return array
	*/
	public static function reset()
	{
		self::$config = [];
		self::init();
		return self::read();
	}


	/**
	* Set proper and safe session cookie
	* @param integer sessionLifetime
	* @return void
	*/
	public static function cookieDomain($sessionLifetime)
	{
		if(isset($_SERVER['HTTP_HOST']))
		{
			if(strpos($_SERVER['HTTP_HOST'], ':') !== false)
			{
				$domain = substr($_SERVER['HTTP_HOST'], 0, (int)strpos($_SERVER['HTTP_HOST'], ':'));
			}else{
				$domain = $_SERVER['HTTP_HOST'];
			}

			$domain = preg_replace('`^www.`', '', $domain);

			$rootDomain = $domain;

			// Per RFC 2109, cookie domains must contain at least one dot other than the
			// first. For hosts such as 'localhost', we don't set a cookie domain.
			$nd = explode('.', $domain);
			if (count($nd) > 2)
			{
				unset($nd[0]);
				$rootDomain = implode('.', $nd);
			}

			session_set_cookie_params ( $sessionLifetime, '/', '.'.$rootDomain, Tools::ssl(), true );
		}
	}

	/**
	* Initialize registry, core values, readonly system variables
	* @param void
	* @return void
	*/
	private static function release($config = [])
	{

		// Read only
		$config = !is_array($config) ? []: $config;
		$valid =['db', 'mail', 'store', 'system'];

		foreach($valid as $v)
		{
			if(!Tools::chef(self::$config, $v))
			{
				$config[$v] = [];
			}
		}

		// Server override
		$__SERVER_NAME = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME']: PHP_SAPI;
		$__REQUEST_URI = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI']: '';

		// INIT defaults

		// Server root directory
		$config['system']['root'] = __LETHE_ROOT__;

		// Protocol
		$config['system']['protocol'] = Tools::ssl() === true ? 'https://': 'http://';

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
		$_queryArray = [];
		if( array_key_exists('query', $config['system']['urlArray']) )
		{
			parse_str( $config['system']['urlArray']['query'], $_queryArray );
		}
		$config['system']['queryArray'] = $_queryArray;

		// Initial time
		$config['system']['start'] = microtime(true);

		// Initial max memory usage
		$config['system']['initMemTop'] = memory_get_usage(true);

		// Initial avg memory usage
		$config['system']['initMemPeakTop'] = memory_get_peak_usage(true);

		// Browser language
		$config['system']['lang'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2): 'en';

		// Browser type
		$config['system']['userAgent'] = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"]: 'Unknown';

		// OS type
		$config['system']['os'] = PHP_OS;

		// Server API type
		$config['system']['sapi'] = PHP_SAPI;

		// System identification
		$config['system']['productName'] = 'Lethe';

		// System version
		$config['system']['version'] = '0.8.1';

		// Code name
		$config['system']['productCodename'] = 'Rising Decay';

		self::$config = $config;
	}
}
?>
