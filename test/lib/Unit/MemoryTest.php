<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */

namespace Horde\HashTable\Test\Lib\Unit;

use Horde_HashTable_Memory;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Tests for the legacy HashTable memory storage driver (lib/).
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */
#[CoversNothing]
class MemoryTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        self::$_driver = new Horde_HashTable_Memory();
    }
}
