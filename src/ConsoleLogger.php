<?php

declare(strict_types=1);

namespace Units\Logger;

use Throwable;

/**
 * ConsoleLogger - the single console (echo) adapter.
 *
 * Ported from @synet/logger's ConsoleLogger, trimmed to the essentials:
 * level filtering, optional timestamp, optional ANSI colour and {placeholder}
 * interpolation.
 *
 * @phpstan-type ConsoleOptions array{
 *     level?: LogLevel|string,
 *     context?: string,
 *     timestamp?: bool,
 *     colorize?: bool,
 *     dateFormat?: 'iso'|'locale'|'epoch'
 * }
 */
final class ConsoleLogger implements ILogger
{
    private LogLevel $level;

    private string $context;

    private bool $timestamp;

    private bool $colorize;

    private string $dateFormat;

    /**
     * @param array{
     *     level?: LogLevel|string,
     *     context?: string,
     *     timestamp?: bool,
     *     colorize?: bool,
     *     dateFormat?: string
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->level = LogLevel::normalize($options['level'] ?? LogLevel::Info);
        $this->context = (string) ($options['context'] ?? 'Default');
        $this->timestamp = (bool) ($options['timestamp'] ?? true);
        $this->dateFormat = (string) ($options['dateFormat'] ?? 'iso');
        $this->colorize = array_key_exists('colorize', $options)
            ? (bool) $options['colorize']
            : (function_exists('stream_isatty') && @stream_isatty(\STDOUT));
    }

    public function debug(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Debug, $message, $args);
    }

    public function info(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Info, $message, $args);
    }

    public function warn(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Warn, $message, $args);
    }

    public function error(string $message, mixed ...$args): void
    {
        $this->write(LogLevel::Error, $message, $args);
    }

    public function log(LogLevel $level, string $message, mixed ...$args): void
    {
        $this->write($level, $message, $args);
    }

    public function child(string $context): ILogger
    {
        return new self([
            'level' => $this->level,
            'context' => $this->context . ':' . $context,
            'timestamp' => $this->timestamp,
            'colorize' => $this->colorize,
            'dateFormat' => $this->dateFormat,
        ]);
    }

    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    // =========================================================================
    // PRIVATE
    // =========================================================================

    /**
     * @param array<int, mixed> $args
     */
    private function write(LogLevel $level, string $message, array $args): void
    {
        if (!LogLevel::shouldLog($level, $this->level)) {
            return;
        }

        $parts = [];

        if ($this->timestamp) {
            $parts[] = '[' . $this->timestamp() . ']';
        }

        $parts[] = '[' . strtoupper($level->value) . ']';
        $parts[] = '[' . $this->context . ']';

        $prefix = $this->colorize(implode(' ', $parts), $level);
        $line = $prefix . ' ' . $this->render($message, $args);

        echo $line . PHP_EOL;
    }

    private function timestamp(): string
    {
        $now = new \DateTimeImmutable();

        return match ($this->dateFormat) {
            'locale' => $now->format('Y-m-d H:i:s'),
            'epoch' => (string) $now->getTimestamp(),
            default => $now->format(DATE_ATOM),
        };
    }

    /**
     * Interpolate {placeholders} from a leading array arg, then append any
     * remaining args.
     *
     * @param array<int, mixed> $args
     */
    private function render(string $message, array $args): string
    {
        if ($args !== [] && is_array($args[0])) {
            $context = $args[0];
            array_shift($args);

            $message = (string) preg_replace_callback(
                '/\{([\w.]+)\}/',
                static fn (array $m): string => array_key_exists($m[1], $context)
                    ? (string) $context[$m[1]]
                    : $m[0],
                $message
            );
        }

        if ($args === []) {
            return $message;
        }

        return $message . ' ' . implode(' ', array_map($this->stringify(...), $args));
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof Throwable) {
            return $value::class . ': ' . $value->getMessage();
        }

        if ($value === null || is_scalar($value)) {
            return var_export($value, true);
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json !== false ? $json : print_r($value, true);
    }

    private function colorize(string $text, LogLevel $level): string
    {
        if (!$this->colorize) {
            return $text;
        }

        $color = match ($level) {
            LogLevel::Debug => "\033[90m",
            LogLevel::Info => "\033[36m",
            LogLevel::Warn => "\033[33m",
            LogLevel::Error => "\033[31m",
            LogLevel::Silent => '',
        };

        return $color === '' ? $text : $color . $text . "\033[0m";
    }
}
