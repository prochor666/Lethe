<?php
namespace Lethe;

/**
* interface.ISystem.php
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.1
*/
interface ISystem
{
	public function config($query);
	public function reg($query);
    public function langConfig();
}