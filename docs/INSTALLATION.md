# Installation


## Table of contents

- [Requirements](#requirements)
- [Install via Composer](#install-via-composer)
- [Symfony Flex (automatic registration)](#symfony-flex-automatic-registration)
- [Manual registration](#manual-registration)
- [Next steps](#next-steps)

## Requirements

- PHP >= 8.2, < 8.6
- Symfony >= 6.0 || >= 7.0 || >= 8.0
- [sentry/sentry-symfony](https://github.com/getsentry/sentry-symfony) >= 5.0 || >= 6.0

## Install via Composer

```bash
composer require nowo-tech/sentry-bundle
```

## Symfony Flex (automatic registration)

If you install from Packagist and your project uses [Symfony Flex](https://flex.symfony.com/), the recipe will:

- Register the bundle in `config/bundles.php`
- Create the default configuration file at `config/packages/nowo_sentry.yaml`

No further steps are required.

## Manual registration

For private bundles or Git installations (when the Flex recipe is not applied), register the bundle in `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Nowo\SentryBundle\NowoSentryBundle::class => ['all' => true],
];
```

**Note:** This bundle extends the official Sentry Symfony bundle. Ensure `sentry/sentry-symfony` is installed and configured first. Your existing `config/packages/sentry.yaml` continues to work; this bundle adds listeners and options on top.

## Next steps

- [Configuration](CONFIGURATION.md) – Configure listeners and options
- [Usage](USAGE.md) – Event listeners and SentryErrorReporter service
- [Engram](ENGRAM.md) – AI persistent memory for Cursor and other MCP clients (optional)
