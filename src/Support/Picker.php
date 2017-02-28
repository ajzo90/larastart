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
        $picker = $this->getPicker($key);

        if ($this->isLocked($key)) {
            throw new \Exception("This key is locked");
        }

        $usersHash = md5(json_encode($users));

        $needUpdate = false;

        $dataToUpdate = [];

        if ($picker->hash !== $usersHash && $users !== null) {
            $needUpdate = true;

            foreach ($users as $userId => $rank) {
                $dataToUpdate[$userId] = ["rank" => $rank, "user_id" => $userId, "picker_id" => $picker->id, "picked" => 0, "order" => null];
            }

            foreach (collect($dataToUpdate)->chunk(5000) as $chunk) {
                $keys = $chunk->keys();
                $this->pickerDataQuery([$picker->id])->delete(); // delete for this key
                $this->pickerDataQuery($this->allPids())->whereIn("user_id", $keys)->update(["order" => null]); // reset order for new users
                $this->pickerDataQuery($this->allPids())->insert($chunk->values()->toArray());
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
        $this->pickerDataQuery()->update(["picked" => 0]);
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
        $pickersVelocity = [];
        $pickersToBooked = [];
        $bookedUsers = [];

        foreach ($pickers as $picker) {
            // notice the order. It is like this: [{order: 2, rank: 1}, {order:2, rank:2}, {order:1, rank: 2}, {order: 1, rank: 1}] The lowest order and the highest rank in the end.
            // This is because the array is traversed with pop operations.
            $pickersToUsers[$picker->key] = $this->pickerDataQuery([$picker->id])->orderBy("order", "desc")->orderBy("rank", "asc")->pluck("user_id")->toArray();
            $pickersVelocity[$picker->key] = $picker->min / max($max, 1);
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
                $toPick = floor($i * $pickersVelocity[$picker->key]);
                $toPickThisLoop = min($toPick, $picker->min) - $pickersToBooked[$picker->id];
                $maxIterations = count($pickersToUsers[$picker->key]);
                $picked = 0;
                $iter = 0;

                while ($picked < $toPickThisLoop && $iter < $maxIterations) {
                    $run = true; // continue the outer loop as long the inner loop is visited
                    $iter++; // iterate while the $pickersToUsers array is not empty
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
            $this->pickerDataQuery([$picker_id])->where("user_id", $user_id)->update(["picked" => 1]);
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
        return $this->pickerDataQuery($this->lockedPids())->where("locked", 1)->select("user_id")->distinct()->pluck("user_id")->toArray();
    }

    public function availableUsers()
    {
        return $this->pickerDataQuery($this->allPids())
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('SUM(locked) = 0 and SUM(picked) = 0')
            ->pluck('user_id')
            ->toArray();
    }

    public function lock($key)
    {
        if ($this->needToRunPicker($key)) {
            $this->runPicker();
            $this->validate();
        }

        $pids = [$this->getPicker($key)->id];
        $userIds = $this->pickerDataQuery($pids)->where("picked", 1)->pluck("user_id")->toArray();

        $this->updatePicker($key, ['locked' => 1, 'locked_at' => Carbon::now()]);
        $this->pickerDataQuery($pids)->whereIn('user_id', $userIds)->update(['locked' => 1]);

        return $userIds;
    }

    public function unLock($key)
    {
        $this->updatePicker($key, ['locked' => 0]);
        $this->updatePickers(['picked' => 0]);
        $this->pickerDataQuery([$this->getPicker($key)->id])->update(['locked' => 0, 'picked' => 0]);
    }


    private function updateOrder()
    {
        $users = $this->pickerDataQuery()->whereNull("order")->orderBy("rank", "desc")->select(["user_id", "picker_id", "rank"])->get()->groupBy("user_id");
        $toInsert = [];
        foreach ($users as $userId => $userData) {
            $i = 1;
            foreach ($userData as $row) {
                $toInsert[] = ["user_id" => $userId, "picker_id" => $row->picker_id, "rank" => $row->rank, "order" => $i++];
            }
        }

        \DB::transaction(function () use ($toInsert) {
            $this->pickerDataQuery()->whereNull("order")->delete();
            $this->pickerDataQuery()->insert($toInsert);
        });
    }

    private function validate()
    {
        if ($this->pickerDataQuery($this->allPids())->select('user_id')->groupBy('user_id')->havingRaw('SUM(picked) > 1')->count() > 0) {
            throw new \Exception("Validation error in picker");
        }
    }


    private function getPickers($locked = 0)
    {
        return $this->pickerQuery()->where(compact('locked'))->get();
    }

    private function updatePicker($key, $data)
    {
        $this->pickerQuery()->where(compact('key'))->update($data);
    }

    private function updatePickers($data, $locked = 0)
    {
        $this->pickerQuery()->where(compact('locked'))->update($data);
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
            $this->pickerDataQuery([$picker_id])->where("user_id", $user_id)->update(["picked" => 1]);
        }
    }

    private function isLocked($key)
    {
        return $this->getPicker($key)->locked;
    }

    private function lockedPids()
    {
        return $this->unlockedPids(1);
    }

    private function unlockedPids($locked = 0)
    {
        return $this->pickerQuery()->where(compact('locked'))->pluck("id")->toArray();
    }

    private function allPids()
    {
        return $this->pickerQuery()->pluck("id")->toArray();
    }


    private function userPickQuery()
    {
        return $this->pickerDataQuery()->whereIn('user_id', $this->availableUsers())->orderBy("order", "asc")->select("picker_id", "user_id")->get();
    }

    public function getPicker($key)
    {
        $picker = $this->pickerQuery()->where(compact('key'))->first();
        if ($picker) {
            return $picker;
        } else {
            ib_db("pickers")->insertGetId(['namespace' => $this->namespace, 'key' => $key]);
            return $this->getPicker($key);
        }
    }

    private function pickerQuery()
    {
        return ib_db("pickers")->where('namespace', $this->namespace);
    }

    private function pickerDataQuery(array $pids = [])
    {
        if (count($pids) === 0) {
            $pids = $this->unlockedPids();
        }
        return ib_db("picker_data")->whereIn("picker_id", $pids);
    }

}