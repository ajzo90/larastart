<?php
namespace Larastart\Providers;

use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class LarastartServiceProvider extends ServiceProvider
{
    public function boot(Registrar $router)
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(realpath(__DIR__ . '/../../database/migrations'));
        }

        $router->group(["middleware" => ['auth:api', 'api'], "prefix" => "api/v1/larastart"], function (Registrar $router) {
            $router->get('echo', function (Request $request) {
                return $request->all();
            });
        });
    }
}