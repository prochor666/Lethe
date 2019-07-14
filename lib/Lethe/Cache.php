<?php
namespace Lethe;

use Lethe\Storage;
use Lethe\Tools;

/**
* Lethe\Cache - basic filesystem caching operations
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
*/
class Cache extends Lethe
{
    public $key, $data, $keepalive, $storage;

    private $cacheFile, $meta, $permission, $extension;

    /**
    * Cache class constructor
    * @param void
    * @return void
    */
    public function __construct()
    {
        parent::__construct();
        $this->keepalive = 15;
        $this->key = 'lethe';
        $this->extension = 'wcache';
        $this->data = '';
        $this->meta = [];
        $this->cacheFile = null;
        $this->storage = sys_get_temp_dir();
        $this->permission = $this->config('system/filePermission');
    }

    /**
    * Get cache content
    * @param string
    * @return string
    */
    public function getKey($key)
    {
        if ($this->has($key)) {
            return json_decode($this->cacheRead(), true);
        }
        return false;
    }

    /**
    * Set cache content
    * @param string
    * @return string
    */
    public function setKey($key, $data)
    {
        $this->has($key);
        $this->data = json_encode($data);
        $this->cacheStore();
    }


    /**
    * Check cache key
    * @param string
    * @return bool
    */
    public function has($key)
    {
        $this->cacheFile = $this->storage.'/'.$key.'.'.$this->extension;
        $this->getMeta();
        return $this->isValid();
    }

    /**
    * Check cache expiration
    * @param void
    * @return bool
    */
    private function isValid()
    {
        if (is_array($this->meta) && count($this->meta)>0 && array_key_exists('mtime',$this->meta) && ((int)$this->keepalive + $this->meta['mtime']) >= time()) {
            return true;
        } elseif (is_array($this->meta) && count($this->meta)>0 && array_key_exists('mtime',$this->meta) && ((int)$this->keepalive + $this->meta['mtime']) < time()) {
            Storage::deleteFile($this->cacheFile);
        }
        return false;
    }

    /**
    * Write cache file
    * @param void
    * @return void
    */
    private function cacheStore()
    {
        Storage::putFile($this->cacheFile, $this->data);
        Storage::permissionChange($this->cacheFile, $this->permission);
    }

    /**
    * Direct read cache file, no compare
    * @param void
    * @return string
    */
    private function cacheRead()
    {
        return  Storage::getFileData($this->cacheFile);
    }

    /**
    * Sets cache file metadata
    * @param void
    * @return void
    */
    private function getMeta()
    {
        $this->meta = Storage::isFile($this->cacheFile) ? @stat($this->cacheFile): [];
    }
}
