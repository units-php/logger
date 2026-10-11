# Units — Logger

**A logger that can say which version of itself wrote the line.**

A log unit: an identity (`log`, versioned) plus an injected adapter. The unit
does not know where the line goes — console today, NATS, Redis or a WebSocket
tomorrow — and because it carries DNA, whatever it writes can be attributed
later: which logger, at which version.

Part of the **Units** collection: concrete unit implementations on the
`patterns/unit` contract. One unit, one package.

- Package: `units/logger`
- Namespace: `Units\Logger`
- Unit contract: `Patterns\IUnit`
- Adapter contract: `Units\Logger\ILogger`
- Depends on: `patterns/unit`, `patterns/value-object`
- PHP: `>=8.1`

---

## Install

```bash
composer require units/logger
```

## Quickstart

```php
use Units\Logger\ConsoleLogger;
use Units\Logger\Logger;
use Units\Logger\LogLevel;

$logger = Logger::create([
    'adapter' => new ConsoleLogger([
        'context'   => 'suppliers',
        'level'     => 'info',
        'timestamp' => true,
    ]),
]);

$logger->info('user {id} logged in', ['id' => 42]);
$logger->warn('supplier {id} went quiet', ['id' => 'aurimas']);
$logger->error('job failed', new RuntimeException('boom'));
$logger->log(LogLevel::Warn, 'careful');

echo $logger->whoami();  // Logger v1.0.0 via Units\Logger\ConsoleLogger
```

```
[2026-10-05T09:14:02+02:00] [INFO] [suppliers] user 42 logged in
```

## Two objects, one seam

`Logger` is a **unit**: it has an identity, and it takes the log line and the
message and hands both to a collaborator. `ILogger` is that collaborator — the
adapter contract.

```php
namespace Units\Logger;

interface ILogger
{
    public function debug(string $message, mixed ...$args): void;
    public function info(string $message, mixed ...$args): void;
    public function warn(string $message, mixed ...$args): void;
    public function error(string $message, mixed ...$args): void;
    public function log(LogLevel $level, string $message, mixed ...$args): void;
    public function child(string $context): ILogger;
}
```

Every call on the unit is a call on the adapter — nothing is swallowed, buffered
or rewritten on the way through:

| Call on the unit | What it does |
| --- | --- |
| `debug()` · `info()` · `warn()` · `error()` | forwards to the same method on the adapter |
| `log(LogLevel::X, ...)` | forwards to `adapter->log()` |
| `child($context)` | wraps `adapter->child()` in a **new unit** — not a view of the old one |
| `dna()` · `whoami()` | answered by the unit; the adapter never sees them |

## Why the adapter is injected

The unit is constructed with the adapter, never for it, so the destination is a
call-site decision:

```php
final class NatsLogger implements ILogger
{
    public function __construct(private readonly NatsClient $nats) {}

    public function info(string $message, mixed ...$args): void
    {
        $this->nats->publish($this->subject, json_encode([$message, $args]));
    }

    // ... debug, warn, error, log, child
}

$logger = Logger::create(['adapter' => new NatsLogger($nats)]);
```

Swapping console output for a broker is one line, and it is the caller's line.
Nothing in this package depends on the console, and the unit never learns where
the line ended up.

### The unit does not filter

`Logger` delegates *every* call, including `debug()`. The threshold belongs to
the adapter, next to the decision about what the output looks like:

```php
$logger = Logger::create(['adapter' => new ConsoleLogger(['level' => 'error'])]);

$logger->info('never printed');   // reaches the adapter; the adapter drops it
$logger->error('always printed');
```

Two responsibilities, two objects. The unit owns identity and delegation; the
adapter owns filtering, formatting and transport.

## Levels

```php
use Units\Logger\LogLevel;

LogLevel::Info->weight();                            // 1 - higher is more severe
LogLevel::shouldLog(LogLevel::Warn, LogLevel::Info); // true  - warn survives an info threshold
LogLevel::normalize('ERROR');                        // LogLevel::Error
LogLevel::normalize('verbose');                      // LogLevel::Info  - unknown input is not fatal
```

| Level | Weight | Meaning |
| --- | --- | --- |
| `Debug` | 0 | detail you only want while investigating |
| `Info` | 1 | normal operation |
| `Warn` | 2 | surprising but handled |
| `Error` | 3 | something failed |
| `Silent` | 4 | a **threshold** only: nothing is severe enough to print |

## Child loggers

A child extends the context and keeps everything else:

```php
$request = $logger->child('request');

$request->info('handling {route}', ['route' => '/checkout']);
// [2026-10-05T09:14:02+02:00] [INFO] [suppliers:request] handling /checkout
```

`child()` returns a new unit wrapping a derived adapter. The parent is untouched
— the child's writes never reach the parent's adapter — and the child's DNA is
identical, because a child is a narrower logger, not a different one.

## Provenance

This is the part the unit buys you. The logger can hand back its own identity,
and that identity is a plain array you can persist:

```php
$record = [
    'message' => 'suppliers recalculated',
    'at'      => $now,
    'dna'     => $logger->dna()->toArray(),
];

// later, from storage
use Units\Logger\LoggerSchema;

$dna = LoggerSchema::create($record['dna']);

$dna->id();       // 'log'
$dna->version();  // '1.0.0'  - which logger wrote this line
```

A log line usually carries a timestamp and a context. When a threshold changed,
a formatter changed or a destination changed, the version that was live at the
time is the difference between a readable log and an archaeology project.

`Logger::schema()` gives you the same DNA without constructing a logger — useful
for a health endpoint or an admin screen:

```php
Logger::schema()->label();        // Logger v1.0.0
Logger::schema()->description();
Logger::schema()->version();      // '1.0.0'
```

## The console adapter

```php
new ConsoleLogger([
    'level'      => 'info',       // LogLevel|string; default info
    'context'    => 'suppliers',  // default 'Default'
    'timestamp'  => true,         // default true
    'colorize'   => false,        // default: auto-detected from the terminal
    'dateFormat' => 'iso',        // 'iso' | 'locale' | 'epoch'
]);
```

Messages interpolate `{placeholders}` from a leading array argument, and anything
else you pass is appended:

```php
$logger->info('user {id} logged in', ['id' => 42]);
// ... user 42 logged in

$logger->info('user {id} logged in', ['id' => 42], 'from 10.0.0.1');
// ... user 42 logged in 'from 10.0.0.1'

$logger->error('job failed', new RuntimeException('boom'));
// ... job failed RuntimeException: boom
```

Appended values are rendered with `var_export()`, so a string keeps its quotes and
`null`, `true` and `false` stay distinguishable from `''`, `1` and `0`:

```php
$logger->info('retrying', 3, null, true);
// ... retrying 3 NULL true
```

An unknown placeholder is left as written. `{id}` stays `{id}` — a typo in a log
message should be visible, not silent.

## What is deliberately not in this package

| Not here | Because |
| --- | --- |
| File, syslog, NATS, Redis adapters | implement `ILogger` — that is the whole point of the seam |
| A level threshold inside the unit | filtering is the adapter's job (see above) |
| A global or static logger | a unit is built by `create()` and handed to whoever needs it |
| Buffering, batching, retries | transport concerns; the adapter or the broker owns them |
| A `params()`/config API | configuration belongs to the adapter, exposed via `adapterInfo()` |
| Framework integration | `Logger` is an object; wire it yourself, in one line |

## Writing an adapter

1. Implement `Units\Logger\ILogger` — six methods, all returning `void` except `child()`.
2. Filter by level yourself if you have a threshold (`LogLevel::shouldLog()` is there for that).
3. Return a **new instance** from `child()` with the extended context; do not mutate `$this`.
4. Throw on transport failure. The unit does not catch anything for you, and a
   logger that silently swallows a failed write is worse than one that is noisy.
5. Pass it in: `Logger::create(['adapter' => new YourAdapter(...)])`.
6. Keep `metadata` in mind — `Logger::create(['adapter' => ..., 'metadata' => [...]])`
   carries anything your adapter or your application wants to attach.

## Testing

```bash
composer test
# or
vendor/bin/phpunit -c phpunit.xml
```

The suite runs without `composer install` — `tests/bootstrap.php` bridges to the
sibling pattern packages when they are not installed. It covers the unit
contract, DNA round-trip and provenance, delegation, child loggers, the level
enum and the console adapter's output.

## License

MIT
