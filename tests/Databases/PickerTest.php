<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 27/02/17
 * Time: 11:33
 */

namespace Larastart\Tests\Databases;


use Larastart\Support\Picker;

class PickerTest extends TestCase
{
    public function testRegister()
    {
        $picker = new Picker("hello");
        $picker->register("c1", [1 => 1.2, 3 => 1.2]);
        $picker->register("c2", [1 => 1.4, 2 => 3], 1, 10);

        $this->assertEquals([], $picker->lockedUsers());

        $this->assertEquals(null, $picker->getPicker('c1')->min);
        $this->assertEquals(null, $picker->getPicker('c1')->max);

        $this->assertEquals(1, $picker->getPicker('c2')->min);
        $this->assertEquals(10, $picker->getPicker('c2')->max);

    }

    public function testPickAfterPick()
    {
        $picker = new Picker("hello");
        $picker->register("c1", [1 => 5, 3 => 1.0], 1);
        $this->assertEquals([1, 3], $picker->availableUsers());
        $this->assertEquals([1, 3], $picker->lock("c1"));
        $picker->register("c2", [1 => 1.3, 2 => 1.2, 4 => 1.3, 5 => 1.4], 4, 10);
        $this->assertEquals([5, 4, 2], $picker->lock("c2"));

    }

    public function testLock()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1], 1);
        $this->assertEquals([1], $picker->lock("t1"));
        $this->assertEquals([1], $picker->lockedUsers());

        $picker->register("t2", [1 => 12], 1);
        $this->assertEquals([1], $picker->lockedUsers());

        $this->assertEquals([], $picker->availableUsers());

        $this->assertEquals([], $picker->lock("t2"));
        $this->assertEquals([1], $picker->lock("t1"));
    }

    public function testUnlock()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1, 2 => 2], 1);
        $this->assertEquals([2, 1], $picker->lock("t1"));
        $this->assertEquals([2, 1], $picker->lock("t1"));
        $this->assertEquals([2, 1], $picker->lockedUsers());

        $picker->unLock("t1");
        $this->assertEquals([], $picker->lockedUsers());
        $this->assertEquals([2, 1], $picker->lock("t1"));

        $picker->register("t2", [1 => 12, 3 => 1], 1);
        $this->assertEquals([2, 1], $picker->lock("t1"));
        $this->assertEquals([3], $picker->lock("t2"));

        $locked = $picker->lockedUsers();
        sort($locked);
        $this->assertEquals([1, 2, 3], $locked);

        $picker->register("t3", [1 => 12, 3 => 6, 2 => 1, 4 => 2]);
        $this->assertEquals([4], $picker->lock("t3"));

    }

    public function testMoreUnlocks()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1, 2 => 2, 3 => 3, 4 => 4], 1);
        $this->assertEquals([4, 3, 2, 1], $picker->lock("t1"));
        $picker->register("t2", [1 => 5, 2 => 4], 1);
        $this->assertEquals([], $picker->lock("t2"));
        $picker->unLock("t1");
        $this->assertEquals([], $picker->lock("t2"));
        $picker->unLock("t2");
        $this->assertEquals([], $picker->lockedUsers());
        $this->assertTrue($picker->needToRunPicker("t2"));
        $this->assertEquals([1, 2, 3, 4], $picker->availableUsers());
        $picker->runPicker(false);
        $this->assertFalse($picker->needToRunPicker());
        $this->assertEquals([4], $picker->lock("t1"));
        $this->assertEquals([1], $picker->lock("t2"));

        $picker->resetLocks();
        $this->assertTrue($picker->needToRunPicker());
        $this->assertTrue($picker->runPicker());
        $this->assertEquals([4, 3], $picker->lock("t1"));
        $this->assertEquals([1, 2], $picker->lock("t2"));
        $this->assertFalse($picker->runPicker());


    }

    public function testStatus()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1, 2 => 2, 3 => 3, 4 => 4], 1);
        $picker->register("t2", [1 => 5, 2 => 4], 1);
        $picker->lock("t1");
        $picker->lock("t2");
        $this->assertArrayHasKey('users', $picker->status());
        $this->assertArrayHasKey('pickers', $picker->status());
    }

    public function testRunPicker()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1, 2 => 2, 3 => 3, 4 => 4], 1);
        $picker->register("t2", [1 => 5, 2 => 4], 1);
        $picker->runPicker();
    }
}