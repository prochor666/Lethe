<?php
namespace Lethe;

/**
* interface.ISystem.php
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
*/
interface ISystem
{
	public function config($query);
    public function reg($query);
    public function langConfig();
}
