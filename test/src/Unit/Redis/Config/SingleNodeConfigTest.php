<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit\Redis\Config;

use Horde\HashTable\Redis\Config\RedisNode;
use Horde\HashTable\Redis\Config\SingleNodeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleNodeConfig::class)]
final class SingleNodeConfigTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $config = new SingleNodeConfig(new RedisNode('127.0.0.1'));
        $this->assertSame('hht_', $config->prefix());
        $this->assertSame(0, $config->database());
        $this->assertFalse($config->persistent());
        $this->assertNull($config->password());
        $this->assertNull($config->username());
        $this->assertFalse($config->isUnixSocket());
    }

    #[Test]
    public function withCredentials(): void
    {
        $config = new SingleNodeConfig(
            new RedisNode('redis.local', 6380),
            password: 'secret',
            username: 'horde',
            database: 3,
            persistent: true,
        );
        $this->assertSame('secret', $config->password());
        $this->assertSame('horde', $config->username());
        $this->assertSame(3, $config->database());
        $this->assertTrue($config->persistent());
    }

    #[Test]
    public function unixSocket(): void
    {
        $config = new SingleNodeConfig(
            new RedisNode('127.0.0.1'),
            socket: '/var/run/redis.sock',
        );
        $this->assertTrue($config->isUnixSocket());
        $this->assertSame('/var/run/redis.sock', $config->socket);
    }
}
