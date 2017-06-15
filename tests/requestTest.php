<?php


class requestTest extends \Larastart\Testing\TestCase
{
    function test_request()
    {
        dd(ib_get_json("http://ip.jsontest.com/"));

    }

}