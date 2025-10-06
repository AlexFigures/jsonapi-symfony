.PHONY: test stan cs-fix rector install mutation deptrac bc-check

COMPOSER ?= composer
COMPOSER_LOCK := $(wildcard composer.lock)
PHPSTAN_MEMORY_LIMIT ?= 1G

vendor/autoload.php: composer.json $(COMPOSER_LOCK)
	$(COMPOSER) install

install: vendor/autoload.php

test: vendor/autoload.php
	vendor/bin/phpunit

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
