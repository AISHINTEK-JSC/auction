DC = docker compose
EXEC = $(DC) exec app
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: build up dev down setup fresh test lint sh logs tinker sync

build:            ## Build image PHP/Node
	$(DC) build

up:               ## Chạy các service (không có Vite dev server)
	$(DC) up -d

dev:              ## Chạy thêm Vite dev server (HMR)
	$(DC) --profile dev up -d

down:
	$(DC) --profile dev down

setup: up         ## Cài lần đầu: .env, composer, npm, migrate + seed, build asset
	$(EXEC) sh -c '[ -f .env ] || cp .env.example .env'
	$(EXEC) composer install
	$(EXEC) sh -c 'grep -q "^APP_KEY=base64" .env || php artisan key:generate --ansi'
	$(EXEC) php artisan storage:link --force
	$(EXEC) npm install
	$(EXEC) php artisan migrate --seed --force
	$(EXEC) npm run build

fresh:            ## Xoá DB và seed lại
	$(EXEC) php artisan migrate:fresh --seed
	$(EXEC) php artisan cache:clear

test:
	$(EXEC) php artisan test

lint:
	$(EXEC) vendor/bin/pint
	$(EXEC) npm run check:fix

sync:             ## Đồng bộ trạng thái phiên đấu giá từ engine ngay
	$(EXEC) php artisan auctions:sync

sh:
	$(EXEC) bash
tinker:
	$(EXEC) php artisan tinker
logs:
	$(DC) logs -f --tail=100
