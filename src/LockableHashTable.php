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

/**
 * Hash table with cross-process locking support.
 *
 * Implementations guarantee that lock/unlock coordinate across multiple
 * PHP processes sharing the same backend (Redis, Memcache). In-process-only
 * drivers (Memory, Null) do NOT implement this interface because their
 * "locks" cannot prevent concurrent access from other processes.
 */
interface LockableHashTable extends HashTable
{
    /**
     * Acquire an exclusive lock on a key.
     *
     * Blocks until the lock is obtained or a timeout is reached.
     *
     * @param string $key  The key to lock.
     *
     * @throws LockTimeoutException If the lock cannot be acquired within
     *                              a reasonable time.
     */
    public function lock(string $key): void;

    /**
     * Release a previously acquired lock.
     *
     * @param string $key  The key to unlock.
     */
    public function unlock(string $key): void;
}
