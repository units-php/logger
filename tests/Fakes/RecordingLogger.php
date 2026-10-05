<?php

declare(strict_types=1);

namespace Units\Logger\Tests\Fakes;

use Units\Logger\ILogger;
use Units\Logger\LogLevel;

/**
 * An adapter that records instead of writing.
 *
 * Lets a test assert what the unit delegated, and to whom - which is the whole
 * point of injecting the adapter rather than constructing it inside the unit.
 */
final class RecordingLogger implements ILogger
{
    /** @var list<array{level: string, message: string, args: array<int, mixed>}> */
    private array $records = [];

    public function __construct(private readonly string $context = 'root')
    {
    }

    public function debug(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Debug->value, $message, $args);
    }

    public function info(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Info->value, $message, $args);
    }

    public function warn(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Warn->value, $message, $args);
    }

    public function error(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Error->value, $message, $args);
    }

    public function log(LogLevel $level, string $message, mixed ...$args): void
    {
        $this->write($level->value, $message, $args);
    }

    public function child(string $context): ILogger
    {
        return new self($this->context . ':' . $context);
    }

    public function context(): string
    {
        return $this->context;
    }

    /**
     * @return list<array{level: string, message: string, args: array<int, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return list<string>
     */
    public function levels(): array
    {
        return array_map(static fn (array $record): string => $record['level'], $this->records);
    }

    /**
     * @param array<int, mixed> $args
     */
    private function write(string $level, string $message, array $args): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'args' => $args];
    }
}
