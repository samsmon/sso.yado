#!/usr/bin/env bash
# Deploy / update script for SSO Engine
# First deploy : sudo bash scripts/deploy.sh
# Update       : bash scripts/deploy.sh

set -euo pipefail

echo "=== SSO Engine - Deploy ==="

# Set up .env if it does not exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo "[!] .env created from .env.example. Edit APP_URL, DB_*, ADMIN_EMAIL, ADMIN_PASSWORD, then run again."
    exit 0
fi

# Generate APP_KEY if empty
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate
fi

# Pull latest
git pull origin main 2>/dev/null || true

# Install dependencies
composer install --no-dev --optimize-autoloader

# Fix storage permissions before artisan runs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Migrate the database (not migrate:fresh, so existing data is safe)
php artisan migrate --force

# Symlink storage/app/public -> public/storage (needed for local-disk avatar uploads)
php artisan storage:link --force || true

# Generate Passport encryption keys if they do not exist
if [ ! -f storage/oauth-private.key ]; then
    php artisan passport:keys --force
fi

# Seed roles + admin user (only on initial setup)
php artisan db:seed --force

# Optimize - run as www-data so cache files are writable by Nginx
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo ""
echo "=== Deploy complete ==="
echo "Make sure ASSET_URL in .env matches the domain/IP being accessed."
