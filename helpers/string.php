<?php

if (!defined('ib_str_helpers')) {
    define('ib_str_helpers', 1);

    function ib_string_starts_with($haystack, $needle)
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    function ib_labels($str)
    {
        return ucwords(str_replace("_", " ", snake_case($str)));
    }

}