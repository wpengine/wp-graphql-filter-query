BIN_DIR=/var/www/html/wp-content/plugins/wp-graphql-filter-query/bin/

all: composer-install build run setup test

build: 
	docker-compose build

composer-install:
	docker run --rm -v `pwd`:/app -w /app composer install

run:
	docker-compose up -d
	docker-compose exec wp $(BIN_DIR)/wait-for-it.sh db:3306
	docker-compose exec wp install-wp-tests

setup:
	docker-compose exec wp $(BIN_DIR)/setup-wp

down:
	docker-compose down --volumes

reset: down all

test:
	docker-compose exec wp composer phpunit