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
 * Common interface for Redis connection configuration.
 *
 * Implementations represent specific topologies (single node, sentinel).
 */
interface RedisConfig
{
    public function prefix(): string;

    public function database(): int;

    public function persistent(): bool;

    public function password(): ?string;

    public function username(): ?string;
}
