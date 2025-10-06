.PHONY: test stan cs-fix rector install

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
