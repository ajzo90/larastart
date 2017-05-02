<?php

namespace Larastart\DataStructures;

class PriorityQueue
{

    private static $table = "larastart_priority_queue";

    private static function q()
    {
        return ib_db(self::$table);
    }


    public static function queueExists($id)
    {
        return self::q()->where("queue_id", $id)->exists();
    }

    public static function queues() :array
    {
        return self::q()->select('queue_id')->distinct()->pluck("id", "id")->toArray();
    }


    /**
     * @var int|null
     */
    private $id;

    public function __construct($id = null)
    {
        if ($id === null) {
            $id = $this->getFreeId();
            self::q()->insert(["queue_id" => $id, "key" => 0, "handled" => true]); // insert dummy record...
        }
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    private function getFreeId()
    {
        // get a free id.
        $ids = self::queues();
        for ($i = 1; $i < pow(2, 16); $i++) {
            if ($ids[$i] ?? false) {

            } else {
                return $i;
            }
        }
    }

    private function transformData(array $values)
    {
        $data = [];
        foreach ($values as $key => $value) {
            if (is_numeric($key) && $key > 0) {
                $data[] = ["queue_id" => $this->id, "key" => $key, "priority" => $value];
            } else {
                throw new \Exception("Invalid key '{$key}'");
            }
        }
        return $data;
    }

    public function insertIgnore(array $values)
    {
        ib_db_insert_ignore(self::$table, $this->transformData($values));
    }


    public function insertReplace(array $values)
    {
        ib_db_insert_replace(self::$table, $this->transformData($values));
    }

    private function getRaw($n, $order)
    {
        return self::q()->where("queue_id", $this->id)->where("handled", false)->orderBy("priority", $order)
            ->select(["key", "priority"])
            ->take($n)->get();
    }

    public function topValue()
    {
        return $this->topValues(1)[0] ?? null;
    }

    public function topValues($n = 1)
    {
        return $this->getRaw($n, "DESC");
    }

    public function bottomValues($n = 1)
    {
        return $this->getRaw($n, "ASC");
    }


    private function processRaw($n = 1, callable $cb, $order)
    {
        $rows = $this->getRaw($n, $order);
        $cb($rows);
        $keys = $rows->pluck("key")->toArray();
        self::q()->where("queue_id", $this->id)->whereIn("key", $keys)->update(["handled" => true]);
        return true;
    }

    public function processTop($n = 1, callable $cb)
    {
        return $this->processRaw($n, $cb, "DESC");
    }

    public function processBottom($n = 1, callable $cb)
    {
        return $this->processRaw($n, $cb, "ASC");
    }
}