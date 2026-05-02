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
use Horde_HashTable_Predis;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use Predis\Client;

/**
 * Tests for the legacy HashTable Predis storage driver (lib/).
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
class PredisTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(Client::class)) {
            self::$_skip = 'predis/predis not installed';
            return;
        }

        $config = self::getConfig('HASHTABLE_PREDIS_TEST_CONFIG', __DIR__ . '/..');
        if (!$config || !isset($config['hashtable']['predis'])) {
            self::$_skip = 'Predis configuration not available.';
            return;
        }

        $predis = new Client($config['hashtable']['predis']);
        self::$_driver = new Horde_HashTable_Predis(['predis' => $predis]);
    }
}
