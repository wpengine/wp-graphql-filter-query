

all: composer-install build run test

build: 
	docker-compose build

composer-install:
	docker run --rm -v `pwd`:/app -w /app composer install

run:
	docker-compose up -d
	docker-compose exec wp install-wp-tests > /dev/null

down:
	docker-compose down --volumes

test:
	docker-compose exec wp composer phpunit