<?php
namespace Lethe;

/**
* Lethe\Reg - session registry configuration engine
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.1
*/
class Reg
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
    * Config init, creating registry, use it once at boot!!!!
    * @param void
    * @return array
    */
    public static function init()
    {
        if(!isset($_SESSION['__lethe_registry'][session_id()]) || !is_array($_SESSION['__lethe_registry']))
        {
            $_SESSION['__lethe_registry'][session_id()] = [];
        }
        self::release();
        return self::read();
    }

    /**
    * Read key/value pair
    * @param string $q
    * @return mixed
    */
    public static function query($q = null)
    {
        $origin = $_SESSION['__lethe_registry'][session_id()];
        $section = explode('/', trim($q, ' /'));
        $result = false;

        if(count($section)>0 && array_key_exists($section[0], $origin) )
        {
            $lastRound = count($section)-1;

            foreach($section as $k => $s){
                if(array_key_exists($s, $origin))
                {
                    $origin = $origin[$s];
                    if($lastRound == $k)
                    {
                        $result = $origin;
                    }
                }
            }
        }

        return $result;
    }

    /**
    * Config array, key/value pairs, multiple
    * @param array $block
    * @return void
    */
    public static function setBlock( $block = [] )
    {
        foreach($block as $k => $v)
        {
            self::set($k, $v);
        }
    }

    /**
    * Config array, key/value pairs, multiple, helper
    * Alias for Reg::setBlock
    * @param array $block
    * @return void
    */
    public static function configure( $block = [] )
    {
        self::setBlock($block);
    }

    /**
    * Config pair, key/value pair
    * @param string $q = 'path/to'
    * @param mixed $value
    * @return void
    */
    public static function set($q = 'store/blind', $value = false)
    {
        $branch = &$_SESSION['__lethe_registry'][session_id()];
        $section = explode('/', trim($q, ' /'));

        if(count($section)>1)
        {

            $lastRound = count($section)-1;

            foreach($section as $k => $s)
            {
                $branch = &$branch[$s];
                if($lastRound == $k)
                {
                    $branch = $value;
                }
            }
            unset($branch);
        }
    }

    /**
    * Read whole registry, use for development
    * @param void
    * @return array
    */
    public static function read()
    {
        return $_SESSION['__lethe_registry'][session_id()];
    }

    /**
    * Reset registry, recreate core defaults, use for development
    * @param void
    * @return array
    */
    public static function reset()
    {
        unset($_SESSION['__lethe_registry'][session_id()]);
        self::init();
        return self::read();
    }

    /**
    * Initialize registry, core values, readonly system variables
    * @param void
    * @return void
    */
    private static function release()
    {
        // Read only
        $config = []; //$_SESSION['__lethe_registry'][session_id()];
        $valid = ['user', 'store', 'system'];

        foreach($valid as $v)
        {
            if(!Tools::chef($_SESSION['__lethe_registry'][session_id()], $v) || !is_array($_SESSION['__lethe_registry'][session_id()]))
            {
                $config[$v] = [];
            }
        }

        // INIT defaults
        $config['system']['uid'] = session_id();

        $_SESSION['__lethe_registry'][session_id()] = array_merge($_SESSION['__lethe_registry'][session_id()], $config);
    }
}
?>
