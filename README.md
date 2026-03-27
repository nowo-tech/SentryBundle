# Sentry Bundle

[![CI](https://github.com/nowo-tech/SentryBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/SentryBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/sentry-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/sentry-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/sentry-bundle.svg)](https://packagist.org/packages/nowo-tech/sentry-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/sentry-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/SentryBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Give it a star on GitHub! It helps us maintain and improve the project.

Symfony bundle extending Sentry integration with enhanced event listeners and configuration options.

## Features

- ✅ Enhanced request context with user and session information
- ✅ Automatic filtering of access denied exceptions
- ✅ Uptime bot detection and handling
- ✅ Compatible with existing Sentry configuration
- ✅ Full integration with Sentry Symfony bundle (extends SentryBundle)
- ✅ Fully configurable event listeners (enable/disable per listener)
- ✅ Type-safe error handling
- ✅ 100% code coverage with comprehensive tests
- ✅ Demo projects for Symfony 7.0, 8.0, and 8.0 with PHP 8.5

## Installation

```bash
composer require nowo-tech/sentry-bundle
```

**Symfony Flex Recipe**: If you're installing from Packagist, the Symfony Flex recipe will automatically:
- Register the bundle in `config/bundles.php`
- Create the default configuration file at `config/packages/nowo_sentry.yaml`

**Manual Installation** (for private bundles or Git installations):
If the Flex recipe doesn't work, manually register the bundle in your `config/bundles.php`:

```php
<?php

return [
  // ... other bundles
  Nowo\SentryBundle\NowoSentryBundle::class => ['all' => true],
];
```

**Important**: 
- This bundle **extends** the official Sentry Symfony bundle (`Sentry\SentryBundle\SentryBundle`). 
- The parent bundle is automatically registered when you register `NowoSentryBundle`.
- Make sure you have `sentry/sentry-symfony` installed and configured first.
- Your existing `config/packages/sentry.yaml` configuration will continue to work as before.

## Usage

### Basic Setup

The bundle works out of the box with your existing Sentry configuration. No additional configuration is required.

Your existing `config/packages/sentry.yaml` will continue to work as before:

```yaml
when@prod:
  sentry:
    dsn: "%env(SENTRY_DSN)%"
    messenger:
      enabled: true
      capture_soft_fails: true
    options:
      environment: '%kernel.environment%'
      release: '%env(APP_LAST_VERSION)%'
      # ... your existing configuration
```

### Event listeners

The bundle registers **three event listeners**:

#### 1. SentryRequestListener

Enriches Sentry reports with request context:
- Sets domain and environment tags
- Configures user information if available
- Adds session ID to extra data

#### 2. IgnoreAccessDeniedSentryListener

Prevents `AccessDeniedException` from being reported to Sentry, reducing noise in your error tracking.

#### 3. SentryUptimeBotListener

Handles requests from uptime monitoring bots (Sentry Uptime Bot, Uptime-Kuma, kube-probe) by returning a simple OK response for specific paths (`/dashboard`, `/`, `/login`).

### SentryErrorReporter service

A safe service for reporting errors to Sentry without breaking your application. All operations are wrapped in try-catch blocks to ensure that failures in Sentry reporting never break the application flow.

**Features:**
- ✅ Safe error reporting (never throws exceptions)
- ✅ Support for exceptions, messages, and context data
- ✅ Automatic error logging if Sentry fails
- ✅ Configurable error levels
- ✅ Breadcrumb tracking
- ✅ User context management

**Usage Example:**

```php
use Nowo\SentryBundle\Service\SentryErrorReporter;

class MyController extends AbstractController
{
  public function myAction(SentryErrorReporter $errorReporter): Response
  {
    try {
      // Your code that might throw an exception
      $this->doSomething();
    } catch (\Throwable $e) {
      // Capture exception safely - never throws
      $errorReporter->captureException(
        $e,
        ['context' => 'data'],
        'Custom message'
      );
      
      // Application continues normally
    }
    
    // Capture a message
    $errorReporter->captureMessage('Something happened', 'warning', ['data' => 'value']);
    
    // Add breadcrumbs
    $errorReporter->addBreadcrumb('User performed action', 'info', ['action' => 'click']);
    
    return new Response('OK');
  }
}
```

See the [demo routes](#demo-projects) for more examples.

## Requirements

- PHP >= 8.1, < 8.6
- Symfony >= 6.0 || >= 7.0 || >= 8.0
- Sentry Symfony Bundle >= 5.0 || >= 6.0

## Version information

Supported PHP and Symfony versions match `composer.json` constraints and the [CI workflow](.github/workflows/ci.yml) matrix.

## Configuration

The bundle works out of the box with default settings. The configuration file `config/packages/nowo_sentry.yaml` is **automatically generated** when the bundle is installed if it doesn't already exist.

> 📖 **For a complete configuration reference with detailed explanations of all options, see [docs/CONFIGURATION.md](docs/CONFIGURATION.md)**

### Automatic Configuration Generation

When you install the bundle, it automatically creates `config/packages/nowo_sentry.yaml` with default settings if:
- The file doesn't exist
- The configuration is not already defined in another config file

You can customize the behavior of each event listener by editing this file.

```yaml
nowo_sentry:
  request_listener:
    enabled: true          # Enable/disable the request listener
    set_domain_tag: true      # Set domain tag in Sentry scope
    set_environment_tag: true    # Set environment tag in Sentry scope
    set_user_info: true       # Set user information in Sentry scope
    set_session_id: true      # Set session ID in Sentry scope extra data
    priority: 0           # Event listener priority
  
  ignore_access_denied_listener:
    enabled: true          # Enable/disable the access denied filter
    priority: 255          # Event listener priority (higher = earlier execution)
  
  uptime_bot_listener:
    enabled: true          # Enable/disable the uptime bot handler
    user_agents:           # List of user agent prefixes to detect as uptime bots
      - 'SentryUptimeBot/1.0'
      - 'Uptime-Kuma'
      - 'kube-probe'
    paths:              # List of paths that should return OK for uptime bots
      - '/dashboard'
      - '/'
      - '/login'
    priority: 255          # Event listener priority (higher = earlier execution)
  
  error_reporter:
    enabled: true          # Enable/disable the error reporter service
```

### Configuration Examples

#### Disable a specific listener

```yaml
nowo_sentry:
  request_listener:
    enabled: false
```

#### Customize uptime bot detection

```yaml
nowo_sentry:
  uptime_bot_listener:
    user_agents:
      - 'MyCustomBot/1.0'
      - 'HealthCheckBot'
    paths:
      - '/health'
      - '/status'
```

#### Disable specific features of the request listener

```yaml
nowo_sentry:
  request_listener:
    set_session_id: false
    set_user_info: false
```

### Configuration Reference

For a complete reference of all configuration options with detailed explanations, default values, and examples, see [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

You can also view the current configuration using:

```bash
php bin/console config:dump nowo_sentry
```

## Demo Projects

The bundle includes demo projects demonstrating usage with different Symfony and PHP versions:

- **Symfony 7.0 Demo** (PHP 8.2) - Port 8007 (default, configurable via `.env`)
- **Symfony 8.0 Demo** (PHP 8.4) - Port 8008 (default, configurable via `.env`)
- **Symfony 8.0 Demo with PHP 8.5** - Port 8009 (default, configurable via `.env`)

Each demo is independent and includes:
- FrankenPHP (Caddy + PHP) Docker setup — see [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md)
- FrankenPHP worker mode: supported and documented (production uses worker mode; dev uses request/classic mode)
- Comprehensive test suite
- Port configuration via `.env` file
- Symfony Web Profiler for debugging (dev and test environments)
- Properly configured routing with attribute-based routes
- **SentryDemoController** with examples of all `SentryErrorReporter` use cases:
 - `/sentry` - Index page with all demo routes
 - `/sentry/capture-exception` - Safe exception capture
 - `/sentry/capture-message` - Message capture with different levels (`?level=debug|info|warning|error|fatal`)
 - `/sentry/capture-error` - Error capture with context
 - `/sentry/add-breadcrumb` - Breadcrumb tracking
 - `/sentry/set-user` - User context management
 - `/sentry/set-context` - Additional context data
 - `/sentry/complete-example` - Complete example combining all features
 - `/sentry/safe-operation` - Demonstrates the service never breaks the application

### Quick Start with Docker

```bash
cd demo
make up-symfony7    # Start Symfony 7.0 demo
make install-symfony7  # Install dependencies
# Access at: http://localhost:8007 (default for symfony7, configurable via .env)
```

Or start any other demo:

```bash
make up-symfony8    # Symfony 8.0
make up-symfony8-php85 # Symfony 8.0 with PHP 8.5
```

### Running Demo Tests

Each demo includes a comprehensive test suite:

```bash
cd demo
make test-symfony7    # Run tests for Symfony 7.0 demo
make test-symfony8    # Run tests for Symfony 8.0 demo
make test-symfony8-php85 # Run tests for Symfony 8.0 + PHP 8.5 demo
make test-all       # Run tests for all demos
```

See `demo/README.md` for detailed instructions for all demos.

## Development

### Using Docker (Recommended)

```bash
# Start the container
make up

# Install dependencies
make install

# Run tests
make test

# Run tests with coverage
make test-coverage

# Run all QA checks
make qa
```

### Without Docker

```bash
composer install
composer test
composer test-coverage
composer qa
```

## Testing

The bundle has **100% code coverage** (all lines, methods, and classes). All tests are located in the `tests/` directory.

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage report
composer test-coverage

# View coverage report
open coverage/index.html
```

### Test Structure

- `tests/Unit/` — bundle class, `DependencyInjection/`, `EventListener/`, `Service/`
- `tests/Integration/` — bundle wiring (e.g. `BundleIntegrationTest.php`)
- `tests/Fixtures/` / `tests/Kernel/` — test kernel and config

All bundle code is covered (100% line coverage enforced in CI for the main coverage job).

## Code Quality

The bundle uses PHP-CS-Fixer to enforce code style (PSR-12).

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## CI/CD

The bundle uses GitHub Actions for continuous integration:

- **Tests**: Runs on PHP 8.2, 8.3, 8.4, and 8.5 with Symfony 6.4, 7.0, and 8.0
 - PHP 8.2 and 8.3: Symfony 6.4 and 7.0 (Symfony 8.0 requires PHP 8.4+)
 - PHP 8.4 and 8.5: All Symfony versions (6.4, 7.0, 8.0)
- **Demo Tests**: All demo projects are tested (Symfony 7.0, 8.0, and 8.0 with PHP 8.5)
- **Code Style**: Automatically fixes code style on push to main/master
- **Code Style Check**: Validates code style on pull requests
- **Coverage**: Validates 100% code coverage requirement for bundle code
- **Dependabot**: Automatically updates dependencies

See `.github/workflows/ci.yml` for details.

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: 100%

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Documentation
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)

### Additional documentation

- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)

## Contributing

Please see [Contributing](docs/CONTRIBUTING.md) for details on how to contribute to this project.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)

