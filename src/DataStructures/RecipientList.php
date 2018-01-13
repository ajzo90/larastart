<?php


namespace Larastart\DataStructures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class RecipientListModel extends Model
{
    public $table = 'larastart_data_structure_list_meta';

    public $fillable = ['updated_at', 'forever', 'minutes', 'key', 'locked_at', 'touched_at', 'data_table', 'id'];

    public $timestamps = false;

    public function isOld()
    {
        return $this->locked_at === 0 && $this->forever === 0 && $this->updated_at < time() - $this->minutes * 60;
    }

}

class RecipientList
{
    private static $dataTable = 'larastart_data_structure_list';

    private $id;
    private $key;
    private $data_table;
    private $hasWritePermission;

    public static function getList($key)
    {
        self::cleanUp();
        return RecipientListModel::where("key", $key)->where("deleted_at", 0)->first();
    }

    public static function hardCleanUp($loops = 1)
    {
        self::truncateOldestDynamicFile();

        if ($loops <= 0) {
            return;
        }

        $list = RecipientListModel::where("deleted_at", ">", 0)->first();
        if ($list) {
            $instance = new self($list);
            $instance->clear();
            RecipientListModel::where("id", $list->id)->delete();
            \Log::info("Hard deleted list with id: $list->id");
            self::hardCleanUp($loops - 1);
        }
    }

    public static function cleanUp()
    {
        $q = "DELETE FROM larastart_data_structure_list_meta WHERE data_table LIKE '%daily_cache%' AND forever=0 AND updated_at + minutes * 60 < ?";
        ib_db_statement($q, [time()]);

        foreach (RecipientListModel::where("deleted_at", 0)->get() as $list) {
            if ($list->isOld()) {
                self::_forget($list);
                \Log::info("Soft deleted list with id: $list->id");
            } else if ($list->locked_at > 0 && $list->locked_at < time() - 5 * 60) { // lock expires after 5 minutes
                \Log::info("Released expired lock on Recipient list " . $list->id);
                $list->locked_at = 0;
                $list->save();
            }
        }
    }

    public static function forget($key)
    {
        self::_forget(self::getList($key));
    }

    public static function has($key)
    {
        self::cleanUp();
        return self::getList($key) ?? false;
    }

    public static function setList($key, $minutes, callable $cb = null)
    {
        return self::_remember($key, $minutes, $cb, true);
    }

    public static function remember($key, $minutes, callable $cb)
    {
        return self::_remember($key, $minutes, $cb, false);
    }

    public static function forever($key, callable $cb)
    {
        return self::remember($key, PHP_INT_MAX, $cb);
    }

    public function query(): Builder
    {
        return ib_db($this->dataTable())->where($this->dataTable() . ".list_id", $this->id)->select($this->dataTable() . ".key");
    }

    public function clear()
    {
        if (str_contains($this->dataTable(), 'daily_cache0')) {
            // dont delete from the cache tables..
            // this is handled by the batch truncate operations instead..
        } else {
            $this->query()->delete();
        }
    }

    public function length()
    {
        return $this->getAttr("length");
    }


    public function append(array $val, $count = true)
    {
        if (!$this->canMutateList()) {
            throw new \Exception("RecipientListIsLockedForMutations");
        }

        foreach (array_chunk($val, 5000) as $chunk) {
            $data = array_map(function ($id) {
                return ["key" => $id, "list_id" => $this->id];
            }, $chunk);
            ib_db_insert_ignore($this->dataTable(), $data);
        }
        if ($count) {
            $this->updateCount();
        }
    }

    public function updateCount()
    {
        $count = $this->query()->count();
        $this->update(["length" => $count]);
        return $count;
    }


    public function unionWith(array $lists = [])
    {
        $q = $this->query();

        foreach ($lists as $id => $list) {
            if ($list instanceof RecipientList) {
                $q->union($list->query());
            }
        }
        return $q;
    }

    public function intersectWith(array $lists = [])
    {
        $table = $this->dataTable();
        $q = $this->query();

        foreach ($lists as $id => $list) {
            if ($list instanceof RecipientList) {
                $key = "t" . $id;
                $q->join($table . " as $key", "$key.key", $table . ".key")
                    ->where("$key.list_id", $list->id);
            }
        }
        return $q;
    }

    public function insertQuery(Builder $builder)
    {
        if (!$this->canMutateList()) {
            throw new \Exception("RecipientListIsLockedForMutations");
        }
        $builder->selectRaw($this->id);
        $insertQuery = 'INSERT IGNORE into ' . $this->dataTable() . ' (`key`,`list_id`) ' . $builder->toSql();
        \DB::insert($insertQuery, $builder->getBindings());
        $this->updateCount();
    }


    public function set(callable $val)
    {
        if (!$this->canMutateList()) {
            throw new \Exception("RecipientListIsLockedForMutations");
        }
        $this->clear();
        $data = $val($this);

        if ($data instanceof Builder) {
            $this->insertQuery($data);
        } else if (is_array($val)) {
            $this->append($data);
        }
    }

    public function join($q, $key)
    {
        $alias = "recipient_list_" . $this->id;
        $q->join($this->dataTable() . " as $alias", "$alias.key", $key)
            ->where("$alias.list_id", $this->id);
    }


    public function __destruct()
    {
        $this->update(["locked_at" => 0]);
    }


    private static function _forget($list = null)
    {
        if ($list) {
            RecipientListModel::where("id", $list->id)->update(["deleted_at" => time()]);
        }
    }


    private function __construct(RecipientListModel $model)
    {
        $this->id = $model->id;
        $this->key = $model->key;
        $this->data_table = $model->data_table;
        $this->hasWritePermission = false;
        RecipientListModel::where('id', $this->id)->update(["touched_at" => time()]);
    }

    private function dataTable()
    {
        return $this->data_table;
    }


    private static function _remember($key, $minutes, callable $cb = null, $update)
    {
        self::cleanUp();
        $list = self::getList($key);

        if ($list) {
            if ($update === false) {
                return new self($list);
            }
        } else if ($minutes === PHP_INT_MAX) {
            $list = self::getOrCreateList($key, ['forever' => true, 'minutes' => 0], RecipientList::$dataTable, 65536, 8388607);
        } else {
            if ($minutes <= 60 * 24 * 2) {
                $list = self::getOrCreateList($key, ['minutes' => $minutes], self::getDailyCacheTable(0), 0, 65535);
            } else {
                $list = self::getOrCreateList($key, ['minutes' => $minutes], RecipientList::$dataTable . '_extended_cache', 0, 65535);
            }
        }

        $instance = new self($list);
        $instance->clear();
        if ($cb) {
            $instance->set($cb);
        }
        return $instance;
    }


    private static function getDailyCacheTable($diff = 0)
    {
        return RecipientList::$dataTable . '_daily_cache' . ((floor(time() / (24 * 60 * 60)) + $diff) % 3);
    }

    private static function findId($table, $min, $max)
    {

        $nextInTable = max($min, intval(ib_db($table)->max("list_id")) + 1);
        $nextInMeta = max($min, intval(ib_db('larastart_data_structure_list_meta')
                ->where("id", ">=", $min)
                ->where("id", "<=", $max)
                ->max("id")) + 1);

        $possible = max($nextInMeta, $nextInTable);
        if ($possible <= $max) {
            return $possible;
        }

        $tries = 0;

        while ($tries < 2000) {
            $id = random_int($min, $max);
            if (!ib_db('larastart_data_structure_list_meta')->where("id", $id)->exists()) {
                if (!ib_db($table)->where("list_id", $id)->exists()) {
                    return $id;
                }
            }
        }
        throw new \Exception("Can not find an empty list identifier");
    }

    private static function getOrCreateList($key, $data = [], $table, $min, $max)
    {
        $list = self::getList($key);
        if ($list) {
            return $list;
        }
        $id = self::findId($table, $min, $max);
        return RecipientListModel::create(array_merge($data, ["data_table" => $table, "id" => $id, "key" => $key, "updated_at" => time()]));
    }

    private static function truncateOldestDynamicFile()
    {
        $tomorrow_table = self::getDailyCacheTable(1);

        $q = "TRUNCATE TABLE " . $tomorrow_table;
        \Log::info($q);
        ib_db_statement($q);
    }


    private function getAttr($key)
    {
        return RecipientListModel::where("id", $this->id)->value($key);
    }


    private function update($data)
    {
        if ($this->hasWritePermission) {
            RecipientListModel::where('id', $this->id)->update(array_merge(["locked_at" => time()], $data, ["updated_at" => time(), "touched_at" => time()]));
        }
    }

    private function canMutateList()
    {
        if ($this->hasWritePermission) {
            return $this->hasWritePermission;
        }

        try {
            app('db')->transaction(function () {
                $record = RecipientListModel::where('id', $this->id)->where("locked_at", 0)->lockForUpdate()->first();
                if ($record) {
                    $record->locked_at = time();
                    $record->save();
                    $this->hasWritePermission = true;
                }
            });
        } catch (\Exception $ignored) {
        }
        return $this->hasWritePermission;
    }

}