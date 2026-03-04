# Makefile for Sentry Bundle
# Standard for Nowo bundles. Single docker-compose (no docker-compose.test.yml).

COMPOSE_FILE := docker-compose.yml
COMPOSE      := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP  := php

.PHONY: help up down build shell install assets test test-coverage coverage-check
.PHONY: cs-check cs-fix rector rector-dry phpstan qa
.PHONY: release-check composer-sync clean update validate setup-hooks ensure-up
.PHONY: release-check-demos up-symfony7 up-symfony8 up-symfony8-php85 demo-down

help:
	@echo "Sentry Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up              Start Docker container"
	@echo "  down            Stop Docker container"
	@echo "  build           Rebuild image (no cache)"
	@echo "  shell           Open shell in container"
	@echo "  install         Install Composer dependencies"
	@echo "  assets          Build frontend assets (no-op: no frontend)"
	@echo "  test            Run PHPUnit tests"
	@echo "  test-coverage   Run tests with code coverage (PCOV)"
	@echo "  coverage-check  Run test-coverage and fail if line coverage < 95%"
	@echo "  cs-check        Check code style"
	@echo "  cs-fix          Fix code style"
	@echo "  rector          Apply Rector refactoring"
	@echo "  rector-dry      Run Rector in dry-run mode"
	@echo "  phpstan         Run PHPStan static analysis"
	@echo "  qa              Run all QA checks (cs-check + test)"
	@echo "  release-check   Pre-release checks (composer-sync, cs, rector-dry, phpstan, test-coverage, demos)"
	@echo "  composer-sync   Validate composer.json and align composer.lock"
	@echo "  clean           Remove vendor and cache"
	@echo "  update         Update dependencies"
	@echo "  validate        Validate composer.json"
	@echo "  setup-hooks     Install git pre-commit hooks"
	@echo ""
	@echo "Demos (from repo root):"
	@echo "  make -C demo up-symfony7        Start Symfony 7 demo"
	@echo "  make -C demo up-symfony8        Start Symfony 8 demo"
	@echo "  make -C demo up-symfony8-php85  Start Symfony 8 + PHP 8.5 demo"
	@echo "  make -C demo demo-down          Stop all demos (use demo/Makefile)"
	@echo ""

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Installing dependencies..."
	@sleep 5
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@echo "✅ Container ready!"

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build --no-cache

shell: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

assets:
	@echo "No frontend assets in this bundle."

# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage

# Run test-coverage and validate minimum 95% line coverage
coverage-check: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage
	$(COMPOSE) exec -T $(SERVICE_PHP) php scripts/check-coverage.php 95

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-install

release-check-demos:
	@$(MAKE) -C demo release-verify

release-check: ensure-up composer-sync cs-fix cs-check rector-dry phpstan coverage-check release-check-demos
	@echo "✅ release-check passed"

clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

setup-hooks:
	chmod +x .githooks/pre-commit
	git config core.hooksPath .githooks
	@echo "✅ Git hooks installed! CS-check and tests will run before each commit."
