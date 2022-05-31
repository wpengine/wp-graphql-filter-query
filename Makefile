BIN_DIR=/var/www/html/wp-content/plugins/wp-graphql-filter-query/bin/
COMPOSER=docker run --rm -v `pwd`:/app -w /app composer

all: composer-install build run setup test

build: 
	docker-compose build

composer-install:
	$(COMPOSER) install

run:
	docker-compose up -d
	docker-compose exec wp $(BIN_DIR)/wait-for-it.sh db:3306
	docker-compose exec wp install-wp-tests

setup:
	docker-compose exec wp $(BIN_DIR)/setup-wp

lint:
	$(COMPOSER) phpcs

down:
	docker-compose down --volumes

reset: down all

test:
	docker-compose exec wp composer phpunit