# Saturn Platform - Development Commands
# Usage: make <command>

.PHONY: help dev dev-build dev-down dev-logs dev-ps shell db-shell redis-shell test build install migrate fresh panel panel-build panel-test

# Default target
help:
	@echo "Saturn Platform - Available Commands:"
	@echo ""
	@echo "  Development:"
	@echo "    make dev          - Start all containers (detached)"
	@echo "    make dev-build    - Build and start containers"
	@echo "    make dev-down     - Stop and remove containers"
	@echo "    make dev-logs     - Show logs (follow mode)"
	@echo "    make dev-ps       - Show running containers"
	@echo ""
	@echo "  Shell Access:"
	@echo "    make shell        - Open shell in saturn container"
	@echo "    make db-shell     - Open PostgreSQL shell"
	@echo "    make redis-shell  - Open Redis shell"
	@echo ""
	@echo "  Laravel:"
	@echo "    make install      - Install all dependencies"
	@echo "    make migrate      - Run database migrations"
	@echo "    make fresh        - Fresh migrate + seed"
	@echo "    make key          - Generate APP_KEY"
	@echo "    make test         - Run PHPUnit tests"
	@echo "    make test-js      - Run JS tests"
	@echo ""
	@echo "  Build:"
	@echo "    make build        - Build frontend assets"
	@echo ""
	@echo "  TUI Panel:"
	@echo "    make panel        - Launch Saturn TUI Panel"
	@echo "    make panel-build  - Build panel distributable"
	@echo "    make panel-test   - Run panel tests"
	@echo ""

# ============================================
# Docker Compose shortcuts
# ============================================
COMPOSE = docker compose -f docker-compose.yml -f docker-compose.dev.yml

dev:
	$(COMPOSE) up -d

dev-build:
	$(COMPOSE) up -d --build

dev-down:
	$(COMPOSE) down

dev-logs:
	$(COMPOSE) logs -f

dev-ps:
	$(COMPOSE) ps

# ============================================
# Shell access
# ============================================
shell:
	docker exec -it saturn bash

db-shell:
	docker exec -it saturn-db psql -U saturn -d saturn

redis-shell:
	docker exec -it saturn-redis redis-cli

# ============================================
# Laravel commands (run inside container)
# ============================================
install:
	docker exec -it saturn composer install

install-npm:
	docker exec -it saturn-vite npm install

migrate:
	docker exec -it saturn php artisan migrate

fresh:
	docker exec -it saturn php artisan migrate:fresh --seed

key:
	docker exec -it saturn php artisan key:generate

test:
	docker exec -it saturn php artisan test

test-js:
	docker exec -it saturn-vite npm test

# ============================================
# Frontend build
# ============================================
build:
	docker exec -it saturn-vite npm run build

# ============================================
# TUI Panel
# ============================================
panel:
	cd panel && npm run dev

panel-build:
	cd panel && npm run build

panel-test:
	cd panel && npm test
