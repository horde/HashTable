<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  HashTable
 */

namespace Horde\HashTable\Driver;

use Horde\HashTable\HashTable;
use Horde\HashTable\LoggingTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Null HashTable implementation that stores nothing.
 *
 * Useful as a fallback when no real backend is available, or in testing
 * scenarios where you need a valid HashTable that discards all data.
 *
 * Does not implement LockableHashTable — a null store cannot
 * provide meaningful cross-process locking guarantees.
 */
final class NullDriver implements HashTable
{
    use LoggingTrait;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function get(string $key): mixed
    {
        $this->logMissed($key);
        return null;
    }

    public function getMultiple(array $keys): array
    {
        if ($keys) {
            $this->logMissed($keys);
        }
        return array_fill_keys($keys, null);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $this->logSet($key, true);
    }

    public function replace(string $key, mixed $value, ?int $ttl = null): bool
    {
        return false;
    }

    public function delete(string|array $keys): void {}

    public function exists(string $key): bool
    {
        return false;
    }

    public function existsMultiple(array $keys): array
    {
        return array_fill_keys($keys, false);
    }

    public function clear(): void {}
}
