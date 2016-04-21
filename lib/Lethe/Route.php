<?php
namespace Lethe;

use Lethe\Config;
use Lethe\Tools;
use Lethe\Url;

/**
* Lethe\Route - simple static router
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.2
*/
class Route
{
    /**
    * @ignore
    */
    final public function __construct(){}

    /**
    * Get relative path
    * @description full relative path
    * @return string
    */
    public static function get()
    {
        return Config::query('system/rel');
    }

    /**
    * Get relative path
    * @description full relative path
    * @param integer $index
    * @return string
    */
    public static function index($index = -1)
    {
        $r = Tools::chef(self::all(), $index, null);
        return $r['path'];
    }

    /**
    * Get full path as array
    * @description full relative path
    * @return array
    */
    public static function all()
    {
        $r = Url::parse(self::get());
        return $r[0];
    }

    /**
    * Path count
    * @description Path parts count
    * @return integer
    */
    public static function count()
    {
        return count(self::all());
    }

    /**
    * Redirect
    * @description redirect helper
    * @param string $path = /some/path/to
    * @return void
    */
    public static function redirect($path = '/')
    {
        Tools::redirect($path);
    }
}
?>
