<?php
/**
* Lethe framework debug interface
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/


namespace Lethe;

/**
* interface.idebug.php
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-06-26)
*/
interface IDebug{

	public function debugSet($code, $message);
	public function debugRead();
	public function debugClear();
}