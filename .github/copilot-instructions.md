## AI contribution guidelines (Nowo Symfony bundle)

Use this when suggesting code, tests, documentation, or CI changes for this repository.

### Scope

- This is a **Symfony bundle** integrating **Sentry** with Symfony (`nowo-tech/*` on Packagist).
- Respect **PHP**, **Symfony**, and **`sentry/sentry-symfony`** (or related) ranges in `composer.json`.
- Prefer **PHP 8 attributes**; do not introduce `doctrine/annotations` for new configuration metadata.

### Code

- Follow **PSR-12** and project CS-Fixer configuration.
- Changes to error reporting, sampling, or integrations must stay **backward compatible** within supported versions unless documented as breaking in `docs/UPGRADING.md` and `CHANGELOG.md`.
- Align with `composer cs-check`, `composer phpstan`, and `composer test`.

### Documentation

- User-facing documentation in **English** under `docs/`.
- Do not document secret DSNs or keys; refer to environment variables only.

### Tests

- Extend tests when changing listeners, configuration, or integrations; keep coverage in line with README and CI.
