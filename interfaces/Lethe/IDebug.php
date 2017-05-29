<?php
namespace Lethe;

/**
* interface.IDebug.php
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
*/
interface IDebug
{
    public function debugSet($code, $message);
    public function debugRead();
    public function debugClear();
    public function log($record);
}
