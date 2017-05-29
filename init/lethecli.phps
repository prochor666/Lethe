#!/usr/bin/php
<?php
/**
* Lethe CLI interpreter
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/
use Lethe\Lethe;
use Lethe\Cmd;

if(PHP_SAPI === 'cli')
{
    require 'core.php';
    $a = $_SERVER['argv'];

    $g = new Cmd;
    $g->command = count($a)>1 ? $a[1]: 'info';
    unset($a[0], $a[1]);
    $g->options = $a;

    echo $g->run();
}
