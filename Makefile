BIN_DIR=/var/www/html/wp-content/plugins/wp-graphql-filter-query/bin/
COMPOSER=docker run --rm -v `pwd`:/app -w /app composer
DC=docker-compose

all: composer-install build run setup lint test

build:
	$(DC) build

composer-install:
	$(COMPOSER) install

composer-update:
	$(COMPOSER) update

run:
	$(DC) up -d
	$(DC) exec wp $(BIN_DIR)/wait-for-it.sh db:3306
	$(DC) exec wp install-wp-tests

down:
	$(DC) down --volumes

setup:
	$(DC) exec wp $(BIN_DIR)/setup-wp

lint:
	$(DC) exec wp composer phpcs

lint-fix:
	$(DC) exec wp composer phpcs:fix

test:
	$(DC) exec wp composer phpunit

reset: down all
