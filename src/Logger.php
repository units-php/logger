<?php

declare(strict_types=1);

namespace Units\Logger;

use DateTimeImmutable;
use InvalidArgumentException;
use Patterns\IUnit;
use Patterns\IUnitSchema;

/**
 * Logger - the log unit: an identity plus an injected adapter.
 *
 * Built on the patterns/unit contract and nothing else: it implements
 * Patterns\IUnit (create + dna + whoami). No capability trinity, no schema
 * object, no validator, no teach/learn, no events.
 *
 * The adapter is INJECTED, never constructed here - the same seam as
 * Patterns\EventChannel, so a channel-backed adapter can publish logs to
 * NATS, Redis or a WebSocket without this class knowing:
 *
 *   Logger::create(['adapter' => new ConsoleLogger(['context' => 'ecom'])]);
 *
 * Because the unit carries DNA, every record it writes can be attributed:
 * which logger, at which version, produced it.
 */
final class Logger implements IUnit, ILogger
{
    public const VERSION = '1.0.1';

    private LoggerProps $props;

    private function __construct(LoggerProps $props)
    {
        $this->props = $props;
    }

    /**
     * @param array{adapter?: ILogger, metadata?: array<string, mixed>} $config
     */
    public static function create(array $config): self
    {
        $adapter = $config['adapter'] ?? null;

        if (!$adapter instanceof ILogger) {
            throw new InvalidArgumentException(sprintf(
                '[log] create() requires an injected "adapter" implementing %s - '
                . 'e.g. Logger::create([\'adapter\' => new ConsoleLogger()])',
                ILogger::class
            ));
        }

        return new self(LoggerProps::create([
            'dna'      => self::schema(),
            'adapter'  => $adapter,
            'metadata' => (array) ($config['metadata'] ?? []),
            'created'  => new DateTimeImmutable(),
        ]));
    }

    /**
     * The unit's DNA, available without constructing an instance.
     *
     * The version is what lets a stored record say which code produced it. What
     * changed between two versions is the repository's job.
     */
    public static function schema(): LoggerSchema
    {
        return LoggerSchema::create([
            'id'          => 'log',
            'version'     => self::VERSION,
            'label'       => 'Logger v' . self::VERSION,
            'description' => 'Log unit - writes through an injected adapter (Patterns\\IUnit).',
        ]);
    }

    // -----------------------------------------------------------------------
    // IUnit
    // -----------------------------------------------------------------------

    public function dna(): IUnitSchema
    {
        return $this->props->dna();
    }

    public function whoami(): string
    {
        return sprintf('%s via %s', $this->dna()->label(), $this->adapter()::class);
    }

    // -----------------------------------------------------------------------
    // State
    // -----------------------------------------------------------------------

    public function props(): LoggerProps
    {
        return $this->props;
    }

    public function adapter(): ILogger
    {
        return $this->props->adapter();
    }

    /**
     * @return array{adapter: class-string, metadata: array<string, mixed>}
     */
    public function adapterInfo(): array
    {
        return [
            'adapter'  => $this->adapter()::class,
            'metadata' => (array) $this->props->get('metadata', []),
        ];
    }

    // -----------------------------------------------------------------------
    // ILogger - every call delegates to the injected adapter
    // -----------------------------------------------------------------------

    public function debug(string $message, mixed ...$args): void
    {
        $this->adapter()->debug($message, ...$args);
    }

    public function info(string $message, mixed ...$args): void
    {
        $this->adapter()->info($message, ...$args);
    }

    public function warn(string $message, mixed ...$args): void
    {
        $this->adapter()->warn($message, ...$args);
    }

    public function error(string $message, mixed ...$args): void
    {
        $this->adapter()->error($message, ...$args);
    }

    public function log(LogLevel $level, string $message, mixed ...$args): void
    {
        $this->adapter()->log($level, $message, ...$args);
    }

    /**
     * A child logger: a new unit wrapping a derived adapter.
     */
    public function child(string $context): ILogger
    {
        return self::create([
            'adapter'  => $this->adapter()->child($context),
            'metadata' => (array) $this->props->get('metadata', []),
        ]);
    }

    // -----------------------------------------------------------------------
    // Living documentation (not part of the pattern)
    // -----------------------------------------------------------------------

    /**
     * Rendered from the DNA and the level enum, never hand-written - so it
     * cannot drift from what the unit actually is.
     */
    public function help(): string
    {
        $levels = implode(', ', array_map(
            static fn (LogLevel $level): string => $level->value,
            LogLevel::cases()
        ));

        return implode(PHP_EOL, [
            $this->whoami(),
            '',
            '  ' . $this->dna()->description(),
            '  levels  : ' . $levels,
            '  adapter : ' . $this->adapter()::class,
            '',
            '  $logger = Logger::create([\'adapter\' => new ConsoleLogger([\'context\' => \'ecom\'])]);',
            '  $logger->info(\'user {id} logged in\', [\'id\' => 42]);',
        ]);
    }
}
