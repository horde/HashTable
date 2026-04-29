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

namespace Horde\HashTable;

use Psr\Log\LoggerInterface;

/**
 * Provides PSR-3 debug tracing for HashTable operations.
 *
 * Logs key retrieval, storage, deletion, and misses at debug level.
 * Zero overhead when a NullLogger is injected.
 *
 * Requires: $this->logger (LoggerInterface property on the using class).
 */
trait LoggingTrait
{
    /**
     * Log a successful retrieval.
     *
     * @param string|list<string> $keys  The key(s) retrieved.
     */
    protected function logRetrieved(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->logger->debug('{driver}: Retrieved keys ({keys})', [
            'driver' => static::class,
            'keys' => implode(', ', $keys),
        ]);
    }

    /**
     * Log keys confirmed as non-existent.
     *
     * @param string|list<string> $keys  The key(s) that missed.
     */
    protected function logMissed(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->logger->debug('{driver}: Non-existent keys ({keys})', [
            'driver' => static::class,
            'keys' => implode(', ', $keys),
        ]);
    }

    /**
     * Log a set operation.
     *
     * @param string $key     The key stored.
     * @param bool   $success Whether the operation succeeded.
     */
    protected function logSet(string $key, bool $success): void
    {
        $this->logger->debug('{driver}: Set key {status}({key})', [
            'driver' => static::class,
            'status' => $success ? '' : 'FAILED ',
            'key' => $key,
        ]);
    }

    /**
     * Log a delete operation.
     *
     * @param string|list<string> $keys  The key(s) deleted.
     */
    protected function logDeleted(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $this->logger->debug('{driver}: Deleted keys ({keys})', [
            'driver' => static::class,
            'keys' => implode(', ', $keys),
        ]);
    }
}
