.PHONY: test stan cs-fix rector install

COMPOSER ?= composer
COMPOSER_LOCK := $(wildcard composer.lock)

vendor/autoload.php: composer.json $(COMPOSER_LOCK)
	$(COMPOSER) install

install: vendor/autoload.php

test: vendor/autoload.php
	vendor/bin/phpunit

stan: vendor/autoload.php
	vendor/bin/phpstan analyse

cs-fix: vendor/autoload.php
	vendor/bin/php-cs-fixer fix

rector: vendor/autoload.php
	vendor/bin/rector process
