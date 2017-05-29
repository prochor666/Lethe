<?phpnamespace Lethe;

/*** Lethe\Mem - Lethe Memcache/Memcached wrapper
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
*/
class Mem extends Lethe
{
    protected $connection, $message, $server, $port, $driver;

    /**
    * Memcache wrapper constructor, configured by global config
    * @param void
    * @return void
    */
    public function __construct()
    {
        parent::__construct();
        $this->server = $this->config('system/memcacheServer');
        $this->port = $this->config('system/memcachePort');
        $this->driver = $this->config('system/memcacheDriver');
        $this->class = '\\'.$this->driver;
        $this->connection = false;
        $this->message = null;

        if(class_exists($this->class))
        {
            $this->connection = new $this->class();
            $a = $this->connection->addServer($this->server, $this->port);

            if($this->connection === false || $a == false)
            {
                $this->message = $this->driver.' connection failed';
            }else{
                $this->message = $this->driver.' connection ok';
            }
        }else{
            $this->message = $this->driver.' class is not installed';
        }
    }

    /**
    * Memcached test connection
    * @param void
    * @return string
    */
    public function test()
    {
        return $this->message;
    }

    /**
    * Memcached server status
    * @param void
    * @return string
    */
    public function status()
    {
        return $this->connection->getStats();
    }

    /**
    * Store data in Memcached
    * @param string $key
    * @param mixed $value
    * @param int $expiration
    * @return bool
    */
    public function store($key, $value, $expiration = 0)
    {
        if($this->connection !== false && $this->driver == 'Memcache')
        {
            return $this->connection->set($key, $value, MEMCACHE_COMPRESSED, $expiration);

        }elseif($this->connection !== false && $this->driver == 'Memcached')
        {
            return $this->connection->set($key, $value, $expiration);
        }

        return false;
    }


    /**
    * Get data from Memcached
    * @param string $key
    * @return mixed
    */
    public function get($key)
    {
        if($this->connection !== false)
        {
            return $this->connection->get($key);
        }

        return false;
    }
}
