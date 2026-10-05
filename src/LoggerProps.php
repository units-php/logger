<?php

declare(strict_types=1);

namespace Units\Logger;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * LoggerProps - the logger's own state, local to this unit.
 *
 * Ported from the old Unit\UnitProps, minus ArrayAccess and the magic
 * accessors - neither was used, and a unit's props should not need three ways
 * to be read.
 *
 * Deliberately NOT a Patterns\ValueObject: props hold a live ILogger adapter,
 * so value-equality and JSON serialization would be misleading. Only the DNA
 * (LoggerSchema) is a serializable shape, and that is what persistence needs.
 */
final class LoggerProps
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): self
    {
        if (!array_key_exists('dna', $data)) {
            throw new InvalidArgumentException('[log] LoggerProps require a "dna" key');
        }

        $dna = $data['dna'];

        if (!$dna instanceof LoggerSchema) {
            if (!is_array($dna)) {
                throw new InvalidArgumentException(
                    '[log] LoggerProps "dna" must be a LoggerSchema or an array'
                );
            }

            $data['dna'] = LoggerSchema::create($dna);
        }

        if (
            isset($data['created'])
            && $data['created'] instanceof DateTimeInterface
            && !$data['created'] instanceof DateTimeImmutable
        ) {
            $data['created'] = DateTimeImmutable::createFromInterface($data['created']);
        }

        return new self($data);
    }

    public function dna(): LoggerSchema
    {
        /** @var LoggerSchema $dna */
        $dna = $this->data['dna'];

        return $dna;
    }

    public function adapter(): ILogger
    {
        /** @var ILogger $adapter */
        $adapter = $this->data['adapter'];

        return $adapter;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Immutable update - returns new props; the original is untouched.
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        return self::create(array_merge($this->data, $overrides));
    }

    public function without(string ...$keys): self
    {
        $data = $this->data;

        foreach ($keys as $key) {
            unset($data[$key]);
        }

        return self::create($data);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
