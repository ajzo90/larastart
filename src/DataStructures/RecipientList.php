<?php


namespace Larastart\DataStructures;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

class RecipientListModel extends Model
{
    public $table = 'larastart_data_structure_list_meta';

    public $fillable = ['updated_at', 'forever', 'minutes', 'key', 'locked'];

    public $timestamps = false;

    public function isOld()
    {
        return $this->locked === false && $this->forever === false && $this->updated_at < Carbon::now()->timestamp - $this->minutes * 60;
    }

}

class RecipientList
{
    private static $dataTable = 'larastart_data_structure_list';

    private $id;
    private $key;


    private static function getList($key)
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
        return RecipientListModel::create(array_merge($data, ["key" => $key, "updated_at" => Carbon::now()->timestamp]));
    }

    public static function cleanUp()
    {
        foreach (RecipientListModel::all() as $list) {
            if ($list->isOld()) {
                self::forget($list->key);
            }
        }

        // todo: clean up dangling lists...
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

    public static function remember($key, $minutes, callable $cb)
    {
        self::cleanUp();
        $list = self::getList($key);

        if ($list && !$list->isOld()) {
            return new self($list);
        }
        if ($list === null) {
            if ($minutes === PHP_INT_MAX) {
                $list = self::getOrCreateList($key, ['forever' => true, 'minutes' => 0]);
            } else {
                $list = self::getOrCreateList($key, compact('minutes'));
            }
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


    public function __construct(RecipientListModel $model)
    {
        $this->id = $model->id;
        $this->key = $model->key;
        $this->update(["locked" => true]);
    }

    public function __destruct()
    {
        $this->update(["locked" => false]);
    }

    public function query() : Builder
    {
        return ib_db(self::$dataTable)->where(self::$dataTable . ".list_id", $this->id)->select(self::$dataTable . ".key");
    }

    public function clear()
    {
        $this->query()->delete();
    }

    private function update($data)
    {
        RecipientListModel::where("id", $this->id)->update($data);
    }

    public function length()
    {
        return RecipientListModel::where("id", $this->id)->value("length");
    }


    public function append(array $val)
    {
        if (RecipientListModel::where("id", $this->id)->where("locked", 1)->exists) {
            throw new \Exception("RecipientListIsLocked");
        }

        foreach (array_chunk($val, 3500) as $chunk) {
            $data = array_map(function ($id) {
                return ["key" => $id, "list_id" => $this->id];
            }, $chunk);
            // check if list exists...
            if (self::has($this->key)) {
                ib_db_insert_ignore(self::$dataTable, $data);
                $this->update(["length" => $this->query()->count()]);
            } else {
                throw new \Exception("The reference to the list is gone.. ");
            }
        }
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

    public function set(callable $val)
    {
        $this->clear();
        $val = $val($this);
        if (is_array($val)) {
            $this->append($val);
        }
        $this->update(["updated_at" => Carbon::now()->timestamp]);
    }

}