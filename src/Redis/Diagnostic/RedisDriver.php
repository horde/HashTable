<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\HashTable\Redis\Diagnostic;

enum RedisDriver: string
{
    case AUTO = 'auto';
    case PHPREDIS = 'phpredis';
    case PREDIS = 'predis';
}
