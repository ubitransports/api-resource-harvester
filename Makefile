SHELL := /bin/bash

.PHONY: install test cs-fix cs-check ci

install:
	@docker-compose run --rm app composer install

test:
	@docker-compose run --rm app vendor/bin/phpunit

cs-fix:
	@docker-compose run --rm app vendor/bin/php-cs-fixer fix

cs-check:
	@docker-compose run --rm app vendor/bin/php-cs-fixer fix --dry-run --diff

ci:
	@make cs-check
	@make test
