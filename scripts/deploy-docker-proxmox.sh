#!/usr/bin/env bash
# Deploy SSO Engine via Docker (docker/compose.standalone.yml) to a VM/LXC guest on Proxmox.
#
# IMPORTANT: run this script INSIDE a VM or LXC container (guest) that lives on
# Proxmox - NOT directly on the Proxmox host itself. Running Docker on the
# Proxmox host is not recommended (it conflicts with Proxmox resource management, and
# makes the host more vulnerable). Assumed guest OS: Debian/Ubuntu.
#
# This script automatically detects first-deploy vs update - safe to run repeatedly.
#
# Usage (from inside the git clone folder):
#   bash scripts/deploy-docker-proxmox.sh

set -euo pipefail

echo "=== SSO Engine - Deploy Docker (Proxmox VM/LXC) ==="

# Warn if it is actually run on the real Proxmox host, not the guest
if command -v pveversion >/dev/null 2>&1; then
    echo "[!] Detected running on the Proxmox HOST ('pveversion' is present), not in a VM/LXC guest."
    echo "    It is strongly recommended to run Docker inside a VM/LXC, not directly on the Proxmox host."
    read -p "    Continue on this host anyway? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Install Docker if it is missing
if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is not installed - installing now via get.docker.com (Debian/Ubuntu)..."
    curl -fsSL https://get.docker.com | sh
    sudo usermod -aG docker "$USER"
    echo ""
    echo "[!] Docker was just installed. Log out and back in (or run: newgrp docker)"
    echo "    so this user can use 'docker' without sudo, then run this script again."
    exit 0
fi

# Set up .env if it does not exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo "[!] .env created from .env.example. Fill it in before continuing, at least:"
    echo "    - CADDY_SITE_ADDRESS   public domain without a scheme (e.g. sso.yourdomain.com),"
    echo "                           or leave http://localhost for plain testing"
    echo "    - DB_USERNAME, DB_PASSWORD  (REQUIRED, must not be empty for a"
    echo "                           Docker deploy - used directly as the Postgres superuser)"
    echo "    - ADMIN_EMAIL, ADMIN_PASSWORD"
    echo ""
    echo "Run this script again after .env is filled in."
    exit 0
fi

if ! grep -qE "^DB_PASSWORD=.+" .env; then
    echo "[!] DB_PASSWORD is still empty in .env."
    echo "    A Docker deploy (standalone/production) does not allow an empty password -"
    echo "    unlike a regular local setup, which may use a user without a password."
    exit 1
fi

# Pull latest (safe to ignore if there is no remote/branch yet, e.g. a manual first clone)
git pull origin main 2>/dev/null || true

COMPOSE="docker compose -f docker/compose.standalone.yml --project-directory ."

# Detect first-deploy vs update: if the app container has never been created, this is a first deploy
IS_FIRST_DEPLOY=0
if ! $COMPOSE ps --status running --services 2>/dev/null | grep -q "^app$"; then
    IS_FIRST_DEPLOY=1
fi

echo "Build image + start containers..."
$COMPOSE up -d --build

if [ "$IS_FIRST_DEPLOY" = "1" ]; then
    echo ""
    echo "First deploy detected - generate key, migrate, Passport keys, seed..."
    $COMPOSE exec -T app php artisan key:generate
    $COMPOSE exec -T app php artisan migrate --force
    $COMPOSE exec -T app php artisan passport:keys --force
    $COMPOSE exec -T app php artisan db:seed --force
else
    echo ""
    echo "Update detected - migrate (not migrate:fresh, data is safe)..."
    $COMPOSE exec -T app php artisan migrate --force
fi

$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo ""
echo "=== Deploy complete ==="
echo "Access it via the CADDY_SITE_ADDRESS in .env - if a public domain is set, Caddy has"
echo "automatically provisioned Let's Encrypt HTTPS (make sure the domain's DNS already points to"
echo "this VM/LXC's IP and that ports 80+443 are open to the internet)."
