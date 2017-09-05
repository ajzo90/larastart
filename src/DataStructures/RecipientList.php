<?php


namespace Larastart\DataStructures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class RecipientListModel extends Model
{
    public $table = 'larastart_data_structure_list_meta';

    public $fillable = ['updated_at', 'forever', 'minutes', 'key', 'locked_at', 'touched_at'];

    public $timestamps = false;

    public function isOld()
    {
        return $this->locked_at === 0 && $this->forever === false && $this->updated_at < time() - $this->minutes * 60;
    }

}

class RecipientList
{
    private static $dataTable = 'larastart_data_structure_list';

    private $id;
    private $key;
    private $hasWritePermission;


    public static function getList($key)
    {
        self::cleanUp();
        return RecipientListModel::where("key", $key)->first();
    }

    private static function getOrCreateList($key, $data = [])
    {
        $list = self::getList($key);
        if ($list) {
            return $list;
        }
        return RecipientListModel::create(array_merge($data, ["key" => $key, "updated_at" => time()]));
    }

    public static function cleanUp()
    {
        foreach (RecipientListModel::all() as $list) {
            if ($list->isOld()) {
                self::forget($list->key);
            } else if ($list->locked_at > 0 && $list->locked_at < time() - 5 * 60) { // lock expires after 5 minutes
                \Log::info("Released expired lock on Recipient list " . $list->id);
                $list->locked_at = 0;
                $list->save();
            }
        }
    }

    public static function has($key)
    {
        self::cleanUp();
        return self::getList($key) ?? false;
    }

    public static function forever($key, callable $cb)
    {
        return self::remember($key, PHP_INT_MAX, $cb);
    }

    public static function setList($key, $minutes, callable $cb)
    {
        return self::_remember($key, $minutes, $cb, true);
    }

    public static function remember($key, $minutes, callable $cb)
    {
        return self::_remember($key, $minutes, $cb, false);
    }

    private static function _remember($key, $minutes, callable $cb, $update)
    {
        self::cleanUp();
        $list = self::getList($key);

        if ($list) {
            if ($update === false) {
                return new self($list);
            }
        } else if ($minutes === PHP_INT_MAX) {
            $list = self::getOrCreateList($key, ['forever' => true, 'minutes' => 0]);
        } else {
            $list = self::getOrCreateList($key, compact('minutes'));
        }

        $instance = new self($list);
        $instance->clear();
        $instance->set($cb);
        return $instance;
    }

    public static function forget($key)
    {
        $list = self::getList($key);
        if ($list) {
            $instance = new self($list);
            $instance->clear();
            RecipientListModel::where("id", $list->id)->delete();
        }
    }


    private function __construct(RecipientListModel $model)
    {
        $this->id = $model->id;
        $this->key = $model->key;
        $this->hasWritePermission = false;
        RecipientListModel::where('id', $this->id)->update(["touched_at" => time()]);
    }

    public function __destruct()
    {
        $this->update(["locked_at" => 0]);
    }

    public function query() : Builder
    {
        return ib_db(self::$dataTable)->where(self::$dataTable . ".list_id", $this->id)->select(self::$dataTable . ".key");
    }

    public function clear()
    {
        $this->query()->delete();
    }

    private function getAttr($key)
    {
        return RecipientListModel::where("id", $this->id)->value($key);
    }

    public function length()
    {
        return $this->getAttr("length");
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


    public function append(array $val)
    {

        if (!$this->canMutateList()) {
            throw new \Exception("RecipientListIsLockedForMutations");
        }

        foreach (array_chunk($val, 5000) as $chunk) {
            $data = array_map(function ($id) {
                return ["key" => $id, "list_id" => $this->id];
            }, $chunk);
            ib_db_insert_ignore(self::$dataTable, $data);
        }
        $this->update(["length" => $this->query()->count()]);
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
        $table = self::$dataTable;
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

    private function insertQuery(Builder $builder)
    {
        $builder->selectRaw($this->id);
        $insertQuery = 'INSERT IGNORE into ' . self::$dataTable . ' (`key`,`list_id`) ' . $builder->toSql();
        \DB::insert($insertQuery, $builder->getBindings());
        $this->update(["length" => $this->query()->count()]);
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
        $q->join(self::$dataTable . " as $alias", "$alias.key", $key)
            ->where("$alias.list_id", $this->id);
    }
}