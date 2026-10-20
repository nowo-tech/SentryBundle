# Code coverage analysis — SentryErrorReporter

This document summarizes test coverage for the `SentryErrorReporter` service.

## Table of contents

- [Summary](#summary)
- [Method analysis](#method-analysis)
  - [1. `__construct()`](#1-__construct)
  - [2. `captureException()`](#2-captureexception)
  - [3. `captureMessage()`](#3-capturemessage)
  - [4. `addBreadcrumb()`](#4-addbreadcrumb)
  - [5. `configureScope()`](#5-configurescope)
  - [6. Private helpers](#6-private-helpers)
- [How to regenerate](#how-to-regenerate)

## Summary

- **Total methods**: 9 (7 public + 2 private)
- **Total tests**: 48+ cases
- **Estimated coverage**: 100%

## Method analysis

### 1. `__construct()`

**Lines**: 37–42 · **Coverage**: complete

- Null logger: `testConstructorWithNullLogger()`
- With logger: `testConstructorWithLogger()`
- Empty config: `testConstructorWithEmptyConfig()`

### 2. `captureException()`

**Lines**: 57–90 · **Coverage**: complete

Covered cases include success with/without context and message, different exception types, null `eventId`, Sentry/`configureScope` failures, and runs without a logger.

### 3. `captureMessage()`

**Lines**: 105–138 · **Coverage**: complete

Covered cases include success with/without context and level, null `eventId`, Sentry/`configureScope` failures, and runs without a logger.

### 4. `addBreadcrumb()`

**Coverage**: complete — success, Sentry failure, and no-logger paths.

### 5. `configureScope()`

**Coverage**: complete — with/without user and extras; Sentry failure.

### 6. Private helpers

Private helpers used by the public API are exercised indirectly by the tests above.

## How to regenerate

```bash
make test-coverage
# or
composer test-coverage
```

See also [CONTRIBUTING.md](CONTRIBUTING.md) and root coverage targets (`coverage-check`).
