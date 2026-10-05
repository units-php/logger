<?php

declare(strict_types=1);

namespace Units\Logger\Tests;

use PHPUnit\Framework\TestCase;
use Units\Logger\LogLevel;

final class LogLevelTest extends TestCase
{
    public function testTheLevelsAreTheDocumentedOnes(): void
    {
        $this->assertSame(
            ['debug', 'info', 'warn', 'error', 'silent'],
            array_map(static fn (LogLevel $level): string => $level->value, LogLevel::cases())
        );
    }

    public function testWeightIncreasesWithSeverity(): void
    {
        $weights = array_map(static fn (LogLevel $level): int => $level->weight(), LogLevel::cases());

        $this->assertSame([0, 1, 2, 3, 4], $weights);
    }

    public function testShouldLogComparesAgainstTheThreshold(): void
    {
        $this->assertFalse(LogLevel::shouldLog(LogLevel::Debug, LogLevel::Info));
        $this->assertTrue(LogLevel::shouldLog(LogLevel::Info, LogLevel::Info));
        $this->assertTrue(LogLevel::shouldLog(LogLevel::Error, LogLevel::Info));
        $this->assertFalse(LogLevel::shouldLog(LogLevel::Warn, LogLevel::Silent));
    }

    public function testNormalizeAcceptsAnEnum(): void
    {
        $this->assertSame(LogLevel::Warn, LogLevel::normalize(LogLevel::Warn));
    }

    public function testNormalizeAcceptsACaseInsensitiveString(): void
    {
        $this->assertSame(LogLevel::Debug, LogLevel::normalize('DEBUG'));
        $this->assertSame(LogLevel::Error, LogLevel::normalize('  Error  '));
    }

    public function testNormalizeFallsBackToInfo(): void
    {
        $this->assertSame(LogLevel::Info, LogLevel::normalize('verbose'));
        $this->assertSame(LogLevel::Info, LogLevel::normalize(''));
    }
}
