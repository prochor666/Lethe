<?php
namespace Lethe;

/**
* Lethe\Db - databse wrapper, multiple SQL databases/engines support
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.7
*/
class Db
{
	/**
	* @ignore
	*/
	final public function __construct() { trigger_error('Unserializing is not allowed.', E_USER_ERROR); }

	/**
	* @ignore
	*/
	final public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }

	/**
	* Database driver choose
	*
	* @param array $conf
	* @return object
	*/
	private static function instance($conf)
	{

		$driver = Tools::chef($conf, 'driver', NULL);

		if(is_null($driver))
		{
			$driver = Config::query('db/0');
		}

		switch($driver['engine'])
		{
			case 'Mysqldb': case 'mysqldb': case 'mysql':
				return new Mysqldb($driver);
			break; case 'PostgreSQLdb': case 'Postgresqldb': case 'postgresql':
				return new Postgresqldb($driver);
			break; default:
				return new Mysqldb($driver);
		}
	}

	/**
	* Database connection test
	*
	* @param array $conf
	* @return bool|resource
	*/
	public static function testConnection($conf)
	{
		$db = self::instance($conf);
		return $db->testConnection();
	}

	/**
	* Database query result
	*
	* @param array $conf
	* @return array|object
	*/

	// $type = 'assoc'
	public static function result($conf)
	{
		$conf['type'] = Tools::chef($conf, 'type', 'assoc');
		$db = self::instance($conf);
		return $db->result($conf['query'], $conf['type'] );
	}

	/**
	* Database query INSET/UPDATE?DELETE etc..
	*
	* @param array $conf
	* @return bool|resource
	*/
	public static function query($conf)
	{
		$db = self::instance($conf);
		return $db->query($conf['query']);
	}

	/**
	* Get next autoincrement value
	*
	* @param array $conf
	* @return int
	*/
	public static function getLastId($conf)
	{
		$db = self::instance($conf);
		return $db->getLastId($conf['table']);
	}

	/**
	* Escape data for safety
	*
	* @param array $conf
	* @return string
	*/
	public static function sanitize($conf)
	{
		$db = self::instance($conf);
		return $db->sanitize($conf['query']);
	}

	/**
	* Automatic memcache fallback
	*
	* @param array $conf
	* @return mixed
	*/
	public static function memAuto($conf)
	{
		$conf['timeout'] = Tools::chef($conf, 'timeout', 10);
		$result = self::memGet(Tools::hash($conf['query']));
		$result = $result === false ? self::memSet(Tools::hash($conf['query']), self::result($conf), $conf['timeout']): $result;
		return $result;
	}

	/**
	* Memcache get key value
	*
	* @param string $key
	* @return mixed
	*/
	public static function memGet($key)
	{
		$m = new Mem;
		$m->key = $key;
		if(Config::query('system/memcacheEnabled') === true && $m->test() === true)
		{
			$m->get();
			return $m->connection === true ? $m->output: false;
		}
		return false;
	}

	/**
	* Memcache set key value
	*
	* @param string $key
	* @param array $data
	* @param int $timeout
	* @return mixed
	*/
	public static function memSet($key, $data, $timeout = 10)
	{
		$m = new Mem;
		$m->key = $key;
		$m->data = $data;
		$m->keepalive = $timeout;
		if(Config::query('system/memcacheEnabled') === true && $m->test() === true)
		{
			$m->store();
		}
		return $data;
	}

}

?>
