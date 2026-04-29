<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Integration;

use Horde\HashTable\Driver\Redis;
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\HashTable\LockTimeoutException;
use Horde\HashTable\RedisHashTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Redis as PhpRedis;

#[CoversClass(Redis::class)]
#[Group('integration')]
final class RedisPhpRedisTest extends TestCase
{
    private static PhpRedis $phpRedis;
    private Redis $ht;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis not available');
        }

        self::$phpRedis = new PhpRedis();
        if (!@self::$phpRedis->connect('127.0.0.1', 6379)) {
            self::markTestSkipped('Redis server not available on 127.0.0.1:6379');
        }
        self::$phpRedis->select(15);
    }

    protected function setUp(): void
    {
        $this->ht = new Redis(self::$phpRedis, prefix: 'phpredis_test_');
        $this->ht->clear();
    }

    protected function tearDown(): void
    {
        $this->ht->clear();
    }

    // --- Interface Compliance ---

    #[Test]
    public function implementsAllInterfaces(): void
    {
        $this->assertInstanceOf(HashTable::class, $this->ht);
        $this->assertInstanceOf(LockableHashTable::class, $this->ht);
        $this->assertInstanceOf(RedisHashTable::class, $this->ht);
    }

    // --- Core KV ---

    #[Test]
    public function setAndGetString(): void
    {
        $this->ht->set('key', 'value');
        $this->assertSame('value', $this->ht->get('key'));
    }

    #[Test]
    public function setAndGetMixedValues(): void
    {
        $this->ht->set('int', 42);
        $this->ht->set('array', ['nested' => [1, 2, 3]]);
        $this->ht->set('bool', false);

        $this->assertSame(42, $this->ht->get('int'));
        $this->assertSame(['nested' => [1, 2, 3]], $this->ht->get('array'));
        $this->assertFalse($this->ht->get('bool'));
    }

    #[Test]
    public function getReturnsNullOnMiss(): void
    {
        $this->assertNull($this->ht->get('nonexistent'));
    }

    #[Test]
    public function setWithTtl(): void
    {
        $this->ht->set('expires', 'val', ttl: 1);
        $this->assertSame('val', $this->ht->get('expires'));
        sleep(2);
        $this->assertNull($this->ht->get('expires'));
    }

    #[Test]
    public function replaceExistingKey(): void
    {
        $this->ht->set('key', 'original');
        $this->assertTrue($this->ht->replace('key', 'updated'));
        $this->assertSame('updated', $this->ht->get('key'));
    }

    #[Test]
    public function replaceNonExistingKeyReturnsFalse(): void
    {
        $this->assertFalse($this->ht->replace('ghost', 'value'));
    }

    #[Test]
    public function deleteRemovesKeys(): void
    {
        $this->ht->set('a', '1');
        $this->ht->set('b', '2');
        $this->ht->delete(['a', 'b']);
        $this->assertNull($this->ht->get('a'));
        $this->assertNull($this->ht->get('b'));
    }

    #[Test]
    public function existsChecksPresence(): void
    {
        $this->ht->set('key', 'val');
        $this->assertTrue($this->ht->exists('key'));
        $this->assertFalse($this->ht->exists('ghost'));
    }

    #[Test]
    public function getMultiple(): void
    {
        $this->ht->set('a', 'va');
        $this->ht->set('b', 'vb');

        $result = $this->ht->getMultiple(['a', 'b', 'c']);
        $this->assertSame('va', $result['a']);
        $this->assertSame('vb', $result['b']);
        $this->assertNull($result['c']);
    }

    #[Test]
    public function existsMultiple(): void
    {
        $this->ht->set('a', '1');

        $result = $this->ht->existsMultiple(['a', 'b']);
        $this->assertTrue($result['a']);
        $this->assertFalse($result['b']);
    }

    #[Test]
    public function clearRemovesOnlyPrefixedKeys(): void
    {
        $this->ht->set('mykey', 'val');

        // Set a key outside our prefix directly
        self::$phpRedis->set('outside_key', 'survives');

        $this->ht->clear();

        $this->assertNull($this->ht->get('mykey'));
        $this->assertSame('survives', self::$phpRedis->get('outside_key'));

        self::$phpRedis->del('outside_key');
    }

    // --- Locking ---

    #[Test]
    public function lockAndUnlock(): void
    {
        $this->ht->lock('resource');
        $this->ht->unlock('resource');
        $this->assertTrue(true);
    }

    // --- Atomic Counters ---

    #[Test]
    public function incrementFromZero(): void
    {
        $this->assertSame(1, $this->ht->increment('counter'));
        $this->assertSame(2, $this->ht->increment('counter'));
    }

    #[Test]
    public function incrementByAmount(): void
    {
        $this->assertSame(5, $this->ht->increment('counter', 5));
        $this->assertSame(8, $this->ht->increment('counter', 3));
    }

    #[Test]
    public function decrementFromZero(): void
    {
        $this->assertSame(-1, $this->ht->decrement('counter'));
    }

    #[Test]
    public function decrementByAmount(): void
    {
        $this->ht->increment('counter', 10);
        $this->assertSame(7, $this->ht->decrement('counter', 3));
    }

    // --- TTL Introspection ---

    #[Test]
    public function ttlReturnsRemainingSeconds(): void
    {
        $this->ht->set('key', 'val', ttl: 60);
        $ttl = $this->ht->ttl('key');
        $this->assertNotNull($ttl);
        $this->assertGreaterThan(50, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }

    #[Test]
    public function ttlReturnsNullForNonExistentKey(): void
    {
        $this->assertNull($this->ht->ttl('ghost'));
    }

    #[Test]
    public function ttlReturnsNullForKeyWithoutExpiry(): void
    {
        $this->ht->set('persistent', 'val');
        $this->assertNull($this->ht->ttl('persistent'));
    }

    // --- Hash Operations ---

    #[Test]
    public function hashSetAndGet(): void
    {
        $this->ht->hashSet('h', 'field', 'value');
        $this->assertSame('value', $this->ht->hashGet('h', 'field'));
    }

    #[Test]
    public function hashGetReturnsNullOnMiss(): void
    {
        $this->assertNull($this->ht->hashGet('h', 'nope'));
    }

    #[Test]
    public function hashGetAll(): void
    {
        $this->ht->hashSet('h', 'f1', 'v1');
        $this->ht->hashSet('h', 'f2', 'v2');
        $this->assertSame(['f1' => 'v1', 'f2' => 'v2'], $this->ht->hashGetAll('h'));
    }

    #[Test]
    public function hashGetAllReturnsEmptyArrayOnMiss(): void
    {
        $this->assertSame([], $this->ht->hashGetAll('nonexistent'));
    }

    #[Test]
    public function hashDelete(): void
    {
        $this->ht->hashSet('h', 'f1', 'v1');
        $this->ht->hashSet('h', 'f2', 'v2');
        $this->ht->hashDelete('h', 'f1');
        $this->assertNull($this->ht->hashGet('h', 'f1'));
        $this->assertSame('v2', $this->ht->hashGet('h', 'f2'));
    }

    #[Test]
    public function hashDeleteMultipleFields(): void
    {
        $this->ht->hashSet('h', 'f1', 'v1');
        $this->ht->hashSet('h', 'f2', 'v2');
        $this->ht->hashSet('h', 'f3', 'v3');
        $this->ht->hashDelete('h', ['f1', 'f2']);
        $this->assertNull($this->ht->hashGet('h', 'f1'));
        $this->assertNull($this->ht->hashGet('h', 'f2'));
        $this->assertSame('v3', $this->ht->hashGet('h', 'f3'));
    }

    // --- List Operations ---

    #[Test]
    public function listPushAndPop(): void
    {
        $this->ht->listPush('list', 'a');
        $this->ht->listPush('list', 'b');
        $this->ht->listPush('list', 'c');

        $this->assertSame('a', $this->ht->listPop('list'));
        $this->assertSame('b', $this->ht->listPop('list'));
        $this->assertSame('c', $this->ht->listPop('list'));
    }

    #[Test]
    public function listPopReturnsNullOnEmpty(): void
    {
        $this->assertNull($this->ht->listPop('empty'));
    }

    #[Test]
    public function listRange(): void
    {
        $this->ht->listPush('list', 'a');
        $this->ht->listPush('list', 'b');
        $this->ht->listPush('list', 'c');

        $this->assertSame(['a', 'b', 'c'], $this->ht->listRange('list'));
        $this->assertSame(['b', 'c'], $this->ht->listRange('list', 1));
        $this->assertSame(['a', 'b'], $this->ht->listRange('list', 0, 1));
    }

    #[Test]
    public function listRangeReturnsEmptyOnMiss(): void
    {
        $this->assertSame([], $this->ht->listRange('nonexistent'));
    }

    #[Test]
    public function listPushReturnsLength(): void
    {
        $this->assertSame(1, $this->ht->listPush('list', 'a'));
        $this->assertSame(2, $this->ht->listPush('list', 'b'));
    }

    // --- Set Operations ---

    #[Test]
    public function setAddAndMembers(): void
    {
        $this->ht->setAdd('s', 'x', 'y', 'z');
        $members = $this->ht->setMembers('s');
        sort($members);
        $this->assertSame(['x', 'y', 'z'], $members);
    }

    #[Test]
    public function setAddReturnsDedupedCount(): void
    {
        $this->assertSame(3, $this->ht->setAdd('s', 'a', 'b', 'c'));
        $this->assertSame(0, $this->ht->setAdd('s', 'a'));
        $this->assertSame(1, $this->ht->setAdd('s', 'd'));
    }

    #[Test]
    public function setIsMember(): void
    {
        $this->ht->setAdd('s', 'x', 'y');
        $this->assertTrue($this->ht->setIsMember('s', 'x'));
        $this->assertFalse($this->ht->setIsMember('s', 'z'));
    }

    #[Test]
    public function setRemove(): void
    {
        $this->ht->setAdd('s', 'x', 'y', 'z');
        $this->assertSame(1, $this->ht->setRemove('s', 'y'));
        $members = $this->ht->setMembers('s');
        sort($members);
        $this->assertSame(['x', 'z'], $members);
    }

    #[Test]
    public function setMembersReturnsEmptyOnMiss(): void
    {
        $this->assertSame([], $this->ht->setMembers('nonexistent'));
    }
}
