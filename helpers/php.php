<?php

if (!defined('ib_php_helpers')) {
    define('ib_php_helpers', 1);

    function ib_swap(&$x, &$y)
    {
        $tmp = $x;
        $x = $y;
        $y = $tmp;
    }

    function ib_to_numeric(&$data)
    {
        if (is_array($data)) {
            foreach ($data as &$value) {
                ib_to_numeric($value);
            }
        } elseif (is_numeric($data)) {
            $data = number_format($data);
        }
    }
}