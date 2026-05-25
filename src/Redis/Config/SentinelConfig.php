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

/**
 * Configuration for a Redis Sentinel topology.
 *
 * Sentinel nodes are queried for the current master address.
 * Data node credentials (password, username) are used when connecting
 * to the resolved master.
 */
final class SentinelConfig implements RedisConfig
{
    /** @var list<RedisNode> */
    public readonly array $sentinels;

    /**
     * @param list<RedisNode> $sentinels        Sentinel endpoints to query.
     * @param string          $service          Sentinel service name (e.g. "mymaster").
     * @param ?string         $password         Data node AUTH password.
     * @param ?string         $username         Data node ACL username (Redis 6+).
     * @param ?string         $sentinelPassword Password for sentinel nodes themselves.
     * @param string          $prefix           Key prefix.
     * @param int             $database         Database index for data node.
     * @param bool            $persistent       Use persistent connections.
     */
    public function __construct(
        array $sentinels,
        public readonly string $service,
        private readonly ?string $password = null,
        private readonly ?string $username = null,
        public readonly ?string $sentinelPassword = null,
        private readonly string $prefix = 'hht_',
        private readonly int $database = 0,
        private readonly bool $persistent = false,
    ) {
        $this->sentinels = array_values($sentinels);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function database(): int
    {
        return $this->database;
    }

    public function persistent(): bool
    {
        return $this->persistent;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    public function username(): ?string
    {
        return $this->username;
    }
}
