<?php

if (!defined('ib_str_helpers')) {
    define('ib_str_helpers', 1);

    function ib_string_starts_with($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    function ib_string_ends_with($haystack, $needle)
    {
        return substr($haystack, strlen($haystack) - strlen($needle), strlen($needle)) === $needle;
    }

    function ib_string_is_wrapped($string, $wrap_with)
    {
        return ib_string_starts_with($string, $wrap_with) && ib_string_ends_with($string, $wrap_with);
    }

    function ib_string_wrap($string, $wrap_with)
    {
        if (ib_string_is_wrapped($string, $wrap_with)) {
            return $string;
        }
        return $wrap_with . $string . $wrap_with;
    }

    function ib_labels($str)
    {
        return ucwords(str_replace("_", " ", snake_case($str)));
    }

    function ib_trim_all($str, $what = NULL, $with = ' ')
    {
        if ($what === NULL) {
            //  Character      Decimal      Use
            //  "\0"            0           Null Character
            //  "\t"            9           Tab
            //  "\n"           10           New line
            //  "\x0B"         11           Vertical Tab
            //  "\r"           13           New Line in Mac
            //  " "            32           Space

            $what = "\\x00-\\x20";    //all white-spaces and control chars
        }

        return trim(preg_replace("/[" . $what . "]+/", $with, $str), $what);
    }

    function ib_stringify_keys($oldArray)
    {
        $newArray = new \stdClass();
        foreach ($oldArray as $key => $value) {
            $newArray->{(string)$key} = $value;
        }
        return (array)$newArray;
    }

}