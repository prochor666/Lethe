<?php
namespace Lethe;

/**
* Lethe\MysqlDb - mysql database manipulation class
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.3
*/
class Mysqldb extends Lethe
{
    private $host, $db, $user, $password, $engine, $errors, $stat;

    /**
    * Construcor, configures MySQL driver
    * @param array $conf
    * @return void
    */
    public function __construct($conf = null)
    {

        if(
            function_exists('mysqli_init') &&
            function_exists('mysqli_connect') &&
            function_exists('mysqli_select_db') &&
            function_exists('mysqli_set_charset') &&
            function_exists('mysqli_connect_error') &&
            function_exists('mysqli_fetch_array') &&
            function_exists('mysqli_fetch_assoc') &&
            function_exists('mysqli_fetch_row') &&
            function_exists('mysqli_fetch_object') &&
            function_exists('mysqli_real_escape_string')
        )
        {
            $this->driver = 'ext/mysqli';
        }else{
            $this->driver = 'ext/mysql';
        }

        $this->host =       null;
        $this->user =       null;
        $this->password =   null;
        $this->db =         null;
        $this->port =       3306;
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
            $this->error('MySQL configuration error');
        }
    }

    private function error($e)
    {
        $this->errors[] =   $e;
        $this->log( ['case' => 'mysql', 'message' => $e] );
        $this->stat =       false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    /**
    * Database connection
    *
    * @param array $conf
    * @return bool|resource
    */
    private function connect()
    {

        if($this->driver == 'ext/mysqli')
        {

            $link = mysqli_init();
            if (!$link)
            {
                $link = false;
            }else{
                $link = @mysqli_connect($this->host, $this->user, $this->password);
                if($link !== false)
                {
                    $db = @mysqli_select_db($link, $this->db);
                    mysqli_set_charset($link, "utf8");
                }
            }

            if($link === false)
            {
                $this->error('MySQL connection error: '.mysqli_connect_error());
            }
        }else{
            $link = @mysql_connect($this->host, $this->user, $this->password);
            if($link !== false)
            {
                $db = @mysql_select_db($this->db,$link);
                mysql_query("SET NAMES 'utf8'");
            }else{
                if($link === false)
                {
                    $this->error('MySQL connection error: '.mysql_error());
                }
            }
        }

        return  $link;
    }

    /**
    * Database connection test
    *
    * @param array $conf
    * @return bool|resource
    */
    public function testConnection()
    {
        return $this->connect();
    }

    /**
    * Database query INSERT/UPDATE?DELETE etc..
    *
    * @param string $query
    * @return bool|resource
    */
    public function query($sqlquery)
    {
        $link = $this->connect();

        if($this->driver == 'ext/mysqli')
        {
            $result = !$link ? false: mysqli_query($link, $sqlquery);
            if($result === false && $link !== false)
            {
                $this->error('MySQL query error: '.mysqli_error($link).' Query: '.$sqlquery);
            }elseif($link !== false){
                mysqli_close($link);
            }
        }else{
            $result = !mysql_error() ? mysql_query(trim($sqlquery), $link ): false;
            if($result === false)
            {
                $this->error('MySQL query error: '.mysql_error().' Query: '.$sqlquery);
            }else{
                mysql_close($link);
            }
        }

        return $result === false ? $this->getErrors(): $result;
    }

    /**
    * Database query result
    *
    * @param string $query
    * @param string $type
    * @return array|object
    */
    public function result($sqlquery, $type = 'assoc')
    {
        $result = $this->query($sqlquery);
        $data = [];

        if($this->driver == 'ext/mysqli')
        {
            if($this->stat === true && mysqli_num_rows($result)>0)
            {
                switch($type)
                {
                    case "array":
                        while($row = mysqli_fetch_array($result))
                        {
                            array_push($data, $row);
                        }
                    break; case "row":
                        while($row = mysqli_fetch_row($result))
                        {
                            array_push($data, $row);
                        }
                    break; case 'object':
                        $data =  mysqli_fetch_object($result);

                    break; case "assoc": default:
                        while($row = mysqli_fetch_assoc($result))
                        {
                            array_push($data, $row);
                        }
                }

                mysqli_free_result($result);
            }
        }else{
            if($this->stat === true && mysql_num_rows($result)>0)
            {
                switch($type)
                {
                    case "array":
                        while($row = mysql_fetch_array($result))
                        {
                            array_push($data, $row);
                        }
                    break; case "row":
                        while($row = mysql_fetch_row($result))
                        {
                            array_push($data, $row);
                        }
                    break; case 'object':
                        $data =  mysql_fetch_object($result);

                    break; case "assoc": default:
                        while($row = mysql_fetch_assoc($result))
                        {
                            array_push($data, $row);
                        }
                }

                mysql_free_result($result);
            }
        }

        return $data;
    }

    /**
    * Get last autoincrement value
    *
    * @param string $table
    * @return int
    */
    public function getLastId($table)
    {
        $result = $this->result("SHOW TABLE STATUS LIKE '".$table."'", 'assoc');
        $result2 = $this->result("SHOW VARIABLES LIKE 'auto_increment_offset'");
        $offset = $result2 !== false ? $result2[0]['Value']: 1;

        return $result !== false && count($result) === 1 ? (int)$result[0]['Auto_increment'] - $offset: 0;
    }

    /**
    * Escape data for safety
    *
    * @param string $data
    * @return string
    */
    public function sanitize($data)
    {
        if($this->driver == 'ext/mysqli')
        {
            $link = $this->connect();
            $result = !$link ? false: mysqli_real_escape_string($link, $data);
            if($result === false)
            {
                $this->error('MySQL error: '.mysqli_connect_error());
            }
            mysqli_close($link);
        }else{
            @mysql_set_charset('utf8');
            $link = $this->connect();
            $result = !mysql_error() ? mysql_real_escape_string($data, $link): false;
            if($result === false)
            {
                $this->error('MySQL error: '.mysql_error());
            }
            mysql_close($link);
        }

        return $result;
    }

}
?>

