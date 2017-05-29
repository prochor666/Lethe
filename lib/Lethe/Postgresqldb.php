<?phpnamespace Lethe;

/**
* Lethe\Postgresqldb - PostgreSQL database manipulation class
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
*/
class Postgresqldb extends Lethe
{
    private $host, $db, $user, $password, $engine, $errors, $stat;

   /**
    * Construcor, configures PostgreSQL driver
    * @param array $conf
    * @return void
    */
    public function __construct($conf = null)
    {
        $this->host =       null;
        $this->user =       null;
        $this->password =   null;
        $this->db =         null;
        $this->port =       5432;
        $this->errors =     [];
        $this->stat =       false;

        if($conf != null && is_array($conf) && count($conf)>3 && isset( $conf['host'], $conf['db'], $conf['user'], $conf['password'] ) )
        {
            $this->host =       $conf['host'];
            $this->user =       $conf['user'];
            $this->password =   $conf['password'];
            $this->db =         $conf['db'];
            $this->port =       isset($conf['port']) ? $conf['port']: $this->port;
            $this->stat =       true;
        }else{
            $this->error('PostgreSQL configuration error');
        }
    }

    private function error($e)
    {
        $this->errors[] =   $e;
        $this->log( ['case' => 'pgsql', 'message' => $e] );
        $this->stat =       false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
    * Database connection
    * @param array $conf
    * @return bool|resource
    */
    private function connect()
    {
        $pg_conn_str = 'host='.$this->host.' port='.$this->port.' dbname='.$this->db.' user='.$this->user.' password='.$this->pass;
        $link = pg_connect($pg_conn_str);
        return  $link;
    }

    /**
    * Database connection test
    * @param array $conf
    * @return bool|resource
    */
    public function testConnection()
    {
        return $this->connect();
    }

    /**    * Database query INSERT/UPDATE?DELETE etc..
    * @param string $query
    * @return bool|resource
    */
    public function query($sqlquery)
    {
        $link = $this->connect();

        $result = !$link ? false: pg_query($link, $sqlquery);
        if($result === false && $link !== false)
        {
            $this->error('PostgreSQL query error: '.pg_last_notice($link).' Query: '.$sqlquery);
        }elseif($link !== false)
        {
            pg_close($link);
        }

        return $result === false ? $this->getErrors(): $result;
    }

    /**    * Database query result
    * @param string $query
    * @param string $type
    * @return array|object
    */
    public function result($sqlquery, $type = 'assoc')
    {
        $result = $this->query($sqlquery);
        $data = [];

        if($this->stat === true && pg_num_rows($result)>0)
        {
            switch($type)
            {
                case "array":
                    while($row = pg_fetch_array($result))
                    {
                        array_push($data, $row);
                    }
                break; case "row":
                    while($row = pg_fetch_row($result))
                    {
                        array_push($data, $row);
                    }
                break; case 'object':
                    $data = pg_fetch_object($result);
                break; case "assoc": default:
                    while($row = pg_fetch_assoc($result))
                    {
                        array_push($data, $row);
                    }
            }
            pg_free_result($result);
        }

        return $data;
    }

    /**    * Get last serial value
    * @param string $table
    * @return int
    */
    public function getLastId($table)
    {
        $link = $this->connect();
        /* Auto field[0]  */
        $sql = "SELECT * FROM " . $table;
        $ret = pg_query($link, $sql);
        $idseq = pg_field_name($ret, 0);
        // Execute last item query
        $result = $this->result("SELECT MAX(".$idseq.") FROM ".$table."", 'row');

        if(count($result)>0)
        {
            return $result[0];
        } else {
            // Case error
            return 0;
        }
    }

    /**
    * Escape data for safety
    * @param string $data
    * @return string
    */
    public function sanitize($data){
        $link = $this->connect();
        $result = !$link ? false: pg_escape_string($link, $data);
        if($result === false)
        {
            $this->error('PostgreSQL error: '.pg_last_notice($link));
        }else{
            pg_close($link);
        }
        return $result;
    }
}
