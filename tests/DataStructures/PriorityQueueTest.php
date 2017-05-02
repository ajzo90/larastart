<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 27/02/17
 * Time: 11:33
 */

namespace Larastart\Tests\Databases;


use Larastart\DataStructures\PriorityQueue;
use Larastart\Testing\TestCase;

class PriorityQueueTestTest extends TestCase
{
    protected $db = 'mysql';

    public function setUp()
    {
        parent::setUp();
        ib_db("larastart_priority_queue")->truncate();
    }

    public function testQueueExists()
    {
        $q = new PriorityQueue();
        $id = $q->getId();
        $this->assertTrue(PriorityQueue::queueExists($id));
    }

    public function test_it_returns_correct_top_value()
    {
        $q = new PriorityQueue();
        $q->insertIgnore([1 => 12, 3 => 16, 2 => 11]);
        $q->insertReplace([1 => 10]);

//        dd($q->topValues(10));

        $q->processTop(2, function ($items) {
//            dd($items);
        });

        dd($q->topValues(10));
    }


}