<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit\Redis\Config;

use Horde\HashTable\Redis\Config\RedisNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedisNode::class)]
final class RedisNodeTest extends TestCase
{
    #[Test]
    public function defaultsToPort6379(): void
    {
        $node = new RedisNode('redis.local');
        $this->assertSame('redis.local', $node->host);
        $this->assertSame(6379, $node->port);
        $this->assertFalse($node->tls);
    }

    #[Test]
    public function customPortAndTls(): void
    {
        $node = new RedisNode('secure.redis.io', 6380, true);
        $this->assertSame('secure.redis.io', $node->host);
        $this->assertSame(6380, $node->port);
        $this->assertTrue($node->tls);
    }

    #[Test]
    public function sentinelPort(): void
    {
        $node = new RedisNode('sentinel1', 26379);
        $this->assertSame(26379, $node->port);
    }
}
