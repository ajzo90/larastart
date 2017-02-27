<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 27/02/17
 * Time: 10:04
 */

namespace Larastart\Support;


class Picker
{
    private $namespace;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
    }

    // update user,product,rank
    // set order to NULL for updated users
    public function register($key, array $users, $min = null, $max = null)
    {

        $pid = $this->getPickerId($key);

        $this->updatePicker($key, ['min' => $min, 'max' => $max]);
        $this->updatePickers(['picked' => 0]);

        $pids = $this->getPickerIds();

        $lockedUsers = $this->lockedUsers();


        $filteredUsers = [];
        foreach ($users as $userId => $rank) {
            $locked = $lockedUsers[$userId] ?? false;
            if (!$locked) {
                $filteredUsers[$userId] = ["rank" => $rank, "user_id" => $userId, "picker_id" => $pid];
            }
        }

        foreach (collect($filteredUsers)->chunk(5000) as $chunk) {
            $keys = $chunk->keys();
            ib_db("picker_data")->whereIn("user_id", $keys)->where("picker_id", $pid)->delete();

            ib_db("picker_data")->whereIn("user_id", $keys)->whereIn("picker_id", $pids)->update(["order" => null, "picked" => 0]);

            ib_db("picker_data")->insert($chunk->values()->toArray());
        }


    }


    public function getData()
    {
        return ib_db("picker_data")->whereIn("picker_id", $this->getPickerIds())->get();
    }


    public function query($key = null)
    {
        if ($key) {
            return ib_db("picker_data")->where("picker_id", $this->getPickerId($key));
        } else {
            return ib_db("picker_data")->whereIn("picker_id", $this->getPickerIds());
        }
    }


    public function updateOrder()
    {
        $users = $this->query()->whereNull("order")->orderBy("rank", "desc")->select(["user_id", "picker_id", "rank"])->get()->groupBy("user_id");
        $toInsert = [];
        foreach ($users as $userId => $userData) {
            $i = 1;
            foreach ($userData as $row) {
                $toInsert[] = ["user_id" => $userId, "picker_id" => $row->picker_id, "rank" => $row->rank, "order" => $i++];
            }
        }

        \DB::transaction(function () use ($toInsert) {
            $this->query()->whereNull("order")->delete();
            $this->query()->insert($toInsert);
        });
    }


    public function pick($key)
    {
        if ($this->needToRunPicker($key)) {
            $this->runPicker();
        }
        return $this->query($key)->where("picked", 1)->pluck("user_id")->toArray();
    }


    // run picker
    // only run it for users whereHas picker false
    private function runPicker()
    {

        $this->updateOrder();

        $pickers = $this->getPickers();
        $max = $pickers->max('min');
        $pickersToUsers = [];
        $pickersToPart = [];
        $pickersToBooked = [];
        $bookedUsers = [];

        foreach ($pickers as $picker) {
            // notice the order. It is like this: [{order: 2, rank: 1}, {order:2, rank:2}, {order:1, rank: 2}, {order: 1, rank: 1}] The lowest order and the highest rank in the end.
            // This is becuase the array is traversed with pop operations.
            $pickersToUsers[$picker->key] = $this->query($picker->key)->orderBy("order", "desc")->orderBy("rank", "asc")->pluck("user_id")->toArray();
            $pickersToPart[$picker->key] = $picker->min / max($max, 1);
            $pickersToBooked[$picker->id] = 0;
        }

        $lockedUsers = $this->lockedUsers();
        $lockedUsers = array_combine($lockedUsers, $lockedUsers);


        $run = true;
        $i = 0;
        while ($run) {
            $i++;
            $run = false;
            foreach ($pickers as $picker) {
                $toPick = floor($i * $pickersToPart[$picker->key]);
                $toPickThisLoop = min($toPick, $picker->min) - $pickersToBooked[$picker->id];
                $maxIterations = count($pickersToUsers[$picker->key]);
                $picked = 0;
                $iter = 0;

                while ($picked < $toPickThisLoop && $iter < $maxIterations) {
                    $run = true; // continue the outer loop as long the inner loop is visited
                    $iter++; // iterate while the $pickersToPart array is not empty
                    $user = array_pop($pickersToUsers[$picker->key]);
                    $isBooked = $bookedUsers[$user] ?? false;
                    $isLocked = $lockedUsers[$user] ?? false;
                    if ($isBooked === false && $isLocked === false) {
                        $bookedUsers[$user] = $picker->id;
                        $pickersToBooked[$picker->id]++;
                        $picked++;
                    }
                }
            }
        }

        // insert booked users from first part
        foreach ($bookedUsers as $user_id => $picker_id) {
            echo "Picked $user_id  $picker_id\n";
            $this->query()->where("picker_id", $picker_id)->where("user_id", $user_id)->update(["picked" => 1]);
        }

        $pickersFreeSlots = [];
        foreach ($pickers as $picker) {
            $pickersFreeSlots[$picker->id] = ($picker->max ?: 1000000) - $pickersToBooked[$picker->id];
        }

//        $this->letTheUsersPick($pickersFreeSlots);

        $this->updatePickers(['picked' => 1]);

    }

    private function letTheUsersPick($pickersFreeSlots)
    {
        // users without lock and pick
        $users = $this->users();

        $users = array_keys($users);


        $bookedUsers = [];
        foreach ($this->query()->whereIn('user_id', $users)->orderBy("order", "asc")->pluck("picker_id", "user_id") as $user_id => $picker_id) {
            $isBooked = $bookedUsers[$user_id] ?? false;

            if ($isBooked === false && $pickersFreeSlots[$picker_id] > 0) {
                $bookedUsers[$user_id] = $picker_id;
                $pickersFreeSlots[$picker_id]--;
            }
        }

        foreach ($bookedUsers as $user_id => $picker_id) {
            $this->query()->where("picker_id", $picker_id)->where("user_id", $user_id)->update(["picked" => 1]);
        }
    }

    private function userIds($key = null)
    {
        return array_keys($this->userIds($key));
    }

    public function users($key = null)
    {
        return $this->query($key)->select('user_id', \DB::raw('SUM(locked) as locked, COUNT(*) as rows'))->groupBy('user_id')->get()->keyBy("user_id")->toArray();
    }

    public function lockedUsers()
    {
        return ib_db("picker_data")->whereIn("picker_id", $this->getPickerIds(1))->where("locked", 1)->select("user_id")->distinct()->pluck("user_id")->toArray();
    }

    public function lock($key)
    {
        $userIds = $this->pick($key);
        $this->updatePicker($key, ['locked' => 1]);
        $this->query($key)->whereIn('user_id', $userIds)->update(['locked' => 1]);
        return $userIds;
    }

    public function unLock($key)
    {
//        $userIds = $this->query($key)->where()
//        $this->query($key)->whereIn('user_id', $userIds)->update(['locked' => 0]);
//        $this->updatePicker($key, ['locked' => 0]);
    }


    public function getPicker($key)
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace, 'key' => $key])->first();
    }

    public function getPickers($locked = 0)
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace, 'locked' => $locked])->get();
    }


    private function updatePicker($key, $data)
    {
        ib_db("pickers")->where(['namespace' => $this->namespace, 'key' => $key])->update($data);
    }

    private function updatePickers($data, $locked = 0)
    {
        ib_db("pickers")->where(['namespace' => $this->namespace, 'locked' => $locked])->update($data);
    }

    private function getPickerId($key)
    {
        $picker = $this->getPicker($key);
        if ($picker) {
            return $picker->id;
        } else {
            return ib_db("pickers")->insertGetId(['namespace' => $this->namespace, 'key' => $key]);
        }
    }

    private function getPickerIds($locked = 0)
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace, 'locked' => $locked])->pluck("id")->toArray();
    }

    private function needToRunPicker($key)
    {
        $picker = $this->getPicker($key);
        if ($picker && $picker->locked) {
            return false;
        }
        foreach ($this->getPickers() as $picker) {
            if ($picker->picked == 0) {
                return true;
            }
        }
        return false;
    }


}