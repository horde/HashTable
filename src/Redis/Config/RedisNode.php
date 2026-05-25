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
 * Value object representing a single Redis or Sentinel endpoint.
 */
final class RedisNode
{
    public function __construct(
        public readonly string $host,
        public readonly int $port = 6379,
        public readonly bool $tls = false,
    ) {}
}
