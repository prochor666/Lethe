<?php
/**
* Formatter, cleaner
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Format - Lethe data formatter, provides basic format and clenaup methods
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @copyright 2014 Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.0 (2014-05-28)
*/
class Format{

	/**
	* @ignore
	*/
	final public function __construct() { trigger_error('Unserializing is not allowed.', E_USER_ERROR); }

	/**
	* @ignore
	*/
	final public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }

	/**
	 * Force to int
	 * @param string $var
	 * @return int
	*/
	public static function makeInt($var){
	  return (int)$var;
	}

	/**
	 * Force to float 
	 * @param string $var
	 * @return float
	*/
	public static function makeFloat($var){
	  return  (float)str_replace(',', '.', $var);
	}

	/**
	 * Human readable bytes
	 * @param int $size
	 * @param int $round
	 * @return string
	*/
	public static function dataSize($size, $round = 2){
		$round = abs((int)$round);
		$mod = 1024;
		$units = explode(' ','B KB MB GB TB PB');
		for ($i = 0; (int)$size > $mod; $i++) {
			(int)$size /= $mod;
		}
		return round((int)$size, $round) . ' ' . $units[$i];
	}


	/**
	 * Human readable xml array/object convertor
	 * @param object|array $a
	 * @return string
	*/
	public static function ObjXml( $a )
	{
	    $xml = NULL;	
	    foreach( $a as $k => $v ){

	    	$tag = trim( $k );
	    	$tagEnd = $tag = is_numeric( $tag ) ? 'num_'.$tag: $tag;
	    	
	    	$tagObj = explode( ' ', $tag ); 

	    	if( count( $tagObj ) > 1 ){
	    		$tagEnd = $tagObj[0];
	    	}

	    	$xml .= is_array( $v ) || is_object( $v ) ? "<$tag>".self::ObjXml( $v )."</$tagEnd>": "<$tag>".htmlentities( $v )."</$tagEnd>";	
	    }

	    return $xml;
	}

	/**
	* Convert string to UTF-8
	* @param string $str
	* @return string
	*/
	public static function autoUTF($str){
		// detect UTF-8
		if (preg_match('#[\x80-\x{1FF}\x{2000}-\x{3FFF}]#u', $str))
		{
			return $str;
		}elseif(preg_match('#[\x7F-\x9F\xBC]#', $str))
		{
			// detect WINDOWS-1250
			return iconv('WINDOWS-1250', 'UTF-8', $str);
		}
		// assume ISO-8859-2
		return iconv('ISO-8859-2', 'UTF-8', $str);
	}


	/**
	* Clear HTML & trim
	* @param string $str
	* @return string
	*/
	public static function clearHtml($str){
		$str = strip_tags($str);
		return trim($str);
	}

	/**
	* Cut string, reflect words delimited by space, cuts some ugly chars from the end
	* @param string $str
	* @param int $length
	* @return string
	*/
	public static function cutStr($str, $length = 255, $suffix = NULL){ 
	
		// printable, formal, to samy co nad tim, jen jinak psany
		// $str = mb_ereg_replace( '[^[:print:]]', '', self::autoUTF( $str ) );  
		
		$str = trim(strip_tags($str));
		$str = trim($str, ',.');

		if (mb_strlen($str)<=$length) { 
			return $str; 
		} 

		$str = mb_substr($str, 0, $length); 

		$pos = mb_strrpos($str, " "); 
		$str = $pos>1 ? mb_substr( $str, 0, $pos ): $str; 
		$str = trim($str, '-, ');

		// short last word
		$wordLimit = 4;
		$words = explode( ' ', $str );
		$lastPos = count( $words ) - 1;
		$lastWordLength = mb_strlen( $words[$lastPos] );

		if( $lastWordLength < $wordLimit ){
			$str = mb_substr( $str, 0, -($lastWordLength+1) );
		}

		return $str.$suffix; 
	}

	/**
	* Proper substr for unicode strings
	* @param string $str
	* @param string $start
	* @param array $length 
	* @return string
	*/
	public static function substr($str, $start, $length = null) {
	    return join("", array_slice(
	        preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY), $start, $length)
	    );
	}


	/**
	* Make URL friendly string 
	* @param string $str
	* @param string $delimiter
	* @param array $replace 
	* @return string
	*/
	public static function urlSafe($str, $delimiter='-', $pathSafe=false, $replace=array()) {
		
		$str = mb_ereg_replace( '[^[:ascii:]]', '', $str);
		

		if( !empty($replace) ) {
			$str = str_replace((array)$replace, ' ', $str);
		}

		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|.+ -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		if( $pathSafe === true){
			$clean = preg_replace("/[_|+ -]+/", $delimiter, $clean);
		}else{
			$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
		}

		return $clean;
	}

	/**
	* Make URL friendly string, soft method, semantic friendly
	* @param string $str
	* @return string
	*/
	public static function urlSoft($str){

		$str = mb_ereg_replace( '[^[:ascii:]]', '', $str);

		$str = strip_tags($str);

		$XFilter = array(
			"А" => "а", "Б" => "б", "В" => "в", "Г" => "г", "Д" => "д", "Е" => "е", "Ж" => "ж", "З" => "з", "И" => "и", "Й" => "и", 
			"К" => "к", "Л" => "л", "М" => "м", "Н" => "н", "О" => "о", "П" => "п", "Р" => "р", "С" => "с", "Т" => "т", "У" => "у", 
			"Ф" => "ф", "Х" => "х", "Ц" => "ц", "Ч" => "ч", "Ш" => "ш", "Щ" => "щ", "Ъ" => "ъ", "Ы" => "ы", "Ь" => "ь", "Э" => "э", 
			"Ю" => "ю", "Я" => "я", "й" => "и",

			"ě" => "e", "š" => "s", "č" => "c","ř" => "r", "ž" => "z","ý" => "y","á" => "a","í" => "i","é" => "e","ů" => "u","ü" => "u",
			"ú" => "u","ó" => "o","ö" => "oe","ň" => "n","ń" => "n","ć" => "c", "ë" => "ea","ä" => "ae","ď" => "d","ľ" => "l", "ť" => "t", "ç" => "c", "ß" => "ss",

			"Ě" => "E", "Š" => "S", "Č" => "C","Ř" => "R", "Ž" => "Z","Ý" => "Y","Á" => "A","Í" => "I","É" => "E","Ů" => "U","Ü" => "U",
			"Ú" => "U","Ó" => "O","Ö" => "Oe","Ň" => "N","Ń" => "N","Ć" => "C", "Ë" => "Ea","Ä" => "Ae","Ď" => "D","Ľ" => "L", "Ť" => "T", "Ç" => "C"
			) ;

		$str = strtr($str,$XFilter);
		
		if($str !== mb_convert_encoding( mb_convert_encoding($str, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32') ){
			$str = mb_convert_encoding($str, 'UTF-8');
		}
		$str = htmlentities($str, ENT_NOQUOTES, 'UTF-8');
		$str = preg_replace('`&([a-z]{1,2})(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig);`i', '\\1', $str);
		$str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
		$str = preg_replace(array('`[^a-z0-9]`i','`[-]+`'), '-', $str);
		$str = mb_strtolower( trim($str, '-') );
		
		return rawurlencode($str);
	}

}
?>
