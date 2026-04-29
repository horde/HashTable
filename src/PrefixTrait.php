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
 * Adds key prefix support to a HashTable driver.
 *
 * Provides hkey() which prepends a configurable prefix to all storage keys,
 * namespacing this instance's keys within the shared backend.
 *
 * Requires: $this->prefix (string property on the using class).
 */
trait PrefixTrait
{
    /**
     * Prepend the instance prefix to a logical key.
     *
     * @param string $key  The logical key.
     *
     * @return string  The prefixed storage key.
     */
    protected function hkey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Prepend prefix to multiple keys, preserving the mapping.
     *
     * @param list<string> $keys  Logical keys.
     *
     * @return array<string, string>  Map of storage key => logical key.
     */
    protected function hkeys(array $keys): array
    {
        $map = [];
        foreach ($keys as $key) {
            $map[$this->hkey($key)] = $key;
        }
        return $map;
    }
}
