<?php

declare(strict_types=1);

namespace Units\Logger\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Patterns\IUnit;
use Patterns\IUnitSchema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Units\Logger\ILogger;
use Units\Logger\Logger;
use Units\Logger\LoggerSchema;
use Units\Logger\Tests\Fakes\RecordingLogger;

/**
 * What the unit promises: identity, and how it is built.
 */
final class LoggerContractTest extends TestCase
{
    private function logger(?ILogger $adapter = null): Logger
    {
        return Logger::create(['adapter' => $adapter ?? new RecordingLogger()]);
    }

    // -----------------------------------------------------------------------
    // The unit contract
    // -----------------------------------------------------------------------

    public function testTheLoggerIsAUnit(): void
    {
        $this->assertTrue(
            (new ReflectionClass(Logger::class))->implementsInterface(IUnit::class),
            'Logger must satisfy the patterns/unit contract'
        );
    }

    public function testTheLoggerIsAlsoItsOwnAdapterContract(): void
    {
        $this->assertTrue(
            (new ReflectionClass(Logger::class))->implementsInterface(ILogger::class),
            'the unit is what callers type-hint, so it must be an ILogger'
        );
    }

    public function testTheUnitTrioIsPublicAndTyped(): void
    {
        $create = new ReflectionMethod(Logger::class, 'create');
        $this->assertTrue($create->isPublic());
        $this->assertTrue($create->isStatic());
        $this->assertSame('self', self::returnTypeName($create));

        $dna = new ReflectionMethod(Logger::class, 'dna');
        $this->assertTrue($dna->isPublic());
        $this->assertFalse($dna->isStatic());
        $this->assertSame('Patterns\\IUnitSchema', self::returnTypeName($dna));

        $whoami = new ReflectionMethod(Logger::class, 'whoami');
        $this->assertTrue($whoami->isPublic());
        $this->assertSame('string', self::returnTypeName($whoami));
    }

    public function testCreateIsTheOnlyWayIn(): void
    {
        $constructor = (new ReflectionClass(Logger::class))->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertTrue(
            $constructor->isPrivate(),
            'a unit is built through create(), never by hand - that is what keeps the adapter mandatory'
        );
    }

    // -----------------------------------------------------------------------
    // create() - the entry point
    // -----------------------------------------------------------------------

    public function testCreateRefusesToBuildWithoutAnAdapter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/adapter/');

        Logger::create([]);
    }

    public function testCreateRefusesAnAdapterThatIsNotAnILogger(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Logger::create(['adapter' => 'syslog']);
    }

    public function testCreateAcceptsAnyILogger(): void
    {
        $adapter = new RecordingLogger();

        $this->assertSame($adapter, $this->logger($adapter)->adapter());
    }

    public function testCreateIgnoresKeysItDoesNotKnow(): void
    {
        $logger = Logger::create(['adapter' => new RecordingLogger(), 'backend' => 'nats', 'ttl' => 5]);

        $this->assertSame([], $logger->adapterInfo()['metadata']);
    }

    public function testCreateKeepsMetadata(): void
    {
        $logger = Logger::create([
            'adapter'  => new RecordingLogger(),
            'metadata' => ['service' => 'ecom', 'region' => 'eu'],
        ]);

        $this->assertSame(['service' => 'ecom', 'region' => 'eu'], $logger->adapterInfo()['metadata']);
    }

    // -----------------------------------------------------------------------
    // DNA
    // -----------------------------------------------------------------------

    public function testDnaIdentifiesTheUnit(): void
    {
        $dna = $this->logger()->dna();

        $this->assertInstanceOf(LoggerSchema::class, $dna);
        $this->assertSame('log', $dna->id());
        $this->assertSame(Logger::VERSION, $dna->version());
        $this->assertSame('Logger v' . Logger::VERSION, $dna->label());
        $this->assertNotSame('', $dna->description());
    }

    public function testTheLedgerRecordsTheReleasedVersion(): void
    {
        $versions = $this->logger()->dna()->versions();

        $this->assertNotSame([], $versions, 'the ledger must not be empty');

        $released = end($versions);

        $this->assertIsArray($released);
        $this->assertSame(
            Logger::VERSION,
            $released['version'],
            'the version the unit reports must appear in its own ledger - a version that never shipped is not provenance'
        );
    }

    public function testDnaIsAvailableWithoutAnInstance(): void
    {
        $schema = Logger::schema();

        $this->assertSame('log', $schema->id());
        $this->assertSame(Logger::VERSION, $schema->version());
        $this->assertTrue($schema->equals($this->logger()->dna()));
    }

    public function testDnaRoundTripsThroughToArrayAndCreate(): void
    {
        $dna = $this->logger()->dna();
        $restored = LoggerSchema::create($dna->toArray());

        $this->assertSame($dna->toArray(), $restored->toArray());
        $this->assertSame($dna->jsonSerialize(), $restored->jsonSerialize());
        $this->assertSame(json_encode($dna), json_encode($restored));
    }

    public function testDnaSurvivesJson(): void
    {
        $decoded = json_decode((string) json_encode($this->logger()->dna()), true);

        $this->assertIsArray($decoded);
        $this->assertSame('log', $decoded['id']);
        $this->assertSame(Logger::VERSION, $decoded['version']);
    }

    // -----------------------------------------------------------------------
    // Provenance - the reason the unit carries DNA at all
    // -----------------------------------------------------------------------

    public function testAStoredLogRecordCanReportWhichLoggerVersionProducedIt(): void
    {
        $logger = $this->logger();

        // what a writer would persist next to what it produced
        $record = ['message' => 'suppliers recalculated', 'dna' => $logger->dna()->toArray()];

        // what an auditor asks months later
        $dna = LoggerSchema::create($record['dna']);

        $this->assertSame('log', $dna->id());
        $this->assertSame(Logger::VERSION, $dna->version());
        $this->assertSame($logger->dna()->toArray(), $dna->toArray());
    }

    // -----------------------------------------------------------------------
    // Voice
    // -----------------------------------------------------------------------

    public function testWhoamiNamesTheUnitAndTheAdapter(): void
    {
        $adapter = new RecordingLogger();
        $whoami = $this->logger($adapter)->whoami();

        $this->assertStringContainsString('Logger v' . Logger::VERSION, $whoami);
        $this->assertStringContainsString(RecordingLogger::class, $whoami);
    }

    public function testHelpIsRenderedFromTheDna(): void
    {
        $logger = $this->logger();
        $help = $logger->help();

        $this->assertStringContainsString($logger->dna()->label(), $help);
        $this->assertStringContainsString($logger->dna()->description(), $help);
        $this->assertStringContainsString('debug, info, warn, error, silent', $help);
        $this->assertStringContainsString(RecordingLogger::class, $help);
    }

    // -----------------------------------------------------------------------
    // State
    // -----------------------------------------------------------------------

    public function testPropsCarryBothDnaAndAdapter(): void
    {
        $adapter = new RecordingLogger();
        $props = $this->logger($adapter)->props();

        $this->assertSame($adapter, $props->adapter());
        $this->assertTrue($props->dna()->equals(Logger::schema()));
        $this->assertTrue($props->has('created'));
    }

    public function testAdapterInfoReportsTheAdapterAndMetadata(): void
    {
        $info = $this->logger()->adapterInfo();

        $this->assertSame(RecordingLogger::class, $info['adapter']);
        $this->assertSame([], $info['metadata']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private static function returnTypeName(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();

        return $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
    }
}
