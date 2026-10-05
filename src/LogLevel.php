<?php

declare(strict_types=1);

namespace Units\Logger;

/**
 * LogLevel - standard log levels (port of @synet/logger types/level).
 */
enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Warn = 'warn';
    case Error = 'error';
    case Silent = 'silent';

    /**
     * Higher number = more severe.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Debug => 0,
            self::Info => 1,
            self::Warn => 2,
            self::Error => 3,
            self::Silent => 4,
        };
    }

    /**
     * Should a message at $level be emitted given the $minimum threshold?
     */
    public static function shouldLog(self $level, self $minimum): bool
    {
        return $level->weight() >= $minimum->weight();
    }

    /**
     * Accept an enum or a case-insensitive string, defaulting to Info.
     */
    public static function normalize(self|string $level): self
    {
        if ($level instanceof self) {
            return $level;
        }

        return self::tryFrom(strtolower(trim($level))) ?? self::Info;
    }
}
