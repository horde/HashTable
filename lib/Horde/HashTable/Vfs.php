<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  HashTable
 */

/**
 * Implementation of HashTable for a VFS backend.
 *
 * NOTE: This driver is not carried forward to the modern Horde\HashTable\
 * interfaces (PSR-4 in src/). It served as a filesystem fallback for systems
 * without Redis or Memcache, but has no locking, no expiration, and
 * performance worse than not caching at all. Its only real consumer is
 * Horde_Core_HashTable_Vfs / PersistentSession (a session-scoped temp file
 * manager in IMP), which should eventually be replaced by a dedicated
 * TempFileStore service.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   HashTable
 * @since     1.2.0
 *
 * @deprecated The legacy Horde_HashTable_Base interface is deprecated. The
 *             VFS-backed file storage pattern has no PSR-4 equivalent in the
 *             new Horde\HashTable\Driver namespace; consumers needing
 *             session-scoped temp storage should look at
 *             Horde_Core_HashTable_Vfs / Horde_Core_HashTable_PersistentSession
 *             which intentionally remain on the legacy interface.
 */
class Horde_HashTable_Vfs extends Horde_HashTable_Base
{
    /**
     */
    protected $_persistent = true;

    /**
     * The VFS object.
     *
     * @var Horde_Vfs_Base
     */
    protected $_vfs;

    /**
     * @param array $params  Additional configuration parameters:
     * <pre>
     *   - vfs: (Horde_Vfs_Base) [REQUIRED] VFS object.
     *   - vfspath: (string) VFS path to use.
     * </pre>
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['vfs'])) {
            throw new InvalidArgumentException('Missing vfs parameter.');
        }

        parent::__construct(array_merge([
            'vfspath' => 'hashtable_vfs',
        ], $params));
    }

    /**
     */
    protected function _init()
    {
        $this->_vfs = $this->_params['vfs'];
    }

    /**
     */
    protected function _delete($keys)
    {
        $ret = true;

        foreach ($keys as $key) {
            try {
                $this->_vfs->deleteFile($this->_params['vfspath'], $key);
            } catch (Horde_Vfs_Exception $e) {
                $ret = false;
            }
        }

        return $ret;
    }

    /**
     */
    protected function _exists($keys)
    {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $this->_vfs->exists($this->_params['vfspath'], $key);
        }
        return $out;
    }

    /**
     */
    protected function _get($keys)
    {
        $out = [];

        foreach ($keys as $key) {
            try {
                $out[$key] = $this->_vfs->read($this->_params['vfspath'], $key);
            } catch (Horde_Vfs_Exception $e) {
                $out[$key] = false;
            }
        }

        return $out;
    }

    /**
     */
    protected function _set($key, $val, $opts)
    {
        try {
            $this->_vfs->writeData($this->_params['vfspath'], $key, $val, true);
        } catch (Horde_Vfs_Exception $e) {
            return false;
        }

        return true;
    }

    /**
     */
    public function clear()
    {
        try {
            $this->_vfs->emptyFolder($this->_params['vfspath']);
        } catch (Horde_Vfs_Exception $e) {
        }
    }

    /**
     */
    public function hkey($key)
    {
        /* Key is SHA-1 encoded (can't use FNV1-32 here, since key must be
         * the same if upgrading from PHP 5.3 -> 5.4+). */
        return hash('sha1', $this->_params['prefix'] . $key);
    }

}
