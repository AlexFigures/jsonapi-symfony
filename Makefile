.PHONY: test test-unit test-functional test-integration test-all
.PHONY: docker-up docker-down docker-test docker-shell
.PHONY: stan cs-fix rector install mutation deptrac bc-check stress-mem stress-perf qa-full

COMPOSER ?= composer
COMPOSER_LOCK := $(wildcard composer.lock)
PHPSTAN_MEMORY_LIMIT ?= 1G

vendor/autoload.php: composer.json $(COMPOSER_LOCK)
	$(COMPOSER) install

install: vendor/autoload.php

# Тесты без Docker (только Unit и Functional)
test: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Unit,Functional

test-unit: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Unit

test-functional: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Functional

# Интеграционные тесты (требуют Docker)
test-integration: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Integration

# Все тесты (включая интеграционные)
test-all: vendor/autoload.php
	vendor/bin/phpunit

# Docker команды
docker-up:
	docker-compose -f docker-compose.test.yml up -d
	@echo "Waiting for databases to be ready..."
	@sleep 10

docker-down:
	docker-compose -f docker-compose.test.yml down -v

docker-test: docker-up
	docker-compose -f docker-compose.test.yml exec php vendor/bin/phpunit --testsuite=Integration
	$(MAKE) docker-down

docker-shell:
	docker-compose -f docker-compose.test.yml exec php sh

stan: vendor/autoload.php
	php -d memory_limit=$(PHPSTAN_MEMORY_LIMIT) vendor/bin/phpstan analyse --memory-limit=$(PHPSTAN_MEMORY_LIMIT)

cs-fix: vendor/autoload.php
	vendor/bin/php-cs-fixer fix

rector: vendor/autoload.php
	vendor/bin/rector process

mutation: vendor/autoload.php
	XDEBUG_MODE=coverage vendor/bin/infection --threads=4 --min-msi=70 --min-covered-msi=70

deptrac: vendor/autoload.php
	vendor/bin/deptrac analyse deptrac.yaml || vendor/bin/deptrac analyse --config-file=deptrac.yaml

bc-check: vendor/autoload.php
	if git describe --tags --abbrev=0 >/dev/null 2>&1; then \
		latest_tag=$$(git describe --tags --abbrev=0); \
		vendor/bin/roave-backward-compatibility-check --from=$$latest_tag; \
	else \
		echo "No git tags found; skipping BC check."; \
	fi

stress-mem: vendor/autoload.php
	@echo "Running memory stress tests..."
	php scripts/stress/run.php --profile=mem

stress-perf: vendor/autoload.php
	@echo "Running performance stress tests..."
	php scripts/stress/run.php --profile=perf

stress: vendor/autoload.php
	@echo "Running all stress tests..."
	php scripts/stress/run.php --profile=all

qa-full: test stan mutation deptrac bc-check
	@echo "✅ All QA checks passed!"
