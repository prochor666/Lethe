<?php

namespace Lethe;

/**
 * Lethe\MysqlDb - mysql database manipulation class
 * @author Jan Prochazka, prochor666 <prochor666@gmail.com>
 */
class Mysqldb extends Lethe
{
    private $host, $db, $user, $password, $engine, $driver, $port, $errors, $stat, $persistent, $linkKey;
    private static $persistentLinks = [];

    /**
     * Construcor, configures MySQL driver
     * @param array $conf
     * @return void
     */
    public function __construct($conf = null)
    {

        if (
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
        ) {
            $this->driver = 'ext/mysqli';
        } else {
            $this->driver = 'ext/mysql';
        }

        $this->host =       null;
        $this->user =       null;
        $this->password =   null;
        $this->db =         null;
        $this->port =       3306;
        $this->errors =     [];
        $this->stat =       false;
        $this->persistent = false;

        if ($conf != null && is_array($conf) && count($conf) > 3 && isset($conf['host'], $conf['db'], $conf['user'], $conf['password'])) {
            $this->host =       $conf['host'];
            $this->user =       $conf['user'];
            $this->password =   $conf['password'];
            $this->db =         $conf['db'];
            $this->port =       isset($conf['port']) ? $conf['port'] : $this->port;
            $this->persistent = isset($conf['persistent']) ? (bool)$conf['persistent'] : false;
            $this->stat =       true;
        } else {
            $this->error('MySQL configuration error');
        }
    }

    private function error($e)
    {
        $this->errors[] =   $e;
        $this->log(['case' => 'mysql', 'message' => $e]);
        $this->stat =       false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function withMysqliReportDisabled($operation)
    {
        $previousMode = (new \mysqli_driver())->report_mode;
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            return $operation();
        } finally {
            mysqli_report($previousMode);
        }
    }

    private function closeMysqliLink($link, $invalidate = false)
    {
        if ($invalidate) {
            unset(self::$persistentLinks[$this->linkKey]);
        }
        if ($this->persistent !== true || $invalidate) {
            mysqli_close($link);
        }
    }

    /**
     * Database connection
     * @param array $conf
     * @return bool|resource
     */
    private function connect()
    {
        if ($this->host === null || $this->db === null || $this->user === null || $this->password === null) {
            return false;
        }

        if ($this->driver == 'ext/mysqli') {
            return $this->withMysqliReportDisabled(function () {
                $host = $this->host;
                if ($this->persistent === true && strpos($host, 'p:') !== 0) {
                    $host = 'p:' . $host;
                }
                $this->linkKey = hash('sha256', serialize([$host, (int)$this->port, $this->db, $this->user, $this->password]));

                if ($this->persistent === true && isset(self::$persistentLinks[$this->linkKey])) {
                    $link = self::$persistentLinks[$this->linkKey];
                    try {
                        if (@mysqli_thread_id($link) > 0) {
                            $this->stat = true;
                            return $link;
                        }
                    } catch (\Throwable $error) {
                        // PHP 8 throws for a closed handle; PHP 7 returns false/null.
                    }
                    unset(self::$persistentLinks[$this->linkKey]);
                }

                $link = @mysqli_connect($host, $this->user, $this->password, $this->db, (int)$this->port);
                if ($link === false) {
                    $this->error('MySQL connection error: ' . mysqli_connect_error());
                    return false;
                }
                if (!mysqli_set_charset($link, "utf8")) {
                    $this->error('MySQL charset error: ' . mysqli_error($link));
                    $this->closeMysqliLink($link, true);
                    return false;
                }
                $this->stat = true;
                if ($this->persistent === true) {
                    self::$persistentLinks[$this->linkKey] = $link;
                }
                return $link;
            });
        } else {
            if (!function_exists('mysql_connect')) {
                $this->error('MySQL extension is not available');
                return false;
            }
            $link = $this->persistent === true ? @mysql_pconnect($this->host, $this->user, $this->password) : @mysql_connect($this->host, $this->user, $this->password);
            if ($link !== false) {
                $db = @mysql_select_db($this->db, $link);
                mysql_query("SET NAMES 'utf8'");
            } else {
                if ($link === false) {
                    $this->error('MySQL connection error: ' . mysql_error());
                }
            }
        }

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

    /**
     * Database query INSERT/UPDATE?DELETE etc..
     * @param string $query
     * @return bool|resource
     */
    public function query($sqlquery)
    {
        $link = $this->connect();
        if ($link === false) {
            return $this->getErrors();
        }

        if ($this->driver == 'ext/mysqli') {
            $result = $this->withMysqliReportDisabled(function () use ($link, $sqlquery) {
                $invalidate = false;
                try {
                    $result = @mysqli_query($link, $sqlquery);
                    if ($result === false) {
                        $invalidate = in_array(mysqli_errno($link), [2006, 2013, 2055], true);
                        $this->error('MySQL query error: ' . mysqli_error($link) . ' Query: ' . $sqlquery);
                    }
                    return $result;
                } finally {
                    $this->closeMysqliLink($link, $invalidate);
                }
            });
        } else {
            $result = !mysql_error() ? mysql_query(trim($sqlquery), $link) : false;

            if ($result === false) {
                $this->error('MySQL query error: ' . mysql_error() . ' Query: ' . $sqlquery);
            } else {
                mysql_close($link);
            }
        }

        return $result === false ? $this->getErrors() : $result;
    }

    /**
     * Database query result
     * @param string $query
     * @param string $type
     * @return array|object
     */
    public function result($sqlquery, $type = 'assoc')
    {
        $result = $this->query($sqlquery);
        $data = [];

        if ($this->driver == 'ext/mysqli') {
            if ($result instanceof \mysqli_result) {
                try {
                    if (mysqli_num_rows($result) > 0) {
                        switch ($type) {
                            case "array":
                                while ($row = mysqli_fetch_array($result)) {
                                    array_push($data, $row);
                                }
                                break;
                            case "row":
                                while ($row = mysqli_fetch_row($result)) {
                                    array_push($data, $row);
                                }
                                break;
                            case 'object':
                                $data = mysqli_fetch_object($result);
                                break;
                            case "assoc":
                            default:
                                while ($row = mysqli_fetch_assoc($result)) {
                                    array_push($data, $row);
                                }
                        }
                    }
                } finally {
                    mysqli_free_result($result);
                }
            }
        } else {
            if ($this->stat === true && mysql_num_rows($result) > 0) {
                switch ($type) {
                    case "array":
                        while ($row = mysql_fetch_array($result)) {
                            array_push($data, $row);
                        }
                        break;
                    case "row":
                        while ($row = mysql_fetch_row($result)) {
                            array_push($data, $row);
                        }
                        break;
                    case 'object':
                        $data =  mysql_fetch_object($result);

                        break;
                    case "assoc":
                    default:
                        while ($row = mysql_fetch_assoc($result)) {
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
     * @param string $table
     * @return int
     */
    public function getLastId($table)
    {
        $result = $this->result("SHOW TABLE STATUS LIKE '" . $table . "'", 'assoc');
        $result2 = $this->result("SHOW VARIABLES LIKE 'auto_increment_offset'");
        $offset = $result2 !== false ? $result2[0]['Value'] : 1;

        return $result !== false && count($result) === 1 ? (int)$result[0]['Auto_increment'] - $offset : 0;
    }

    /**
     * Escape data for safety
     * @param string $data
     * @return string
     */
    public function sanitize($data)
    {
        $link = $this->connect();
        if ($link === false) {
            return false;
        }
        if ($this->driver == 'ext/mysqli') {
            try {
                $result = mysqli_real_escape_string($link, $data);
                if ($result === false) {
                    $this->error('MySQL error: ' . mysqli_connect_error());
                }
            } finally {
                $this->closeMysqliLink($link);
            }
        } else {
            @mysql_set_charset('utf8');
            $result = !$link ? mysql_real_escape_string($data, $link) : false;
            if ($result === false) {
                $this->error('MySQL error: ' . mysql_error());
            } else {
                mysql_close($link);
            }
        }

        return $result;
    }

    /**
     * Get server version
     * @param void
     * @return string
     */
    public function version()
    {
        $link = $this->connect();
        if ($link === false) {
            return '';
        }
        if ($this->driver == 'ext/mysqli') {
            try {
                $result = mysqli_get_server_info($link);
                if ($result === false) {
                    $this->error('MySQL error: ' . mysqli_connect_error());
                }
            } finally {
                $this->closeMysqliLink($link);
            }
        } else {
            @mysql_set_charset('utf8');
            $result = !$link ? mysql_get_server_info($link) : false;
            if ($result === false) {
                $this->error('MySQL error: ' . mysql_error());
            } else {
                mysql_close($link);
            }
        }

        return $result !== false ? (string)$result : '';
    }
}
