<?php

declare(strict_types=1);

namespace Horde\HashTable\Test\Src\Unit\Redis\Config;

use Horde\HashTable\Redis\Config\RedisConfigMapper;
use Horde\HashTable\Redis\Config\SentinelConfig;
use Horde\HashTable\Redis\Config\SingleNodeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedisConfigMapper::class)]
final class RedisConfigMapperTest extends TestCase
{
    #[Test]
    public function emptyArrayProducesSingleNodeLocalhost(): void
    {
        $config = RedisConfigMapper::fromArray([]);
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
        $this->assertSame('127.0.0.1', $config->node->host);
        $this->assertSame(6379, $config->node->port);
    }

    #[Test]
    public function singleHostTcp(): void
    {
        $params = [
            'hostspec' => ['redis.local'],
            'port' => ['6380'],
            'protocol' => 'tcp',
            'password' => 'secret',
            'username' => 'horde',
            'database' => 3,
            'persistent' => true,
        ];

        $config = RedisConfigMapper::fromArray($params, 'myapp_');
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
        $this->assertSame('redis.local', $config->node->host);
        $this->assertSame(6380, $config->node->port);
        $this->assertSame('secret', $config->password());
        $this->assertSame('horde', $config->username());
        $this->assertSame(3, $config->database());
        $this->assertTrue($config->persistent());
        $this->assertSame('myapp_', $config->prefix());
    }

    #[Test]
    public function unixSocket(): void
    {
        $params = [
            'protocol' => 'unix',
            'socket' => '/var/run/redis.sock',
            'password' => 'pw',
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
        $this->assertTrue($config->isUnixSocket());
        $this->assertSame('/var/run/redis.sock', $config->socket);
        $this->assertSame('pw', $config->password());
    }

    #[Test]
    public function sentinelConfig(): void
    {
        $params = [
            'hostspec' => ['edm-cmfe01', 'edm-cmfe02', 'edm-webapps01', 'edm-webapps02'],
            'port' => ['26379', '26379', '26379', '26379'],
            'service' => 'mymaster',
            'replication' => 'sentinel',
            'protocol' => 'tcp',
            'persistent' => true,
            'password' => 'data_pass',
            'username' => 'horde_user',
            'sentinelPassword' => 'sentinel_pass',
        ];

        $config = RedisConfigMapper::fromArray($params, 'hht_');
        $this->assertInstanceOf(SentinelConfig::class, $config);
        $this->assertSame('mymaster', $config->service);
        $this->assertCount(4, $config->sentinels);
        $this->assertSame('edm-cmfe01', $config->sentinels[0]->host);
        $this->assertSame(26379, $config->sentinels[0]->port);
        $this->assertSame('edm-webapps02', $config->sentinels[3]->host);
        $this->assertSame('data_pass', $config->password());
        $this->assertSame('horde_user', $config->username());
        $this->assertSame('sentinel_pass', $config->sentinelPassword);
        $this->assertTrue($config->persistent());
    }

    #[Test]
    public function sentinelDefaultsPort26379WhenPortArrayShorter(): void
    {
        $params = [
            'hostspec' => ['s1', 's2', 's3'],
            'port' => ['26379'],
            'replication' => 'sentinel',
            'service' => 'redis',
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SentinelConfig::class, $config);
        $this->assertSame(26379, $config->sentinels[0]->port);
        $this->assertSame(26379, $config->sentinels[1]->port);
        $this->assertSame(26379, $config->sentinels[2]->port);
    }

    #[Test]
    public function emptyPasswordTreatedAsNull(): void
    {
        $params = [
            'hostspec' => ['localhost'],
            'port' => ['6379'],
            'password' => '',
            'username' => '',
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
        $this->assertNull($config->password());
        $this->assertNull($config->username());
    }

    #[Test]
    public function scalarHostspecHandledGracefully(): void
    {
        $params = [
            'hostspec' => 'single-host',
            'port' => 6380,
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
        $this->assertSame('single-host', $config->node->host);
        $this->assertSame(6380, $config->node->port);
    }

    #[Test]
    public function replicationNoneProducesSingleNode(): void
    {
        $params = [
            'hostspec' => ['myhost'],
            'port' => ['6379'],
            'replication' => 'none',
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SingleNodeConfig::class, $config);
    }

    #[Test]
    public function nelsLindquistRealWorldConfig(): void
    {
        $params = [
            'hostspec' => ['edm-cmfe01', 'edm-cmfe02', 'edm-webapps01', 'edm-webapps02'],
            'port' => ['26379', '26379', '26379', '26379'],
            'service' => 'mymaster',
            'replication' => 'sentinel',
            'protocol' => 'tcp',
            'persistent' => true,
        ];

        $config = RedisConfigMapper::fromArray($params);
        $this->assertInstanceOf(SentinelConfig::class, $config);
        $this->assertSame('mymaster', $config->service);
        $this->assertCount(4, $config->sentinels);
        $this->assertNull($config->password());
        $this->assertNull($config->sentinelPassword);
        $this->assertTrue($config->persistent());
    }
}
