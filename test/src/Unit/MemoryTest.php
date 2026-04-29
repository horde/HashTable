<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit;

use Horde\HashTable\Driver\Memory;
use Horde\HashTable\HashTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Memory::class)]
final class MemoryTest extends TestCase
{
    private Memory $ht;

    protected function setUp(): void
    {
        $this->ht = new Memory(prefix: 'test_');
    }

    #[Test]
    public function implementsHashTable(): void
    {
        $this->assertInstanceOf(HashTable::class, $this->ht);
    }

    #[Test]
    public function getReturnsNullOnMiss(): void
    {
        $this->assertNull($this->ht->get('nonexistent'));
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
        $this->ht->set('array', ['a' => 1, 'b' => [2, 3]]);
        $this->ht->set('bool', true);
        $this->ht->set('null_val', null);

        $this->assertSame(42, $this->ht->get('int'));
        $this->assertSame(['a' => 1, 'b' => [2, 3]], $this->ht->get('array'));
        $this->assertTrue($this->ht->get('bool'));
        $this->assertNull($this->ht->get('null_val'));
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
    public function replaceSucceedsOnExistingKey(): void
    {
        $this->ht->set('key', 'original');
        $this->assertTrue($this->ht->replace('key', 'updated'));
        $this->assertSame('updated', $this->ht->get('key'));
    }

    #[Test]
    public function replaceFailsOnMissingKey(): void
    {
        $this->assertFalse($this->ht->replace('ghost', 'value'));
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
    public function existsReturnsFalseForExpiredKey(): void
    {
        $this->ht->set('key', 'val', ttl: 1);
        sleep(2);
        $this->assertFalse($this->ht->exists('key'));
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
        $this->ht->set('a', 'val_a');
        $this->ht->set('b', 'val_b');

        $result = $this->ht->getMultiple(['a', 'b', 'c']);

        $this->assertSame('val_a', $result['a']);
        $this->assertSame('val_b', $result['b']);
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
    public function prefixIsolatesInstances(): void
    {
        $ht1 = new Memory(prefix: 'ns1_');
        $ht2 = new Memory(prefix: 'ns2_');

        $ht1->set('key', 'from_ns1');
        $ht2->set('key', 'from_ns2');

        $this->assertSame('from_ns1', $ht1->get('key'));
        $this->assertSame('from_ns2', $ht2->get('key'));
    }
}
