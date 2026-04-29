<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit;

use Horde\HashTable\Driver\NullDriver;
use Horde\HashTable\HashTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullDriver::class)]
final class NullDriverTest extends TestCase
{
    private NullDriver $ht;

    protected function setUp(): void
    {
        $this->ht = new NullDriver();
    }

    #[Test]
    public function implementsHashTable(): void
    {
        $this->assertInstanceOf(HashTable::class, $this->ht);
    }

    #[Test]
    public function getAlwaysReturnsNull(): void
    {
        $this->ht->set('key', 'value');
        $this->assertNull($this->ht->get('key'));
    }

    #[Test]
    public function existsAlwaysReturnsFalse(): void
    {
        $this->ht->set('key', 'value');
        $this->assertFalse($this->ht->exists('key'));
    }

    #[Test]
    public function replaceAlwaysReturnsFalse(): void
    {
        $this->ht->set('key', 'value');
        $this->assertFalse($this->ht->replace('key', 'new'));
    }

    #[Test]
    public function getMultipleReturnsAllNull(): void
    {
        $result = $this->ht->getMultiple(['a', 'b', 'c']);
        $this->assertSame(['a' => null, 'b' => null, 'c' => null], $result);
    }

    #[Test]
    public function existsMultipleReturnsAllFalse(): void
    {
        $result = $this->ht->existsMultiple(['a', 'b']);
        $this->assertSame(['a' => false, 'b' => false], $result);
    }

    #[Test]
    public function deleteAndClearDoNotThrow(): void
    {
        $this->ht->delete('key');
        $this->ht->delete(['a', 'b']);
        $this->ht->clear();
        $this->assertTrue(true);
    }
}
