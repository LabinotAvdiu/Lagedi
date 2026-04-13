.PHONY: install up down exec logs lint format test

# Build and start all containers
install:
	docker-compose build
	docker-compose up -d

# Start containers (rebuild if needed)
up:
	docker-compose up -d --build

# Stop all containers
down:
	docker-compose down

# Open a shell in the PHP container
exec:
	docker-compose exec php bash

# Stream logs in real time
logs:
	docker-compose logs -f

# Lint frontend code
lint:
	docker-compose exec frontend npm run lint

# Format frontend code
format:
	docker-compose exec frontend npm run format

# Run PHPUnit tests
#   make test                                        → all tests
#   make test file=RegisterTest                      → a specific file/class
#   make test file=RegisterTest::testUserCanRegister → a specific function
test:
	docker-compose exec php php artisan test $(if $(file),--filter=$(file),)
