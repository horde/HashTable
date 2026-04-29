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
 * Extended interface exposing Redis-native data structures.
 *
 * This interface is what gives HashTable its value beyond PSR-16 (simple
 * string cache). Consumers that need Redis-native power — lists, hashes,
 * sets, atomic counters — type-hint this interface directly, accepting that
 * they are coupled to a Redis-capable backend.
 *
 * Drivers backed by string-only stores (Memcache) do NOT implement this.
 */
interface RedisHashTable extends LockableHashTable
{
    // --- Atomic Counters ---

    /**
     * Atomically increment a key's integer value.
     *
     * Creates the key with value 0 before incrementing if it does not exist.
     *
     * @param string $key  The key to increment.
     * @param int    $by   Amount to increment by.
     *
     * @return int  The value after incrementing.
     */
    public function increment(string $key, int $by = 1): int;

    /**
     * Atomically decrement a key's integer value.
     *
     * Creates the key with value 0 before decrementing if it does not exist.
     *
     * @param string $key  The key to decrement.
     * @param int    $by   Amount to decrement by.
     *
     * @return int  The value after decrementing.
     */
    public function decrement(string $key, int $by = 1): int;

    // --- TTL Introspection ---

    /**
     * Get the remaining time-to-live for a key.
     *
     * @param string $key  The key to inspect.
     *
     * @return ?int  Seconds remaining, or null if the key does not exist
     *              or has no expiration set.
     */
    public function ttl(string $key): ?int;

    // --- Hash (field-value map within a key) ---

    /**
     * Get a single field from a hash.
     *
     * @param string $key    The hash key.
     * @param string $field  The field name within the hash.
     *
     * @return mixed  The field value, or null if the field or key does not exist.
     */
    public function hashGet(string $key, string $field): mixed;

    /**
     * Set a field in a hash.
     *
     * @param string $key    The hash key.
     * @param string $field  The field name.
     * @param mixed  $value  The value to store.
     */
    public function hashSet(string $key, string $field, mixed $value): void;

    /**
     * Get all fields and values from a hash.
     *
     * @param string $key  The hash key.
     *
     * @return array<string, mixed>  All field => value pairs, or empty array
     *                               if key does not exist.
     */
    public function hashGetAll(string $key): array;

    /**
     * Delete one or more fields from a hash.
     *
     * @param string              $key     The hash key.
     * @param string|list<string> $fields  Field or fields to remove.
     */
    public function hashDelete(string $key, string|array $fields): void;

    // --- Lists (ordered, push/pop) ---

    /**
     * Push a value onto the end (right) of a list.
     *
     * Creates the list if it does not exist.
     *
     * @param string $key    The list key.
     * @param mixed  $value  The value to push.
     *
     * @return int  The length of the list after pushing.
     */
    public function listPush(string $key, mixed $value): int;

    /**
     * Pop a value from the front (left) of a list.
     *
     * @param string $key  The list key.
     *
     * @return mixed  The popped value, or null if the list is empty or
     *               does not exist.
     */
    public function listPop(string $key): mixed;

    /**
     * Get a range of elements from a list.
     *
     * @param string $key    The list key.
     * @param int    $start  Start index (0-based, negative counts from end).
     * @param int    $stop   Stop index (inclusive, -1 means last element).
     *
     * @return list<mixed>  The elements in the range, or empty array.
     */
    public function listRange(string $key, int $start = 0, int $stop = -1): array;

    // --- Sets (unordered, unique members) ---

    /**
     * Add one or more members to a set.
     *
     * Creates the set if it does not exist.
     *
     * @param string $key      The set key.
     * @param mixed  ...$members  Members to add.
     *
     * @return int  The number of members actually added (excluding duplicates).
     */
    public function setAdd(string $key, mixed ...$members): int;

    /**
     * Get all members of a set.
     *
     * @param string $key  The set key.
     *
     * @return list<mixed>  All members, or empty array if key does not exist.
     */
    public function setMembers(string $key): array;

    /**
     * Remove one or more members from a set.
     *
     * @param string $key      The set key.
     * @param mixed  ...$members  Members to remove.
     *
     * @return int  The number of members actually removed.
     */
    public function setRemove(string $key, mixed ...$members): int;

    /**
     * Check if a value is a member of a set.
     *
     * @param string $key     The set key.
     * @param mixed  $member  The value to check.
     *
     * @return bool  True if the member exists in the set.
     */
    public function setIsMember(string $key, mixed $member): bool;
}
