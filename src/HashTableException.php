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

use Horde\Exception\DetailsTrait;
use Horde\Exception\HordeThrowable;
use Horde\Exception\LogThrowable;
use Horde\Exception\LogTrait;
use RuntimeException;

/**
 * Base exception for HashTable operations.
 */
class HashTableException extends RuntimeException implements HordeThrowable, LogThrowable
{
    use DetailsTrait;
    use LogTrait;
}
