<?php
namespace Lethe;

/**
* Lethe\FtpClient - FTP client used to download/upload/manage files on remote FTP server
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @todo non-blocking support
* @todo listing directory (iterations)
* @todo remote file/directory exists
* @todo stream transport (fput, fget)
* @todo non blocking transport (exec, raw)
* @todo execute remote command API (exec, raw)
*/
class FtpClient extends Lethe
{
    private $connection,
            $mode;

    public  $user,
            $password,
            $host,
            $port,
            $passive,
            $ssl,
            $timeout,
            $remoteDir,
            $status,
            $binary;

    /**
    * Grab connection settings
    * @param string $host
    * @param string $user
    * @param string $password
    * @return void
    */
    public function __construct($host, $user = null, $password = null)
    {
        parent::__construct();
        $this->host = $host;
        $this->port = 21;
        $this->passive = true;
        $this->ssl = false;
        $this->binary = true;
        $this->timeout = 120;
        $this->user = $user;
        $this->password = $password;
        $this->connection = false;
        $this->status = false;
    }

    /**
    * Establish FTP connection
    * @param void
    * @return bool
    */
    public function connect()
    {
        $cf = $this->ssl === true ? 'ftp_ssl_connect': 'ftp_connect';
        $this->connection = @$cf($this->host, $this->port, $this->timeout);

        if($this->connection !== false)
        {

            $this->debugSet('400','Connection ok');
            $login = @ftp_login($this->connection, $this->user, $this->password);

            if($login === true)
            {
                $this->debugSet('401', 'Login ok');

                if($this->passive === true)
                {
                    $pasv = ftp_pasv($this->connection, true);

                    if($login === true)
                    {
                        $this->debugSet('402', 'Passive mode ok');
                    }else{
                        $this->debugSet('1402', 'Passive mode failed');
                    }
                }

                $this->status = true;

            }else{
                $this->debugSet('1401', 'FTP Login failed');
                ftp_close($this->connection);
                $this->connection = false;
            }
        }else{
            $this->debugSet('1400', 'FTP Connection failed');
        }

        return $this->status;
    }

    /**
    * Change active directory on FTP server
    * @param string $dir
    * @return bool
    */
    public function chdir($dir)
    {
        $action = @ftp_chdir($this->connection, $dir);

        if($action === true)
        {
            $this->debugSet('403', 'Chdir ok ('.$dir.')');
        }else{
            $this->debugSet('1403', 'Chdir failed ('.$dir.')');
        }

        return $action;
    }

    /**
    * Go to the parent directory on FTP server
    * @param void
    * @return bool
    */
    public function cdup()
    {
        $action = @ftp_cdup($this->connection);

        if($action === true)
        {
            $this->debugSet('404', 'Cdup ok');
        }else{
            $this->debugSet('1404', 'Cdup failed');
        }

        return $action;
    }

    /**
    * Make new directory on remote FTP server
    * @param string $dir
    * @return string|bool
    */
    public function mkdir($dir)
    {
        $action = @ftp_mkdir($this->connection, $dir);

        if($action !== false){
            $this->debugSet('405', 'Mkdir ok ('.$dir.')');
        }else{
            $this->debugSet('1405', 'Mkdir failed ('.$dir.')');
        }

        return $action;
    }

    /**
    * Identify active directory on remote FTP server
    * @param void
    * @return string|bool
    */
    public function pwd()
    {
        $action = @ftp_pwd($this->connection);

        if($action !== false)
        {
            $this->debugSet('406', 'Pwd ok ('.$action.')');
        }else{
            $this->debugSet('1406', 'Pwd failed');
        }

        return $action;
    }

    /**
    * Identify remote FTP server
    * @param void
    * @return string|bool
    */
    public function sysType()
    {
        $action = @ftp_systype($this->connection);

        if($action !== false)
        {
            $this->debugSet('407', 'SysType ok ('.$action.')');
        }else{
            $this->debugSet('407', 'SysType failed');
        }

        return $action;
    }

    /**
    * Check directory on remote FTP server
    * @param string $dir
    * @return bool
    */
    public function isDir($dir)
    {
        $action = @ftp_chdir($this->connection, $dir);

        if($action !== false)
        {
            $this->debugSet('408', 'Dir exists ('.$dir.')');
             @ftp_cdup($this->connection);
        }else{
            $this->debugSet('1408', 'Dir doesn\'t exist ('.$dir.')');
        }

        return $action;
    }

    /**
    * Upload file from ($from) to the FTP server directory ($to)
    * @param string $from
    * @param string $to
    * @return bool
    */
    public function putFile($from, $to)
    {
        $mode = $this->binary === true ? FTP_BINARY: FTP_ASCII;
        $action = @ftp_put($this->connection, $to, $from, $mode);

        if($action === true)
        {
            $this->debugSet('409', 'File upload ok ('.$from.' -> '.$to.')');
        }else{
            $this->debugSet('1409', 'File upload failed ('.$from.' -> '.$to.')');
        }

        return $action;
    }

    /**
    * Download file from FTP server ($from) to local directory ($to)
    * @param string $from
    * @param string $to
    * @return bool
    */
    public function getFile($from, $to)
    {
        $mode = $this->binary === true ? FTP_BINARY: FTP_ASCII;
        $action = @ftp_get($this->connection, $to, $from, $mode);

        if($action === true)
        {
            $this->debugSet('410', 'File download ok ('.$from.' -> '.$to.')');
        }else{
            $this->debugSet('1410', 'File download failed ('.$from.' -> '.$to.')');
        }

        return $action;
    }

    /**
    * Upload file from ($from) to the FTP server directory ($to)
    * Alias for FtpClient::putFile()
    * @param string $from
    * @param string $to
    * @return bool
    */
    public function upload($from, $to)
    {
        return $this->putFile($from, $to);
    }

    /**
    * Download file from FTP server ($from) to local directory ($to)
    * Alias for FtpClient::getFile()
    * @param string $from
    * @param string $to
    * @return bool
    */
    public function download($from, $to)
    {
        return $this->getFile($from, $to);
    }

    /**
    * Close FTP connection
    */
    public function close()
    {
        ftp_close($this->connection);
        $this->connection = false;
    }

    /**
    * Close FTP connection
    * Alias of FtpClient::close()
    */
    public function quit()
    {
        $this->close();
    }
}
