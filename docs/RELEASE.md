# Release process

This document describes how to prepare and publish a new release of Sentry Bundle.


## Table of contents

- [Pre-release checks](#pre-release-checks)
- [Versioning](#versioning)
- [Releasing](#releasing)
- [After release](#after-release)
- [Example: releasing 1.2.0](#example-releasing-120)

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

1. Update `docs/CHANGELOG.md`: add a new `## [X.Y.Z] - YYYY-MM-DD` section with the changes and move them from `[Unreleased]` (see [Keep a Changelog](https://keepachangelog.com/)).
2. Update `docs/UPGRADING.md` if the new version has upgrade notes (release date, breaking changes, new config).
3. Commit: `git add docs/CHANGELOG.md docs/UPGRADING.md && git commit -m "Prepare release X.Y.Z"`.
4. Create an annotated tag:
   ```bash
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```
5. GitHub Actions will create the GitHub Release from the tag and attach the changelog entry for that version.

## After release

- Verify the release on Packagist and that the Flex recipe (if used) applies correctly.
- Optionally announce in your usual channels.

## Example: releasing 1.2.0

After CHANGELOG and UPGRADING are updated and committed:

```bash
# 1. Run pre-release checks
make release-check

# 2. Commit release docs (if not already committed)
git add docs/CHANGELOG.md docs/UPGRADING.md docs/RELEASE.md
git commit -m "Prepare release 1.2.0"

# 3. Create and push tag (triggers GitHub Release via Actions)
git tag -a v1.2.0 -m "Release v1.2.0"
git push origin v1.2.0
```
