<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 27/02/17
 * Time: 11:33
 */

namespace Larastart\Tests\Databases;


use Larastart\Testing\TestCase;

class ArrayTest extends TestCase
{
    public function testItReturnSameHashForArrayWithEqualContent()
    {
        $hash1 = ib_array_hash(["a" => 1, "b" => 2]);
        $hash2 = ib_array_hash(["a" => 1, "b" => 2]);
        $this->assertEquals($hash1, $hash2);
    }

    public function testItReturnSameHashForArrayWithEqualContentUnsorted()
    {
        $hash1 = ib_array_hash(["a" => 1, "b" => 2]);
        $hash2 = ib_array_hash(["b" => 2, "a" => 1]);
        $this->assertEquals($hash1, $hash2);
    }

    public function testItReturnSameHashForArrayWithEqualContentUnsortedNested()
    {
        $hash1 = ib_array_hash(["a" => ["c" => 1, "d" => "2"], "b" => 2]);
        $hash2 = ib_array_hash(["b" => 2, "a" => ["d" => "2", "c" => 1]]);
        $this->assertEquals($hash1, $hash2);
    }

}