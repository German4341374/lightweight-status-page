.PHONY: setup test analyse lint format verify up down logs clean

setup:
	composer install
	cp -n .env.example .env || true

test:
	composer test

analyse:
	composer analyse

lint:
	composer cs-check
	composer analyse

format:
	composer cs-fix

verify:
	composer verify

up:
	docker compose up --build --detach

down:
	docker compose down

logs:
	docker compose logs --follow web php

clean:
	docker compose down --volumes --remove-orphans
	rm -rf vendor coverage .phpunit.cache .phpstan-cache .php-cs-fixer.cache
