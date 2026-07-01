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
 * Transparent serialization for string-only backends.
 *
 * Backends like Memcache can only store strings. This trait provides
 * serialize/deserialize methods that convert mixed values to/from strings
 * transparently. Strings are stored as-is (no double-serialization).
 *
 * Drivers that support native types (Redis for most operations) should NOT
 * use this trait for those operations — only for the generic set/get path
 * when storing arbitrary PHP values.
 */
trait SerializationTrait
{
    /**
     * Serialize a value for storage in a string-only backend.
     *
     * Strings pass through unchanged. All other types are PHP-serialized
     * with a type prefix to distinguish from raw strings on retrieval.
     *
     * @param mixed $value  The value to serialize.
     *
     * @return string  The serialized representation.
     */
    protected function serializeValue(mixed $value): string
    {
        if (is_string($value)) {
            return 's:' . $value;
        }
        return 'p:' . serialize($value);
    }

    /**
     * Deserialize a value retrieved from a string-only backend.
     *
     * Accepts mixed so co-tenant writers (legacy Horde_HashTable_Memcache,
     * prior releases of this package, or unrelated components sharing the
     * same memcached pool) that stored bare non-string scalars are passed
     * through instead of tripping a strict-type error. String values still
     * go through the s:/p: prefix protocol; anything else is returned as-is.
     *
     * @param mixed $stored  The raw stored value as returned by the backend.
     *
     * @return mixed  The original value.
     */
    protected function deserializeValue(mixed $stored): mixed
    {
        if (!is_string($stored)) {
            return $stored;
        }
        if (str_starts_with($stored, 's:')) {
            return substr($stored, 2);
        }
        if (str_starts_with($stored, 'p:')) {
            return unserialize(substr($stored, 2));
        }
        // Legacy data without prefix — treat as raw string
        return $stored;
    }
}
