<?php

if (!defined('ib_db_helpers')) {
    define('ib_db_helpers', 1);
    function ib_db_select($query, $bindings = [], $connection = null)
    {
        if (file_exists($query)) {
            $query = file_get_contents($query);
        }
        $words = explode(' ', strtoupper($query));
        foreach (['DROP', 'UPDATE', 'TRUNCATE', 'INSERT', 'REPLACE', 'ALTER', 'CREATE', 'DELETE'] as $operation) {
            if (in_array($operation, $words)) {
                throw new \Exception('Invalid operator: ' . $operation);
            }
        }
        return DB::connection($connection)->select($query, $bindings);
    }

    function ib_db_is_safe_number($str)
    {
        return sprintf('%d', intval($str)) === '' . $str;
    }

    function ib_db_raw_query($query, $connection = null)
    {
        \Log::info('Deprecated function: ib_db_raw_query. Please use ib_db_statement instead...');
        return DB::connection($connection)->statement($query);
    }

    function ib_db_statement($query, $bindings = [], $connection = null)
    {
        if (file_exists($query)) {
            $query = file_get_contents($query);
        }
        return DB::connection($connection)->statement($query, $bindings);
    }

    function ib_db_listings($database = null)
    {
        if ($database) {
            return ib_db_select(__DIR__ . '/../database/queries/db_table_listings.sql', ['b' => $database]);
        }
        return ib_db_select(__DIR__ . '/../database/queries/db_listing.sql');
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

    function ib_db_transaction(callable $f)
    {
        DB::transaction($f);
    }

    function ib_db_insert_ignore($table, array $attributes)
    {
        return __ib_db_insert_special($table, $attributes, 'insert ignore into');
    }

    function ib_db_insert_replace($table, array $attributes)
    {
        return __ib_db_insert_special($table, $attributes, 'replace into');
    }

    function ib_db_insert_on_duplicate_update($table, array $attributes)
    {
        return __ib_db_insert_special($table, $attributes, 'insert into', true);
    }

    function ib_db_insert_immutable($table, array $data, $created_at_col = null, $hash_col = 'hash', $primary_col = 'id')
    {
        $exclude = [$hash_col, $primary_col];
        $data = array_except($data, $exclude);
        $hash = $data[$hash_col] ?? ib_array_hash($data); // todo: wtf. we remove hash col in the first line, this is stupid

        $res = ib_db($table)->where($hash_col, $hash)->value($primary_col);
        if ($res) {
            return $res;
        }

        $data[$hash_col] = $hash;
        if ($created_at_col) {
            $data[$created_at_col] = time();
        }

        return ib_db($table)->insertGetId($data);
    }


    function ib_db_initiate($table, array $values, $primary = 'id')
    {
        $q = '';
        $bindings = [];
        foreach ($values as $value) {
            if (ib_db_is_safe_number($value)) {
                $q .= '(' . $value . '),';
            } else {
                $bindings[] = $value;
                $q .= '(?),';
            }
        }
        return ib_db_statement("insert ignore into {$table} ({$primary}) VALUES " . rtrim($q, ','), $bindings);
    }

    function ib_db_insert_immutable_batch($table, array $attributes, $created_at_col = null, $hash_col = 'hash', $primary_col = 'id')
    {
        $now = time();
        $exclude = [$hash_col, $primary_col];
        $hashes = [];
        foreach ($attributes as $i => $row) {
            $row = array_except($row, $exclude);
            $hash = $row[$hash_col] ?? ib_array_hash($row);
            $hashes[] = $hash;
            $row[$hash_col] = $hash;
            $attributes[$i] = $row;
        }
        $hash_to_id = ib_db($table)->whereIn($hash_col, $hashes)->pluck($primary_col, $hash_col);

        $toInsert = [];
        foreach ($attributes as $i => $row) {
            $hash = $row[$hash_col];
            if ($id = $hash_to_id[$hash] ?? false) {
            } else {
                if ($created_at_col) {
                    $row[$created_at_col] = $now;
                }
                $toInsert[] = $row;
            }
        }
        ib_db_insert_ignore($table, $toInsert);

        $hash_to_id = ib_db($table)->whereIn($hash_col, $hashes)->pluck($primary_col, $hash_col);

        return array_map(function ($item) use ($hash_to_id, $hash_col) {
            return $hash_to_id[$item[$hash_col]];
        }, $attributes);
    }

    function __ib_db_insert_special($table, array $attributes, $special, $dup = false)
    {
        if (count($attributes) === 0) {
            return 0;
        }
        $attributes = collect($attributes);
        $first = $attributes->first();
        if (!is_array($first)) {
            $attributes = collect([$attributes->toArray()]);
        }
        $keys = collect($attributes->first())->keys()
            ->transform(function ($key) {
                return '`' . $key . '`';
            });
        $bindings = [];
        $query = "{$special} {$table} (" . $keys->implode(',') . ') values ';
        $inserts = [];

        foreach ($attributes as $data) {
            $inner = '';
            foreach ($data as $value) {
                if (ib_db_is_safe_number($value)) {
                    $inner .= $value . ',';
                } else {
                    $bindings[] = $value;
                    $inner .= '?,';
                }
            }
            $inserts[] = '(' . rtrim($inner, ',') . ')';
        }
        $query .= implode(',', $inserts);

        if ($dup) {
            $query .= ' on duplicate key update ' . join(',', $keys->map(function ($key) {
                    return "$key=VALUES($key)";
                })->toArray());
        }

        return ib_db_statement($query, $bindings);
    }

}



