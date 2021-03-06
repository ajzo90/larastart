<?php


function redis_encode_value($res)
{
    return json_encode(["value" => $res, "created_at" => time()]);
}

function redis_decode_value($string)
{
    $obj = json_decode($string, 1);
    return $obj["value"];
}

function redis_mutex_promise($key, callable $f, $max_wait_seconds = 10, $result_valid_seconds = 60, $max_block_time = 60)
{
    $data_key = 'redis_mutex_promise_data:' . $key;
    $lock_key = 'redis_mutex_promise_lock:' . $key;
    $max_ts = $max_wait_seconds + time();
    $loop = 0;

    do {
        if (($res = Redis::get($data_key))) {
            return redis_decode_value($res);
        }
        if ($loop === 0 && Redis::setNx($lock_key, time())) {
            Redis::expire($lock_key, $max_block_time);
            try {
                $res = $f();
                Redis::setEx($data_key, $result_valid_seconds, redis_encode_value($res));
                Redis::del($lock_key);
                return $res;
            } catch (\Exception $e) {
                Redis::del($lock_key);
                return false;
            }
        }
        $loop++;
        sleep(1);
    } while (time() <= $max_ts);
    return false;
}


function redis_mutex_cb($key, callable $f, $sleep = 10, $max_block_seconds = 60 * 60)
{
    $key = 'redis_mutex_cb:' . $key;
    $max_block_seconds = max($sleep + 1, $max_block_seconds);
    $max_blocked_to = time() + $max_block_seconds;

    if (Redis::setNx($key, time()) === 1) {
        Redis::expire($key, $max_block_seconds);
        try {
            $f();
        } catch (\Exception $exception) {
//            echo "failed\n";
        }
        if (time() >= $max_blocked_to) {
//            echo "we passed max block seconds... Release the lock without sleep." . PHP_EOL;
        } else {
            $ttl = min($max_blocked_to, time() + $sleep) - time();
            Redis::expire($key, $ttl);
//            echo "update ttl for key=$key to $ttl seconds" . PHP_EOL;
        }
    } else {
//        echo "Blocked in " . Redis::ttl($key) . " seconds" . PHP_EOL;
    }
}


//60 * 60 * 24 * 7=604800 (7 days)
/*
 *  redis_state_watcher("hello", "world", function ($newVal, $oldVal) {
 *      echo "Value changed from $oldVal to $newVal";
 *  });
 */
function redis_state_watcher($key, $new_value, callable $cb, $expiration = 604800)
{
    if (is_string($new_value)) {
        $old_val = Redis::get($key);
        if ($old_val !== $new_value) {
            Redis::setEx($key, $expiration, $new_value);
            $cb($new_value, $old_val);
        }
    } else {
        throw new \Exception("Must use string value");
    }
}


function redis_buffer_flush($key, callable $f)
{
    $src = "redis-buffer-" . $key;
    if (Redis::exists($src)) {
        $count = Redis::llen($src);
        if ($count === 0) {
            return;
        }
        $dest = $src . "-" . str_random();
        // move to new temp buffer
        Redis::rename($src, $dest);
        // add all items in the buffer to processing
        $items = Redis::lrange($dest, 0, -1);
        $f($items);
        Redis::del($dest);
    }
}

function redis_buffer_push($key, $string)
{
    $src = "redis-buffer-" . $key;
    return Redis::command("rpush", [$src, $string]);
}

function redis_buffer($key, $string, callable $f, $max)
{
    $queued_items = redis_buffer_push($key, $string);
    if ($queued_items >= $max) {
        redis_buffer_flush($key, $f);
    }
}


//https://stackoverflow.com/questions/4006324/how-to-atomically-delete-keys-matching-a-pattern-using-redis
function redis_del_prefix($prefix)
{
    $script = <<<'LUA'
local keys = redis.call('keys', ARGV[1])
for i=1,#keys,5000 do
    redis.call('del', unpack(keys, i, math.min(i+4999, #keys)))
end
return keys
LUA;

    return Redis::eval($script, 0, $prefix . "*");
}