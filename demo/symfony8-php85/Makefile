# Makefile for Sentry Bundle - Symfony 8.0 + PHP 8.5 demo
# Run from demo/symfony8-php85 or via: make -C demo up DEMO=symfony8-php85 (from repo root)

.PHONY: help up down restart build install test test-coverage update-bundle cache-clear ensure-up shell logs verify debug-env

SERVICE_PHP := php

help:
	@echo "Sentry Bundle - Symfony 8.0 + PHP 8.5 Demo"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up             Start containers and install deps"
	@echo "  down           Stop containers"
	@echo "  debug-env      Debug why SENTRY_DSN may be missing inside container"
	@echo "  restart        Restart containers"
	@echo "  build          Rebuild image (no cache)"
	@echo "  install        Install Composer dependencies"
	@echo "  test           Run tests"
	@echo "  test-coverage  Run tests with coverage"
	@echo "  update-bundle  Update nowo-tech/sentry-bundle in container"
	@echo "  cache-clear    Clear Symfony cache in container"
	@echo "  shell          Open shell in PHP container"
	@echo "  logs           Follow container logs"
	@echo "  verify         Ensure up and check HTTP 200"
	@echo ""

ensure-up:
	@if ! docker-compose exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting containers..."; \
		docker-compose up -d; \
		sleep 5; \
		docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	docker-compose build
	docker-compose up -d
	@sleep 5
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@echo "✅ Demo is up. Port: $$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 || echo 8001)"

down:
	docker-compose down

restart: ensure-up
	docker-compose restart

build:
	docker-compose build --no-cache

install: ensure-up
	docker-compose exec -T $(SERVICE_PHP) composer install

# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	docker-compose exec $(SERVICE_PHP) composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	docker-compose exec $(SERVICE_PHP) composer test-coverage

update-bundle: ensure-up
	docker-compose exec -T $(SERVICE_PHP) composer update nowo-tech/sentry-bundle --with-all-dependencies
	docker-compose exec -T $(SERVICE_PHP) php bin/console cache:clear

cache-clear: ensure-up
	docker-compose exec -T $(SERVICE_PHP) php bin/console cache:clear

shell:
	docker-compose exec $(SERVICE_PHP) sh

logs:
	docker-compose logs -f

verify: ensure-up
	@PORT=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 || echo "8001"); \
	CODE=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" 2>/dev/null || echo "000"); \
	if [ "$$CODE" = "200" ]; then \
		echo "✅ HTTP 200 at http://127.0.0.1:$$PORT"; \
	else \
		echo "❌ Expected HTTP 200, got $$CODE"; exit 1; \
	fi

# Debug: show why SENTRY_DSN can be "not found" when running composer install in container.
# If .env is "not found" in container: ensure .env exists on host (copy from .env.example) and volume .:/app is correct.
debug-env: ensure-up
	@echo "=== CWD and .env in container (working_dir /app) ==="
	@docker-compose exec -T $(SERVICE_PHP) sh -c 'cd /app && pwd && ls -la .env 2>/dev/null || echo ".env missing at /app (volume mount may not include it)"'
	@echo ""
	@echo "=== SENTRY lines in .env (if present) ==="
	@docker-compose exec -T $(SERVICE_PHP) sh -c 'cd /app && test -f .env && grep -n SENTRY .env || echo "no .env or no SENTRY"'
	@echo ""
	@echo "=== SENTRY_DSN from container env (docker-compose) ==="
	@docker-compose exec -T $(SERVICE_PHP) sh -c 'echo "[$$SENTRY_DSN]"'
	@echo ""
	@echo "=== After PHP Dotenv->load(/app/.env) ==="
	@docker-compose exec -T $(SERVICE_PHP) sh -c 'cd /app && php -r "require \"vendor/autoload.php\"; (new \Symfony\Component\Dotenv\Dotenv())->load(\"/app/.env\"); echo \"SENTRY_DSN=\" . (getenv(\"SENTRY_DSN\") ?: \"(empty)\") . \"\n\";"'
