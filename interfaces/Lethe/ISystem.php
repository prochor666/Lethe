<?php
namespace Lethe;

/**
* interface ISystem.php
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
*/
interface ISystem
{
	public function config($query);
    public function reg($query);
    public function langConfig();
}
