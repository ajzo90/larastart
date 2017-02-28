<?php

if (!defined('ib_db_helpers')) {
    define('ib_db_helpers', 1);
    function ib_db_select($query, $params = [], $connection = null)
    {
        if (file_exists($query)) {
            $query = file_get_contents($query);
        }
        return DB::connection($connection)->select($query, $params);
    }

    function ib_db_listings($database = null)
    {
        if ($database) {
            return ib_db_select(__DIR__ . "/../database/queries/db_table_listings.sql", ["db" => $database]);
        }
        return ib_db_select(__DIR__ . "/../database/queries/db_listing.sql");
    }


    function ib_db($table = null, $connection = null)
    {
        if ($connection && $table) {
            return DB::connection($connection)->table($table);
        } else if ($connection) {
            return DB::connection($connection);
        } else if ($table) {
            return DB::table($table);
        }
        return DB::connection();
    }

    function ib_db_raw($str)
    {
        return DB::raw($str);
    }

}



