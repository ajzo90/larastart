<?php
/**
 * Created by PhpStorm.
 * User: christianpersson
 * Date: 26/02/17
 * Time: 20:10
 */

namespace Larastart\Tests\Stubs;


use Illuminate\Support\ServiceProvider;

class TestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../Stubs/migrations'));
    }

}