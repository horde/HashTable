<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package HashTable
 */

namespace Horde\HashTable\Redis\Config;

use Horde\HashTable\ConnectionException;
use Predis\Client as PredisClient;
use Predis\ClientInterface as PredisClientInterface;
use Redis as PhpRedis;
use RedisSentinel;
use RedisException;

/**
 * Creates connected Redis client instances from typed configuration.
 *
 * Supports both ext-redis (PhpRedis) and predis/predis, with automatic
 * sentinel resolution for sentinel topologies.
 */
final class RedisClientFactory
{
    /**
     * Create a connected Redis client from the given configuration.
     *
     * @param RedisConfig $config          Typed Redis configuration.
     * @param bool        $preferPhpRedis  Prefer ext-redis over Predis when both available.
     *
     * @return PhpRedis|PredisClientInterface
     *
     * @throws ConnectionException If no client is available or connection fails.
     */
    public function create(RedisConfig $config, bool $preferPhpRedis = true): PhpRedis|PredisClientInterface
    {
        if ($preferPhpRedis && extension_loaded('redis')) {
            return $this->createPhpRedis($config);
        }

        if (class_exists(PredisClient::class)) {
            return $this->createPredis($config);
        }

        if (extension_loaded('redis')) {
            return $this->createPhpRedis($config);
        }

        throw new ConnectionException(
            'No Redis client available. Install ext-redis or predis/predis.'
        );
    }

    private function createPredis(RedisConfig $config): PredisClient
    {
        if ($config instanceof SentinelConfig) {
            return $this->createPredisSentinel($config);
        }

        if ($config instanceof SingleNodeConfig) {
            return $this->createPredisSingle($config);
        }

        throw new ConnectionException('Unsupported RedisConfig type: ' . $config::class);
    }

    private function createPredisSingle(SingleNodeConfig $config): PredisClient
    {
        if ($config->isUnixSocket()) {
            $connectionParams = [
                'scheme' => 'unix',
                'path' => $config->socket,
            ];
        } else {
            $connectionParams = [
                'scheme' => $config->node->tls ? 'tls' : 'tcp',
                'host' => $config->node->host,
                'port' => $config->node->port,
            ];
        }

        if ($config->password() !== null) {
            $connectionParams['password'] = $config->password();
        }

        if ($config->username() !== null) {
            $connectionParams['username'] = $config->username();
        }

        if ($config->database() !== 0) {
            $connectionParams['database'] = $config->database();
        }

        if ($config->persistent()) {
            $connectionParams['persistent'] = true;
        }

        return new PredisClient($connectionParams);
    }

    private function createPredisSentinel(SentinelConfig $config): PredisClient
    {
        $parameters = [];
        foreach ($config->sentinels as $node) {
            $entry = [
                'scheme' => $node->tls ? 'tls' : 'tcp',
                'host' => $node->host,
                'port' => $node->port,
            ];
            if ($config->sentinelPassword !== null) {
                $entry['password'] = $config->sentinelPassword;
            }
            $parameters[] = $entry;
        }

        $options = [
            'replication' => 'sentinel',
            'service' => $config->service,
        ];

        $dataParams = [];
        if ($config->password() !== null) {
            $dataParams['password'] = $config->password();
        }
        if ($config->username() !== null) {
            $dataParams['username'] = $config->username();
        }
        if ($config->database() !== 0) {
            $dataParams['database'] = $config->database();
        }
        if ($config->persistent()) {
            $dataParams['persistent'] = true;
        }
        if ($dataParams !== []) {
            $options['parameters'] = $dataParams;
        }

        return new PredisClient($parameters, $options);
    }

    private function createPhpRedis(RedisConfig $config): PhpRedis
    {
        if ($config instanceof SentinelConfig) {
            return $this->createPhpRedisSentinel($config);
        }

        if ($config instanceof SingleNodeConfig) {
            return $this->createPhpRedisSingle($config);
        }

        throw new ConnectionException('Unsupported RedisConfig type: ' . $config::class);
    }

    private function createPhpRedisSingle(SingleNodeConfig $config): PhpRedis
    {
        $redis = new PhpRedis();

        if ($config->isUnixSocket()) {
            $connected = $config->persistent()
                ? $redis->pconnect($config->socket)
                : $redis->connect($config->socket);
        } else {
            $host = $config->node->tls ? 'tls://' . $config->node->host : $config->node->host;
            $connected = $config->persistent()
                ? $redis->pconnect($host, $config->node->port)
                : $redis->connect($host, $config->node->port);
        }

        if (!$connected) {
            throw new ConnectionException(
                'Failed to connect to Redis at ' . $config->node->host . ':' . $config->node->port
            );
        }

        $this->phpRedisAuth($redis, $config);
        $this->phpRedisSelect($redis, $config);

        return $redis;
    }

    private function createPhpRedisSentinel(SentinelConfig $config): PhpRedis
    {
        if (!class_exists(RedisSentinel::class)) {
            throw new ConnectionException(
                'ext-redis RedisSentinel class not available. '
                . 'Upgrade to phpredis 5.x+ or use Predis for sentinel support.'
            );
        }

        foreach ($config->sentinels as $node) {
            $opts = ['host' => $node->host, 'port' => $node->port];
            if ($config->sentinelPassword !== null) {
                $opts['auth'] = $config->sentinelPassword;
            }

            try {
                $sentinel = new RedisSentinel($opts);
                $master = $sentinel->getMasterAddrByName($config->service);
            } catch (RedisException $e) {
                continue;
            }

            if ($master === false || !is_array($master)) {
                continue;
            }

            $redis = new PhpRedis();
            $masterHost = (string) $master[0];
            $masterPort = (int) $master[1];

            $connected = $config->persistent()
                ? $redis->pconnect($masterHost, $masterPort)
                : $redis->connect($masterHost, $masterPort);

            if (!$connected) {
                continue;
            }

            $this->phpRedisAuth($redis, $config);
            $this->phpRedisSelect($redis, $config);

            return $redis;
        }

        throw new ConnectionException(
            'No sentinel responded with master address for service: ' . $config->service
        );
    }

    private function phpRedisAuth(PhpRedis $redis, RedisConfig $config): void
    {
        if ($config->password() === null) {
            return;
        }

        if ($config->username() !== null) {
            $result = $redis->auth([$config->username(), $config->password()]);
        } else {
            $result = $redis->auth($config->password());
        }

        if (!$result) {
            throw new ConnectionException('Redis authentication failed.');
        }
    }

    private function phpRedisSelect(PhpRedis $redis, RedisConfig $config): void
    {
        if ($config->database() !== 0) {
            $redis->select($config->database());
        }
    }
}
