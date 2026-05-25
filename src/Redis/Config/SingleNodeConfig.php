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
 * Configuration for a single Redis server (TCP or Unix socket).
 */
final class SingleNodeConfig implements RedisConfig
{
    public function __construct(
        public readonly RedisNode $node,
        private readonly ?string $password = null,
        private readonly ?string $username = null,
        private readonly string $prefix = 'hht_',
        private readonly int $database = 0,
        private readonly bool $persistent = false,
        public readonly ?string $socket = null,
    ) {}

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

    public function isUnixSocket(): bool
    {
        return $this->socket !== null;
    }
}
