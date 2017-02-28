<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 27/02/17
 * Time: 10:04
 */

namespace Larastart\Support;


use Carbon\Carbon;

class Picker
{
    private $namespace;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
    }

    public function needToRunPicker($key = null)
    {
        if ($key) {
            $picker = $this->getPicker($key);
            if ($picker && $picker->locked) {
                return false;
            }
        }

        foreach ($this->getPickers() as $picker) {
            if ($picker->picked == 0) {
                return true;
            }
        }
        return false;
    }

    // update user,product,rank
    // set order to NULL for updated users
    public function register($key, array $users = null, $min = null, $max = null)
    {
        $pid = $this->getPickerId($key);

        if ($this->isLocked($key)) {
            throw new \Exception("This key is locked");
        }

        $usersHash = md5(json_encode($users));

        $picker = $this->getPicker($key);

        $needUpdate = false;

        if ($picker->hash !== $usersHash && $users !== null) {
            $needUpdate = true;

            foreach ($users as $userId => $rank) {
                $dataToUpdate[$userId] = ["rank" => $rank, "user_id" => $userId, "picker_id" => $pid, "picked" => 0, "order" => null];
            }

            foreach (collect($dataToUpdate)->chunk(5000) as $chunk) {
                $keys = $chunk->keys();
                $this->pickerDataQuery([$pid])->where("picker_id", $pid)->delete(); // delete for this key
                $this->pickerDataQuery()->whereIn("user_id", $keys)->update(["order" => null]); // reset order for new users
                $this->pickerDataQuery()->insert($chunk->values()->toArray());

            }
        }

        if ($min !== $picker->min || $max !== $picker->max) {
            $needUpdate = true;
        }

        if ($needUpdate) {
            $this->updatePicker($key, ['min' => $min, 'max' => $max, 'hash' => $usersHash, 'registered_at' => Carbon::now()]);

            $this->resetPicker();
        }

    }


    public function status()
    {
        $pickers = ib_db("pickers")->where(['namespace' => $this->namespace])->get()->transform(function ($picker) {
            $picker->users = (array)$this->pickerDataQuery([$picker->id])
                ->select(ib_db_raw('SUM(locked) locked, SUM(picked) picked, count(*) count'))->first();

            return array_only((array)$picker, ['id', 'namespace', 'key', 'min', 'max', 'picked', 'locked', 'users', 'locked_at', 'picked_at', 'registered_at', 'hash']);
        })->toArray();

        $users = (array)$this->pickerDataQuery(collect($pickers)->pluck('id')->toArray())->select(ib_db_raw("COUNT(DISTINCT user_id) unique_users"))->first();

        return compact('users', 'pickers');

    }

    public function resetLocks()
    {
        foreach ($this->getPickers(1) as $locked) {
            $this->unLock($locked->key);
        }
    }

    public function resetPicker()
    {
        $this->updatePickers(['picked' => 0, 'picked_at' => null, 'locked_at' => null]);
        $this->query()->update(["picked" => 0]);
    }


    // run picker
    // only run it for users whereHas picker false
    public function runPicker($next = true)
    {
        if (!$this->needToRunPicker()) {
            return false;
        }

        $this->updateOrder();

        $this->resetPicker();

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
//            echo "Picked $user_id  $picker_id\n";
            $this->query()->where("picker_id", $picker_id)->where("user_id", $user_id)->update(["picked" => 1]);
        }

        $pickersFreeSlots = [];
        foreach ($pickers as $picker) {
            $pickersFreeSlots[$picker->id] = ($picker->max ?: 1000000) - $pickersToBooked[$picker->id];
        }

        if ($next) {
            $this->letTheUsersPick($pickersFreeSlots);

        }

        $this->updatePickers(['picked' => 1, 'picked_at' => Carbon::now()]);

        $this->validate();

        return true;

    }


    public function lockedUsers()
    {
        return ib_db("picker_data")->whereIn("picker_id", $this->getLockedPickerIds(1))->where("locked", 1)->select("user_id")->distinct()->pluck("user_id")->toArray();
    }

    public function availableUsers()
    {
        $pids = ib_db("pickers")->where(['namespace' => $this->namespace])->pluck("id");
        $q = ib_db("picker_data")->whereIn("picker_id", $pids)
            ->select('user_id')->groupBy('user_id')->havingRaw('SUM(locked) = 0 and SUM(picked) = 0');
        return $q->pluck('user_id')->toArray();
    }

    public function lock($key)
    {
        if ($this->needToRunPicker($key)) {
            $this->runPicker();
            $this->validate();
        }

        $userIds = $this->query($key)->where("picked", 1)->pluck("user_id")->toArray();

        $this->updatePicker($key, ['locked' => 1, 'locked_at' => Carbon::now()]);
        $this->query($key)->whereIn('user_id', $userIds)->update(['locked' => 1]);

        return $userIds;
    }

    public function unLock($key)
    {
        $this->updatePicker($key, ['locked' => 0]);
        $this->updatePickers(['picked' => 0]);
        $this->query($key)->update(['locked' => 0, 'picked' => 0]);
    }


    public function getPicker($key)
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace, 'key' => $key])->first();
    }

    private function query($key = null)
    {
        if ($key) {
            return ib_db("picker_data")->where("picker_id", $this->getPickerId($key));
        } else {
            return ib_db("picker_data")->whereIn("picker_id", $this->getUnlockedPickerIds());
        }
    }


    private function updateOrder()
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

    private function validate()
    {
        $pids = ib_db("pickers")->where(['namespace' => $this->namespace])->pluck("id");
        if (ib_db("picker_data")->whereIn("picker_id", $pids)->select('user_id')->groupBy('user_id')->havingRaw('SUM(picked) > 1')->count() > 0) {
            throw new \Exception("Validation error in picker");
        }
    }


    private function getPickers($locked = 0)
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

    private function getLockedPickerIds()
    {
        return $this->getUnlockedPickerIds(1);
    }

    private function getUnlockedPickerIds($locked = 0)
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace, 'locked' => $locked])->pluck("id")->toArray();
    }


    private function userPickQuery()
    {
        $users = $this->availableUsers();

        return $this->query()->whereIn('user_id', $users)->orderBy("order", "asc")->select("picker_id", "user_id")->get();
    }

    private function letTheUsersPick($pickersFreeSlots)
    {

        $bookedUsers = [];
        foreach ($this->userPickQuery()->chunk(1000) as $chunk) {
            foreach ($chunk as $row) {
                $user_id = $row->user_id;
                $picker_id = $row->picker_id;
                $isBooked = $bookedUsers[$user_id] ?? false;

                if ($isBooked === false && $pickersFreeSlots[$picker_id] > 0) {
                    $bookedUsers[$user_id] = $picker_id;
                    $pickersFreeSlots[$picker_id]--;
                }
            }
        }


        foreach ($bookedUsers as $user_id => $picker_id) {
            $this->query()->where("picker_id", $picker_id)->where("user_id", $user_id)->update(["picked" => 1]);
        }
    }

    private function isLocked($key)
    {
        $this->getPickerId($key);
        $picker = $this->getPicker($key);

        return $picker->locked;
    }


    private function allPids()
    {
        return ib_db("pickers")->where(['namespace' => $this->namespace])->pluck("id")->toArray();
    }

    private function pickerDataQuery(array $pids = [])
    {
        if (count($pids) === 0) {
            $pids = $this->allPids();
        }
        return ib_db("picker_data")->whereIn("picker_id", $pids);
    }
}