<?php
/**
* Lethe framework system configurator interface
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* interface.isystem.php
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-06-26)
*/
interface ISystem{

	public function config($query);
	public function reg($query);
}