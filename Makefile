.PHONY: install up exec down logs

install:
	docker-compose build
	docker-compose up -d db php-nginx
	sleep 5
	docker-compose exec -T php-nginx php artisan migrate --force

up:
	docker-compose up -d --build

exec:
	docker-compose exec php-nginx bash

down:
	docker-compose down

logs:
	docker-compose logs -f
