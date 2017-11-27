<?php


class databaseTest extends \Larastart\Testing\TestCase
{
    protected $db = 'mysql';

    function test_request()
    {
        ib_db_statement("INSERT INTO test(name) VALUES (?)", ["name"]);
        ib_db_initiate("test", ["name1", "name2"], "name");
        ib_db_insert_ignore("test", [["name" => "1231", "id" => 123], ["name" => "11", "id" => 1]]);
    }

}


