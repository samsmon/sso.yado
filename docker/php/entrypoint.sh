#!/bin/sh
set -e

# public/ is mounted as a named volume (shared with the nginx container) so
# its contents are NOT refreshed automatically when the image is rebuilt - Docker only auto-populates
# an empty volume once at the start. The manual sync here makes sure the files (including the
# Vite build output) are always the latest from the image on every container start.
rm -rf /var/www/html/public/*
cp -rf /var/www/html/public-src/. /var/www/html/public/

# Symlink storage/app/public -> public/storage (needed for local-disk avatar uploads).
# public/ was just wiped and resynced above, so the old symlink is gone too - recreate it on every start.
cd /var/www/html && php artisan storage:link --force > /dev/null 2>&1 || true

exec "$@"
