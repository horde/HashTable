<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit\Redis\Config;

use Horde\HashTable\Redis\Config\RedisNode;
use Horde\HashTable\Redis\Config\SentinelConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SentinelConfig::class)]
final class SentinelConfigTest extends TestCase
{
    #[Test]
    public function basicSentinel(): void
    {
        $config = new SentinelConfig(
            sentinels: [
                new RedisNode('sentinel1', 26379),
                new RedisNode('sentinel2', 26379),
            ],
            service: 'mymaster',
        );

        $this->assertCount(2, $config->sentinels);
        $this->assertSame('mymaster', $config->service);
        $this->assertNull($config->password());
        $this->assertNull($config->username());
        $this->assertNull($config->sentinelPassword);
        $this->assertSame('hht_', $config->prefix());
        $this->assertSame(0, $config->database());
        $this->assertFalse($config->persistent());
    }

    #[Test]
    public function withAllCredentials(): void
    {
        $config = new SentinelConfig(
            sentinels: [new RedisNode('s1', 26379)],
            service: 'prod',
            password: 'data_pass',
            username: 'horde',
            sentinelPassword: 'sentinel_pass',
            prefix: 'app_',
            database: 2,
            persistent: true,
        );

        $this->assertSame('data_pass', $config->password());
        $this->assertSame('horde', $config->username());
        $this->assertSame('sentinel_pass', $config->sentinelPassword);
        $this->assertSame('app_', $config->prefix());
        $this->assertSame(2, $config->database());
        $this->assertTrue($config->persistent());
    }

    #[Test]
    public function sentinelsAreReindexed(): void
    {
        $nodes = [
            2 => new RedisNode('a', 26379),
            5 => new RedisNode('b', 26379),
        ];
        $config = new SentinelConfig($nodes, 'svc');
        $this->assertSame(0, array_key_first($config->sentinels));
        $this->assertSame(1, array_key_last($config->sentinels));
    }
}
