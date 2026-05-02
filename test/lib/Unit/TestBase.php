<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */

namespace Horde\HashTable\Test\Lib\Unit;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Base test class for legacy HashTable lib/ storage drivers.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */
abstract class TestBase extends TestCase
{
    protected static $_driver;

    protected static $_skip = false;

    public function setUp(): void
    {
        if (self::$_skip) {
            $this->markTestSkipped(self::$_skip);
        }
    }

    /**
     * Load test configuration from environment variable or conf.php file.
     */
    protected static function getConfig(string $env, ?string $path = null): ?array
    {
        $conf = [];
        $config = getenv($env);
        if ($config) {
            $json = json_decode($config, true);
            if ($json) {
                return $json;
            }
        }

        $configFile = ($path ?? __DIR__) . '/conf.php';
        if (file_exists($configFile)) {
            require $configFile;
            return $conf;
        }

        return null;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$_driver) {
            self::$_driver->clear();
        }
    }

    public function testSet()
    {
        $this->assertTrue(self::$_driver->set('foo', 1));

        /* This expires after a second. */
        $this->assertTrue(self::$_driver->set('foo2', 1, ['expire' => 1]));
        self::$_driver->exists('foo3');
        $this->assertFalse(self::$_driver->set('foo3', 1, ['replace' => true]));
        $this->assertTrue(self::$_driver->set('foo3', 1));
        $this->assertTrue(self::$_driver->set('foo3', 2, ['replace' => true]));
        /* @todo BC: 'timeout' will work also for 1.x. */
        $this->assertTrue(self::$_driver->set('foo4', 1, ['timeout' => 1]));
        sleep(2);
    }

    #[Depends('testSet')]
    public function testExists()
    {
        $this->assertTrue(self::$_driver->exists('foo'));
        $this->assertFalse(self::$_driver->exists('foo2'));
        $this->assertTrue(self::$_driver->exists('foo3'));
        $this->assertFalse(self::$_driver->exists('foo4'));
    }

    #[Depends('testSet')]
    #[Depends('testExists')]
    public function testGet()
    {
        $this->assertEquals(
            1,
            self::$_driver->get('foo')
        );
        $this->assertFalse(self::$_driver->get('foo2'));
        $this->assertEquals(
            2,
            self::$_driver->get('foo3')
        );
        $this->assertFalse(self::$_driver->get('foo4'));
    }

    #[Depends('testExists')]
    #[Depends('testSet')]
    #[Depends('testGet')]
    public function testDelete()
    {
        $this->assertTrue(self::$_driver->delete('foo'));
        $this->assertTrue(self::$_driver->delete('foo2'));
        $this->assertTrue(self::$_driver->delete('foo3'));
        $this->assertTrue(self::$_driver->delete('foo4'));
    }
}
