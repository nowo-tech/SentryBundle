# Sentry Bundle – Standards compliance (BUNDLES_STANDARDS_PROMPT.md)

Verification date: 2025. This document summarizes alignment with the Nowo bundles standards.

---

## §2 Docker

| Criterion | Status |
|-----------|--------|
| **2.1** Base image `php:8.2-cli-alpine`, single Dockerfile, PCOV, Composer 2, `git safe.directory` | ✅ |
| **2.2** Single `docker-compose.yml` (no docker-compose.test.yml), `name: sentry-bundle`, service `php`, coverage volume | ✅ |
| **2.3** Demos in `demo/symfony7`, `demo/symfony8`, `demo/symfony8-php85`; each has `docker-compose.yml` with `name: <bundle-slug>-demo-symfony-<n>` | ✅ (added `name` to each demo compose) |
| **2.4** FrankenPHP | N/A (demos use PHP-FPM + Nginx) |

---

## §3 License

| Criterion | Status |
|-----------|--------|
| LICENSE in root, MIT | ✅ |
| composer.json `"license": "MIT"` | ✅ |
| **3.1** `"archive": {"exclude": ["/demo"]}` in composer.json | ✅ |

---

## §4 PHP-CS-Fixer & tools

| Criterion | Status |
|-----------|--------|
| **4.1–4.2** `.php-cs-fixer.dist.php` in root, canonical config (@PSR12 + @Symfony + @Symfony:risky), tests not excluded | ✅ |
| **4.4** Scripts `cs-check`, `cs-fix`, `qa` in composer.json | ✅ |
| **4.6** Rector: `rector.php`, scripts `rector`, `rector-dry`, PHP 8.1, skip demo/vendor/tests | ✅ |
| **4.6** PHPStan: `phpstan.neon`, level 8, paths src+tests, excludePaths demo/*, memoryLimit 512M, script `phpstan` | ✅ |

---

## §5 Makefile (root)

| Criterion | Status |
|-----------|--------|
| **5.1** help, up, down, build, shell, install, assets, test, test-coverage, cs-check, cs-fix, rector, rector-dry, phpstan, qa, release-check, composer-sync, clean, update, validate | ✅ |
| **5.1.1** Help order: container → deps → assets → tests → quality → release → clean → composer (update, validate); demos at end | ✅ |
| **5.2** ensure-up with sleep 5 and COMPOSER_MEMORY_LIMIT=-1; targets that run in container depend on ensure-up | ✅ |
| **5.3** COMPOSE_FILE, COMPOSE, SERVICE_PHP | ✅ |
| **5.4.1** release-check: composer-sync → cs-fix → cs-check → rector-dry → phpstan → test-coverage → release-check-demos | ✅ |
| **5.4.2** composer-sync: validate --strict + update --no-install | ✅ |
| **7.2.1** test / test-coverage without `-T` for colored console output | ✅ |

---

## §5.5 Makefile demo/

| Criterion | Status |
|-----------|--------|
| demo/Makefile with DEMOS, help, per-demo targets delegating via `$(MAKE) -C <demo> <target>` | ✅ |
| restart-*, build-*, update-bundle-*, verify-*, test-all, test-coverage-all, verify-all, release-verify, clean, **demo-down** | ✅ |
| **demo/symfony7**, **demo/symfony8**, **demo/symfony8-php85** each have Makefile with up, down, restart, build, install, test, test-coverage, update-bundle, ensure-up, shell, logs, verify | ✅ |
| demo/README.md | ✅ |
| README in each demo subfolder (demo/symfony7, etc.) | ⚠️ Optional: only demo/ has README; subfolders could have a short README each |

---

## §6 Documentation

| Criterion | Status |
|-----------|--------|
| **6.0** Only README.md in repo root (no other .md in root) | ✅ |
| **6.0.1** README “Documentation” section: Installation, Configuration, Usage, Contributing, Changelog, Upgrading, Release, Security (in that order) | ✅ |
| **6.2** docs/INSTALLATION.md, CONFIGURATION.md, USAGE.md, CONTRIBUTING.md, CHANGELOG.md, UPGRADING.md, RELEASE.md, SECURITY.md | ✅ |
| **6.1** Docs in English | ✅ |

---

## §7 Tests

| Criterion | Status |
|-----------|--------|
| phpunit.xml.dist: colors=true, cacheDirectory, testsuites → tests/, coverage clover+html | ✅ |
| Composer scripts test, test-coverage with --coverage-text | ✅ |
| Makefile test/test-coverage without -T for colors | ✅ |

---

## §8 CI (GitHub Actions)

| Criterion | Status |
|-----------|--------|
| **8.1** .github/workflows/ci.yml (push/PR main|master, matrix PHP/Symfony, code-style, coverage) | ✅ |
| **8.2** .github/workflows/release.yml (tags v*, create/update Release with changelog) | ✅ |

---

## §9 Recipe (Symfony Flex)

| Criterion | Status |
|-----------|--------|
| .symfony/recipes/nowo-tech/sentry-bundle/1.0.0/ with manifest.json (bundles, copy-from-recipe) | ✅ |

---

## §10 Symfony versions & demos

| Criterion | Status |
|-----------|--------|
| composer.json Symfony ^6.0 \|\| ^7.0 \|\| ^8.0 | ✅ |
| Demos: symfony7, symfony8, symfony8-php85 (no symfony6) | ✅ (aligned with supported versions) |

---

## Summary

- **Fully aligned** with BUNDLES_STANDARDS_PROMPT.md for Docker, License, archive, PHP-CS-Fixer, Rector, PHPStan, Makefile (root and demo), docs, tests, CI, recipe, and Symfony versions.
- **Applied in this check:** added `name:` to each demo `docker-compose.yml` (§2.3) and target **demo-down** in demo/Makefile (§5.5).
- **Optional:** add a short README.md in each of demo/symfony7, demo/symfony8, demo/symfony8-php85 if you want to match the “demo/symfonyX con README.md” note in the standards tables.
