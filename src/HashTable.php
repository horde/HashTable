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
 * Core hash table interface.
 *
 * Provides a key-value store that accepts mixed values. Drivers serialize
 * transparently for string-only backends (memcache) and use native types
 * for capable backends (Redis).
 *
 * Returns null on cache miss (not false).
 */
interface HashTable
{
    /**
     * Retrieve a value by key.
     *
     * @param string $key  The key to retrieve.
     *
     * @return mixed  The stored value, or null if the key does not exist.
     */
    public function get(string $key): mixed;

    /**
     * Retrieve multiple values by key.
     *
     * @param list<string> $keys  Keys to retrieve.
     *
     * @return array<string, mixed>  Map of key => value. Non-existent keys
     *                               have null as their value.
     */
    public function getMultiple(array $keys): array;

    /**
     * Store a value.
     *
     * @param string $key    The key to store under.
     * @param mixed  $value  The value to store.
     * @param ?int   $ttl    Time-to-live in seconds. Null means no expiration.
     *
     * @throws HashTableException On storage failure.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void;

    /**
     * Replace a value only if the key already exists.
     *
     * @param string $key    The key to replace.
     * @param mixed  $value  The new value.
     * @param ?int   $ttl    Time-to-live in seconds. Null means no expiration.
     *
     * @return bool  True if the key existed and was replaced, false otherwise.
     */
    public function replace(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete one or more keys.
     *
     * @param string|list<string> $keys  Key or keys to delete.
     */
    public function delete(string|array $keys): void;

    /**
     * Check whether a key exists.
     *
     * @param string $key  The key to check.
     *
     * @return bool  True if the key exists and has not expired.
     */
    public function exists(string $key): bool;

    /**
     * Check existence of multiple keys.
     *
     * @param list<string> $keys  Keys to check.
     *
     * @return array<string, bool>  Map of key => existence.
     */
    public function existsMultiple(array $keys): array;

    /**
     * Remove all keys managed by this instance (scoped by prefix).
     */
    public function clear(): void;
}
