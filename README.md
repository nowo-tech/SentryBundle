# Sentry Bundle

[![CI](https://github.com/nowo-tech/SentryBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/SentryBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/sentry-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/sentry-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/sentry-bundle.svg)](https://packagist.org/packages/nowo-tech/sentry-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.0%2B%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com)

> ⭐ **Found this useful?** Give it a star on GitHub! It helps us maintain and improve the project.

Symfony bundle extending Sentry integration with enhanced event listeners and configuration options.

## Features

- ✅ Enhanced request context with user and session information
- ✅ Pure access denied filtered via `before_send`; parent-page failures from sub-request 403 reported with context
- ✅ Doctrine DBAL SQL exception reporting (`dbal_exception_reporter`) — captures schema/query errors even in `catch` blocks
- ✅ Uptime bot detection and handling
- ✅ Compatible with existing Sentry configuration
- ✅ Full integration with Sentry Symfony bundle (complements SentryBundle)
- ✅ Fully configurable event listeners (enable/disable per listener)
- ✅ Type-safe error handling
- ✅ 100% code coverage with comprehensive tests
- ✅ Demo project for Symfony 8.1

## Installation

```bash
composer require nowo-tech/sentry-bundle
```

**Symfony Flex Recipe**: If you're installing from Packagist, the Symfony Flex recipe will automatically:
- Register `SentryBundle` and `NowoSentryBundle` in `config/bundles.php`
- Create the default configuration file at `config/packages/nowo_sentry.yaml`

**Manual Installation** (for private bundles or Git installations):
If the Flex recipe doesn't work, manually register **both** bundles in your `config/bundles.php`:

```php
<?php

return [
  // ... other bundles
  Sentry\SentryBundle\SentryBundle::class => ['all' => true],
  Nowo\SentryBundle\NowoSentryBundle::class => ['all' => true],
];
```

**Important**: 
- This bundle **complements** the official Sentry Symfony bundle (`Sentry\SentryBundle\SentryBundle`).
- Register `SentryBundle` first (or via its own Flex recipe), then `NowoSentryBundle`.
- Make sure you have `sentry/sentry-symfony` installed and configured.
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

### Event listeners and Sentry integration

The bundle registers **kernel event listeners**, a **`before_send` handler**, optional **Doctrine DBAL middleware**, and the **`SentryErrorReporter`** service:

#### 1. SentryRequestListener

Enriches Sentry reports with request context:
- Sets domain and environment tags
- Configures user information if available
- Adds session ID to extra data when enabled

#### 2. BeforeSendHandler (`nowo_sentry.before_send_handler`)

Registered as `sentry.options.before_send` (automatically when not configured). Drops **pure** access denied responses (main or sub). Keeps parent-page failures where the reported exception wraps a sub-request 403 (e.g. Twig template rendering error).

#### 3. SubRequestAccessDeniedContextListener

When a sub-request access denied breaks the parent page, adds `access_denied.*` tags and route/controller context to Sentry.

#### 4. SentryUptimeBotListener

Handles requests from uptime monitoring bots (default: Sentry Uptime Bot on `/health`). Configure additional user agents and paths as needed.

#### 5. DBAL exception reporter (`dbal_exception_reporter`)

Optional Doctrine DBAL driver middleware. Reports SQL/driver exceptions to Sentry with query context **before** your code catches them. Requires `doctrine/dbal` + `doctrine/doctrine-bundle`. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md#dbal-exception-reporter-configuration).

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

- PHP >= 8.2, < 8.6
- Symfony >= 7.0 || >= 8.0
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
    set_session_id: false     # Disabled by default; enable only when needed for correlation
    priority: 0           # Event listener priority
  
  ignore_access_denied_listener:
    enabled: true          # BC toggle; maps to before_send_handler.ignore_pure_access_denied

  before_send_handler:
    enabled: true
    ignore_pure_access_denied: true  # Drop pure 403; keep parent-page failures wrapping sub-request 403
    register_automatically: true     # Prepend sentry.options.before_send when not set

  sub_request_access_denied_listener:
    enabled: true          # Add context when sub-request 403 breaks the parent page
    priority: 256
  
  uptime_bot_listener:
    enabled: true          # Enable/disable the uptime bot handler
    user_agents:           # List of user agent prefixes to detect as uptime bots
      - 'SentryUptimeBot/1.0'
    paths:              # List of paths that should return OK for uptime bots
      - '/health'
    priority: 255          # Event listener priority (higher = earlier execution)
  
  error_reporter:
    enabled: true          # Public SentryErrorReporter + alias; independent from dbal_exception_reporter

  dbal_exception_reporter:
    enabled: true          # Requires doctrine/dbal + doctrine-bundle; reports SQL errors at query time
    connections: []
    sql_states: []         # e.g. ['42S22'] for column-not-found only
    deduplicate: true
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

The bundle includes a demo project demonstrating usage with Symfony:

- **Symfony 8.1 Demo** (PHP 8.4) - Port 8008 (default, configurable via `.env`)

The demo is independent and includes:
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
make up-symfony8    # Start Symfony 8.1 demo
make install-symfony8  # Install dependencies
# Access at: http://localhost:8008 (default for symfony8, configurable via .env)
```

### Running Demo Tests

```bash
cd demo
make test-symfony8
make test-all
```

See `demo/README.md` for detailed instructions.

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

- **Tests**: Runs on PHP 8.2, 8.3, 8.4, and 8.5 with Symfony 7.0, 7.4, 8.0, and 8.1
 - PHP 8.2–8.3: Symfony 7.0 and 7.4 (Symfony 8.x requires PHP 8.4+)
 - PHP 8.4 and 8.5: All supported Symfony versions (7.0, 7.4, 8.0, 8.1)
- **Demo Tests**: Demo project is tested (Symfony 8.1)
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

- [GitHub Actions CI requirements](docs/GITHUB_CI.md)
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)

## Contributing

Please see [Contributing](docs/CONTRIBUTING.md) for details on how to contribute to this project.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)

