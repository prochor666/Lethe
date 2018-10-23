<?php
namespace Lethe;

/**
* Lethe\Tools - Lethe tools/generators
* @author Jan Prochazka, prochor666 <prochor666@gmail.com>
*/
class Tools
{
    /**
    * @ignore
    */
    final public function __construct(){}

    /**
    * Random generator
    * @param int $length
    * @param bool $numOnly
    * @return int|string
    */
    public static function rnd($length = 5, $numOnly = false)
    {
        $args = $numOnly === true ? '0123456789': 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $str = null;
        while(strlen($str) < $length)
        {
            $str .= mb_substr($args, mt_rand(0, strlen($args) - 1), 1);
        }

        return (string)$str;
    }

    /**
    * Hash wrapper
    * @param string $str
    * @param string $algo
    * @return string
    */
    public static function hash($str, $algo = 'sha512')
    {
        $algo = mb_strtolower($algo);

        // DES std, salted
        if (CRYPT_STD_DES == 1 && $algo == 'stddes')
        {
            return crypt($str, self::rnd(2));
        }

        // DES extended, salted
        if (CRYPT_EXT_DES == 1 && $algo == 'extdes')
        {
            return crypt($str, '_'.self::rnd(8));
        }

        // Blowfish salted
        if (CRYPT_BLOWFISH == 1 && $algo == 'blowfish')
        {
            return crypt($str, '$2y$11$'.self::rnd(22).'$');
        }

        // SHA-512 salted
        if (CRYPT_SHA512 == 1 && $algo == 'sha512salt')
        {
            return crypt($str, '$6$rounds=5000$'.self::rnd(16).'$');
        }

        // SHA-256 salted
        if (CRYPT_SHA256 == 1 && $algo == 'sha256salt')
        {
            return crypt($str, '$5$rounds=5000$'.self::rnd(16).'$');
        }

        // SHA-512
        if (function_exists('hash') && in_array( 'sha512', hash_algos() ) && $algo == 'sha512' )
        {
            return hash('sha512', $str);
        }

        // SHA-384
        if (function_exists('hash') && in_array( 'sha384', hash_algos() ) && $algo == 'sha384' )
        {
            return hash('sha384', $str);
        }

        // SHA-256
        if (function_exists('hash') && in_array( 'sha256', hash_algos() ) && $algo == 'sha256' )
        {
            return hash('sha256', $str);
        }

        // SHA-1
        if (function_exists('sha1') && $algo == 'sha1')
        {
            return sha1($str);
        }

        // CRC32
        if (function_exists('hash') && in_array( 'crc32', hash_algos() ) && $algo == 'crc32' )
        {
            return hash('crc32', $str);
        }

        return md5($str);
    }

    /**
    * Validate str vs hash
    * @param string $str
    * @param string $hash
    * @return string
    */
    public static function validate($str, $hash)
    {
        return crypt($str, $hash) == $hash;
    }

    /**
    * Dump
    * @return string
    */
    public static function dump()
    {
        $n = func_num_args();
        $a = func_get_args();
        ob_start();

        if($n>0)
        {
            foreach($a as $var)
            {
                if(PHP_SAPI !== 'cli')
                {
                    echo '<pre>';
                }
                var_dump($var);

                if(PHP_SAPI !== 'cli')
                {
                    echo '</pre>';
                }
            }
        }else{
            echo PHP_SAPI === 'cli' ? 'DUMP: no-data': '<pre>DUMP: no-data</pre>';
        }

        $result = ob_get_clean();

        return $result;
    }

    /**
    * Die dump
    * @return void
    */
    public static function dd()
    {
        die(self::dump(func_get_args()));
    }

    /**
    * Slice big array
    * @param array $data
    * @param int $from
    * @param int $to
    * @return array
    */
    public static function slice($data=[], $from = 0, $to = 0)
    {
        $newDataset = [];

        if(is_array($data) && count($data)>0 && $to > 0)
        {
            $newDataset = array_slice($data, $from, $to);
            unset($data);
        }

        return $newDataset;
    }

    /**
    * Sort single array by length
    * @param array $data
    * @return array
    */
    public static function sortByLength($data)
    {
        usort($data, function($a, $b)
        {
            return mb_strlen($b) - mb_strlen($a);
        });

        return $data;
    }

    /**
    * Tests if string starts with another string
    * @param string $path
    * @param string|array $needle
    * @return bool|string
    */
    public static function startsWith($str = NULL, $needle = [])
    {
        if(mb_strlen($str) == 0)
        {
            return false;
        }

        if(!is_array($needle))
        {
            $needle = [$needle];
        }
        $needle = self::sortByLength($needle);

        foreach($needle as $s)
        {
            if( mb_strlen($s)>0 && strpos($str, $s) === 0 )
            {
                return $s;
            }
        }

        return false;
    }

    /**
    * Tests if string ends with another string
    * @param string $path
    * @param string|array $needle
    * @return bool|string
    */
    public static function endsWith($str = NULL, $needle = [])
    {
        if(mb_strlen($str) == 0)
        {
            return false;
        }

        if(!is_array($needle))
        {
            $needle = [$needle];
        }
        $needle = self::sortByLength($needle);

        foreach($needle as $s)
        {
            $l = mb_strlen($s);
            if( mb_strlen($s) > 0 && mb_substr($str, -mb_strlen($s)) == $s )
            {
                return $s;
            }
        }

        return false;
    }

    /**
    * array check helper
    * @param array $a
    * @param string $k as key value
    * @param mixed $d default value
    * @return mixed
    */
    public static function chef($a, $k, $d = false)
    {
        return is_array($a) && array_key_exists($k, $a) ? $a[$k]: $d;
    }

    /**
    * Domain string detect
    * @return string
    */
    public static function detectDomain()
    {
        $domain = 'www';
        if(array_key_exists('SERVER_NAME', $_SERVER))
        {
            $strs = explode('.', $_SERVER['SERVER_NAME']);
            if(count($strs) == 3)
            {
                $domain = $strs[0];
            }
        }

        return $domain;
    }

    /**
    * Web redirect
    * @param string $url
    * @return void
    */
    public static function redirect($url = null)
    {
        $url = !is_null($url) && !is_array($url) && mb_strlen($url)>0 ? $url: false;
        if( $url !== false )
        {
            header("Location:".$url);
            exit();
        }
    }

    /**
    * Http/s GET and POST request
    * @param string $url
    * @param array $config
    * @return void
    */
    public static function request($url = null, $config = [])
    {
        $options = [
            CURLOPT_RETURNTRANSFER => self::chef($config, 'CURLOPT_RETURNTRANSFER', true),                 // return web page
            CURLOPT_HEADER         => self::chef($config, 'CURLOPT_HEADER', false),                        // don't return headers
            CURLOPT_FOLLOWLOCATION => self::chef($config, 'CURLOPT_FOLLOWLOCATION', true),                 // follow redirects
            CURLOPT_ENCODING       => self::chef($config, 'CURLOPT_ENCODING', ''),                         // handle all encodings
            CURLOPT_USERAGENT      => self::chef($config, 'CURLOPT_USERAGENT', 'Lethe\Tools::request'),    // who am i
            CURLOPT_AUTOREFERER    => self::chef($config, 'CURLOPT_AUTOREFERER', true),                    // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => self::chef($config, 'CURLOPT_CONNECTTIMEOUT', 30),                   // timeout on connect
            CURLOPT_TIMEOUT        => self::chef($config, 'CURLOPT_TIMEOUT', 60),                          // timeout on response
            CURLOPT_MAXREDIRS      => self::chef($config, 'CURLOPT_MAXREDIRS', 10),                        // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => self::chef($config, 'CURLOPT_SSL_VERIFYPEER', false),                // Disabled SSL Cert checks
            CURLOPT_HTTPHEADER     => self::chef($config, 'CURLOPT_SSL_VERIFYPEER', false),                // HTTP headers
        ];

        // POST request
        if (self::chef($config, 'CURLOPT_POST', 0)==1) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = is_array(self::chef($config, 'CURLOPT_POSTFIELDS', '')) ? http_build_query($config['CURLOPT_POSTFIELDS']): $config['CURLOPT_POSTFIELDS'];
        }

        $ci      = curl_init( $url );
        curl_setopt_array( $ci, $options );
        $content = curl_exec( $ci );
        $err     = curl_errno( $ci );
        $errmsg  = curl_error( $ci );
        $result  = curl_getinfo( $ci );
        curl_close( $ci );

        $result['errno']   = $err;
        $result['errmsg']  = $errmsg;
        $result['content'] = $content;

        return $result;
    }

    /**
    * Sned custom HTTP status code
    * @param string $headerStatus
    */
    public static function headerStatus($headerStatus, $replace = false, $code = 0)
    {
        if($code > 0)
        {
            header($headerStatus, $replace, $code);
        }else{
            header($headerStatus, $replace);
        }
    }

    /**
    * Get client IPv4/IPv6 address (proxies enabled)
    * @return string
    */
    public static function clientIp()
    {
        $clientVars = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach($clientVars as $key)
        {
            if(array_key_exists($key, $_SERVER) === true)
            {
                foreach(explode(',', $_SERVER[$key]) as $ip)
                {
                    if(filter_var($ip, FILTER_VALIDATE_IP) !== false)
                    {
                        return $ip;
                    }
                }
            }
        }
    }

    /**
    * Detect SSL (proxies enabled)
    * @return bool
    */
    public static function ssl()
    {
        return isset($_SERVER['HTTP_X_FORWARDED_SSL']) || isset($_SERVER['HTTPS']);
    }
}
