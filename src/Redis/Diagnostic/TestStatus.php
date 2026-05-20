<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\HashTable\Redis\Diagnostic;

enum TestStatus: string
{
    case OK = 'OK';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case SKIP = 'SKIP';
    case INFO = 'INFO';
}
