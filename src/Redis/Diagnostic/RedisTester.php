<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\HashTable\Redis\Diagnostic;

use Horde\HashTable\Redis\Config\RedisConfig;
use Horde\HashTable\Redis\Config\RedisNode;
use Horde\HashTable\Redis\Config\SentinelConfig;
use Horde\HashTable\Redis\Config\SingleNodeConfig;
use Predis\Client as PredisClient;
use Redis as PhpRedis;
use RedisSentinel;
use Throwable;
use RuntimeException;

/**
 * Standalone Redis diagnostic tester.
 *
 * Tests Redis connectivity and command availability without requiring
 * the full Horde stack. Useful for diagnosing ACL restrictions,
 * proxy limitations, or misconfiguration.
 */
final class RedisTester
{
    private PredisClient|PhpRedis|null $client = null;
    private RedisDriver $resolvedDriver;

    public function __construct(
        private readonly string $hostname,
        private readonly int $port,
        private readonly bool $tls,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly string $prefix,
        private readonly int $database,
        private readonly RedisDriver $driver = RedisDriver::AUTO,
        private readonly ?RedisConfig $config = null,
    ) {}

    /**
     * Create a tester from a typed RedisConfig object.
     */
    public static function fromConfig(RedisConfig $config, RedisDriver $driver = RedisDriver::AUTO): self
    {
        if ($config instanceof SentinelConfig) {
            $firstNode = $config->sentinels[0] ?? new RedisNode('127.0.0.1', 26379);
            return new self(
                hostname: $firstNode->host,
                port: $firstNode->port,
                tls: $firstNode->tls,
                username: $config->username(),
                password: $config->password(),
                prefix: $config->prefix(),
                database: $config->database(),
                driver: $driver,
                config: $config,
            );
        }

        if ($config instanceof SingleNodeConfig) {
            return new self(
                hostname: $config->node->host,
                port: $config->node->port,
                tls: $config->node->tls,
                username: $config->username(),
                password: $config->password(),
                prefix: $config->prefix(),
                database: $config->database(),
                driver: $driver,
                config: $config,
            );
        }

        return new self(
            hostname: '127.0.0.1',
            port: 6379,
            tls: false,
            username: $config->username(),
            password: $config->password(),
            prefix: $config->prefix(),
            database: $config->database(),
            driver: $driver,
            config: $config,
        );
    }

    public function run(): DiagnosticResult
    {
        $result = new DiagnosticResult();

        $this->reportDriverAvailability($result);

        if ($this->config instanceof SentinelConfig) {
            $this->testSentinelTopology($result);
        } else {
            $this->testConnection($result);
        }

        if ($result->hasErrors()) {
            return $result;
        }

        $this->testSelect($result);
        if ($result->hasErrors()) {
            return $result;
        }

        $this->testCommandInfo($result);
        $this->testSet($result);
        if ($result->hasErrors()) {
            return $result;
        }

        $this->testGet($result);
        $this->testMget($result);
        $this->testDel($result);
        $this->testKeyScan($result);

        return $result;
    }

    private function reportDriverAvailability(DiagnosticResult $result): void
    {
        $phpredisAvailable = extension_loaded('redis');
        $predisAvailable = class_exists(PredisClient::class);

        $parts = [];
        if ($phpredisAvailable) {
            $parts[] = 'ext-redis ' . phpversion('redis');
        }
        if ($predisAvailable) {
            $parts[] = 'predis (composer)';
        }
        if (empty($parts)) {
            $result->add(new TestResult(
                'Driver',
                TestStatus::ERROR,
                'No Redis client available — install ext-redis or predis/predis',
            ));
            return;
        }

        $this->resolvedDriver = $this->resolveDriver($phpredisAvailable, $predisAvailable);

        $chosen = match ($this->resolvedDriver) {
            RedisDriver::PHPREDIS => 'ext-redis',
            RedisDriver::PREDIS => 'predis',
            default => 'unknown',
        };

        $status = TestStatus::INFO;
        $message = 'Available: ' . implode(', ', $parts) . ' | Using: ' . $chosen;

        if ($this->driver !== RedisDriver::AUTO) {
            $message .= ' (forced via --driver)';
        }

        $result->add(new TestResult('Driver', $status, $message));
    }

    private function resolveDriver(bool $phpredisAvailable, bool $predisAvailable): RedisDriver
    {
        if ($this->driver === RedisDriver::PHPREDIS) {
            if (!$phpredisAvailable) {
                throw new RuntimeException('ext-redis requested but not available');
            }
            return RedisDriver::PHPREDIS;
        }

        if ($this->driver === RedisDriver::PREDIS) {
            if (!$predisAvailable) {
                throw new RuntimeException('predis requested but not installed');
            }
            return RedisDriver::PREDIS;
        }

        // AUTO: prefer ext-redis
        if ($phpredisAvailable) {
            return RedisDriver::PHPREDIS;
        }

        return RedisDriver::PREDIS;
    }

    private function testSentinelTopology(DiagnosticResult $result): void
    {
        assert($this->config instanceof SentinelConfig);
        $sentinelConfig = $this->config;

        $reachable = 0;
        $masterAddr = null;

        foreach ($sentinelConfig->sentinels as $node) {
            try {
                if ($this->resolvedDriver === RedisDriver::PREDIS) {
                    $params = ['scheme' => 'tcp', 'host' => $node->host, 'port' => $node->port];
                    if ($sentinelConfig->sentinelPassword !== null) {
                        $params['password'] = $sentinelConfig->sentinelPassword;
                    }
                    $sentinel = new PredisClient($params);
                    $sentinel->ping();
                    $reachable++;

                    if ($masterAddr === null) {
                        $addr = $sentinel->sentinel('get-master-addr-by-name', $sentinelConfig->service);
                        if (is_array($addr) && count($addr) === 2) {
                            $masterAddr = [(string) $addr[0], (int) $addr[1]];
                        }
                    }
                } else {
                    $opts = ['host' => $node->host, 'port' => $node->port];
                    if ($sentinelConfig->sentinelPassword !== null) {
                        $opts['auth'] = $sentinelConfig->sentinelPassword;
                    }
                    $sentinel = new RedisSentinel($opts);
                    $sentinel->ping();
                    $reachable++;

                    if ($masterAddr === null) {
                        $addr = $sentinel->getMasterAddrByName($sentinelConfig->service);
                        if (is_array($addr) && count($addr) === 2) {
                            $masterAddr = [(string) $addr[0], (int) $addr[1]];
                        }
                    }
                }
            } catch (Throwable $e) {
                $result->add(new TestResult(
                    'Sentinel',
                    TestStatus::WARNING,
                    'Cannot reach sentinel ' . $node->host . ':' . $node->port,
                    $e->getMessage(),
                ));
            }
        }

        $total = count($sentinelConfig->sentinels);
        if ($reachable === 0) {
            $result->add(new TestResult(
                'Sentinel',
                TestStatus::ERROR,
                'No sentinels reachable (tried ' . $total . ')',
            ));
            return;
        }

        $result->add(new TestResult(
            'Sentinel',
            TestStatus::OK,
            $reachable . '/' . $total . ' sentinels reachable',
        ));

        if ($masterAddr === null) {
            $result->add(new TestResult(
                'Master',
                TestStatus::ERROR,
                'No sentinel returned master address for service: ' . $sentinelConfig->service,
            ));
            return;
        }

        $result->add(new TestResult(
            'Master',
            TestStatus::OK,
            'Resolved master: ' . $masterAddr[0] . ':' . $masterAddr[1] . ' (service: ' . $sentinelConfig->service . ')',
        ));

        $this->connectToResolvedMaster($result, $masterAddr[0], $masterAddr[1]);
    }

    private function connectToResolvedMaster(DiagnosticResult $result, string $host, int $port): void
    {
        try {
            if ($this->resolvedDriver === RedisDriver::PHPREDIS) {
                $redis = new PhpRedis();
                $connected = $redis->connect($host, $port);
                if (!$connected) {
                    throw new RuntimeException('connect() returned false');
                }
                if ($this->username !== null && $this->username !== '') {
                    $redis->auth([$this->username, $this->password ?? '']);
                } elseif ($this->password !== null && $this->password !== '') {
                    $redis->auth($this->password);
                }
                $this->client = $redis;
                $info = $redis->info('server');
                $version = $info['redis_version'] ?? 'unknown';
            } else {
                $connectionParams = ['scheme' => 'tcp', 'host' => $host, 'port' => $port];
                if ($this->password !== null && $this->password !== '') {
                    $connectionParams['password'] = $this->password;
                }
                if ($this->username !== null && $this->username !== '') {
                    $connectionParams['username'] = $this->username;
                }
                $this->client = new PredisClient($connectionParams);
                $info = $this->client->info('server');
                $version = $info['Server']['redis_version'] ?? $info['redis_version'] ?? 'unknown';
            }

            $driverName = $this->resolvedDriver === RedisDriver::PHPREDIS ? 'ext-redis' : 'predis';
            $result->add(new TestResult(
                'Connection',
                TestStatus::OK,
                'Connected to master Redis ' . $version . ' via ' . $driverName,
            ));
            $this->reportAuth($result);
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'Connection',
                TestStatus::ERROR,
                'Failed to connect to resolved master ' . $host . ':' . $port,
                $e->getMessage(),
            ));
        }
    }

    private function testConnection(DiagnosticResult $result): void
    {
        try {
            if ($this->resolvedDriver === RedisDriver::PHPREDIS) {
                $this->connectPhpRedis($result);
            } else {
                $this->connectPredis($result);
            }
        } catch (Throwable $e) {
            $scheme = $this->tls ? 'tls' : 'tcp';
            $result->add(new TestResult(
                'Connection',
                TestStatus::ERROR,
                'Failed to connect',
                $scheme . '://' . $this->hostname . ':' . $this->port . ' — ' . $e->getMessage(),
            ));
        }
    }

    private function connectPhpRedis(DiagnosticResult $result): void
    {
        $redis = new PhpRedis();

        $host = $this->hostname;
        if ($this->tls) {
            $host = 'tls://' . $host;
        }

        $connected = $redis->connect($host, $this->port);
        if (!$connected) {
            throw new RuntimeException('connect() returned false');
        }

        if ($this->username !== null && $this->username !== '') {
            $redis->auth([$this->username, $this->password ?? '']);
        } elseif ($this->password !== null && $this->password !== '') {
            $redis->auth($this->password);
        }

        $this->client = $redis;

        $info = $redis->info('server');
        $version = $info['redis_version'] ?? 'unknown';

        $result->add(new TestResult(
            'Connection',
            TestStatus::OK,
            'Connected to Redis ' . $version . ' via ext-redis',
        ));

        $this->reportAuth($result);
    }

    private function connectPredis(DiagnosticResult $result): void
    {
        $scheme = $this->tls ? 'tls' : 'tcp';
        $connectionParams = [
            'scheme' => $scheme,
            'host' => $this->hostname,
            'port' => $this->port,
        ];

        if ($this->password !== null && $this->password !== '') {
            $connectionParams['password'] = $this->password;
        }

        if ($this->username !== null && $this->username !== '') {
            $connectionParams['username'] = $this->username;
        }

        $this->client = new PredisClient($connectionParams);
        $info = $this->client->info('server');

        $version = $info['Server']['redis_version'] ?? $info['redis_version'] ?? 'unknown';

        $result->add(new TestResult(
            'Connection',
            TestStatus::OK,
            'Connected to Redis ' . $version . ' via predis',
        ));

        $this->reportAuth($result);
    }

    private function reportAuth(DiagnosticResult $result): void
    {
        if ($this->username !== null && $this->username !== '') {
            $result->add(new TestResult(
                'AUTH',
                TestStatus::OK,
                'Authenticated as user \'' . $this->username . '\'',
            ));
        } elseif ($this->password !== null && $this->password !== '') {
            $result->add(new TestResult(
                'AUTH',
                TestStatus::OK,
                'Authenticated with password',
            ));
        } else {
            $result->add(new TestResult(
                'AUTH',
                TestStatus::SKIP,
                'No credentials provided',
            ));
        }
    }

    private function testSelect(DiagnosticResult $result): void
    {
        if ($this->database === 0) {
            $result->add(new TestResult(
                'SELECT',
                TestStatus::SKIP,
                'Using default database 0',
            ));
            return;
        }

        try {
            $this->client->select($this->database);
            $result->add(new TestResult(
                'SELECT',
                TestStatus::OK,
                'Selected database ' . $this->database,
            ));
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'SELECT',
                TestStatus::ERROR,
                'Failed to select database ' . $this->database,
                $e->getMessage(),
            ));
        }
    }

    private function testCommandInfo(DiagnosticResult $result): void
    {
        $commands = ['GET', 'SET', 'MGET', 'DEL', 'SETEX', 'KEYS'];
        $available = [];
        $restricted = [];

        try {
            if ($this->client instanceof PhpRedis) {
                $info = $this->client->rawCommand('COMMAND', 'INFO', ...$commands);
            } else {
                $info = $this->client->command('INFO', ...$commands);
            }

            foreach ($commands as $i => $cmd) {
                $entry = $info[$i] ?? $info[strtolower($cmd)] ?? null;
                if ($entry === null || (is_array($entry) && empty($entry))) {
                    $restricted[] = $cmd;
                } else {
                    $available[] = $cmd;
                }
            }
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'COMMAND INFO',
                TestStatus::WARNING,
                'COMMAND INFO not available — cannot introspect commands',
                $e->getMessage(),
            ));
            return;
        }

        $parts = [];
        foreach ($available as $cmd) {
            $parts[] = $cmd . ': available';
        }
        foreach ($restricted as $cmd) {
            $parts[] = $cmd . ': RESTRICTED';
        }

        $status = empty($restricted) ? TestStatus::OK : TestStatus::WARNING;
        $result->add(new TestResult(
            'COMMAND INFO',
            $status,
            implode(', ', $parts),
            $restricted ? 'Restricted commands may cause Horde errors' : null,
        ));
    }

    private function testSet(DiagnosticResult $result): void
    {
        $key = $this->prefix . '__diag_test';
        $value = 'horde_diag_' . bin2hex(random_bytes(4));

        try {
            $this->client->setex($key, 60, $value);
            $result->add(new TestResult(
                'SET',
                TestStatus::OK,
                'Wrote test key ' . $key,
            ));
        } catch (Throwable $e) {
            try {
                $this->client->set($key, $value);
                $this->client->expire($key, 60);
                $result->add(new TestResult(
                    'SET',
                    TestStatus::OK,
                    'Wrote test key ' . $key . ' (SETEX unavailable, used SET+EXPIRE)',
                ));
            } catch (Throwable $e2) {
                $result->add(new TestResult(
                    'SET',
                    TestStatus::ERROR,
                    'Cannot write to Redis',
                    $e2->getMessage(),
                ));
            }
        }
    }

    private function testGet(DiagnosticResult $result): void
    {
        $key = $this->prefix . '__diag_test';

        try {
            if ($this->client instanceof PhpRedis) {
                $value = $this->client->get($key);
                if ($value === false) {
                    $value = null;
                }
            } else {
                $value = $this->client->get($key);
            }

            if ($value !== null && str_starts_with($value, 'horde_diag_')) {
                $result->add(new TestResult(
                    'GET',
                    TestStatus::OK,
                    'Read back matches written value',
                ));
            } else {
                $result->add(new TestResult(
                    'GET',
                    TestStatus::WARNING,
                    'GET returned unexpected value',
                    'Expected horde_diag_*, got: ' . var_export($value, true),
                ));
            }
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'GET',
                TestStatus::ERROR,
                'GET command failed',
                $e->getMessage(),
            ));
        }
    }

    private function testMget(DiagnosticResult $result): void
    {
        $key = $this->prefix . '__diag_test';

        try {
            if ($this->client instanceof PhpRedis) {
                $values = $this->client->mGet([$key]);
            } else {
                $values = $this->client->mget([$key]);
            }

            if (is_array($values) && isset($values[0]) && is_string($values[0]) && str_starts_with($values[0], 'horde_diag_')) {
                $result->add(new TestResult(
                    'MGET',
                    TestStatus::OK,
                    'MGET works — multi-key retrieval available',
                ));
            } else {
                $result->add(new TestResult(
                    'MGET',
                    TestStatus::WARNING,
                    'MGET returned unexpected result',
                    'Got: ' . var_export($values, true),
                ));
            }
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'MGET',
                TestStatus::WARNING,
                'MGET unavailable — cluster/ACL restriction',
                $e->getMessage() . "\n→ Horde will fall back to individual GETs (slower but functional if GET works).",
            ));
        }
    }

    private function testDel(DiagnosticResult $result): void
    {
        $key = $this->prefix . '__diag_test';

        try {
            if ($this->client instanceof PhpRedis) {
                $this->client->del($key);
            } else {
                $this->client->del([$key]);
            }
            $result->add(new TestResult(
                'DEL',
                TestStatus::OK,
                'Cleaned up test key',
            ));
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'DEL',
                TestStatus::WARNING,
                'DEL command failed — test key may remain (TTL 60s)',
                $e->getMessage(),
            ));
        }
    }

    private function testKeyScan(DiagnosticResult $result): void
    {
        try {
            $pattern = $this->prefix . '*';
            $keys = $this->client->keys($pattern);
            $count = is_array($keys) ? count($keys) : 0;

            $result->add(new TestResult(
                'Key scan',
                TestStatus::INFO,
                'Found ' . $count . ' keys with prefix ' . $this->prefix,
            ));
        } catch (Throwable $e) {
            $result->add(new TestResult(
                'Key scan',
                TestStatus::INFO,
                'KEYS command not available (non-critical)',
                $e->getMessage(),
            ));
        }
    }
}
