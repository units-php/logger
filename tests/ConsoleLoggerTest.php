<?php

declare(strict_types=1);

namespace Units\Logger\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Units\Logger\ConsoleLogger;
use Units\Logger\ILogger;
use Units\Logger\LogLevel;

/**
 * The one adapter that ships: the console writer.
 *
 * Every assertion runs with colouring and timestamps off, so the expected line
 * is exactly what a reader would see in a log file.
 */
final class ConsoleLoggerTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    private function adapter(array $options = []): ConsoleLogger
    {
        return new ConsoleLogger($options + ['colorize' => false, 'timestamp' => false]);
    }

    private function capture(callable $call): string
    {
        $output = '';

        ob_start();

        try {
            $call();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }

    // -----------------------------------------------------------------------
    // Threshold
    // -----------------------------------------------------------------------

    public function testInfoIsWritten(): void
    {
        $adapter = $this->adapter(['level' => 'info']);

        $this->assertSame('[INFO] [Default] hello' . PHP_EOL, $this->capture(static fn () => $adapter->info('hello')));
    }

    public function testDebugIsFilteredBelowTheThreshold(): void
    {
        $adapter = $this->adapter(['level' => 'info']);

        $this->assertSame('', $this->capture(static fn () => $adapter->debug('noise')));
    }

    public function testSevereLevelsPassAnyThreshold(): void
    {
        $adapter = $this->adapter(['level' => 'debug']);

        $this->assertStringContainsString('[ERROR]', $this->capture(static fn () => $adapter->error('broken')));
    }

    public function testSilentSuppressesEverything(): void
    {
        $adapter = $this->adapter(['level' => 'silent']);

        $this->assertSame('', $this->capture(static fn () => $adapter->error('even this')));
    }

    public function testTheLevelMayBeAnEnum(): void
    {
        $adapter = $this->adapter(['level' => LogLevel::Warn]);

        $this->assertSame('', $this->capture(static fn () => $adapter->info('quiet')));
        $this->assertStringContainsString('[WARN]', $this->capture(static fn () => $adapter->warn('heard')));
    }

    public function testTheLevelIsInfoByDefault(): void
    {
        $adapter = $this->adapter();

        $this->assertSame('', $this->capture(static fn () => $adapter->debug('dropped')));
        $this->assertStringContainsString('[INFO]', $this->capture(static fn () => $adapter->info('kept')));
    }

    // -----------------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------------

    public function testPlaceholdersAreInterpolated(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->info('user {id} logged in', ['id' => 42]));

        $this->assertStringContainsString('user 42 logged in', $line);
    }

    public function testUnknownPlaceholdersAreLeftAlone(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->info('user {id} logged in', ['other' => 1]));

        $this->assertStringContainsString('user {id} logged in', $line);
    }

    public function testExtraArgumentsAreAppended(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->info('boot', 1, 'two'));

        $this->assertStringContainsString("boot 1 'two'", $line);
    }

    public function testThrowablesAreRenderedReadably(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->error('job failed', new RuntimeException('boom')));

        $this->assertStringContainsString('RuntimeException: boom', $line);
    }

    public function testALeadingArrayIsTheInterpolationContext(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->info('{a} and {b}', ['a' => 1, 'b' => 2]));

        $this->assertStringContainsString('1 and 2', $line);
    }

    public function testOtherArraysAreRenderedAsJson(): void
    {
        $adapter = $this->adapter();

        $line = $this->capture(static fn () => $adapter->info('payload', 'tag', ['a' => 1]));

        $this->assertStringContainsString('{"a":1}', $line);
    }

    // -----------------------------------------------------------------------
    // Context
    // -----------------------------------------------------------------------

    public function testTheContextIsIncluded(): void
    {
        $adapter = $this->adapter(['context' => 'suppliers']);

        $this->assertStringContainsString('[suppliers]', $this->capture(static fn () => $adapter->info('x')));
    }

    public function testChildExtendsTheContext(): void
    {
        $adapter = $this->adapter(['context' => 'app']);
        $child = $adapter->child('request');

        $this->assertInstanceOf(ILogger::class, $child);
        $this->assertStringContainsString('[app:request]', $this->capture(static fn () => $child->info('x')));
    }

    public function testChildKeepsTheThresholdAndTheContextOfItsParent(): void
    {
        $adapter = $this->adapter(['level' => 'error', 'context' => 'app']);
        $child = $adapter->child('request');

        $this->assertSame('', $this->capture(static fn () => $child->info('filtered')));
        $this->assertStringContainsString('[app:request]', $this->capture(static fn () => $child->error('kept')));
    }

    // -----------------------------------------------------------------------
    // Timestamps
    // -----------------------------------------------------------------------

    public function testNoTimestampMeansNoPrefix(): void
    {
        $adapter = $this->adapter();

        $this->assertStringStartsWith('[INFO]', $this->capture(static fn () => $adapter->info('x')));
    }

    public function testATimestampIsEmittedWhenEnabled(): void
    {
        $adapter = $this->adapter(['timestamp' => true]);

        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}\] \[INFO\]/',
            $this->capture(static fn () => $adapter->info('x'))
        );
    }

    public function testTheDateFormatIsSelectable(): void
    {
        $adapter = $this->adapter(['timestamp' => true, 'dateFormat' => 'epoch']);

        $this->assertMatchesRegularExpression(
            '/^\[\d+\] \[INFO\]/',
            $this->capture(static fn () => $adapter->info('x'))
        );
    }
}
