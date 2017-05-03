<?php

namespace Larastart\Tests\Databases;


use Larastart\DataStructures\RecipientList;
use Larastart\Testing\TestCase;

class RecipientListTest extends TestCase
{
    protected $db = 'mysql';

    public function setUp()
    {
        parent::setUp();
        ib_db("larastart_data_structure_list")->truncate();
        ib_db("larastart_data_structure_list_meta")->truncate();
    }

    public function testRemember()
    {
        $list1 = RecipientList::remember("test1", 12, function (RecipientList $list) {
            $list->append([1, 2, 3, 433]); // for larger/chunked/cursors..
        });

        $list2 = RecipientList::remember("test2", 14, function () {
            return [111, 2, 3, 12121];
        });

        $list3 = RecipientList::forever("test3", function () {
            return [111, 2, 3];
        });

        RecipientList::cleanUp();

//        $list1->length();

//        $list3->forget();

//        $list3->append([1,2,3]);

        // set: forget and append
//        $list3->set(function ($list) {
//            return [1, 2, 3];
//        });

//        $list3->set(function ($list) {
//            $list->append([1, 2, 3, 433])
//        });


        foreach ($list3->query()->cursor() as $item) {
            var_dump($item);
        }
        // object(stdClass)#532 (1) {
        //     ["key"]=>
        //     int(2)
        // }
        // object(stdClass)#510 (1) {
        //     ["key"]=>
        //     int(3)
        // }
        // object(stdClass)#532 (1) {
        //     ["key"]=>
        //     int(111)
        // }

        foreach ($list1->unionWith([$list2, $list3])->cursor() as $item) {
            var_dump($item->key);
        }
        // int(2)
        // int(3)
        // int(433)
        // int(111)
        // int(12121)

        foreach ($list1->intersectWith([$list2, $list3])->cursor() as $item) {
            var_dump($item->key);
        }
        // int(2)
        // int(3)

    }
}