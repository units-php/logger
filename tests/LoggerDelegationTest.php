<?php

declare(strict_types=1);

namespace Units\Logger\Tests;

use PHPUnit\Framework\TestCase;
use Units\Logger\ILogger;
use Units\Logger\Logger;
use Units\Logger\LogLevel;
use Units\Logger\Tests\Fakes\RecordingLogger;

/**
 * What the unit does with the adapter it was handed.
 */
final class LoggerDelegationTest extends TestCase
{
    private RecordingLogger $adapter;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->adapter = new RecordingLogger();
        $this->logger = Logger::create([
            'adapter'  => $this->adapter,
            'metadata' => ['service' => 'ecom'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Delegation
    // -----------------------------------------------------------------------

    public function testEveryShortcutReachesTheAdapter(): void
    {
        $this->logger->debug('d');
        $this->logger->info('i');
        $this->logger->warn('w');
        $this->logger->error('e');

        $this->assertSame(['debug', 'info', 'warn', 'error'], $this->adapter->levels());
    }

    public function testLogTakesALevelEnum(): void
    {
        $this->logger->log(LogLevel::Warn, 'careful');

        $this->assertSame(['warn'], $this->adapter->levels());
    }

    public function testArgumentsAreForwardedUntouched(): void
    {
        $this->logger->info('user {id} logged in', ['id' => 42], 'extra');

        $record = $this->adapter->records()[0];

        $this->assertSame('user {id} logged in', $record['message']);
        $this->assertSame([['id' => 42], 'extra'], $record['args']);
    }

    public function testTheUnitDoesNotFilterLevels(): void
    {
        $this->logger->debug('filtering is the adapter\'s job, not the unit\'s');

        $this->assertSame(
            ['debug'],
            $this->adapter->levels(),
            'the unit delegates every call; where the threshold lives is the adapter\'s business'
        );
    }

    // -----------------------------------------------------------------------
    // Children
    // -----------------------------------------------------------------------

    public function testChildReturnsANewUnit(): void
    {
        $child = $this->logger->child('request');

        $this->assertInstanceOf(Logger::class, $child);
        $this->assertNotSame($this->logger, $child);
        $this->assertInstanceOf(ILogger::class, $child);
    }

    public function testChildWrapsADerivedAdapter(): void
    {
        $child = $this->child('request');

        $this->assertInstanceOf(RecordingLogger::class, $child->adapter());
        $this->assertSame('root:request', $child->adapter()->context());
    }

    public function testChildKeepsTheIdentity(): void
    {
        $child = $this->child('request');

        $this->assertSame($this->logger->dna()->toArray(), $child->dna()->toArray());
        $this->assertSame(Logger::VERSION, $child->dna()->version());
    }

    public function testChildCarriesTheMetadata(): void
    {
        $this->assertSame(['service' => 'ecom'], $this->child('request')->adapterInfo()['metadata']);
    }

    public function testChildDoesNotDisturbItsParent(): void
    {
        $child = $this->child('request');
        $child->error('only the child writes here');

        $this->assertSame('root', $this->adapter->context());
        $this->assertSame([], $this->adapter->records());
        $this->assertNotSame($this->adapter, $child->adapter());
    }

    public function testAChildIsAUnitInItsOwnRight(): void
    {
        $grandchild = $this->child('request')->child('db');

        $this->assertInstanceOf(Logger::class, $grandchild);
        $this->assertSame('root:request:db', $grandchild->adapter()->context());
        $this->assertTrue($grandchild->dna()->equals($this->logger->dna()));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * child() is declared to return the adapter contract; here it is always a unit.
     */
    private function child(string $context): Logger
    {
        $child = $this->logger->child($context);

        $this->assertInstanceOf(Logger::class, $child);

        return $child;
    }
}
