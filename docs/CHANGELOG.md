# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_No changes yet._

## [1.1.1] - 2025-03-06

### Added
- **PHPUnit bootstrap** (`tests/bootstrap.php`) so the RedisException stub is loaded before tests (fixes CI/Docker when autoload-dev files are not loaded in time)
- **PHPStan stub** (`phpstan-stubs/RedisException.php`) for static analysis when phpredis is not installed
- **Tests for 100% line coverage**: `Configuration::generateConfigFile` when YAML component is missing, `NowoSentryBundle::boot` when `kernel.project_dir` is not a string or when `config/packages` does not exist yet

### Changed
- **Configuration**: extracted `hasYamlComponent()` for testability; class is no longer `final` so the exception path can be covered via a test subclass
- **RedisException stub**: `tests/RedisExceptionStub.php` now defines the class `RedisException` (same as phpredis) so listener and tests resolve the class correctly

### Fixed
- Test failure in CI/Docker: "Class Redis\Exception\RedisException not found" by ensuring the stub is loaded in the PHPUnit bootstrap
- PHPStan: redundant null coalesce on `$userIdentifier` in `SentryRequestListener` when setting Sentry user

## [1.1.0] - 2025-03-04

### Added
- **SentryErrorReporter Service**: New service for safely reporting errors to Sentry without breaking the application
  - `captureException()`: Safely capture exceptions with context and custom messages
  - `captureMessage()`: Capture messages with different severity levels (debug, info, warning, error, fatal)
  - `captureError()`: Convenience method for capturing errors
  - `addBreadcrumb()`: Add breadcrumbs for tracking user actions leading up to errors
  - `setUser()`: Set user context for all subsequent error reports
  - `setContext()`: Set additional context data for all subsequent error reports
  - All methods are wrapped in try-catch blocks to ensure failures in Sentry never break the application
  - Automatic error logging if Sentry fails (when logger is available)
  - Service is automatically available via dependency injection
  - Configuration option `error_reporter.enabled` to enable/disable the service
  - Comprehensive test suite with 100% code coverage
- **Documentation**: Complete documentation for the new service
  - Usage examples in README.md
  - Complete configuration reference in docs/CONFIGURATION.md
  - Service methods documented with PHPDoc
- **Code Quality**: Added PHP CS Fixer configuration (`.php-cs-fixer.dist.php`), PHPStan level 8 with bootstrap for Redis stub
- **Branching Strategy**: Added `docs/BRANCHING.md` with Git Flow workflow documentation
- **Symfony Flex Recipe**: Added Flex recipe for automatic bundle registration and configuration
  - Automatically registers bundle in `config/bundles.php`
  - Creates default configuration file `config/packages/nowo_sentry.yaml`
  - Recipe location: `.symfony/recipes/nowo-tech/sentry-bundle/1.0.0/`
  - Ready to publish to `symfony/recipes-contrib` when bundle is on Packagist
- **Demo apps**: Bootstrap UI and navigation; Sentry demo routes with use-case labels and log/Sentry hints; `make down-all` in demo Makefile; `.env.example` and `SENTRY_DSN` in demos

### Changed
- Updated `src/Resources/config/services.yaml` to register the new `SentryErrorReporter` service
- Updated `src/DependencyInjection/Configuration.php` to include `error_reporter` configuration
- Updated `src/DependencyInjection/NowoSentryExtension.php` to pass error reporter configuration
- **Package distribution**: The `demo/` folder is no longer included when the bundle is installed via Composer (`.gitattributes` export-ignore and `composer.json` archive exclude). Demo apps remain in the repository for development and CI only.

### Fixed
- PHPStan level 8 compliance: type safety in `SentryRequestListener` (UserInterface, null checks), `NowoSentryBundle` (container/extension/projectDir/glob), `NowoSentryExtension::load()` configs type, `SentryErrorReporter::setContext()` array type, and test assertions

## [1.0.0] - Initial Release

### Added
- Initial release of Sentry Bundle
- Bundle extends the official Sentry Symfony bundle (`Sentry\SentryBundle\SentryBundle`)
  - Automatically registers parent bundle when `NowoSentryBundle` is registered
  - Inherits all configuration and services from SentryBundle
  - Full compatibility with existing Sentry configuration

#### Event Listeners
- **SentryRequestListener**: Enhanced request context listener with user and session information
  - Configurable domain and environment tags
  - Optional user information in Sentry scope
  - Optional session ID in extra data
  - Configurable priority (default: 0)
  - Can be enabled/disabled via configuration

- **IgnoreAccessDeniedSentryListener**: Access denied exception filter listener
  - Prevents `AccessDeniedException` from being reported to Sentry
  - Reduces noise in error tracking
  - Configurable priority (default: 255)
  - Can be enabled/disabled via configuration

- **SentryUptimeBotListener**: Uptime bot detection and handling listener
  - Handles requests from Sentry Uptime Bot, Uptime-Kuma, and kube-probe
  - Configurable user agents (default: `['SentryUptimeBot/1.0', 'Uptime-Kuma', 'kube-probe']`)
  - Configurable paths (default: `['/dashboard', '/', '/login']`)
  - Returns OK response for monitoring requests
  - Configurable priority (default: 255)
  - Can be enabled/disabled via configuration

#### Configuration System
- Complete configuration system for all event listeners
  - Enable/disable each listener individually via `enabled` option
  - Configure listener priorities
  - Customize listener behavior (tags, user info, session, etc.)
  - Configuration file: `config/packages/nowo_sentry.yaml`
  - All listeners enabled by default with sensible defaults
  - **Automatic configuration file generation**: The configuration file is automatically created during bundle installation if it doesn't exist
  - Smart detection: Won't overwrite existing configuration if already defined in any config file

#### Testing
- Comprehensive test suite with 100% code coverage
  - Bundle class tests
  - Dependency injection tests (Configuration, Extension)
  - Event listener tests (all three listeners)
  - Tests for enabled/disabled states
  - Tests for configuration options

#### Demo Projects
- Demo projects for different Symfony and PHP versions:
  - **Symfony 7.0 Demo** (PHP 8.2)
  - **Symfony 8.0 Demo** (PHP 8.4)
  - **Symfony 8.0 Demo with PHP 8.5**
- Each demo includes:
  - Complete Docker setup with PHP-FPM and Nginx
  - Comprehensive test suite
  - Port configuration via `.env` file
  - Symfony Web Profiler for debugging (dev and test environments)
  - Properly configured routing with attribute-based routes
  - **SentryDemoController** with examples of all SentryErrorReporter use cases:
    - `captureException()` - Safe exception capture
    - `captureMessage()` - Message capture with different severity levels
    - `captureError()` - Error capture with context
    - `addBreadcrumb()` - Breadcrumb tracking
    - `setUser()` - User context management
    - `setContext()` - Additional context data
    - Complete examples combining all features
- Makefile commands for easy demo management
- Test commands for all demos (`test-symfony7`, `test-symfony8`, `test-symfony8-php85`, `test-all`)
- Access demo routes at `http://localhost:8001/sentry` (or configured port)

