PLUGIN_DIR=/var/www/html/wp-content/plugins/wp-graphql-filter-query
BIN_DIR=$(PLUGIN_DIR)/bin
COMPOSER=docker run --rm -it -v `pwd`:/app -w /app composer
DC=docker compose

all: composer-install composer-dump-autoload build run setup lint test

build:
	$(DC) build

composer-install:
	$(COMPOSER) install

composer-update:
	$(COMPOSER) update

composer-dump-autoload:
	$(COMPOSER) dump-autoload

clean:
	rm -rf .wordpress

run:
	$(DC) up -d
	$(DC) exec wp $(BIN_DIR)/wait-for-it.sh db:3306
	$(DC) exec wp install-wp-tests
	$(DC) cp  wp:/tmp/wordpress-tests-lib `pwd`/.wordpress/wordpress-tests-lib

down:
	$(DC) down --volumes

setup:
	$(DC) exec wp $(BIN_DIR)/setup-wp

shell:
	$(DC) exec wp bash

lint:
	$(DC) exec wp composer phpcs

lint-fix:
	$(DC) exec wp composer phpcs:fix

test:
	$(DC) exec wp composer phpunit

test-watch:
	$(DC) exec -w $(PLUGIN_DIR) wp ./vendor/bin/phpunit-watcher watch

reset: down clean all

gbuild-pull-requests:
	gcloud builds submit \
		--config="cloud-build/pull-requests.yaml" \
		--project="wp-engine-headless-build"

tail-debug-log:
	$(DC) run -T --rm wp tail -f /var/www/html/wp-content/debug.log

wp-version:
	$(DC) exec wp bash -c 'cat /var/www/html/wp-includes/version.php | grep wp_version'
