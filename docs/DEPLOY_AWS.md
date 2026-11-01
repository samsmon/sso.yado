# Deploy to AWS - SSO Engine

Deployment guide for `sso.yado.my.id` to AWS using **EC2** (VPS-like, most flexible) or **Elastic Beanstalk** (managed, auto-scaling).

---

## Choose an Approach

| | EC2 | Elastic Beanstalk |
|---|---|---|
| Setup | Manual via SSH | CLI / console |
| Control | Full | Limited |
| Price | From ~$8/mo (t3.micro) | EC2 + overhead ~$10-15/mo |
| Best for | Full control, VPS-like | Fast deploy, auto-scaling |

**Recommendation**: EC2 — closest to the existing `make deploy` workflow and easiest to debug.

---

## Method A - EC2 (Recommended)

### 1. Create EC2 Instance

1. Open [console.aws.amazon.com](https://console.aws.amazon.com) → **EC2** → **Launch Instance**
2. Configuration:
   - **Name**: `sso-yado`
   - **AMI**: Ubuntu Server 24.04 LTS (Free Tier eligible) — **do not choose 26.04**, ondrej PPA does not support it yet
   - **Instance type**: `t3.micro` (2 vCPU, 1GB RAM) — sufficient to start
   - **Key pair**: Create new → download `.pem` → store securely
   - **Security Group** — allow inbound:
     - SSH (22) from your IP only
     - HTTP (80) from anywhere
     - HTTPS (443) from anywhere
3. Click **Launch Instance**

### 2. Allocate Elastic IP (Optional but Recommended)

EC2 public IP changes on each restart unless an Elastic IP is attached:

1. EC2 → **Elastic IPs** → **Allocate Elastic IP**
2. **Associate** with the newly created instance
3. Use this Elastic IP for your DNS records

### 3. Connect to Instance

```bash
chmod 400 your-key.pem
ssh -i your-key.pem ubuntu@<ELASTIC_IP_OR_PUBLIC_IP>
```

### 4. Install Dependencies

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

### 5. Setup Database

Log in as superuser `postgres` (local peer auth, no password):

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE db_sso;
ALTER USER postgres WITH PASSWORD 'strong-password';
\q
```

This project uses a single Postgres superuser model (rather than root + dedicated user like MySQL) — in `.env`, fill `DB_USERNAME=postgres` and `DB_PASSWORD` with the password set above.

### 6. Clone & Setup Project

```bash
cd /var/www
sudo git clone https://github.com/srytmj/sso.yado.git sso
sudo chown -R ubuntu:ubuntu /var/www/sso
cd /var/www/sso

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
DB_PASSWORD=strong-password

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

### 7. Configure Nginx

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
sudo ln -s /etc/nginx/sites-available/sso /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### 8. DNS & SSL (Cloudflare)

1. Cloudflare Dashboard → **DNS** → Add record:
   - Type: `A`
   - Name: `sso`
   - IPv4: `<ELASTIC_IP_EC2>`
   - Proxy: **Proxied** (orange cloud)
2. Cloudflare Dashboard → **SSL/TLS**:
   - Mode: **Full** atau **Full (strict)**
3. Install Certbot jika pakai Full (strict):
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d sso.yado.my.id
   ```

---

## Method B - Elastic Beanstalk

### 1. Prerequisites

- AWS CLI installed & configured (`aws configure`)
- EB CLI installed: `pip install awsebcli`

### 2. Create RDS PostgreSQL (Managed Database)

1. AWS Console → **RDS** → **Create database**
2. Engine: **PostgreSQL 16**, Template: **Free tier**
3. DB identifier: `sso-db`, username: `postgres`, password: set your password
4. Note the **Endpoint** (hostname) after the database is ready

### 3. Init Elastic Beanstalk

```bash
cd /path/to/sso.yado

eb init
# Choose region, platform: PHP 8.3, create new app: sso-yado
```

Create `.ebextensions/nginx.conf` for PHP-FPM:

```yaml
# .ebextensions/laravel.config
option_settings:
  aws:elasticbeanstalk:container:php:phpini:
    document_root: /public
  aws:elasticbeanstalk:application:environment:
    APP_ENV: production
    APP_DEBUG: false
```

### 4. Set Environment Variables

```bash
eb setenv \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_URL=https://sso.yado.my.id \
  APP_KEY=$(php artisan key:generate --show) \
  DB_HOST=<rds-endpoint> \
  DB_PORT=5432 \
  DB_DATABASE=db_sso \
  DB_USERNAME=postgres \
  DB_PASSWORD=xxx \
  SESSION_DRIVER=database \
  RESEND_API_KEY=re_xxx \
  MAIL_FROM_ADDRESS=noreply@yado.my.id
```

### 5. Deploy

```bash
eb create sso-production
# or update existing:
eb deploy
```

### 6. Run Migrations (once)

```bash
eb ssh
cd /var/app/current
php artisan migrate --force
php artisan passport:install --force
php artisan db:seed
```

### 7. Custom Domain + HTTPS

1. Open EB environment → **Configuration** → **Load Balancer** → add HTTPS listener (port 443)
2. Upload SSL certificate via **AWS Certificate Manager** (ACM) — request certificate for `sso.yado.my.id`
3. Cloudflare DNS: CNAME `sso` → EB environment URL

---

## Updates (Both Methods)

### EC2

**From local** (without manual SSH — requires `SERVER_HOST` in `.env`):

```bash
make remote-update
```

**Manual via SSH**:

```bash
# SSH into server
make ssh
# or: ssh -i your-key.pem ubuntu@<IP>

# Inside server:
cd /var/www/sso
bash scripts/update.sh   # same as: make update

# If assets changed:
npm run build
sudo systemctl reload nginx
```

### Elastic Beanstalk

```bash
eb deploy
```

---

## Make Commands (EC2)

Add the following variables to local `.env` to enable remote commands:

```env
SERVER_HOST=<elastic-ip-ec2>
SSH_KEY_PATH=~/.ssh/your-key.pem
SERVER_USER=ubuntu
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

## Optional: RDS as EC2 Database

To run PostgreSQL on a dedicated managed server (more production-grade than local Postgres):

1. AWS Console → RDS → Create database → PostgreSQL 16
2. **VPC Security Group**: allow inbound connections from EC2 Security Group to port 5432
3. Update `.env` on EC2:
   ```env
   DB_HOST=<rds-endpoint>
   DB_READ_HOST=<rds-endpoint>
   DB_WRITE_HOST=<rds-endpoint>
   ```

---

## Post-Deploy Checklist

- [ ] `https://sso.yado.my.id` accessible, landing page renders
- [ ] Superadmin login successful
- [ ] `GET /api/user` with valid token → returns profile JSON
- [ ] Create OAuth client in dashboard → Quick Start panel appears
- [ ] Forgot password → email sent (Resend domain must be verified)
- [ ] SSL active, no mixed content warnings
- [ ] `APP_DEBUG=false` — errors do not expose stack trace to browser
- [ ] `storage/` and `bootstrap/cache/` writable by `www-data`

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 500 error | `tail -f /var/www/sso/storage/logs/laravel.log` |
| 403 Forbidden | `sudo chown -R www-data:www-data /var/www/sso/storage /var/www/sso/bootstrap/cache` |
| DB connection failed | Check `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` in `.env` — if RDS, check Security Group |
| Passport keys error | `php artisan passport:install --force` |
| Assets 404 | `npm run build` has not been run |
| HTTPS redirect loop | In Cloudflare SSL/TLS mode ensure **Full (strict)**, not Flexible |
| Session does not persist | `SESSION_DRIVER=database` and `php artisan session:table && php artisan migrate` |
| CSS/JS fails to load | Ensure `ASSET_URL` in `.env` matches accessed URL (http vs https, domain vs IP) |
| `composer install` fails (PHP version) | Symfony 8.x requires PHP >=8.4.1 — install PHP 8.4 from ondrej PPA |
| `psql: FATAL: password authentication failed for user postgres` | Set/reset password via `sudo -u postgres psql` then `ALTER USER postgres WITH PASSWORD '...';`, ensure `DB_PASSWORD` in `.env` matches |
| `config:cache` Permission denied | Run as www-data: `sudo -u www-data php artisan config:cache` |
| Cloudflare 522 | EC2 Security Group does not allow port 80/443 inbound from 0.0.0.0/0 |