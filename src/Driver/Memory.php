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
use Horde\HashTable\PrefixTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * In-process memory HashTable implementation.
 *
 * Stores values in a PHP array with optional TTL expiration.
 * Does not implement LockableHashTable because it cannot
 * coordinate across processes.
 */
final class Memory implements HashTable
{
    use PrefixTrait;
    use LoggingTrait;

    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $data = [];

    public function __construct(
        private readonly string $prefix = 'hht_',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function get(string $key): mixed
    {
        $hk = $this->hkey($key);
        if (!$this->isValid($hk)) {
            $this->logMissed($key);
            return null;
        }
        $this->logRetrieved($key);
        return $this->data[$hk]['value'];
    }

    public function getMultiple(array $keys): array
    {
        $result = [];
        $hits = [];
        $misses = [];
        foreach ($keys as $key) {
            $hk = $this->hkey($key);
            if ($this->isValid($hk)) {
                $result[$key] = $this->data[$hk]['value'];
                $hits[] = $key;
            } else {
                $result[$key] = null;
                $misses[] = $key;
            }
        }
        if ($hits) {
            $this->logRetrieved($hits);
        }
        if ($misses) {
            $this->logMissed($misses);
        }
        return $result;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $hk = $this->hkey($key);
        $this->data[$hk] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
        $this->logSet($key, true);
    }

    public function replace(string $key, mixed $value, ?int $ttl = null): bool
    {
        $hk = $this->hkey($key);
        if (!$this->isValid($hk)) {
            return false;
        }
        $this->data[$hk] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
        $this->logSet($key, true);
        return true;
    }

    public function delete(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            unset($this->data[$this->hkey($key)]);
        }
        $this->logDeleted($keys);
    }

    public function exists(string $key): bool
    {
        return $this->isValid($this->hkey($key));
    }

    public function existsMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->isValid($this->hkey($key));
        }
        return $result;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    /**
     * Check if a storage key exists and has not expired.
     */
    private function isValid(string $hk): bool
    {
        if (!isset($this->data[$hk])) {
            return false;
        }
        if ($this->data[$hk]['expires'] !== null && $this->data[$hk]['expires'] < time()) {
            unset($this->data[$hk]);
            return false;
        }
        return true;
    }
}
