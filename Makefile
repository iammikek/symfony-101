.PHONY: test serve migrate jwt-keys docker-up docker-down

test:
	php bin/phpunit

serve:
	php -S 127.0.0.1:8002 -t public public/index.php

migrate:
	php bin/console doctrine:migrations:migrate --no-interaction

jwt-keys:
	bash bin/generate-jwt-keys.sh

docker-up:
	docker compose up --build

docker-down:
	docker compose down
