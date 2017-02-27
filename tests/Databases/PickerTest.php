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
        $this->assertEquals([], $picker->users("fafsdafd"));
        $this->assertEquals([1, 3], array_keys($picker->users("c1")));
        $this->assertEquals([1, 2], array_keys($picker->users("c2")));

        $this->assertEquals(null, $picker->getPicker('c1')->min);
        $this->assertEquals(null, $picker->getPicker('c1')->max);

        $this->assertEquals(1, $picker->getPicker('c2')->min);
        $this->assertEquals(10, $picker->getPicker('c2')->max);

    }

    public function testPickAfterPick()
    {
        $picker = new Picker("hello");
        $picker->register("c1", [1 => 5, 3 => 1.0], 1);
        $picker->register("c2", [1 => 1.3, 2 => 1.2, 4 => 1.3, 5 => 1.4], 4, 10);

    }

    public function testLock()
    {
        $picker = new Picker("test");
        $picker->register("t1", [1 => 1], 1);
        $this->assertEquals([1], $picker->pick("t1"));
        $this->assertEquals([1], $picker->lock("t1"));
        $this->assertEquals([1], $picker->lockedUsers());

        $picker->register("t2", [1 => 12], 1);
        $this->assertEquals([1], $picker->lockedUsers());

        $this->assertEquals([], $picker->pick("t2"));
        $this->assertEquals([1], $picker->pick("t1"));
    }
}