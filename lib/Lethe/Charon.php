<?php
/**
* Queue system
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Charon - Lethe framework queue system
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-06-28)
*/

class Charon extends Lethe
{

	protected $souls;

	/**
	* @ignore
	*/
	public function __construct()
	{
		parent::__construct();

		$this->souls = new StdCLass;
		$this->souls->identity = $this->setIdentity(Tools::rnd(71));
		$this->souls->data = array();
		$this->souls->worker = function($config = array()){};
	}

	/**
	* Set queue item
	* @param array $data
	* @return void
	*/
	public function add($data)
	{
		$this->souls->data[] = $data;
	}

	/**
	* Run queue
	* @param int $usleep
	* @return void
	*/
	public function ship($usleep=0)
	{
		foreach($this->souls->data[] as $task)
		{

		}
	}

	/**
	* Set custom worker
	* @param object|function $worker
	* @return void
	*/
	public function worker($worker)
	{
		$this->souls->worker = $worker;
	}

	/**
	* Set custom queue identifier
	* @param string $identifier
	* @return void
	*/
	public function setIdentity($identifier)
	{
		$this->souls->identity = $identifier;
	}

	/**
	* Get queue data
	* @param void
	* @return object
	*/
	public function getData()
	{
		return $this->souls->data;
	}

}