.PHONY: help setup up down restart build logs shell test migrate fresh seed

help: ## Mostra esta ajuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

setup: ## Setup inicial do projeto
	@echo "🚀 Configurando TaskForge API..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker-compose build
	docker-compose up -d
	docker-compose exec app composer install
	docker-compose exec app php artisan key:generate
	docker-compose exec app php artisan storage:link
	docker-compose exec app chmod -R 777 storage bootstrap/cache
	@echo "✅ Setup concluído! Acesse http://localhost:8000"

up: ## Inicia os containers
	docker-compose up -d

down: ## Para os containers
	docker-compose down

restart: ## Reinicia os containers
	docker-compose restart

build: ## Rebuilda os containers
	docker-compose build --no-cache

logs: ## Mostra os logs
	docker-compose logs -f

shell: ## Acessa o shell do container app
	docker-compose exec app bash

mysql: ## Acessa o MySQL CLI
	docker-compose exec mysql mysql -u taskforge -ptaskforge taskforge

redis: ## Acessa o Redis CLI
	docker-compose exec redis redis-cli

test: ## Executa os testes
	docker-compose exec app php artisan test

test-coverage: ## Executa testes com cobertura
	docker-compose exec app php artisan test --coverage --min=80

migrate: ## Executa migrations
	docker-compose exec app php artisan migrate

migrate-fresh: ## Reseta o banco e executa migrations
	docker-compose exec app php artisan migrate:fresh

seed: ## Executa seeders
	docker-compose exec app php artisan db:seed

fresh: ## Reseta banco, migra e popula
	docker-compose exec app php artisan migrate:fresh --seed

lint: ## Verifica estilo de código
	docker-compose exec app ./vendor/bin/pint --test

lint-fix: ## Corrige estilo de código
	docker-compose exec app ./vendor/bin/pint

cache-clear: ## Limpa todos os caches
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

optimize: ## Otimiza a aplicação
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache

queue-work: ## Inicia queue worker
	docker-compose exec app php artisan queue:work

horizon: ## Inicia Laravel Horizon
	docker-compose exec app php artisan horizon

tinker: ## Inicia Laravel Tinker
	docker-compose exec app php artisan tinker

fresh-start: down build up migrate-fresh seed ## Reinicia tudo do zero
