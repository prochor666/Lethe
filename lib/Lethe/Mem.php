<?php
/**
* Memcache support
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Mem - Lethe framework memcache wrapper
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-06-10)
*/ 

use Lethe\Lethe;

class Mem extends Lethe{
	
	public $key, $data, $keepalive, $server, $port, $output, $connection, $message;

	/** 
	* Memcache wrapper contructor, configured by framework config
	* @param void
	* @return void
	*/
	public function __construct(){
		$this->server = Config::query('system/memcacheServer');
		$this->port = Config::query('system/memcachePort');
		$this->keepalive = 30;
		$this->key = null;
		$this->data = null;
		$this->connection = false;
		$this->output = false;
		$this->message = null;
	}

	/** 
	* Memcache test connection
	* @param void
	* @return bool
	*/
	public function test(){
		if(class_exists('Memcache')){
			$m = new \Memcache;
			$this->connection = @$m->connect($this->server, $this->port, 1);
			if($this->connection === false){
				$this->message = 'Memcache connection failed';
			}
		}else{
			$this->message = 'Memcache class is not installed';
		}

		return $this->connection;
	}

	/**
	* Automatic memcache get/set
	*
	* @param string $key
	* @param int $data
	* @param int $timeout
	* @return mixed
	*/
	public static function auto($key, $data, $keepalive = 10){
		$this->key = $key;
		$this->data = $data;
		$this->keepalive = false;

		$result = $this->get($key);
		$result = $result === false ? $this->store(): $result;
		$ret = $this->output;

		return $ret;
	}

	/** 
	* Sotre data in Memcache 
	* @param void
	* @return void
	*/
	public function store(){
		$m = new \Memcache;
		$this->connection = @$m->connect($this->server, $this->port, 1);
		if($this->connection === true){
			$m->set($this->key, $this->data, 0, $this->keepalive);
			$m->close();
			$this->output = $this->data;
		}
	}


	/** 
	* Memcache wrapper contructor, configured by framework config
	* @param void
	* @return void
	*/
	public function get(){
		$m = new \Memcache;
		$this->connection = @$m->connect($this->server, $this->port, 1);
		if($this->connection === true){
			$this->output = $m->get($this->key);
			$m->close();
		}
	}


}
?>
