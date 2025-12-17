# Upgrade Guide

This guide provides step-by-step instructions for upgrading the Sentry Bundle between versions.

## General Upgrade Process

1. **Backup your configuration**: Always backup your `config/packages/nowo_sentry.yaml` file before upgrading
2. **Check the changelog**: Review [CHANGELOG.md](CHANGELOG.md) for breaking changes in the target version
3. **Update composer**: Run `composer update nowo-tech/sentry-bundle`
4. **Update configuration**: Apply any configuration changes required for the new version
5. **Clear cache**: Run `php bin/console cache:clear`
6. **Test your application**: Verify that Sentry integration works as expected

## Upgrade Instructions by Version

### Upgrading to 1.1.0 (Unreleased)

**Release Date**: TBD

#### What's New

- **SentryErrorReporter Service**: New service for safely reporting errors to Sentry
  - Safe error reporting that never throws exceptions
  - Support for exceptions, messages, breadcrumbs, and context data
  - Automatic error logging if Sentry fails
  - Available via dependency injection in all controllers and services

- **Code Quality Improvements**:
  - Added PHP CS Fixer configuration (`.php-cs-fixer.dist.php`)
  - Added branching strategy documentation (`docs/BRANCHING.md`)

- **Symfony Flex Recipe**:
  - Added Flex recipe for automatic bundle registration and configuration
  - Automatically registers bundle in `config/bundles.php` when installing from Packagist
  - Creates default configuration file `config/packages/nowo_sentry.yaml` automatically
  - Recipe is ready to publish to `symfony/recipes-contrib`

#### Breaking Changes

None. This is a backward-compatible feature release.

#### Configuration Changes

**New Optional Configuration**:

The bundle now includes an `error_reporter` configuration section:

```yaml
nowo_sentry:
    # ... existing configuration ...
    error_reporter:
        enabled: true  # Enable/disable the error reporter service (default: true)
```

**Default Behavior**:
- The `error_reporter` service is enabled by default
- No configuration changes are required if you want to use the default settings
- The service is automatically available via dependency injection

#### Migration Steps

1. **Update Composer**:
   ```bash
   composer update nowo-tech/sentry-bundle
   ```

2. **Clear Cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Optional: Update Configuration**:
   If you want to explicitly configure the error reporter, add to `config/packages/nowo_sentry.yaml`:
   ```yaml
   nowo_sentry:
       error_reporter:
           enabled: true
   ```

4. **Start Using the Service** (Optional):
   The service is now available in your controllers and services:
   ```php
   use Nowo\SentryBundle\Service\SentryErrorReporter;
   
   class MyController extends AbstractController
   {
       public function myAction(SentryErrorReporter $errorReporter): Response
       {
           // Safely capture errors
           $errorReporter->captureException($exception, ['context' => 'data']);
           return new Response('OK');
       }
   }
   ```

#### What Changed Under the Hood

- New service `Nowo\SentryBundle\Service\SentryErrorReporter` registered in the container
- Service is public and available via dependency injection
- Configuration parameter `nowo_sentry.error_reporter` added
- No changes to existing event listeners or their behavior

#### Testing After Upgrade

1. **Verify Service is Available**:
   ```bash
   php bin/console debug:container SentryErrorReporter
   ```

2. **Test Error Reporting**:
   Create a test controller to verify the service works:
   ```php
   $errorReporter->captureMessage('Test message', 'info');
   ```

3. **Check Sentry Dashboard**:
   Verify that errors are being captured correctly in your Sentry dashboard

#### Rollback Instructions

If you need to rollback:

1. **Disable the Service** (if causing issues):
   ```yaml
   nowo_sentry:
       error_reporter:
           enabled: false
   ```

2. **Or Downgrade**:
   ```bash
   composer require nowo-tech/sentry-bundle:^1.0.0
   php bin/console cache:clear
   ```

#### Troubleshooting

**Service Not Found**:
- Clear cache: `php bin/console cache:clear`
- Verify bundle is registered: `php bin/console debug:container --bundles`

**Errors Not Appearing in Sentry**:
- Check Sentry DSN configuration in `config/packages/sentry.yaml`
- Verify `error_reporter.enabled` is `true`
- Check application logs for Sentry errors

**Service Throws Exceptions**:
- This should never happen - the service is designed to never throw
- If it does, please report as a bug
- Check that you're using the latest version

## Upgrade from Pre-1.0.0 Versions

If you're upgrading from a version before 1.0.0:

1. Follow the general upgrade process above
2. Review the [CHANGELOG.md](CHANGELOG.md) for all changes since your version
3. Check for any deprecated features or configuration options
4. Update your code to use the new service if needed

## Need Help?

If you encounter issues during upgrade:

1. Check the [CHANGELOG.md](CHANGELOG.md) for known issues
2. Review the [CONFIGURATION.md](CONFIGURATION.md) for configuration options
3. Open an issue on GitHub: https://github.com/nowo-tech/sentry-bundle/issues
4. Contact maintainers at hectorfranco@nowo.tech

