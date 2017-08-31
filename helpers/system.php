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

    function ib_arrayify_proc($data)
    {
        return array_reduce($data, function ($p, $c) {
            $t = array_map(function ($col) {
                return trim($col);
            }, explode(":", str_replace("\n", "", $c)));
            if (count($t) == 2) {
                $p[$t[0]] = $t[1];
            }
            return $p;
        }, []);
    }

    function ib_ls_cpu($only = [
        'Architecture', 'CPU op-mode(s)', 'CPU(s)', 'Thread(s) per core', 'Core(s) per socket', 'Socket(s)', 'L1d cache', 'L1i cache', 'L2 cache', 'L3 cache', 'L4 cache'])
    {
        return array_only(ib_arrayify_proc(explode("\n", shell_exec('lscpu'))), $only);

    }

    function ib_mem_info($only = ['MemTotal', 'MemFree', 'MemAvailable', 'Buffers', 'Cached'])
    {
        return array_only(ib_arrayify_proc(file('/proc/meminfo')), $only);
    }

    function ib_cpu_info($only = ['cpu MHz', 'model name', 'cache size', 'cpu cores', 'bogomips'])
    {
        return array_only(ib_arrayify_proc(file('/proc/cpuinfo')), $only);
    }


    function ib_memory_usage()
    {
        $free = shell_exec('free');
        $free = (string)trim($free);
        $free_arr = explode("\n", $free);
        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);
        $memory_usage = $mem[2] / $mem[1] * 100;
        return ["usage" => $memory_usage, "free" => $mem[3], "shared" => $mem[4], "buffers" => $mem[5], "cached" => $mem[6], "used" => $mem[2], "total" => $mem[1]];
    }

    function human_to_bytes($human)
    {
        if (str_contains($human, "GB")) {
            return trim(str_replace_first("GB", "", $human)) * 1024 * 1024 * 1024;
        } else if (str_contains($human, "MB")) {
            return trim(str_replace_first("MB", "", $human)) * 1024 * 1024;
        } else if (str_contains($human, "kB")) {
            return trim(str_replace_first("kB", "", $human)) * 1024;
        }
        return $human;
    }

    function bytes_to_human($bytes)
    {
        if ($bytes > 1024) {
            $kb = $bytes / 1024;
            if ($kb > 1024) {
                $mb = $kb / 1024;
                if ($mb > 1024) {
                    $gb = $mb / 1024;
                    return round($gb, 2) . " GB";
                }
                return round($mb, 2) . " MB";
            }
            return round($kb, 2) . " kB";
        }
        return $bytes;

    }

}
