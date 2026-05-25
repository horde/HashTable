<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Integration;

use Horde\HashTable\Driver\Redis;
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\HashTable\RedisHashTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client as PredisClient;
use Exception;

#[CoversClass(Redis::class)]
#[Group('integration')]
final class RedisPredisTest extends TestCase
{
    private static PredisClient $predis;
    private Redis $ht;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(PredisClient::class)) {
            self::markTestSkipped('predis/predis not installed');
        }

        try {
            self::$predis = new PredisClient([
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 15,
            ]);
            self::$predis->ping();
        } catch (Exception $e) {
            self::markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        $this->ht = new Redis(self::$predis, prefix: 'predis_test_');
        $this->ht->clear();
    }

    protected function tearDown(): void
    {
        $this->ht->clear();
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
        $this->ht->set('array', ['a' => 'b']);

        $this->assertSame(42, $this->ht->get('int'));
        $this->assertSame(['a' => 'b'], $this->ht->get('array'));
    }

    #[Test]
    public function getReturnsNullOnMiss(): void
    {
        $this->assertNull($this->ht->get('ghost'));
    }

    #[Test]
    public function replaceExistingKey(): void
    {
        $this->ht->set('key', 'old');
        $this->assertTrue($this->ht->replace('key', 'new'));
        $this->assertSame('new', $this->ht->get('key'));
    }

    #[Test]
    public function replaceNonExistingReturnsFalse(): void
    {
        $this->assertFalse($this->ht->replace('ghost', 'val'));
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
    public function deleteAndExists(): void
    {
        $this->ht->set('key', 'val');
        $this->assertTrue($this->ht->exists('key'));
        $this->ht->delete('key');
        $this->assertFalse($this->ht->exists('key'));
    }

    // --- Locking ---

    #[Test]
    public function lockAndUnlock(): void
    {
        $this->ht->lock('res');
        $this->ht->unlock('res');
        $this->assertTrue(true);
    }

    // --- Counters ---

    #[Test]
    public function incrementAndDecrement(): void
    {
        $this->assertSame(1, $this->ht->increment('ctr'));
        $this->assertSame(6, $this->ht->increment('ctr', 5));
        $this->assertSame(4, $this->ht->decrement('ctr', 2));
    }

    // --- Hash ---

    #[Test]
    public function hashOperations(): void
    {
        $this->ht->hashSet('h', 'f1', 'v1');
        $this->ht->hashSet('h', 'f2', 'v2');
        $this->assertSame('v1', $this->ht->hashGet('h', 'f1'));
        $this->assertSame(['f1' => 'v1', 'f2' => 'v2'], $this->ht->hashGetAll('h'));
        $this->ht->hashDelete('h', 'f1');
        $this->assertNull($this->ht->hashGet('h', 'f1'));
    }

    // --- List ---

    #[Test]
    public function listOperations(): void
    {
        $this->assertSame(1, $this->ht->listPush('l', 'a'));
        $this->assertSame(2, $this->ht->listPush('l', 'b'));
        $this->assertSame(['a', 'b'], $this->ht->listRange('l'));
        $this->assertSame('a', $this->ht->listPop('l'));
        $this->assertSame(['b'], $this->ht->listRange('l'));
    }

    #[Test]
    public function listPopReturnsNullOnEmpty(): void
    {
        $this->assertNull($this->ht->listPop('empty'));
    }

    // --- Set ---

    #[Test]
    public function setOperations(): void
    {
        $this->assertSame(3, $this->ht->setAdd('s', 'x', 'y', 'z'));
        $this->assertSame(0, $this->ht->setAdd('s', 'x'));
        $this->assertTrue($this->ht->setIsMember('s', 'x'));
        $this->assertFalse($this->ht->setIsMember('s', 'w'));
        $members = $this->ht->setMembers('s');
        sort($members);
        $this->assertSame(['x', 'y', 'z'], $members);
        $this->assertSame(1, $this->ht->setRemove('s', 'y'));
    }
}
