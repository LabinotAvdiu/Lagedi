.PHONY: install makeup makeexec down logs

install:
	docker-compose build
	docker-compose up -d db php-nginx

up:
	docker-compose up -d --build

exec:
	docker-compose exec php-nginx bash

down:
	docker-compose down

logs:
	docker-compose logs -f
