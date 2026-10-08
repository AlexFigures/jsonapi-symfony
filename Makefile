.PHONY: test test-unit test-functional test-integration test-all
.PHONY: docker-up docker-down docker-test docker-shell
.PHONY: stan cs-fix rector install mutation deptrac bc-check qa-full

PYTHON ?= python3
COMPOSER ?= composer
PHP ?= php
DOCKER_COMPOSE ?= docker compose -f docker-compose.test.yml
COMPOSER_LOCK := $(wildcard composer.lock)
PHPSTAN_MEMORY_LIMIT ?= 1G
MUTATION_MEMORY_LIMIT ?= 1G

vendor/autoload.php: composer.json $(COMPOSER_LOCK)
	$(COMPOSER) install

install: vendor/autoload.php

# Tests without Docker (including protocol/conformance regression coverage)
test: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Unit,Functional,Conformance,JsonApiStatus

test-unit: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Unit

test-functional: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Functional

# Integration tests (require Docker)
test-integration: vendor/autoload.php
	vendor/bin/phpunit --testsuite=Integration

# All tests (including integration)
test-all: vendor/autoload.php
	vendor/bin/phpunit

# Docker commands
docker-up:
	$(DOCKER_COMPOSE) up -d --wait --wait-timeout 120

docker-down:
	$(DOCKER_COMPOSE) down -v

docker-test: docker-up
	$(DOCKER_COMPOSE) exec php vendor/bin/phpunit --testsuite=Integration
	$(MAKE) docker-down

docker-shell:
	$(DOCKER_COMPOSE) exec php sh

stan: vendor/autoload.php
	$(PHP) -d memory_limit=$(PHPSTAN_MEMORY_LIMIT) vendor/bin/phpstan analyse --memory-limit=$(PHPSTAN_MEMORY_LIMIT)

cs-fix: vendor/autoload.php
	$(PHP) vendor/bin/php-cs-fixer fix

rector: vendor/autoload.php
	vendor/bin/rector process

tools/mutation/vendor/autoload.php: tools/mutation/composer.json
	$(COMPOSER) install --working-dir=tools/mutation --no-interaction

mutation: vendor/autoload.php tools/mutation/vendor/autoload.php
	XDEBUG_MODE=coverage $(PHP) -d memory_limit=$(MUTATION_MEMORY_LIMIT) tools/mutation/vendor/bin/infection --threads=4

deptrac: vendor/autoload.php
	vendor/bin/deptrac analyse

tools/bc/vendor/autoload.php: tools/bc/composer.json
	$(COMPOSER) install --working-dir=tools/bc --no-interaction

bc-check: tools/bc/vendor/autoload.php
	sh scripts/bc-check.sh


qa-full: test stan deptrac bc-check docs-check api-inventory
	@echo "✅ All QA checks passed!"

# Documentation preparation does not require Composer or integration databases.
.PHONY: docs-check api-inventory
docs-check:
	$(PYTHON) scripts/check-doc-links.py

api-inventory:
	$(PYTHON) scripts/public-api-inventory.py
