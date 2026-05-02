<?php

declare(strict_types=1);

/**
 * Copyright 2015-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */

namespace Horde\HashTable\Test\Lib\Integration;

use Horde\HashTable\Test\Lib\Unit\TestBase;
use Horde_HashTable_Memcache;
use Horde_Memcache;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the legacy HashTable memcache storage driver (lib/).
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2015-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */
#[CoversNothing]
#[Group('integration')]
class MemcacheTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        if ((!extension_loaded('memcache') && !extension_loaded('memcached'))) {
            self::$_skip = 'memcache/memcached extension not available.';
            return;
        }

        $config = self::getConfig('HASHTABLE_MEMCACHE_TEST_CONFIG', __DIR__ . '/..');
        if (!$config || !isset($config['hashtable']['memcache'])) {
            self::$_skip = 'Memcache configuration not available.';
            return;
        }

        $memcache = new Horde_Memcache(
            array_merge(
                $config['hashtable']['memcache'],
                ['prefix' => 'horde_hashtable_memcachetest']
            )
        );
        self::$_driver = new Horde_HashTable_Memcache(['memcache' => $memcache]);
    }
}
