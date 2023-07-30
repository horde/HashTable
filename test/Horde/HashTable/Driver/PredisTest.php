<?php
/**
 * Copyright 2015-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */

/**
 * Tests for the HashTable redis storage driver.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    HashTable
 * @subpackage UnitTests
 */
class Horde_HashTable_Driver_PredisTest extends Horde_HashTable_Driver_TestBase
{
    public static function setUpBeforeClass(): void
    {
        if (class_exists('Predis\\Client')) {
            if (($config = self::getConfig('HASHTABLE_PREDIS_TEST_CONFIG', __DIR__ . '/..')) &&
                isset($config['hashtable']['predis'])) {
                $predis = new Predis\Client($config['hashtable']['predis']);
                self::$_driver = new Horde_HashTable_Predis(array('predis' => $predis));
            } else {
                self::$_skip = 'Predis configuration not available.';
            }
        } else {
            self::$_skip = 'Predis\\Client class not available.';
        }
    }
}
