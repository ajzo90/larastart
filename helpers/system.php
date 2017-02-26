<?php

if (!defined('ib_sys_helpers')) {
    define('ib_sys_helpers', 1);

    function ib_sys_disk_usage($dir = null)
    {
        if ($dir) {
            return array_map('array_reverse', array_filter(array_map(function ($line) {
                return preg_split('/\s+/', $line);
            }, explode("\n", trim(shell_exec('du -s ' . $dir . '/*/ | sort -nr | head -n 5')))), function ($row) {
                return count($row) === 2;
            }));
        } else {
            return [
                ["total", disk_total_space(__DIR__) / 1000],
                ["free", disk_free_space(__DIR__) / 1000]
            ];
        }
    }
}