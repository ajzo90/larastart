<?php
namespace Larastart\Providers;

use Illuminate\Support\ServiceProvider;

class LarastartServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../../database/migrations'));
    }
}