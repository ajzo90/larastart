<?php


function redis_mutex_cb($key, callable $f, $sleep = 10, $max_block_seconds = 60 * 60)
{
    $key = 'mutex_cb:' . $key;
    $max_block_seconds = max($sleep + 1, $max_block_seconds);
    $max_blocked_to = time() + $max_block_seconds;

    if (Redis::setNx($key, 'dummy') === 1) {
        Redis::expire($key, $max_block_seconds);
        try {
            $f();
        } catch (\Exception $exception) {
            echo "failed\n";
        }
        if (time() >= $max_blocked_to) {
            echo "we passed max block seconds... Release the lock without sleep." . PHP_EOL;
        } else {
            $ttl = min($max_blocked_to, time() + $sleep) - time();
            Redis::expire($key, $ttl);
            echo "update ttl for key=$key to $ttl seconds" . PHP_EOL;
        }
    } else {
        echo "Blocked in " . Redis::ttl($key) . " seconds" . PHP_EOL;
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