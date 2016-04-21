<?php
namespace Lethe;

/**
* Base class, initalizes config and registry, provide debug messaging
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.1
*/

class Lethe implements ISystem, IDebug
{
    /**
    * Debug info
    */
    protected   $debug;

    /**
    * Debug info, human readable
    */
    protected   $debugHuman;

    /**
    * Lethe class contructor
    * @param void
    * @return void
    */
    public function __construct()
    {
        $this->debug = [];
        $this->debugHuman = [];
    }

    /**
    * Set debug message
    * @param string|int $code
    * @param string $message
    * @return void
    */
    public function debugSet($code, $message)
    {
        $t = microtime(true);
        $tt = explode('.', (string)$t);
        $message = (int)$code < 1000 ? 'MESSAGE::'.$message: 'ERROR::'.$message;
        $th = count($tt)>1 ? date('Y-m-d-H-i-s', $tt[0]).'-'.$tt[1]: date('Y-m-d-H-i-s', $tt[0]);
        $this->debug[] = ['time' => $th, 'code' => $code, 'message' => $message];
    }

    /**
    * Read debug
    * @param void
    * @return array
    */
    public function debugRead()
    {
        return $this->debug;
    }

    /**
    * Clear debug
    * @param void
    * @return void
    */
    public function debugClear()
    {
        $this->debug = [];
    }

    /**
    * Query configuration
    * @param string $query
    * @return mixed
    */
    public function config($query)
    {
        return Config::query($query);
    }

    /**
    * Store log record (LIFO)
    * @param array $record
    * @return array
    */
    public function log($record = [])
    {
        $log = Config::query('store/log');

        if(
            array_key_exists( 'case', $record ) && array_key_exists( 'message', $record )
        ){
            $log[] = ['timestamp' => time(), 'case' => $record['case'], 'message' => $record['message']];
        }

        Config::set('store/log', $log);
    }

    /**
    * Query SESSION based registry
    * @param string $query
    * @return mixed
    */
    public function reg($query)
    {
        return Reg::query($query);
    }

    /**
    * i18n ISO 639-1 JSON source
    * @param void
    * @return array
    */
    public function langConfig()
    {
        return json_decode(Storage::getFileData(__LETHE_LETHE__.'/init/lang.json'), true);
    }

}
