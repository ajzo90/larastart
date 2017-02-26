<?php

if (!defined('ib_arr_helpers')) {
    define('ib_arr_helpers', 1);

    function ib_array_to_assoc($array)
    {
        $new_array = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $new_array[$key] = $value;
            } else {
                $new_array[$value] = $value;
            }
        }
        return $new_array;
    }

    function ib_array_ordered_by_keys($arr, $keys, $default = null)
    {
        $values1 = [];
        foreach ($keys as $key) {
            $values1[$key] = $arr[$key] ?? $default;
        }
        return $values1;
    }

}