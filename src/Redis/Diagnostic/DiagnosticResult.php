<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\HashTable\Redis\Diagnostic;

final class DiagnosticResult
{
    /** @var list<TestResult> */
    private array $results = [];

    public function add(TestResult $result): void
    {
        $this->results[] = $result;
    }

    /** @return list<TestResult> */
    public function getResults(): array
    {
        return $this->results;
    }

    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === TestStatus::ERROR) {
                return true;
            }
        }
        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === TestStatus::WARNING) {
                return true;
            }
        }
        return false;
    }

    public function countByStatus(TestStatus $status): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if ($result->status === $status) {
                $count++;
            }
        }
        return $count;
    }
}
