# Makefile for Sentry Bundle Demo
# Delegates to each demo's Makefile (demo/symfony7, demo/symfony8, demo/symfony8-php85)

DEMOS := symfony7 symfony8 symfony8-php85

.PHONY: help clean demo-down test-all test-coverage-all verify-all update-bundle-all release-check release-verify up-all down-all
.PHONY: $(foreach demo,$(DEMOS),up-$(demo) down-$(demo) restart-$(demo) build-$(demo) install-$(demo) update-bundle-$(demo) cache-clear-$(demo) shell-$(demo) logs-$(demo) test-$(demo) test-coverage-$(demo) verify-$(demo))
.PHONY: up down restart build install test test-coverage cache-clear shell logs

help:
	@echo "Sentry Bundle - Demo Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@for demo in $(DEMOS); do \
		version=$$(echo $$demo | sed 's/symfony/Symfony /' | sed 's/7/7.0/' | sed 's/8/8.0/' | sed 's/-php85/ + PHP 8.5/'); \
		echo "$$version Demo:"; \
		echo "  up-$$demo        Start $$version demo"; \
		echo "  down-$$demo      Stop $$version demo"; \
		echo "  restart-$$demo   Restart $$version demo"; \
		echo "  build-$$demo     Rebuild $$version demo image"; \
		echo "  install-$$demo   Install dependencies"; \
		echo "  update-bundle-$$demo  Update sentry-bundle in demo"; \
		echo "  cache-clear-$$demo    Clear Symfony cache in container"; \
		echo "  shell-$$demo     Open shell in container"; \
		echo "  logs-$$demo      Show logs"; \
		echo "  test-$$demo      Run tests"; \
		echo "  test-coverage-$$demo  Run tests with coverage"; \
		echo "  verify-$$demo    Verify HTTP 200"; \
		echo ""; \
	done
	@echo "Testing:"
	@echo "  test-all           Run tests for all demos"
	@echo "  test-coverage-all  Run tests with coverage for all demos"
	@echo "  verify-all         Verify all demos (HTTP 200)"
	@echo "  update-bundle-all  Run update-bundle in every demo (before tests)"
	@echo "  release-check      Pre-release: update-bundle-all → test-coverage-all → release-verify"
	@echo "  release-verify     For release-check: up → verify → down per demo"
	@echo ""
	@echo "All demos:"
	@echo "  up-all           Start all demos (build + composer install per demo)"
	@echo "  down-all         Stop all demos"
	@echo ""
	@echo "Generic (use DEMO=<name>):"
	@echo "  up, down, restart, build, install, test, test-coverage, cache-clear, shell, logs"
	@echo "  e.g. make up DEMO=symfony7"
	@echo ""
	@echo "Other:"
	@echo "  demo-down          Stop all demos"
	@echo "  clean              Remove vendor/var/cache from all demos"
	@echo ""
	@echo "Access URLs (default port 8001):"
	@for demo in $(DEMOS); do \
		version=$$(echo $$demo | sed 's/symfony/Symfony /' | sed 's/7/7.0/' | sed 's/8/8.0/' | sed 's/-php85/ + PHP 8.5/'); \
		port="8001"; \
		[ -f $$demo/.env ] && port=$$(grep -E '^PORT=' $$demo/.env 2>/dev/null | cut -d= -f2 | head -1) || true; \
		echo "  $$version: http://localhost:$${port}"; \
	done

# Per-demo targets: delegate to demo's Makefile
define up-target
up-$(1):
	@$(MAKE) -C $(1) up
endef
define down-target
down-$(1):
	@$(MAKE) -C $(1) down
endef
define restart-target
restart-$(1):
	@$(MAKE) -C $(1) restart
endef
define build-target
build-$(1):
	@$(MAKE) -C $(1) build
endef
define install-target
install-$(1):
	@$(MAKE) -C $(1) install
endef
define update-bundle-target
update-bundle-$(1):
	@$(MAKE) -C $(1) update-bundle
endef
define cache-clear-target
cache-clear-$(1):
	@$(MAKE) -C $(1) cache-clear
endef
define shell-target
shell-$(1):
	@$(MAKE) -C $(1) shell
endef
define logs-target
logs-$(1):
	@$(MAKE) -C $(1) logs
endef
define test-target
test-$(1):
	@$(MAKE) -C $(1) test
endef
define test-coverage-target
test-coverage-$(1):
	@$(MAKE) -C $(1) test-coverage
endef
define verify-target
verify-$(1):
	@$(MAKE) -C $(1) verify
endef

$(foreach d,$(DEMOS),$(eval $(call up-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call down-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call restart-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call build-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call install-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call update-bundle-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call cache-clear-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call shell-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call logs-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call test-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call test-coverage-target,$(d))))
$(foreach d,$(DEMOS),$(eval $(call verify-target,$(d))))

# Start all demos (build + up + composer install in each)
up-all:
	@echo "Starting all demos (build, up, composer install)..."
	@for demo in $(DEMOS); do \
		echo ""; echo "=== $$demo ==="; \
		$(MAKE) -C $$demo up || exit 1; \
	done
	@echo ""; echo "✅ All demos are up with dependencies installed."

# Stop all demos
down-all:
	@echo "Stopping all demos..."
	@for demo in $(DEMOS); do \
		echo "=== $$demo ==="; \
		$(MAKE) -C $$demo down; \
	done
	@echo "✅ All demos stopped."

# Generic targets (require DEMO=)
up:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make up DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) up-$(DEMO)

down:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make down DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) down-$(DEMO)

restart:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make restart DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) restart-$(DEMO)

build:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make build DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) build-$(DEMO)

install:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make install DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) install-$(DEMO)

test:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make test DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) test-$(DEMO)

test-coverage:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make test-coverage DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) test-coverage-$(DEMO)

cache-clear:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make cache-clear DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) cache-clear-$(DEMO)

shell:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make shell DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) shell-$(DEMO)

logs:
	@if [ -z "$(DEMO)" ]; then echo "❌ Usage: make logs DEMO=symfony7|symfony8|symfony8-php85"; exit 1; fi
	@$(MAKE) logs-$(DEMO)

test-all:
	@echo "Running tests for all demos..."
	@for demo in $(DEMOS); do $(MAKE) -C $$demo test || exit 1; done
	@echo "✅ All tests completed!"

test-coverage-all:
	@echo "Running tests with coverage for all demos..."
	@for demo in $(DEMOS); do $(MAKE) -C $$demo test-coverage || exit 1; done
	@echo "✅ All coverage tests completed!"

verify-all:
	@echo "Verifying all demos..."
	@FAILED=0; \
	for demo in $(DEMOS); do \
		echo "Checking $$demo..."; \
		$(MAKE) -C $$demo verify && echo "✅ $$demo OK" || { echo "❌ $$demo failed"; FAILED=$$((FAILED+1)); }; \
	done; \
	[ $$FAILED -eq 0 ] || exit 1
	@echo "✅ All demos verified!"

# Stop all demos
demo-down:
	@for demo in $(DEMOS); do $(MAKE) down-$$demo; done

update-bundle-all:
	@echo "Running update-bundle for all demos..."
	@for demo in $(DEMOS); do echo ""; echo "=== update-bundle $$demo ==="; $(MAKE) -C $$demo update-bundle || exit 1; done
	@echo "✅ update-bundle completed for all demos."

# REQ-DEMO-007: update-bundle before tests; then coverage; then HTTP verify
release-check: update-bundle-all test-coverage-all release-verify
	@echo "✅ All demos passed release-check."

release-verify:
	@echo "Release verify: up → verify → down per demo..."
	@for demo in $(DEMOS); do \
		echo ""; echo "=== $$demo ==="; \
		$(MAKE) -C $$demo up && sleep 10 && $(MAKE) -C $$demo verify && $(MAKE) -C $$demo down || { $(MAKE) -C $$demo down 2>/dev/null; exit 1; }; \
	done
	@echo ""; echo "✅ release-verify passed for all demos"

clean:
	@echo "Cleaning vendor/var/.phpunit.cache from all demos..."
	@$(foreach demo,$(DEMOS),rm -rf $(demo)/vendor $(demo)/var $(demo)/.phpunit.cache;)
	@echo "✅ Cleaned!"
