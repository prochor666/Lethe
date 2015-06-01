<?php
/**
* Validator
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Validator - Lethe data validator
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-05-04)
*/
class Validator{

	/**
	* @ignore
	*/
	final public function __construct() { trigger_error('Unserializing is not allowed.', E_USER_ERROR); }

	/**
	* @ignore
	*/
	final public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }

	/**
	* Array check helper
	* @param array $a
	* @param string $k as key value
	* @param mixed $d default value
	* @return mixed
	*/
	public static function chef($a, $k, $d = false){
		return is_array($a) && array_key_exists($k, $a) ? $a[$k]: $d;
	}

	/**
	 * Test int>0
	 * @param mixed $var
	 * @return bool
	*/
	public static function isInt($var){
		return is_int($var);
	}

	/**
	 * Test numeric
	 * @param mixed $var
	 * @return bool
	*/
	public static function isNumber($var){
		return is_numeric($var);
	}

	/**
	* Time format checker
	* @param string $val
	* @return bool
	*/
	public static function isTime($val){
		return (bool)strtotime($val);
	}

	/**
	* Compare two values
	* @param string $val1
	* @param string $val2
	* @return bool
	*/
	public static function compare($val1, $val2){
		return $val1 === $val2 ? true: false;
	}

	/**
	* Check mail
	* @param string $email
	* @return bool
	*/
	public static function checkMail($email){
		return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	/**
	* Check common url
	* @param string $url
	* @return bool
	*/
	public static function checkUrl($url){
		return (bool)filter_var($url, FILTER_VALIDATE_URL);
	}

	/**
	* Check http url
	* @param string $url
	* @return bool
	*/
	public static function checkHttp($url){
		return (bool)preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
	}

}
?>
