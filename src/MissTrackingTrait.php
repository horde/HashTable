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
 * Tracks keys known to not exist (negative cache).
 *
 * Avoids redundant backend lookups for keys that were recently confirmed
 * absent. Bounded to prevent memory leaks in long-running processes.
 *
 * Drivers that hold their own authoritative state (Memory) should not use
 * this trait — their internal array is already the truth.
 */
trait MissTrackingTrait
{
    /** @var array<string, true> */
    private array $misses = [];

    private int $missTrackingLimit = 10_000;

    /**
     * Record a key as non-existent.
     */
    protected function recordMiss(string $key): void
    {
        if (count($this->misses) >= $this->missTrackingLimit) {
            $this->misses = array_slice($this->misses, -(int) ($this->missTrackingLimit / 2), preserve_keys: true);
        }
        $this->misses[$key] = true;
    }

    /**
     * Check if a key is known to not exist.
     */
    protected function isMiss(string $key): bool
    {
        return isset($this->misses[$key]);
    }

    /**
     * Remove a key from the miss cache (e.g. after a successful set).
     */
    protected function clearMiss(string $key): void
    {
        unset($this->misses[$key]);
    }

    /**
     * Clear the entire miss cache.
     */
    protected function clearAllMisses(): void
    {
        $this->misses = [];
    }

    /**
     * Filter keys into known-missed and unknown (need backend lookup).
     *
     * @param list<string> $keys  Keys to partition.
     *
     * @return array{missed: list<string>, pending: list<string>}
     */
    protected function partitionByMissCache(array $keys): array
    {
        $missed = [];
        $pending = [];
        foreach ($keys as $key) {
            if (isset($this->misses[$key])) {
                $missed[] = $key;
            } else {
                $pending[] = $key;
            }
        }
        return ['missed' => $missed, 'pending' => $pending];
    }
}
