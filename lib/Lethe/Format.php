<?phpnamespace Lethe;

/*** Lethe\Format - data formatter
* @author Jan Prochazka aka prochor <prochor666@gmail.com>
*/
class Format
{
    /**
    * @ignore
    */
    final public function __construct(){}

    /**
     * Human readable bytes
     * @param int $size
     * @param int $round
     * @return string
    */
    public static function dataSize($size, $round = 2)
    {
        $round = abs((int)$round);
        $mod = 1024;
        $units = explode(' ','B KB MB GB TB PB');

        for ($i = 0; (int)$size > $mod; $i++)        {
            (int)$size /= $mod;
        }

        return round($size, $round) . ' ' . $units[$i];    }

    /**
    * Human readable xml array/object convertor
     * @param object|array $arr
     * @param boolean $cdata
     * @return string
    */
    public static function ObjXml( $arr, $cdata = false )
    {
        $xml = NULL;

        foreach( $arr as $k => $v )
        {
            $tag = trim( $k );
            $tag = is_numeric( $tag ) ? 'num_'.$tag: $tag;
            $tag = str_replace([" ", "\t"], "-", $tag);

            if( is_array( $v ) || is_object( $v ) )            {
                $xml .=  "<$tag>".self::ObjXml( $v, $cdata )."</$tag>";
            }else{
                $xml .= $cdata === true ? "<$tag><![CDATA[".$v."]]></$tag>": "<$tag>".htmlentities( $v )."</$tag>";
            }
        }

        return $xml;
    }

    /**
    * Convert string to UTF-8
    * @param string $str
    * @return string
    */
    public static function autoUTF($str)
    {
        // detect UTF-8
        if (preg_match('#[\x80-\x{1FF}\x{2000}-\x{3FFF}]#u', $str))
        {
            return $str;
        }elseif(preg_match('#[\x7F-\x9F\xBC]#', $str))
        {
            // detect WINDOWS-1250
            return iconv('WINDOWS-1250', 'UTF-8', $str);
        }
        // assume ISO-8859-2
        return iconv('ISO-8859-2', 'UTF-8', $str);
    }

    /**
    * Clear HTML & trim
    * @param string $str
    * @return string
    */
    public static function clearHtml($str)
    {
        $str = strip_tags($str);
        return trim($str);
    }

    /**
    * Cut string, reflect words delimited by space, cuts some ugly chars from the end
    * @param string $str
    * @param int $length
    * @return string
    */
    public static function cutStr($str, $length = 255, $suffix = NULL)
    {
        // printable, formal
        // $str = mb_ereg_replace( '[^[:print:]]', '', self::autoUTF( $str ) );
        $str = trim(strip_tags($str));
        $str = trim($str, ',.');

        if (mb_strlen($str)<=$length)
        {
            return $str;
        }

        $str = mb_substr($str, 0, $length);        $pos = mb_strrpos($str, " ");
        $str = $pos>1 ? mb_substr( $str, 0, $pos ): $str;
        $str = trim($str, '-, ');

        // short last word
        $wordLimit = 4;
        $words = explode( ' ', $str );
        $lastPos = count( $words ) - 1;
        $lastWordLength = mb_strlen( $words[$lastPos] );

        if( $lastWordLength < $wordLimit )
        {
            $str = mb_substr( $str, 0, -($lastWordLength+1) );
        }

        return $str.$suffix;
    }

    /**
    * Proper substr for unicode strings
    * @param string $str
    * @param string $start
    * @param array $length
    * @return string
    */
    public static function substr($str, $start, $length = null)
    {
        return join("", array_slice(
            preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY), $start, $length)
        );
    }

    /**
    * Make URL friendly string
    * @param string $str
    * @param string $delimiter
    * @return string
    */
    public static function urlSafe($str, $delimiter='-', $pathSafe=false)
    {
        // Make sure string is in UTF-8 and strip invalid UTF-8 characters
        $str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
        $char_map = array(
            // Latin
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
            'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
            'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
            'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
            'ÿ' => 'y',

            // Latin symbols
            '©' => '(c)',

            // Greek
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => '8',
            'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => '3', 'Ο' => 'O', 'Π' => 'P',
            'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'Y', 'Φ' => 'F', 'Χ' => 'X', 'Ψ' => 'PS', 'Ω' => 'W',
            'Ά' => 'A', 'Έ' => 'E', 'Ί' => 'I', 'Ό' => 'O', 'Ύ' => 'Y', 'Ή' => 'H', 'Ώ' => 'W', 'Ϊ' => 'I',
            'Ϋ' => 'Y',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => '8',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => '3', 'ο' => 'o', 'π' => 'p',
            'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'x', 'ψ' => 'ps', 'ω' => 'w',
            'ά' => 'a', 'έ' => 'e', 'ί' => 'i', 'ό' => 'o', 'ύ' => 'y', 'ή' => 'h', 'ώ' => 'w', 'ς' => 's',
            'ϊ' => 'i', 'ΰ' => 'y', 'ϋ' => 'y', 'ΐ' => 'i',

            // Turkish
            'Ş' => 'S', 'İ' => 'I', 'Ç' => 'C', 'Ü' => 'U', 'Ö' => 'O', 'Ğ' => 'G',
            'ş' => 's', 'ı' => 'i', 'ç' => 'c', 'ü' => 'u', 'ö' => 'o', 'ğ' => 'g',

            // Russian
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'J', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sh', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu',
            'Я' => 'Ya',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
            'я' => 'ya',

            // Ukrainian            'Є' => 'Ye', 'І' => 'I', 'Ї' => 'Yi', 'Ґ' => 'G',
            'є' => 'ye', 'і' => 'i', 'ї' => 'yi', 'ґ' => 'g',

            // Czech
            'Č' => 'C', 'Ď' => 'D', 'Ě' => 'E', 'Ň' => 'N', 'Ř' => 'R', 'Š' => 'S', 'Ť' => 'T', 'Ů' => 'U',
            'Ž' => 'Z',
            'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ů' => 'u',
            'ž' => 'z',

            // Polish            'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'e', 'Ł' => 'L', 'Ń' => 'N', 'Ó' => 'o', 'Ś' => 'S', 'Ź' => 'Z',
            'Ż' => 'Z',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z',

            // Latvian            'Ā' => 'A', 'Č' => 'C', 'Ē' => 'E', 'Ģ' => 'G', 'Ī' => 'i', 'Ķ' => 'k', 'Ļ' => 'L', 'Ņ' => 'N',
            'Š' => 'S', 'Ū' => 'u', 'Ž' => 'Z',
            'ā' => 'a', 'č' => 'c', 'ē' => 'e', 'ģ' => 'g', 'ī' => 'i', 'ķ' => 'k', 'ļ' => 'l', 'ņ' => 'n',
            'š' => 's', 'ū' => 'u', 'ž' => 'z'
        );

        $str = str_replace(array_keys($char_map), $char_map, $str);

        // Replace non-alphanumeric characters with our delimiter
        if( $pathSafe === true )
        {
            $str = preg_replace('/[^\/.+\p{L}\p{Nd}]+/u', $delimiter, $str);
        }else{
            $str = preg_replace('/[^\p{L}\p{Nd}]+/u', $delimiter, $str);
        }

        // Remove duplicate delimiters        $str = preg_replace('/(' . preg_quote($delimiter, '/') . '){2,}/', '$1', $str);        $str = mb_strtolower($str, 'UTF-8');

        if( $pathSafe === true )        {
            $str = preg_replace("/[_|+ -]+/", $delimiter, $str);
        }else{
            $str = preg_replace("/[\/_|+ -]+/", $delimiter, $str);
        }

        // Remove delimiter from ends
        $str = trim($str, $delimiter);

        return $str;    }
}
