<?php
namespace Lethe;

/**
* Lethe\Url - universal url parser/extractor
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.1
*/
class Url
{
	/**
	* @ignore
	*/
	final public function __construct() { trigger_error('Unserializing is not allowed.', E_USER_ERROR); }

	/**
	* @ignore
	*/
	final public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }

	/**
	* Url parse
	* @description Create array from given path!!!!
	* @param string $path = some/path/to
	* @return string
	*/
	public static function parse($path = null, $delimiter = '-')
	{

		$result = array(0 => array(), 1 => array());

		$qspos = strpos( $path, '?' );
		if( $qspos !== false )
		{
			$path = mb_substr( $path, 0, $qspos );
		}
		$src = explode('/', $path);
		$src = array_filter($src, array('self', 'string'));

		if (!is_null($delimiter) || mb_strlen($delimiter) > 0)
		{
			foreach($src as $s)
			{
				$result[0][] = self::extract($s, $delimiter);
				$result[1][$s] = self::extract($s, $delimiter);
			}
		}

		return $result;
	}


	/**
	* Check string
	* @description Check string length
	* @mixed $index
	* @return bool
	*/
	public static function string($v)
	{
		$v = (string)$v;
		return mb_strlen($v)>0 ? true: false;
	}

	/**
	* Url model checker
	* @description Create array from given path!!!!
	* @param array $model
	* @param int $index
	* @return bool|string
	*/
	public static function check($model, $index = 0)
	{
		return is_array($model) && array_key_exists($index, $model) ? $model[$index]['path']: false;
	}

	/**
	* Fragment parse
	* @description Create identifier from given fragment!!!!
	* @param string $path = name-to-123
	* @param string $delimiter = null, '-'...
	* @return string
	*/
	public static function extract($path = null, $delimiter = null)
	{

		$result = array('path' => null, 'name' => null, 'id' => null);

		$result['path'] = $result['name'] = $path;

		$src = array_filter(explode($delimiter, $path));
		$i = count($src) - 1;

		if($i>0)
		{
			$id = $src[$i];
			array_pop($src);
			$result['name'] = implode($delimiter, $src);
			$result['id'] = $id;
		}

		return $result;
	}

	/**
	* Fragment update
	* @description Create identifier from given fragment!!!!
	* @param array $urlArray
	* @param array $keys
	* @return string
	*/
	public static function update($urlArray, $keys = array())
	{
		$query = array();
		if( array_key_exists('query', $urlArray) )
		{
			parse_str( $urlArray['query'], $query );
		}

		foreach($keys as $key => $value)
		{
			$query[$key] = $value;
		}

		$urlArray['query'] = http_build_query($query, 'p', '&amp;');

		$url = $urlArray['scheme'].'://'.$urlArray['host'].rtrim($urlArray['path'], '/').'?'.$urlArray['query'];

		return $url;
	}

}
?>