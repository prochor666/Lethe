<?php
namespace Lethe;

/**
* Lethe\Storage - basic filesystem operations, copy/delete/create files and directories
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
* @version 1.2
*/
class Storage
{
	/**
	* @ignore
	*/
	final public function __construct() { trigger_error('Unserializing is not allowed.', E_USER_ERROR); }

	/**
	* @ignore
	*/
	final public function __clone() { trigger_error('Clone is not allowed.', E_USER_ERROR); }


	/*
	* *******************
	* Support
	* *******************
	*/

	/**
	* Open base dir effect, can we read?
	* @param string $path
	* @return bool
	*/
	public static function canRead($path)
	{
		return @is_readable($path);
	}

	/**
	* Open base dir effect, can we write?
	*
	* @param string $path
	* @return bool
	*/
	public static function canWrite($path)
	{
		return @is_readable($path) && @is_writable($path);
	}

	/*
	* *******************
	* Files
	* *******************
	*/

	/**
	* Copy file
	* @param string $pathFrom
	* @param string $pathTo
	* @return bool
	*/
	public static function copyFile($pathFrom, $pathTo)
	{
		$status = false;
		if(self::canRead($pathFrom) && self::canWrite(dirname($pathTo)) && self::isFile($pathFrom))
		{
			if(function_exists('copy'))
			{
				$status = copy($pathFrom, $pathTo);
			}else{
				$emz = file_get_contents($pathFrom);
				$status = file_put_contents($pathTo, $emz);
			}
			umask(0000);
			chmod($pathTo, Config::query('system/filePermission'));
		}
		return (bool)$status;
	}

	/**
	* Rename/move file
	* @param string $pathFrom
	* @param string $pathTo
	* @return bool
	*/
	public static function moveFile($pathFrom, $pathTo)
	{
		if(self::canRead($pathFrom) && self::canWrite(dirname($pathTo)) && self::isFile($pathFrom))
		{
			return rename($pathFrom, $pathTo);
		}
		return false;
	}

	/**
	* Delete file
	* @param string $path
	* @return bool
	*/
	public static function deleteFile($path)
	{
		if( self::canWrite($path) && self::isFile($path) )
		{
			return unlink($path);
		}
		return false;
	}

	/**
	* Read file content
	* @param string $path
	* @return string|bool
	*/
	public static function getFileData($path = null)
	{
		return self::canRead($path) && ( self::isFile($path) || self::isLink($path) ) ? file_get_contents($path): false;
	}

	/**
	* Create file with content defined in $data
	* @param string $path
	* @param string $data
	* @return int|bool
	*/
	public static function putFile($path = null, $data = null)
	{
		$res = false;
		if( self::canWrite(dirname($path)) && !self::isDir($path) )
		{
			$res = file_put_contents($path, $data);
			umask(0000);
			chmod($path, Config::query('system/filePermission'));
		}
		return $res;
	}

	/**
	* Check file
	* @param string $path
	* @return bool
	*/
	public static function isFile($path)
	{
		return self::canRead($path) &&  file_exists($path) && is_file($path) ? true: false;
	}

	/**
	* Check symlink
	* @param string $path
	* @return bool
	*/
	public static function isLink($path)
	{
		return self::canRead($path) && file_exists($path) && is_link($path) ? true: false;
	}

	/**
	* File, symlink or directory information
	* @param string $path
	* @return array|bool
	*/
	public static function fileInfo($path)
	{
		return self::canRead($path) && self::isFile($path) ? lstat($path): stat($path);
	}

	/**
	* File extension, it's not checking with file_exists!
	* @param string $path
	* @return string
	*/
	public static function extension($path)
	{
		return pathinfo($path, PATHINFO_EXTENSION);
	}

	/**
	* File extension alias of @self::extension
	* @param string $path
	* @return string
	*/
	public static function ext($path)
	{
		return self::extension($path);
	}

	/**
	* File name without extension, it's not checking with file_exists!
	* @param string $path
	* @return string
	*/
	public static function fileNameOnly($path)
	{
		if(version_compare(PHP_VERSION, '5.2.0', '>='))
		{
			return pathinfo($path, PATHINFO_FILENAME);
		}
		$f = explode('.', $path);
		$ext = array_pop($f);
		return trim(implode('.', $f));
	}


	/*
	* *******************
	* Directories
	* *******************
	*/

	/**
	* Directory test
	* @param string $path
	* @return bool
	*/
	public static function isDir($path)
	{
		return self::canRead($path) && file_exists($path) && is_dir($path) ? true: false;
	}

	/**
	* Create directory
	* @param string $path
	* @return bool
	*/
	public static function makeDir($path)
	{
		$stat = false;

		if(!self::isDir($path))
		{
			umask(0000);
			$stat = mkdir($path, Config::query('system/directoryPermission'), true);
		}

		return $stat;
	}

	/**
	* Rename/move directory
	* @param string $pathFrom
	* @param string $pathTo
	* @return bool
	*/
	public static function moveDir($pathFrom, $pathTo)
	{
		if(self::isDir($pathFrom))
		{
			return rename($pathFrom, $pathTo);
		}
		return false;
	}

	/**
	* Delete directory
	* @param string $path
	* @return bool
	*/
	public static function deleteDir($path)
	{
		if(self::isDir($path) && self::isEmptyDir($path))
		{
			return rmdir($path);
		}
		return false;
	}

	/**
	* List directory [experimental]
	* @param string $path
	* @param bool $recursive
	* @return array
	*/
	public static function listDir($path, $recursive = true)
	{
		$stat = ['dirs' => [], 'files' => []];

		if(self::isDir($path))
		{
			$dir = opendir($path);

			while(false !== ( $o = readdir($dir)))
			{
				if(( $o != '.' ) && ( $o != '..' ))
				{
					if(self::isDir($path . '/' . $o))
					{
						$stat['dirs'][$o] = [
							'path' => $path. '/' . $o,
							'info' => self::fileInfo( $path. '/' . $o )
						];

						if($recursive === true)
						{
							$stat['dirs'][$o]['subdirs'] = self::listDir( $path . '/' . $o, $recursive);
						}

					}else{
						$stat['files'][$o] = [
							'path' => $path. '/' . $o,
							'info' => self::fileInfo( $path. '/' . $o )
						];
					}
				}
			}
			closedir($dir);
		}

		ksort($stat['dirs']);
		ksort($stat['files']);

		return $stat;
	}

	/**
	* Copy directory [experimental]
	* @param string $pathFrom
	* @param string $pathTo
	* @return bool
	*/
	public static function copyDir($pathFrom, $pathTo)
	{
		$stat = self::isEmptyDir($pathFrom);

		if(self::isDir($pathFrom))
		{
			$dir = opendir($pathFrom);
			self::makeDir($pathTo);
			while(false !== ( $file = readdir($dir)) )
			{
				if (( $file != '.' ) && ( $file != '..' ))
				{
					if ( self::isDir($pathFrom . '/' . $file) )
					{
						$stat = self::copyDir($pathFrom . '/' . $file, $pathTo . '/' . $file);
					}elseif( self::isFile($pathFrom . '/' . $file) )
					{
						$stat = self::copyFile($pathFrom . '/' . $file, $pathTo . '/' . $file);
					}
				}
			}
			closedir($dir);
		}

		return $stat;
	}

	/**
	* Check directory ??????
	* @param string $path
	* @return bool
	*/
	public static function isEmptyDir($path)
	{
		$dir = $path;
		return ( self::isDir($dir) && ($files = @scandir($dir)) && count($files) <= 2);
	}

	/*
	* *******************
	* Permissions
	* *******************
	*/

	/**
	* Check permission
	* @param string $path
	* @return int|string|bool
	*/
	public static function permission($path)
	{
		return self::canRead($path) ? substr(sprintf('%o', fileperms($path)), -4): false;
	}

	/**
	* Set permission $perm is octal int (0777 NOT 777)
	* @param string $path
	* @param int $perm
	* @return bool
	*/
	public static function permissionChange($path, $perm = 0666)
	{
		$f = $path;
		if( self::canWrite($f) )
		{
			umask(0000);
			return chmod($f, $perm);
		}

		return false;
	}


	/*
	* *******************
	* Downloader
	* *******************
	*/


	/**
	* Downloads file data from specified path to local file
	* Returns -1 on fail
	* @param string $pathFrom
	* @param string $pathTo
	* @return int
	*/
	public static function downloadFile($pathFrom, $pathTo)
	{
        $chunksize = 4*(1024*1024); // 4M default

        try {
            // parse_url() parse host, path, etc.
            $parts = parse_url($pathFrom);
            $i_handle = fsockopen($parts['host'], 80, $errstr, $errcode, 5);
            $o_handle = fopen($pathTo, 'wb');

            if ($i_handle == false || $o_handle == false)
            {
                return false;
            }

            if (!empty($parts['query']))
            {
                $parts['path'] .= '?' . $parts['query'];
            }

            // Send http request to remote server
            $request = "GET {$parts['path']} HTTP/1.1\r\n";
            $request .= "Host: {$parts['host']}\r\n";
            $request .= "User-Agent: Mozilla/5.0\r\n";
            $request .= "Keep-Alive: 115\r\n";
            $request .= "Connection: keep-alive\r\n\r\n";
            fwrite($i_handle, $request);

            // Read headers from remote server
            $headers = array();
            while(!feof($i_handle))
            {
                $line = fgets($i_handle);
                if ($line == "\r\n") break;
                $headers[] = $line;
            }

            // Look for the Content-Length header, and get the size of remote file
            $length = 0;
            foreach($headers as $header)
            {
                if (stripos($header, 'Content-Length:') === 0)
                {
                    $length = (int)str_replace('Content-Length: ', '', $header);
                    break;
                }
            }

            // Read remote file, and store it to the local file one chunk at a time.
            $bytesTotal = 0;

            while(!feof($i_handle))
            {
                $buf = '';
                $buf = fread($i_handle, $chunksize);
                $bytes = fwrite($o_handle, $buf);
                if ($bytes == false)
                {
                    return false;
                }
                $bytesTotal += $bytes;

                // Reading until reach the conent length
                if($bytesTotal >= $length)
                {
                    break;
                }
            }

            fclose($i_handle);
            fclose($o_handle);

        }catch(Exception $e)
        {
            $bytesTotal = -1;
        }

        return $bytesTotal;
	}
}

