# Deploy to Azure - SSO Engine

Deployment guide for `sso.yado.my.id` to Azure using **Azure App Service** (easiest approach) or **Azure VM** (more control, VPS-like).

---

## Choose an Approach

| | App Service | Azure VM |
|---|---|---|
| Setup | Easier, GUI | Manual, SSH |
| Control | Limited | Full |
| Price | From ~$13/mo (B1) | From ~$7/mo (B1s) |
| Best for | Quick production-ready | Flexible, multi-service |

**Recommendation**: Azure VM if already familiar with Linux servers. App Service if you prefer quick setup without managing a server.

---

## Method A - Azure VM (Recommended)

### 1. Create VM in Azure Portal

1. Open [portal.azure.com](https://portal.azure.com) → **Create a resource** → **Virtual Machine**
2. Configuration:
   - **Image**: Ubuntu Server 24.04 LTS — **do not choose 26.04**, PHP PPA does not support it yet
   - **Size**: B1s (1 vCPU, 1GB RAM) — sufficient to start
   - **Authentication**: SSH public key
   - **Inbound ports**: open port 22 (SSH), 80 (HTTP), 443 (HTTPS)
3. Click **Review + Create** → **Create**
4. Download private key `.pem` when prompted — store securely

### 2. Connect to VM

```bash
chmod 400 your-key.pem
ssh -i your-key.pem azureuser@<PUBLIC_IP>
```

### 3. Install Dependencies on VM

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.4 + extensions (required - symfony 8.x requires PHP >=8.4.1)
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4 php8.4-fpm php8.4-pgsql php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-cli

# If using Ubuntu 26.04 (Resolute), skip PPA - use PHP 8.5 from default repo:
# sudo apt install -y php8.5 php8.5-fpm php8.5-pgsql php8.5-mbstring \
#     php8.5-xml php8.5-curl php8.5-zip php8.5-bcmath php8.5-cli

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install -y nginx

# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib
# There is no mysql_secure_installation in Postgres - Ubuntu defaults to peer/md5 auth,
# set password for user postgres directly in the "Setup Database" step below

# Install Node (for Vite build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install Git
sudo apt install -y git
```

### 4. Setup Database

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE db_sso;
ALTER USER postgres WITH PASSWORD 'strong-password-here';
\q
```

This project uses a single Postgres superuser model (rather than root + dedicated user like MySQL) — in `.env`, fill `DB_USERNAME=postgres` and `DB_PASSWORD` with the password set above.

### 5. Clone & Setup Project

```bash
cd /var/www
sudo git clone https://github.com/srytmj/sso.yado.git sso
sudo chown -R azureuser:azureuser /var/www/sso
cd /var/www/sso

# Copy .env and fill in configuration
cp .env.example .env
nano .env
```

Required `.env` values to update:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sso.yado.my.id
ASSET_URL=https://sso.yado.my.id   # must match APP_URL - without this CSS will not load

DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_sso
DB_USERNAME=postgres
DB_PASSWORD=strong-password-here

SESSION_DRIVER=database

MAIL_MAILER=resend
RESEND_API_KEY=re_xxx
MAIL_FROM_ADDRESS=noreply@yado.my.id

ADMIN_EMAIL=your@email.com
ADMIN_PASSWORD=strong-admin-password
```

```bash
# Run deploy script (same as make deploy)
sudo bash scripts/deploy.sh

# Build assets
npm install && npm run build

# Seed roles + admin user
php artisan db:seed
```

### 6. Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/sso
```

```nginx
server {
    listen 80;
    server_name sso.yado.my.id;
    root /var/www/sso/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;  # change to php8.5-fpm.sock if Ubuntu 26.04
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/sso /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Set permissions
sudo chown -R www-data:www-data /var/www/sso/storage
sudo chown -R www-data:www-data /var/www/sso/bootstrap/cache
sudo chmod -R 775 /var/www/sso/storage
sudo chmod -R 775 /var/www/sso/bootstrap/cache
```

### 7. Setup Static Public IP (Recommended)

By default, Azure VM dynamic IP changes when stopped:

1. Azure Portal → Virtual Machines → `sso-vm`
2. **Networking** → click on Public IP address
3. **Configuration** → Assignment: change **Dynamic** to **Static**
4. Save

### 8. DNS & SSL (Cloudflare)

1. Cloudflare Dashboard → DNS → Add record:
   - Type: `A`
   - Name: `sso`
   - IPv4: `<PUBLIC_IP_VM>`
   - Proxy: **Proxied** (orange cloud)
2. Cloudflare Dashboard → SSL/TLS → set to **Full** atau **Full (strict)**
3. Install Certbot jika pakai Full (strict):
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d sso.yado.my.id
   ```

### 9. Verify Services Running

```bash
# Check services are active
sudo systemctl status nginx
sudo systemctl status php8.4-fpm
sudo systemctl status postgresql

# Test access
curl -I https://sso.yado.my.id
```

---

## Method B - Azure App Service

### 1. Create App Service

1. Azure Portal → **Create a resource** → **Web App**
2. Configuration:
   - **Runtime stack**: PHP 8.3
   - **Operating System**: Linux
   - **Region**: Southeast Asia (closest)
   - **Plan**: B1 (Basic)
3. Click **Create**

### 2. Create Azure Database for PostgreSQL

1. Azure Portal → **Create a resource** → **Azure Database for PostgreSQL**
2. Choose **Flexible Server**
3. Note hostname, username, password
4. In **Networking**: add firewall rule for App Service IP

### 3. Setup Deployment via GitHub Actions

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Azure

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Setup Node & build assets
        run: |
          npm install
          npm run build

      - name: Deploy to Azure Web App
        uses: azure/webapps-deploy@v3
        with:
          app-name: ${{ secrets.AZURE_WEBAPP_NAME }}
          publish-profile: ${{ secrets.AZURE_WEBAPP_PUBLISH_PROFILE }}
```

### 4. Set Environment Variables

In Azure Portal → App Service → **Configuration** → **Application settings**, add all `.env` contents:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sso.yado.my.id
DB_HOST=<azure-postgres-hostname>
DB_PORT=5432
DB_DATABASE=db_sso
DB_USERNAME=postgres
DB_PASSWORD=xxx
SESSION_DRIVER=database
RESEND_API_KEY=re_xxx
# ... etc
```

### 5. Run Migrations (once)

In Azure Portal → App Service → **SSH** or via **Console**:

```bash
cd /home/site/wwwroot
php artisan migrate --force
php artisan passport:install --force
php artisan db:seed
php artisan config:cache && php artisan route:cache
```

---

## Updates (Both Methods)

### Azure VM

**From local** (without manual SSH — requires `SERVER_HOST` in `.env`):

```bash
make remote-update
```

**Manual via SSH**:

```bash
make ssh
# or: ssh -i your-key.pem azureuser@<IP>

cd /var/www/sso
bash scripts/update.sh   # same as: make update

# If assets changed:
npm run build
sudo systemctl reload nginx
```

### App Service

GitHub Actions automatically deploys upon pushing to `main`.

---

## Make Commands (Azure VM)

Add the following variables to local `.env` to enable remote commands:

```env
SERVER_HOST=<public-ip-vm>
SSH_KEY_PATH=~/.ssh/your-key.pem
SERVER_USER=azureuser
SERVER_PATH=/var/www/sso
```

| Command | Description |
|---------|-------------|
| `make ssh` | SSH to server |
| `make remote-deploy` | First-time deploy from local via SSH |
| `make remote-update` | Pull latest + rebuild from local via SSH |
| `make deploy` | First-time deploy (run **inside** server) |
| `make update` | Pull latest + rebuild (run **inside** server) |

---

## Post-Deploy Checklist

- [ ] `https://sso.yado.my.id` accessible, redirects to landing page
- [ ] Superadmin login successful
- [ ] `GET https://sso.yado.my.id/api/user` with valid token → returns JSON
- [ ] Create OAuth client in dashboard → Quick Start panel appears
- [ ] Forgot password → email sent via Resend (ensure `RESEND_API_KEY` is set and domain is verified)
- [ ] SSL active, no mixed content warnings
- [ ] `APP_DEBUG=false` in production — ensure errors do not expose stack trace

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 500 error | Check `storage/logs/laravel.log` |
| 403 Forbidden | `sudo chown -R www-data:www-data storage/ bootstrap/cache/` |
| DB connection failed | Check `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` in `.env` |
| Passport error | `php artisan passport:install --force` |
| Assets do not appear | `npm run build` has not been run |
| Session does not persist | Ensure `SESSION_DRIVER=database` and sessions migration has run |
| CSS/JS fails to load | Ensure `ASSET_URL` in `.env` matches accessed URL (http vs https, domain vs IP) |
| `composer install` fails (PHP version) | Symfony 8.x requires PHP >=8.4.1 — install PHP 8.4 from ondrej PPA |
| `psql: FATAL: password authentication failed for user postgres` | Set/reset password via `sudo -u postgres psql` then `ALTER USER postgres WITH PASSWORD '...';`, ensure `DB_PASSWORD` in `.env` matches |
| `config:cache` Permission denied | Run as www-data: `sudo -u www-data php artisan config:cache` |