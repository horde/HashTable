<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit;

use Horde\HashTable\SerializationTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exposes the protected trait methods so tests can hit them directly.
 */
final class SerializationTraitConsumer
{
    use SerializationTrait;

    public function encode(mixed $value): string
    {
        return $this->serializeValue($value);
    }

    public function decode(mixed $stored): mixed
    {
        return $this->deserializeValue($stored);
    }
}

#[CoversTrait(SerializationTrait::class)]
final class SerializationTraitTest extends TestCase
{
    private SerializationTraitConsumer $sut;

    protected function setUp(): void
    {
        $this->sut = new SerializationTraitConsumer();
    }

    #[Test]
    public function stringRoundTrip(): void
    {
        $this->assertSame('hello', $this->sut->decode($this->sut->encode('hello')));
    }

    #[Test]
    public function intRoundTrip(): void
    {
        $this->assertSame(42, $this->sut->decode($this->sut->encode(42)));
    }

    #[Test]
    public function arrayRoundTrip(): void
    {
        $value = ['a' => 1, 'b' => [2, 3]];
        $this->assertSame($value, $this->sut->decode($this->sut->encode($value)));
    }

    /**
     * Values retrieved from the backend that never went through the trait's
     * writer (co-tenant writer, legacy driver, prior release) must pass
     * through instead of tripping a strict-type error.
     */
    #[Test]
    public function nonStringInputPassesThroughUnchanged(): void
    {
        $this->assertSame(1735689600, $this->sut->decode(1735689600));
        $this->assertSame(true, $this->sut->decode(true));
        $this->assertSame(null, $this->sut->decode(null));
        $this->assertSame(['x' => 1], $this->sut->decode(['x' => 1]));
    }

    /**
     * Bare (unprefixed) strings — the "legacy data" case documented in the
     * trait — must round-trip as a raw string.
     */
    #[Test]
    public function unprefixedStringTreatedAsRaw(): void
    {
        $this->assertSame('legacy', $this->sut->decode('legacy'));
    }

    #[Test]
    public function stringPrefixShortForm(): void
    {
        // Bytes that happen to look like the p: prefix don't get confused
        // with prefixed content when they came from the s: writer.
        $encoded = $this->sut->encode('p:not-really-serialized');
        $this->assertSame('p:not-really-serialized', $this->sut->decode($encoded));
    }
}
