# Changelog

All notable changes to `units/logger` are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) ·
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-05

### Added

- `Units\Logger\Logger` — the log unit: a versioned identity that writes through
  an injected `ILogger` adapter, on the `patterns/unit` contract.
- `Units\Logger\ILogger` — the adapter contract (`debug`, `info`, `warn`, `error`,
  `log`, `child`), so the destination is a call-site decision.
- `Units\Logger\ConsoleLogger` — the console adapter: level filtering,
  `{placeholder}` interpolation, optional timestamps and ANSI colour.
- `Units\Logger\LogLevel` — the level enum, with weights, threshold comparison
  and case-insensitive normalization.
- `Units\Logger\LoggerSchema` — the unit's DNA (`Patterns\IUnitSchema`), a
  serializable shape that can be persisted alongside whatever the logger writes.
- `Units\Logger\LoggerProps` — the unit's own state: DNA, adapter and metadata.

[1.0.0]: https://github.com/units-php/logger/releases/tag/v1.0.0
