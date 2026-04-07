.PHONY: install up down exec logs lint format

install:
	docker-compose build
	docker-compose up -d

up:
	docker-compose up -d --build

down:
	docker-compose down

exec:
	docker-compose exec php bash

logs:
	docker-compose logs -f

lint:
	docker-compose exec frontend npm run lint

format:
	docker-compose exec frontend npm run format
