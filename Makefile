.PHONY: help sync deploy update ssh remote-deploy remote-update docker-up docker-down docker-build docker-fresh docker-shell docker-logs docker-prod-deploy docker-prod-update docker-prod-down docker-prod-logs docker-prod-shell docker-standalone-deploy docker-standalone-update docker-standalone-down docker-standalone-logs docker-standalone-shell

# Load SSH config from .env if present
-include .env
SERVER_USER ?= ubuntu
SERVER_PATH ?= /var/www/sso

# docker/compose.prod.yml and docker/compose.standalone.yml live inside docker/,
# but all relative paths inside them (env_file, volumes, build context) are still written
# as if relative to the repo root - --project-directory . is what makes that valid without
# having to change the compose files at all.
PROD_COMPOSE := docker compose -f docker/compose.prod.yml --project-directory .
STANDALONE_COMPOSE := docker compose -f docker/compose.standalone.yml --project-directory .

help:
	@echo "Available commands:"
	@echo "  make sync           Sync the stack from docs/SRS.md to .claude/CLAUDE.md"
	@echo ""
	@echo "  -- On the server (run after SSH) --"
	@echo "  make deploy         First-time deploy wizard on the server"
	@echo "  make update         Pull latest + rebuild on the server"
	@echo ""
	@echo "  -- From local to the server (needs SERVER_HOST in .env) --"
	@echo "  make ssh            SSH into the server"
	@echo "  make remote-deploy  First-time deploy via SSH from local"
	@echo "  make remote-update  Update the server via SSH from local"
	@echo ""
	@echo "  -- Docker (local) --"
	@echo "  make docker-fresh   First-time setup: build + up + migrate + seed"
	@echo "  make docker-up      Start containers (build if needed)"
	@echo "  make docker-down    Stop containers"
	@echo "  make docker-build   Rebuild the image (after changing composer.json/package.json)"
	@echo "  make docker-shell   Open a shell in the app container"
	@echo "  make docker-logs    Tail logs of all containers"
	@echo ""
	@echo "  -- Docker Production (homelab/Proxmox, needs the Traefik 'proxy' network) --"
	@echo "  make docker-prod-deploy  First-time deploy: build + up + migrate + seed"
	@echo "  make docker-prod-update  Update: pull + rebuild + migrate (data is safe)"
	@echo "  make docker-prod-down    Stop production containers"
	@echo "  make docker-prod-logs    Tail production container logs"
	@echo "  make docker-prod-shell   Open a shell in the production app container"
	@echo ""
	@echo "  -- Docker Standalone (universal - local testing OR public deploy, no Traefik) --"
	@echo "  make docker-standalone-deploy  First-time deploy: build + up + migrate + seed"
	@echo "  make docker-standalone-update  Update: pull + rebuild + migrate (data is safe)"
	@echo "  make docker-standalone-down    Stop containers"
	@echo "  make docker-standalone-logs    Tail logs of all containers (including Caddy)"
	@echo "  make docker-standalone-shell   Open a shell in the app container"
	@echo ""
	@echo "  -- Deploy Docker to a new Proxmox VM/LXC (one shot, from inside the guest) --"
	@echo "  bash scripts/deploy-docker-proxmox.sh   Install Docker + first-time standalone deploy"

sync:
	bash sync.sh

deploy:
	sudo bash scripts/deploy.sh

update:
	bash scripts/deploy.sh

ssh:
	@if [ -z "$(SERVER_HOST)" ]; then echo "Error: SERVER_HOST is not set in .env"; exit 1; fi
	ssh -i $(SSH_KEY_PATH) $(SERVER_USER)@$(SERVER_HOST)

remote-deploy:
	@if [ -z "$(SERVER_HOST)" ]; then echo "Error: SERVER_HOST is not set in .env"; exit 1; fi
	ssh -i $(SSH_KEY_PATH) $(SERVER_USER)@$(SERVER_HOST) "cd $(SERVER_PATH) && sudo bash scripts/deploy.sh"

remote-update:
	@if [ -z "$(SERVER_HOST)" ]; then echo "Error: SERVER_HOST is not set in .env"; exit 1; fi
	ssh -i $(SSH_KEY_PATH) $(SERVER_USER)@$(SERVER_HOST) "cd $(SERVER_PATH) && bash scripts/deploy.sh"

docker-fresh:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker compose up -d --build
	docker compose exec app php artisan key:generate
	docker compose exec app php artisan migrate:fresh --force
	docker compose exec app php artisan passport:keys --force
	docker compose exec app php artisan db:seed --force
	@echo "Selesai. Akses di http://localhost:8000"

docker-up:
	docker compose up -d

docker-down:
	docker compose down

docker-build:
	docker compose up -d --build

docker-shell:
	docker compose exec app sh

docker-logs:
	docker compose logs -f

docker-prod-deploy:
	@if [ ! -f .env ]; then echo "Error: .env does not exist. Copy it from .env.example and fill it in first."; exit 1; fi
	@if ! docker network inspect proxy >/dev/null 2>&1; then echo "Error: the 'proxy' network (Traefik) does not exist. Create it first: docker network create proxy"; exit 1; fi
	$(PROD_COMPOSE) up -d --build
	$(PROD_COMPOSE) exec app php artisan key:generate
	$(PROD_COMPOSE) exec app php artisan migrate --force
	$(PROD_COMPOSE) exec app php artisan passport:keys --force
	$(PROD_COMPOSE) exec app php artisan db:seed --force
	$(PROD_COMPOSE) exec app php artisan config:cache
	$(PROD_COMPOSE) exec app php artisan route:cache
	$(PROD_COMPOSE) exec app php artisan view:cache
	@echo "Done. Check the Traefik routing for the domain configured in docker/compose.prod.yml."

docker-prod-update:
	git pull origin main
	$(PROD_COMPOSE) up -d --build
	$(PROD_COMPOSE) exec app php artisan migrate --force
	$(PROD_COMPOSE) exec app php artisan config:cache
	$(PROD_COMPOSE) exec app php artisan route:cache
	$(PROD_COMPOSE) exec app php artisan view:cache

docker-prod-down:
	$(PROD_COMPOSE) down

docker-prod-logs:
	$(PROD_COMPOSE) logs -f

docker-prod-shell:
	$(PROD_COMPOSE) exec app sh

docker-standalone-deploy:
	@if [ ! -f .env ]; then cp .env.example .env; echo "[!] .env created from .env.example. Edit CADDY_SITE_ADDRESS, DB_*, ADMIN_EMAIL/PASSWORD, then run again."; exit 0; fi
	$(STANDALONE_COMPOSE) up -d --build
	$(STANDALONE_COMPOSE) exec app php artisan key:generate
	$(STANDALONE_COMPOSE) exec app php artisan migrate --force
	$(STANDALONE_COMPOSE) exec app php artisan passport:keys --force
	$(STANDALONE_COMPOSE) exec app php artisan db:seed --force
	$(STANDALONE_COMPOSE) exec app php artisan config:cache
	$(STANDALONE_COMPOSE) exec app php artisan route:cache
	$(STANDALONE_COMPOSE) exec app php artisan view:cache
	@echo "Selesai. Akses sesuai CADDY_SITE_ADDRESS di .env (default: http://localhost)."

docker-standalone-update:
	git pull origin main
	$(STANDALONE_COMPOSE) up -d --build
	$(STANDALONE_COMPOSE) exec app php artisan migrate --force
	$(STANDALONE_COMPOSE) exec app php artisan config:cache
	$(STANDALONE_COMPOSE) exec app php artisan route:cache
	$(STANDALONE_COMPOSE) exec app php artisan view:cache

docker-standalone-down:
	$(STANDALONE_COMPOSE) down

docker-standalone-logs:
	$(STANDALONE_COMPOSE) logs -f

docker-standalone-shell:
	$(STANDALONE_COMPOSE) exec app sh
