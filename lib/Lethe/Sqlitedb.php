<?php
namespace Lethe;

/**
* Lethe\SqliteDb - Sqlite3 database manipulation class
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.3
*/
class Sqlitedb extends Lethe
{
    private $host, $db, $user, $password, $engine, $errors, $stat;

    /**
    * Construcor, configures Sqlite driver
    * @param array $conf
    * @return void
    */
    public function __construct($conf = null)
    {

        $this->db =         'Lethe.db';
        $this->errors =     array();
        $this->stat =       false;

        if($conf != null && is_array($conf) && count($conf)>1 && isset( $conf['db'] ) )
        {
            $this->db =     $conf['db'];
            $this->stat =       true;
        }else{
            $this->error('Sqlite configuration error');
        }
    }

    private function error($e)
    {
        $this->errors[] =   $e;
        $this->log( array('case' => 'sqlite', 'message' => $e) );
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
        try {
            
            $link = new \Sqlite3(__LETHE_ROOT__.'/'.$this->db);
            $this->stat = true;
        } catch (Exception $e) 
        {
            $link = false;
            $this->error('Sqlite connection error: '.$e->getMessage());
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

        try {
            
            $result = $link->exec($sqlquery);
            $link->close();
            
        } catch (Exception $e) 
        {
            $result = false;
            $this->error('Sqlite query error: '.$e->getMessage().' Query: '.$sqlquery);
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
        $data = array();
        $link = $this->connect();

        try{
            
            $result = $link->query($sqlquery);

            if($this->stat === true && $result!==false)
            {
                switch($type)
                {
                    case "array":
                        
                        while($row = $result->fetchArray(SQLITE3_BOTH))
                        {
                            $data[] = $row;
                        }                        
                    break; case "row":
                        
                        while($row = $result->fetchArray(SQLITE3_NUM))
                        {
                            $data[] = $row;
                        }
                    break; case 'object':
                        
                        $data = false;
                    break; case "assoc": default:

                        while($row = $result->fetchArray(SQLITE3_ASSOC))
                        {
                            $data[] = $row;
                        }
                }

                $result->finalize();
                $link->close();
            }

        } catch (Exception $e) 
        {
            $result = false;
            $this->error('Sqlite query error: '.$e->getMessage().' Query: '.$sqlquery);
        }

        return $data;
    }

    /**
    * Get last autoincrement value
    *
    * @param string $table
    * @return int
    */
    public function getLastId()
    {
        $link = $this->connect();
        return $link->lastInsertRowID(); 
    }

    /**
    * Escape data for safety
    *
    * @param string $data
    * @return string
    */
    public function sanitize($data)
    {
        $link = $this->connect();
        $result = !$link ? false: $link->escapeString($data);
        if($result === false)
        {
            $this->error('Sqlite error: '.$link->lastErrorMsg());
        }
        
        return $result;
    }

}
?>

