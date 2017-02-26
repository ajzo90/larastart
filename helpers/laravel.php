<?php

if (!defined('ib_laravel_helpers')) {
    define('ib_laravel_helpers', 1);

    function ib_dispatch($job, $connection)
    {
        if (in_array($connection, ['db', 'redis'])) {
            $job->onConnection($connection);
            dispatch($job);
        } else {
            throw new \Exception("connection '{$connection}' is not supported");
        }
    }

}