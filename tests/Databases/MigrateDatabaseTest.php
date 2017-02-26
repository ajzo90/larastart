<?php
namespace Larastart\Tests\Databases;


use Illuminate\Database\Eloquent\Model;
use Larastart\Configurable\Configurable;

class User extends Model
{
    use Configurable;
    protected $fillable = ['name'];
}

class MigrateDatabaseTest extends TestCase
{
    public function testRunningMigration()
    {
        User::create(['name' => 'Christian']);
        $savedUser = User::where('id', '=', 1)->first();
        $this->assertEquals(1, User::count());
        $this->assertEquals('Christian', $savedUser->name);
    }

    public function test_it_sets_and_get_configuration()
    {
        $this->assertEquals(0, User::count());
        $user = User::create(['name' => 'Christian']);
        $this->assertEquals(1, User::count());
        $user2 = User::create(['name' => "test"]);
        $this->assertEquals(2, User::count());

        $user->setConfig("someKey", "someValue");
        $this->assertEquals("someValue", $user->getConfig("someKey"));

        $user->setConfig("hello", "world123");
        $this->assertEquals(["hello" => "world123", "someKey" => "someValue"], $user->getConfig());

        $user2->setConfig("foo", "bar");
        $this->assertEquals("bar", $user2->getConfig("foo"));
        $this->assertEquals("world123", $user->getConfig("hello"));
        $this->assertNotEquals("world123", $user2->getConfig("hello"));

    }

    public function test_recursive_config()
    {
        $user = User::create(['name' => 'Christian']);

        $user->setConfig(['test' => 'hello']);

        $user->setConfig(['foo' => ['bar' => 'baz']]);

        $this->assertEquals(['test' => 'hello', 'foo' => ['bar' => 'baz']], $user->getConfig());

        $user->setConfig(['foo' => ['bars' => 'bazz']]);

        $this->assertEquals(['test' => 'hello', 'foo' => ['bar' => 'baz', 'bars' => 'bazz']], $user->getConfig());

        $this->assertEquals('bazz', $user->getConfig('foo.bars'));

    }

    public function testRoute()
    {
        $this->withoutMiddleware();
        $crawler = $this->json('GET', 'api/v1/larastart/echo?a=1');
        $this->assertEquals(json_encode(['a' => '1']), $crawler->getContent());
    }
}