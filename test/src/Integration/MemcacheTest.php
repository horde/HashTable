<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  HashTable
 */

namespace Horde\HashTable\Test\Src\Integration;

use Horde\HashTable\Driver\Memcache;
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\Memcache\MemcacheApi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Memcache HashTable driver.
 *
 * Requires a running Memcache server on localhost:11211.
 */
#[CoversClass(Memcache::class)]
#[Group('integration')]
final class MemcacheTest extends TestCase
{
    private static MemcacheApi $memcacheApi;
    private Memcache $ht;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists(MemcacheApi::class)) {
            self::markTestSkipped('horde/memcache not installed');
        }

        if (!extension_loaded('memcache') && !extension_loaded('memcached')) {
            self::markTestSkipped('memcache or memcached extension not available');
        }

        try {
            self::$memcacheApi = new MemcacheApi([
                'hostspec' => ['localhost'],
                'port' => [11211],
                'prefix' => 'hht_integration_test',
            ]);
        } catch (\Exception $e) {
            self::markTestSkipped('Memcache server not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        $this->ht = new Memcache(self::$memcacheApi, prefix: 'mc_test_');
        $this->ht->clear();
    }

    protected function tearDown(): void
    {
        $this->ht->clear();
    }

    #[Test]
    public function implementsHashTable(): void
    {
        $this->assertInstanceOf(HashTable::class, $this->ht);
    }

    #[Test]
    public function implementsLockableHashTable(): void
    {
        $this->assertInstanceOf(LockableHashTable::class, $this->ht);
    }

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
    public function setOverwritesExistingKey(): void
    {
        $this->ht->set('key', 'first');
        $this->ht->set('key', 'second');
        $this->assertSame('second', $this->ht->get('key'));
    }

    #[Test]
    public function ttlExpiresKeys(): void
    {
        $this->ht->set('expires', 'val', ttl: 1);
        $this->assertSame('val', $this->ht->get('expires'));
        sleep(2);
        $this->assertNull($this->ht->get('expires'));
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
    public function existsReturnsTrueForStoredKey(): void
    {
        $this->ht->set('key', 'val');
        $this->assertTrue($this->ht->exists('key'));
    }

    #[Test]
    public function existsReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->ht->exists('ghost'));
    }

    #[Test]
    public function deleteRemovesKey(): void
    {
        $this->ht->set('key', 'val');
        $this->ht->delete('key');
        $this->assertFalse($this->ht->exists('key'));
    }

    #[Test]
    public function deleteAcceptsArrayOfKeys(): void
    {
        $this->ht->set('a', '1');
        $this->ht->set('b', '2');
        $this->ht->delete(['a', 'b']);
        $this->assertFalse($this->ht->exists('a'));
        $this->assertFalse($this->ht->exists('b'));
    }

    #[Test]
    public function getMultipleReturnsMixedResults(): void
    {
        $this->ht->set('a', 'va');
        $this->ht->set('b', 'vb');
        $result = $this->ht->getMultiple(['a', 'b', 'c']);
        $this->assertSame('va', $result['a']);
        $this->assertSame('vb', $result['b']);
        $this->assertNull($result['c']);
    }

    #[Test]
    public function existsMultipleReturnsMixedResults(): void
    {
        $this->ht->set('a', 'val');
        $result = $this->ht->existsMultiple(['a', 'b']);
        $this->assertTrue($result['a']);
        $this->assertFalse($result['b']);
    }

    #[Test]
    public function clearRemovesAllKeys(): void
    {
        $this->ht->set('a', '1');
        $this->ht->set('b', '2');
        $this->ht->clear();
        $this->assertNull($this->ht->get('a'));
        $this->assertNull($this->ht->get('b'));
    }

    #[Test]
    public function lockAndUnlock(): void
    {
        $this->ht->lock('resource');
        $this->ht->unlock('resource');
        $this->assertTrue(true);
    }
}
