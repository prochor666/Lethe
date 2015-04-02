<?php
/**
* Cache system
*
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @package Lethe
*/

namespace Lethe;

/**
* Lethe\Cache - basic filesystem caching operations
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @license http://opensource.org/licenses/mit-license.php MIT License
* @version 1.0 (2014-05-18)
*/

use Lethe\Storage;
use Lethe\Tools;

class Cache{
	
public $data, $keepalive, $forceRewrite, $cacheFile;	

private $storage, $meta, $permission;

/**
* Cache class constructor 
* @param void
* @return void
*/	
public function __construct(){
	$this->keepalive = 3600;
	$this->meta = array();
	$this->data = null;
	$this->cacheFile = null;
	$this->permission = Config::query('system/filePermission');
}

/**
* Autocache content, compare cache and live data
* @param void
* @return string
*/
public function auto(){
	
	if(is_null($this->cacheFile)){
		return $this->data;
	}

	$this->cacheFile = $this->storage.'/'.$this->cacheFile;
	
	$this->getMeta();

	if(!is_array($this->meta) || !array_key_exists('mtime',$this->meta) || $this->isExpired() || $this->meta['size']<1 || $this->keepalive == 0 ){
	   $this->cacheStore(); 
	}
	
	return $this->cacheRead();
}

/**
* Check cache expiration 
* @param void
* @return bool
*/
public function isExpired(){

	if(is_null( $this->cacheFile )){
		return true;
	}

	$this->getMeta();
	
	return ( !is_array($this->meta) || count($this->meta)<1 ) || ((int)$this->keepalive + $this->meta['mtime']) < time() ? true: false;
}

/**
* Write cache file
* @param void
* @return void
*/
private function cacheStore(){
	Storage::putFile( $this->cacheFile, $this->data );
	Storage::permissionChange( $this->cacheFile, $this->permission );
}

/**
* Direct read cache file, no compare
* @param void
* @return string
*/
private function cacheRead(){
	return  Storage::getFileData($this->cacheFile);
}

/**
* Sets cache file metadata
* @param void
* @return void
*/
private function getMeta(){
	$this->meta = Storage::isFile($this->cacheFile) ? @stat($this->cacheFile): array();
}

}
