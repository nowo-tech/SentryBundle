# Sentry Bundle - Demo

This directory contains a demo project demonstrating the usage of the Sentry Bundle with Symfony.

## Features

- Example Sentry integration
- Doctrine DBAL SQL exception reporting demos (<code>/sentry/sql-caught</code>, <code>/sentry/sql-uncaught</code>)
- Docker setup for easy development
- Comprehensive test suite
- Symfony Web Profiler included for debugging (dev and test environments)

## Demo Project

1. **Symfony 8.1 Demo** - PHP 8.4 (Port 8008 by default, configurable via `.env`)

The demo includes:
- FrankenPHP (Caddy + PHP) Docker setup — see [../docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md)
- Comprehensive test suite
- Port configuration via `.env` file
- Symfony Web Profiler for debugging (dev and test environments)
- Properly configured routing with attribute-based routes

## Sentry demo routes

Open <code>/sentry</code> in the running demo to browse all use cases. Highlights:

| Route | What it demonstrates |
|-------|----------------------|
| <code>/sentry/sql-caught</code> | Invalid SQL caught in application code; <code>dbal_exception_reporter</code> still sends it to Sentry |
| <code>/sentry/sql-uncaught</code> | Uncaught SQL error; middleware reports and <code>before_send_handler</code> deduplicates |
| <code>/sentry/access-denied</code> | Pure 403 filtered by <code>before_send_handler</code> |
| <code>/sentry/trigger-error</code> | Uncaught exception captured by the Sentry SDK |

The demo uses SQLite (<code>var/demo.db</code>) via Doctrine DBAL. Rebuild Docker images after pulling so <code>pdo_sqlite</code> is available.

## Quick Start with Docker

```bash
cd demo
make up-symfony8
make install-symfony8
```

Access at: http://localhost:8008 (default, configurable via `PORT` in `.env`).

## Running Tests

```bash
cd demo
make test-symfony8
# or
make test-all
```

Or run tests directly in the demo:

```bash
cd demo/symfony8
docker-compose exec php vendor/bin/phpunit
```

## Available Commands

All commands are available through the Makefile in the `demo/` directory:

- `make up-symfony8` - Start demo containers
- `make down-symfony8` - Stop demo containers
- `make install-symfony8` - Install dependencies
- `make shell-symfony8` - Open shell in PHP container
- `make logs-symfony8` - Show container logs
- `make test-symfony8` - Run tests
- `make test-all` - Run tests for all demos
- `make clean` - Remove vendor and cache

## License

This demo is part of the Sentry Bundle project and follows the same MIT license.
