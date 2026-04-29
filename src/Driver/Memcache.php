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

use Horde\HashTable\LockableHashTable;
use Horde\HashTable\LoggingTrait;
use Horde\HashTable\MissTrackingTrait;
use Horde\HashTable\PrefixTrait;
use Horde\HashTable\SerializationTrait;
use Horde\Memcache\HordeMemcacheInterface;
use Horde\Memcache\MemcacheApi;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Memcache-backed HashTable implementation.
 *
 * Delegates to HordeMemcacheInterface (or MemcacheApi) for storage and locking.
 * Serializes mixed values transparently since Memcache is a string-only store.
 *
 * Implements LockableHashTable because Memcache supports real
 * cross-process locking via atomic add operations.
 */
final class Memcache implements LockableHashTable
{
    use PrefixTrait;
    use MissTrackingTrait;
    use LoggingTrait;
    use SerializationTrait;

    public function __construct(
        private readonly MemcacheApi $memcache,
        private readonly string $prefix = 'hht_',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function get(string $key): mixed
    {
        if ($this->isMiss($key)) {
            $this->logMissed($key);
            return null;
        }

        $result = $this->memcache->get($this->hkey($key));

        if ($result === null) {
            $this->recordMiss($key);
            $this->logMissed($key);
            return null;
        }

        $this->logRetrieved($key);
        return $this->deserializeValue($result);
    }

    public function getMultiple(array $keys): array
    {
        $partition = $this->partitionByMissCache($keys);
        $output = array_fill_keys($partition['missed'], null);

        if (empty($partition['pending'])) {
            if ($partition['missed']) {
                $this->logMissed($partition['missed']);
            }
            return $output;
        }

        $keyMap = [];
        foreach ($partition['pending'] as $key) {
            $keyMap[$this->hkey($key)] = $key;
        }

        $results = $this->memcache->getItems(array_keys($keyMap));

        $hits = [];
        $misses = $partition['missed'];

        foreach ($keyMap as $storageKey => $logicalKey) {
            $val = $results[$storageKey] ?? false;
            if ($val === false) {
                $output[$logicalKey] = null;
                $this->recordMiss($logicalKey);
                $misses[] = $logicalKey;
            } else {
                $output[$logicalKey] = $this->deserializeValue($val);
                $hits[] = $logicalKey;
            }
        }

        if ($hits) {
            $this->logRetrieved($hits);
        }
        if ($misses) {
            $this->logMissed($misses);
        }

        return $output;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        $serialized = $this->serializeValue($value);
        $result = $this->memcache->set($this->hkey($key), $serialized, $ttl);

        $this->logSet($key, $result);
        if ($result) {
            $this->clearMiss($key);
        }
    }

    public function replace(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($this->isMiss($key)) {
            return false;
        }

        $serialized = $this->serializeValue($value);
        $result = $this->memcache->replace($this->hkey($key), $serialized, $ttl ?? 0);

        if ($result) {
            $this->clearMiss($key);
            $this->logSet($key, true);
        }
        return (bool) $result;
    }

    public function delete(string|array $keys): void
    {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            $this->memcache->delete($this->hkey($key));
            $this->recordMiss($key);
        }
        $this->logDeleted($keys);
    }

    public function exists(string $key): bool
    {
        if ($this->isMiss($key)) {
            return false;
        }
        $result = $this->memcache->has($this->hkey($key));
        if (!$result) {
            $this->recordMiss($key);
        }
        return $result;
    }

    public function existsMultiple(array $keys): array
    {
        $output = [];
        foreach ($keys as $key) {
            $output[$key] = $this->exists($key);
        }
        return $output;
    }

    public function clear(): void
    {
        $this->memcache->flush();
        $this->clearAllMisses();
    }

    public function lock(string $key): void
    {
        $this->memcache->lock($this->hkey($key));
    }

    public function unlock(string $key): void
    {
        $this->memcache->unlock($this->hkey($key));
    }
}
