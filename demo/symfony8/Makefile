# Makefile for Sentry Bundle - Symfony 8.0 demo
# Run from demo/symfony8 or via: make -C demo up DEMO=symfony8 (from repo root)

.PHONY: help up down restart build install test test-coverage update-bundle cache-clear ensure-up shell logs verify

# Prefer Compose V2; absolute docker path avoids shadowing by local docker/ when PATH has "." (REQ-MAKE-010).
DOCKER_BIN := $(shell command -v docker 2>/dev/null)
ifeq ($(DOCKER_BIN),)
COMPOSE ?= docker-compose
else
COMPOSE ?= $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
endif
SERVICE_PHP := php

help:
	@echo "Sentry Bundle - Symfony 8.0 Demo"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up             Start containers and install deps"
	@echo "  down           Stop containers"
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
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting containers..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
		$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction; \
	fi

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 $(SERVICE_PHP) composer install --no-interaction
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

# Run tests (demos have no PCOV; release-check uses this target for smoke tests)
test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

update-bundle: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update nowo-tech/sentry-bundle --with-all-dependencies
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

cache-clear: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php bin/console cache:clear

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

logs:
	$(COMPOSE) logs -f

verify: ensure-up
	@PORT=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 || echo "8001"); \
	CODE=$$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$$PORT" 2>/dev/null || echo "000"); \
	if [ "$$CODE" = "200" ]; then \
		echo "✅ HTTP 200 at http://127.0.0.1:$$PORT"; \
	else \
		echo "❌ Expected HTTP 200, got $$CODE"; exit 1; \
	fi


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/.scripts/Makefile.demo-update-deps.mk
