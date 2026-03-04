# Release process

This document describes how to prepare and publish a new release of Sentry Bundle.

## Pre-release checks

Run the full release check (style, static analysis, tests, demos):

```bash
make release-check
```

This runs:

- `composer-sync` – validate composer.json and align composer.lock
- `cs-fix` and `cs-check` – code style
- `rector-dry` – Rector in dry-run
- `phpstan` – static analysis
- `test-coverage` – tests with coverage
- `release-check-demos` – start each demo, verify HTTP 200, stop

Fix any failure before tagging.

## Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** – incompatible API changes
- **MINOR** – new backward-compatible functionality
- **PATCH** – backward-compatible bug fixes

## Releasing

1. Update `docs/CHANGELOG.md` with the new version and changes (see [Keep a Changelog](https://keepachangelog.com/)).
2. Commit the changelog: `git add docs/CHANGELOG.md && git commit -m "Prepare release X.Y.Z"`.
3. Create an annotated tag:
   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```
4. GitHub Actions will create or update the GitHub Release from the tag and attach the changelog entry.

## After release

- Verify the release on Packagist and that the Flex recipe (if used) applies correctly.
- Optionally announce in your usual channels.
