.PHONY: test stan cs-fix rector

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyse

cs-fix:
	vendor/bin/php-cs-fixer fix

rector:
	vendor/bin/rector process
