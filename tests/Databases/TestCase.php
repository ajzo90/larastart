<?php
namespace Larastart\Tests\Databases;

use Larastart\Providers\LarastartServiceProvider;
use Larastart\Tests\Stubs\TestServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--database' => 'testing'
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            LarastartServiceProvider::class,
            TestServiceProvider::class
        ];
    }

}