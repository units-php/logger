<?php

declare(strict_types=1);

namespace Units\Logger;

/**
 * ILogger - the adapter contract the Logger unit delegates to.
 *
 * Mirrors @synet/logger's ILogger. Adapters are INJECTED into the unit
 * (see Logger::create(['adapter' => ...])), not constructed inside it.
 */
interface ILogger
{
    public function debug(string $message, mixed ...$args): void;

    public function info(string $message, mixed ...$args): void;

    public function warn(string $message, mixed ...$args): void;

    public function error(string $message, mixed ...$args): void;

    public function log(LogLevel $level, string $message, mixed ...$args): void;

    /**
     * Derive a child adapter with an extended context.
     */
    public function child(string $context): ILogger;
}
