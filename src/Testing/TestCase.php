<?php
namespace Larastart\Testing;

use Larastart\Providers\LarastartServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $db = 'sqlite';

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
        if ($this->db === 'sqlite') {
            $app['config']->set('database.default', 'testing');
            $app['config']->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);
        } else if ($this->db === 'mysql') {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.testing', [
                'driver' => 'mysql',
                'username' => 'homestead',
                'host' => '127.0.0.1',
                'port' => '33061',
                'database' => 'phpunit',
                'password' => 'secret'
            ]);
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            LarastartServiceProvider::class,
            TestServiceProvider::class
        ];
    }

}