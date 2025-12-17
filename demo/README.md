# Sentry Bundle - Demo

This directory contains demo projects demonstrating the usage of the Sentry Bundle with different Symfony and PHP versions.

## Features

- Demo projects for different Symfony/PHP combinations
- Example Sentry integration
- Docker setup for easy development
- Independent Docker containers for each demo
- Comprehensive test suite for each demo
- Symfony Web Profiler included for debugging (dev and test environments)

## Demo Projects

The bundle includes demo projects:

1. **Symfony 7.0 Demo** - PHP 8.2 (Port 8001 by default, configurable via `.env`)
2. **Symfony 8.0 Demo** - PHP 8.4 (Port 8001 by default, configurable via `.env`)
3. **Symfony 8.0 Demo with PHP 8.5** - PHP 8.5 (Port 8001 by default, configurable via `.env`)

Each demo is independent and includes:
- Complete Docker setup with PHP-FPM and Nginx
- Comprehensive test suite
- Port configuration via `.env` file
- Symfony Web Profiler for debugging (dev and test environments)
- Properly configured routing with attribute-based routes

## Quick Start with Docker

Each demo has its own `docker-compose.yml` and can be run independently. You can start any demo you want:

### Symfony 7.0 Demo (PHP 8.2)

```bash
# Navigate to the demo directory
cd demo/demo-symfony7

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Access at: http://localhost:8001 (default port, configurable via PORT env variable)
```

Or using the Makefile from the `demo/` directory:

```bash
cd demo
make up-symfony7
make install-symfony7
```

### Symfony 8.0 Demo (PHP 8.4)

```bash
cd demo
make up-symfony8
make install-symfony8
```

### Symfony 8.0 Demo with PHP 8.5

```bash
cd demo
make up-symfony8-php85
make install-symfony8-php85
```

## Running Tests

Each demo includes a comprehensive test suite. You can run tests for individual demos or all demos at once:

### Run tests for a specific demo

```bash
cd demo
make test-symfony7        # Run tests for Symfony 7.0 demo
make test-symfony8        # Run tests for Symfony 8.0 demo
make test-symfony8-php85  # Run tests for Symfony 8.0 + PHP 8.5 demo
```

### Run all tests

```bash
cd demo
make test-all
```

Or run tests directly in a demo:

```bash
cd demo/demo-symfony7
docker-compose exec php vendor/bin/phpunit
```

## Available Commands

All commands are available through the Makefile in the `demo/` directory:

- `make up-symfony7` / `make up-symfony8` / `make up-symfony8-php85` - Start demo containers
- `make down-symfony7` / `make down-symfony8` / `make down-symfony8-php85` - Stop demo containers
- `make install-symfony7` / `make install-symfony8` / `make install-symfony8-php85` - Install dependencies
- `make shell-symfony7` / `make shell-symfony8` / `make shell-symfony8-php85` - Open shell in PHP container
- `make logs-symfony7` / `make logs-symfony8` / `make logs-symfony8-php85` - Show container logs
- `make test-symfony7` / `make test-symfony8` / `make test-symfony8-php85` - Run tests for specific demo
- `make test-all` - Run tests for all demos
- `make clean` - Remove vendor and cache from all demos

## License

This demo is part of the Sentry Bundle project and follows the same MIT license.
