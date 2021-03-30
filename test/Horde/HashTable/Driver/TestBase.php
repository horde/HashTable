<?php
/**
 * Copyright 2013-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2013 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */
namespace Horde\HashTable\Driver;
use Horde_Test_Case as TestCase;

/**
 * Tests for the HashTable storage drivers.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2013 Horde LLC
 * @ignore
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
        $this->assertTrue(self::$_driver->set('foo2', 1, array('expire' => 1)));
        self::$_driver->exists('foo3');
        $this->assertFalse(self::$_driver->set('foo3', 1, array('replace' => true)));
        $this->assertTrue(self::$_driver->set('foo3', 1));
        $this->assertTrue(self::$_driver->set('foo3', 2, array('replace' => true)));
        /* @todo BC: 'timeout' will work also for 1.x. */
        $this->assertTrue(self::$_driver->set('foo4', 1, array('timeout' => 1)));
        sleep(2);
    }

    /**
     * @depends testSet
     */
    public function testExists()
    {
        $this->assertTrue(self::$_driver->exists('foo'));
        $this->assertFalse(self::$_driver->exists('foo2'));
        $this->assertTrue(self::$_driver->exists('foo3'));
        $this->assertFalse(self::$_driver->exists('foo4'));
    }

    /**
     * @depends testSet
     * @depends testExists
     */
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

    /**
     * @depends testExists
     * @depends testSet
     * @depends testGet
     */
    public function testDelete()
    {
        $this->assertTrue(self::$_driver->delete('foo'));
        $this->assertTrue(self::$_driver->delete('foo2'));
        $this->assertTrue(self::$_driver->delete('foo3'));
        $this->assertTrue(self::$_driver->delete('foo4'));
    }

}
