<?php
/**
* Lethe framework autoloader
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/
namespace Lethe;

/**
* Lethe\Autoloader - Lethe framework class autoload system
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-04-28)
*/
class Autoloader{

	/**
	* Singleton instance
	*/	
	private static $instance = NULL;

	/**
	* Files system paths, separated by PATH_SEPARATOR
	*/
	private static $libreg = NULL;

	/**
	* @ignore
	*/
	private function __construct() {
	}
 
  	/**
	* Autoloader init, creating instance
	* @param void
	* @return object 
	*/
	public static function init() {
		if (self::$instance == NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}
 
	/**
	* @ignore
	*/
	public function __clone() {
		trigger_error('Clone is not allowed.', E_USER_ERROR);
	}
 
 	/**
	* @ignore
	*/
	public function __wakeup() {
		trigger_error('Unserializing is not allowed.', E_USER_ERROR);
	}
	
	/**
	* Register inlcude/require paths
	* @param array $param
	*/
	public function register($param = array()){
		spl_autoload_register(array(self::$instance, 'append'));
		$paths = is_array($param) ? implode(PATH_SEPARATOR, $param): $param;
		self::$libreg = self::$libreg.PATH_SEPARATOR.$paths;
		set_include_path(self::$libreg);
	}

	/**
	* Register inlcude/require paths, name variants, namespaces
	* @param string $className
	* @return bool
	*/
	private function append($className) {
		
		$ns = explode('\\', $className);
		$nsIndex = count($ns) - 1;
		$classNameNS = $ns[$nsIndex];

		array_pop($ns);

		//, 'class.%s.php', 'static.class.%s.php', 'interface.%s.php'
		$prefixes = array('%s.php', '%s.inc.php', 'class.%s.php');
		
		$ex = explode(PATH_SEPARATOR, get_include_path()); 

		$debug = null;

		foreach($ex as $_dir_){

			if( mb_strlen( $_dir_ )>0 )
			{	

				foreach($prefixes as $prefix){
				
					// namespaced first
					if($nsIndex>0)
					{

						$fileNameNS = str_replace( '//', '/', $_dir_.'/'.implode('/', $ns).'/'.sprintf( $prefix, $classNameNS ) );
						$fileNameNSLower = str_replace( '//', '/', $_dir_.'/'.implode('/', $ns).'/'.mb_strtolower( sprintf( $prefix, $classNameNS ) ) );
			
						if(file_exists($fileNameNS)){
							require_once $fileNameNS;
							return true;
						}elseif(file_exists($fileNameNSLower)){
							require_once $fileNameNSLower;
							return true;
						}
					
					}

					if($nsIndex<1){	
						$fileName = str_replace( '//', '/', $_dir_.'/'.sprintf( $prefix, $classNameNS ) );
						$fileNameLower = str_replace( '//', '/', $_dir_.'/'.mb_strtolower( sprintf( $prefix, $classNameNS ) ) );

						if(file_exists($fileName)){
							require_once $fileName;
							return true;
						}elseif(file_exists($fileNameLower)){
							require_once $fileNameLower;
							return true;
						}

						$debug .= $_dir_.' -> '.$fileName.'<br>';
					}

				}
			}
		}

		//echo($debug);
		
		return false;
	}
	
}

