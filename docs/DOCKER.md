# Docker - SSO Engine

There are 3 ways to run SSO Engine via Docker, pick whichever fits your needs:

| File | When to use |
|---|---|
| `docker-compose.yml` | Local development - hot-friendly, ports published straight to the host, Postgres `POSTGRES_HOST_AUTH_METHOD=trust` (no password, local only) |
| `docker/compose.prod.yml` | Production on a homelab that **already has** Traefik running (many projects on one host, shared `proxy` network) |
| **`docker/compose.standalone.yml`** | **Universal** - the same single file for local testing/use OR deploying to any public server, **no other infrastructure needed** (uses Caddy, self-contained, auto-HTTPS) |

This section focuses on `docker-compose.yml` (dev). For `docker/compose.standalone.yml`, see the ["Standalone: Universal"](#standalone-universal-local-testing-or-public-deploy) section below. For `docker/compose.prod.yml`, see ["Production: Homelab"](#production-homelab-proxmox--many-projects-on-one-host).

---

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running

---

## Quick Start

```bash
git clone https://github.com/srytmj/sso.yado.git
cd sso.yado
make docker-fresh
```

`make docker-fresh` automatically: copies `.env.example` → `.env`, builds the image, starts the containers, generates `APP_KEY`, migrates the database, generates Passport keys, and seeds roles + the admin user.

When it finishes, open **http://localhost:8000**.

Log in as superadmin with `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env` (default: `admin@yado.my.id` / `change-me-in-production` - **you must change this** unless it is just local testing).

---

## Everyday Commands

| Command | Description |
|---------|------------|
| `make docker-up` | Start containers (builds automatically if the image does not exist yet) |
| `make docker-down` | Stop all containers |
| `make docker-build` | Rebuild the image - run it after changing `composer.json` or `package.json` |
| `make docker-shell` | Open a shell in the `app` container (for running `artisan` manually, etc.) |
| `make docker-logs` | Tail logs of all containers (app, nginx, postgres) |

Example of running an artisan command manually:

```bash
make docker-shell
php artisan migrate
php artisan tinker
exit
```

Or directly without opening a shell:

```bash
docker compose exec app php artisan migrate
```

---

## Structure

```
docker/
  php/
        Dockerfile            # Multi-stage: build assets (Node) → install deps (Composer) → runtime (PHP-FPM Alpine)
        entrypoint.sh          # Sync public/ from the image into the named volume on every container start
    nginx/default.conf       # Nginx config (used by compose.prod.yml)
    caddy/Caddyfile          # Caddy config (used by compose.standalone.yml)
    compose.prod.yml         # Production homelab/Traefik
    compose.standalone.yml   # Universal production/Caddy (or local testing)
docker-compose.yml          # Dev - stays in the root, following Docker Compose's default lookup convention
```

`docker-compose.yml` (dev) deliberately stays in the root so a plain `docker compose up` (without `-f`) just works. The two production files (`compose.prod.yml`, `compose.standalone.yml`) were moved into `docker/` to keep the repo root tidy - as a consequence, calling either manually **requires** the `--project-directory .` flag (explained in the "Standalone: Universal" section below). `make docker-prod-*` and `make docker-standalone-*` already handle this automatically, so if you always use `make`, you do not need to think about this flag at all.

- **app** - PHP 8.4-FPM. `storage/` is mounted read-write (logs, cache, sessions). `public/` is mounted as a named volume (`sso_public`) shared with `nginx` - it is re-synced from the image contents on every container start via `docker/php/entrypoint.sh`, so assets produced by `npm run build` (which live inside the image, not on the host) can be served directly by Nginx.
- **nginx** - serves `public/` from the `sso_public` volume, proxies `.php` to `app:9000`
- **postgres** - Postgres 16 Alpine, trust auth (no password, local only - never do this in production), data persisted in the `sso_postgres_data` volume

> Why is `public/` not bind-mounted straight from the host like `storage/`? Because `public/build` (the Vite output) is in `.gitignore`, so it does not exist on the host unless you run `npm run build` manually - which is exactly what Docker is meant to avoid. A named volume + sync in the entrypoint makes sure Nginx and PHP-FPM always see exactly the same files, from the latest build in the image.

Ports can be changed via `.env`:
```env
APP_PORT=8000
DB_PORT=5432
```

---

## Local Testing: SSO + Client App, Both in Docker

This is the part that confuses people most - once the client app is also Dockerized, the containers cannot call each other via `localhost` as usual.

### Why `localhost` does not work between containers

Inside a container, `localhost` refers to **the container itself**, not the host or another container. This matters because the OAuth flow has two kinds of communication:

1. **Browser → SSO** (redirect to the login page, etc.) - this is a request from the user's browser, not from inside a container. As long as the SSO nginx maps to a host port (`localhost:8000`), this always works normally.
2. **Client app (server) → SSO (server)** - exchanging the `code` for an `access_token` at `/oauth/token`, and fetching the profile at `/api/user`. These are **requests from inside the client app container**, so `http://localhost:8000` will not reach the SSO container.

### Solution: `host.docker.internal`

Docker Desktop (Windows & Mac) provides a special DNS name `host.docker.internal` that always points to the host machine. Use it for server-to-server communication, while browser redirects keep using `localhost`.

In the client app, split this into two configs:

```php
// config/sso.php (in the client app)
return [
    'client_id'     => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri'  => env('SSO_REDIRECT_URI'),

        // Used for browser redirects (must be localhost, matching what the user types)
    'base_url'      => env('SSO_BASE_URL', 'http://localhost:8000'),

        // Used for server-to-server requests (token exchange, profile fetch)
        // If empty, falls back to base_url (safe for non-Docker setups)
    'internal_url'  => env('SSO_INTERNAL_URL', env('SSO_BASE_URL', 'http://localhost:8000')),
];
```

```env
# client app .env (also running in Docker)
SSO_BASE_URL=http://localhost:8000
SSO_INTERNAL_URL=http://host.docker.internal:8000
```

Then in the client app's `SsoController`:

```php
// redirect() - still uses base_url because this is the URL the browser opens
return redirect(config('sso.base_url') . '/oauth/authorize?' . $query);

// callback() - uses internal_url because this is an HTTP call from the client server to the SSO server
$tokenResponse = Http::asForm()->post(config('sso.internal_url') . '/oauth/token', [...]);
$profile = Http::withToken($token)->get(config('sso.internal_url') . '/api/user')->json();
```

### Setup Steps

1. Run SSO via `make docker-fresh` - nginx maps to `localhost:8000`
2. Register the client app in the SSO dashboard with the redirect URI `http://localhost:8001/auth/callback` (assuming the client app is on port 8001)
3. In the client app project, create a similar `docker-compose.yml` with nginx mapped to a **different** port (`8001:80`)
4. Fill in the client app's `.env` as in the example above - `SSO_BASE_URL` uses `localhost`, `SSO_INTERNAL_URL` uses `host.docker.internal`
5. Test: open `http://localhost:8001` → click login → redirected to `http://localhost:8000/login` → log in → redirected back to `http://localhost:8001/dashboard` with an active session

> Linux without Docker Desktop: `host.docker.internal` is not available automatically. Add `extra_hosts: ["host.docker.internal:host-gateway"]` to the `app` service in the client app's `docker-compose.yml`.

---

## Standalone: Universal (Local Testing OR Public Deploy)

`docker/compose.standalone.yml` differs from the other two files - **there is no infrastructure to prepare first** (not Traefik like `docker/compose.prod.yml`, and not just dev like `docker-compose.yml`). It uses [Caddy](https://caddyserver.com) as the reverse proxy, which has one important property: **automatic HTTPS** - just give it a real domain and Caddy provisions a Let's Encrypt certificate by itself, with no certbot and no manual configuration.

This single file serves two scenarios, distinguished by just one variable in `.env`:

| Scenario | `.env` |
|---|---|
| Local testing / use | `CADDY_SITE_ADDRESS=http://localhost` (default - plain HTTP, no HTTPS attempt) |
| Deploy to a public server | `CADDY_SITE_ADDRESS=sso.yourdomain.com` (no scheme - Caddy automatically uses HTTPS + Let's Encrypt) |

### How this differs from `docker/compose.prod.yml`

`docker/compose.prod.yml` assumes you already have Traefik running (suited to a homelab with many projects). `docker/compose.standalone.yml` is the opposite - it is **self-sufficient**, needs no external network, and no other reverse proxy to be installed. Suitable for:
- Quick testing before committing to a bigger homelab setup
- Deploying to a single VPS (DigitalOcean, Linode, standalone EC2, etc.) that only runs this SSO Engine
- Demo or staging environments that need instant HTTPS without hassle

### Setup

1. Copy `.env.example` → `.env`, fill in `DB_USERNAME`/`DB_PASSWORD` (required, same as `docker/compose.prod.yml` - there is no empty-password option)
2. For **local testing**: leave `CADDY_SITE_ADDRESS=http://localhost` (default)
3. For **public deploy**: set `CADDY_SITE_ADDRESS=sso.yourdomain.com` - make sure that domain's DNS already points to the server's IP **before** the containers run (Caddy needs it for Let's Encrypt verification), and that ports 80+443 are open to the internet (Caddy uses 80 for the HTTP-01 challenge and 443 for HTTPS traffic)
4. Deploy:
   ```bash
   make docker-standalone-deploy
   ```
5. Access it according to `CADDY_SITE_ADDRESS` - `http://localhost` for local, `https://sso.yourdomain.com` for public (HTTPS is automatic, no extra steps)

### Update

```bash
make docker-standalone-update
```

### Commands

| Command | Description |
|---------|------------|
| `make docker-standalone-deploy` | First-time deploy: build + up + migrate + seed |
| `make docker-standalone-update` | Update: pull + rebuild + migrate (data is safe) |
| `make docker-standalone-down` | Stop all containers |
| `make docker-standalone-logs` | Tail logs of all containers (including Caddy) |
| `make docker-standalone-shell` | Open a shell in the `app` container |

### If you hit an error trying Docker before

If you tried running this SSO via Docker before and hit an error, the most likely cause is the `sodium` PHP extension missing from the image (Passport needs it for JWT, via `lcobucci/jwt`). This has been fixed in `docker/php/Dockerfile` (`libsodium-dev` + `docker-php-ext-install sodium` were added) - if you are still on an old image, run it again with `--build` so the image is rebuilt from scratch:

```bash
docker compose -f docker/compose.standalone.yml --project-directory . up -d --build
```

> Note the `--project-directory .` flag - it is required every time you call `docker compose` manually with `-f docker/compose.*.yml`, so that all relative paths inside that file (env_file, the `.env` volume, etc.) still resolve to the repo root, not the `docker/` folder where the compose file lives. `make docker-standalone-*` already includes this flag automatically.

### Deploy to a Proxmox VM/LXC (one shot)

If the target is a new VM or LXC container on Proxmox, there is a dedicated script that automatically: checks/installs Docker, sets up `.env` if it does not exist, and detects first-deploy vs update:

```bash
git clone https://github.com/srytmj/sso.yado.git
cd sso.yado
bash scripts/deploy-docker-proxmox.sh
```

Run the same script again at any time to update (it automatically pulls + migrates, not migrate:fresh - data is safe). The script warns (and asks for confirmation) if it detects it is being run directly on the Proxmox host instead of in the VM/LXC guest - Docker should not run directly on the hypervisor host.

---

## Production: Homelab (Proxmox + Many Projects on One Host)

`docker/compose.prod.yml` is separate from `docker-compose.yml` (dev) - the differences:

| | Dev (`docker-compose.yml`) | Prod (`docker/compose.prod.yml`) |
|---|---|---|
| Port | Published directly to the host (`localhost:8000`) | No published ports - traffic enters through the reverse proxy |
| PostgreSQL | Trust auth (no password) | `POSTGRES_USER`/`POSTGRES_PASSWORD` must be set to strong values |
| Migration | `migrate:fresh` (data loss is fine, dev) | `migrate` (production data must not be lost) |
| Restart policy | Not set | `unless-stopped` |
| Network | Standalone | Joins the external `proxy` network (Traefik) |

### Why Traefik?

If you have ~10 Docker Compose projects on one Proxmox host, the most maintainable approach is **one shared reverse proxy** that automatically "discovers" new containers via labels - rather than each project making its own port mapping + Nginx + SSL. Traefik is the most common choice for this pattern because it natively reads the Docker socket and auto-provisions Let's Encrypt SSL without per-domain manual config.

`docker/compose.prod.yml` is already ready for that - the `nginx` service joins an external network named `proxy` and has Traefik labels. If you already have Traefik running for other projects, just make sure the network name matches.

> **Using a different reverse proxy** (Nginx Proxy Manager, Caddy, etc.)? The principle is the same: remove the Traefik `labels:` block, join the network your proxy uses, then register the host/domain in that proxy's UI/config pointing to the `nginx` container on port 80.

### Initial Setup

1. **Create the Traefik network** (once only, shared by all projects):
   ```bash
   docker network create proxy
   ```
      If your Traefik is already running and has its own network, use that name - edit `networks.proxy.name` in `docker/compose.prod.yml` to match.

2. **Prepare `.env`** - besides the usual variables, production needs:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://sso.yado.my.id
   ASSET_URL=https://sso.yado.my.id

   DB_USERNAME=postgres
      DB_PASSWORD=enter-a-strong-password
   ```
      Unlike dev, production **requires** `DB_USERNAME`/`DB_PASSWORD` to be set with a real password (must not be empty) - both directly become the Postgres superuser credentials the app uses to authenticate. There is no root/user separation like MySQL, because the official Postgres image only has one superuser, created from `POSTGRES_USER`/`POSTGRES_PASSWORD`. `docker/compose.prod.yml` is already set up to require this.

3. **Adjust the domain** in `docker/compose.prod.yml` by changing the Traefik label part:
   ```yaml
   - "traefik.http.routers.sso.rule=Host(`sso.yado.my.id`)"
   ```

4. **Deploy**:
   ```bash
   make docker-prod-deploy
   ```
      This builds the image, starts the containers, generates `APP_KEY` + Passport keys, migrates (not fresh), and seeds roles + the admin user once at the start.

5. Traefik will automatically detect the container and provision SSL. Check your Traefik dashboard to confirm the `sso` router appears and is healthy.

### Update

```bash
make docker-prod-update
```

Pulls the latest code, rebuilds the image, runs `migrate --force` (not fresh - data is safe), then re-caches config/route/view.

### Production Commands

| Command | Description |
|---------|------------|
| `make docker-prod-deploy` | First-time deploy: build + up + migrate + seed |
| `make docker-prod-update` | Update: pull + rebuild + migrate (data is safe) |
| `make docker-prod-down` | Stop production containers |
| `make docker-prod-logs` | Tail logs of all production containers |
| `make docker-prod-shell` | Open a shell in the production `app` container |

### If You Have 10 Projects on the Same Host

- Each project has its own `docker/compose.prod.yml` with a unique `name:` on the first line (so volume/network names do not collide) - this SSO Engine already uses `name: sso-yado`.
- All join the **same** `proxy` network (one Traefik for everything), but each has its own internal network (`yado_sso_prod` here) for app↔nginx↔postgres communication isolated from other projects.
- Each project's Postgres is ideally a **separate container** (as in this setup) unless you deliberately want one Postgres server shared by many projects - in that case, change `DB_HOST` to the shared Postgres hostname and remove the `postgres` service from this compose file.
- Backups: the `sso_postgres_data` and `sso_storage` volumes need regular backups (not `sso_public`, which is regenerable from the image).

---

## Migrating Data from the Old MySQL (Deployment Upgrade)

If you are upgrading an old deployment that still uses MySQL to this version (Postgres), a one-shot command is available to move all the data:

```bash
php artisan db:migrate-to-pgsql
```

This command reads the old MySQL connection from the env vars `MYSQL_LEGACY_HOST`/`MYSQL_LEGACY_PORT`/`MYSQL_LEGACY_DATABASE`/`MYSQL_LEGACY_USERNAME`/`MYSQL_LEGACY_PASSWORD` (see `.env.example`), then **TRUNCATEs** the destination tables in Postgres before copying data - safe to run repeatedly, but destructive to data currently in Postgres.

- `php artisan db:migrate-to-pgsql` - interactive, asks for confirmation first
- `php artisan db:migrate-to-pgsql --force` - skips confirmation (suited to a scripted upgrade flow)

Only relevant for old MySQL-era deployments being upgraded. Fresh installs can ignore this and leave `MYSQL_LEGACY_*` empty.

---

## Troubleshooting

| Problem | Solution |
|---------|--------|
| `docker compose up` fails, port already in use | Change `APP_PORT` / `DB_PORT` in `.env`, or stop the local service using the same port |
| CSS/JS does not show up | Assets are built during `docker build` (Node stage) - if you change files in `resources/`, run `make docker-build` again |
| `Connection refused` to Postgres | The `postgres` container is not ready yet - `docker-compose.yml` already uses `healthcheck` + `depends_on: condition: service_healthy`, but if it still fails, check `docker compose logs postgres` |
| PHP code changes have no effect | Only `storage/` is volume-mounted; if you change files in `app/`, `routes/`, etc. you need to run `make docker-build` again (because the code is copied at build time, not mounted) |
| `host.docker.internal` does not resolve (Linux) | Add `extra_hosts: ["host.docker.internal:host-gateway"]` to the compose file |
| `docker-prod-deploy` fails: network `proxy` does not exist | Run `docker network create proxy` first, or adjust the network name in `docker/compose.prod.yml` to match your existing Traefik |
| Traefik does not detect the container | Check that the `traefik.enable=true` label is present, the container joins the same network as Traefik, and Traefik has access to the Docker socket (`/var/run/docker.sock`) |
| Production Postgres fails to start | `POSTGRES_USER`/`POSTGRES_PASSWORD` are filled from `DB_USERNAME`/`DB_PASSWORD` - if empty, the `postgres` container may fail to start or fall back to an insecure default. Make sure both are set in the production `.env` |
| `ext-sodium` / JWT errors during `composer install` inside the container | The old image lacks the `sodium` extension - run again with `--build` so the image is rebuilt from `docker/php/Dockerfile`, which already includes `libsodium-dev` + `docker-php-ext-install sodium` |
| Caddy fails to provision HTTPS (`docker-standalone`) | Make sure the DNS of the domain in `CADDY_SITE_ADDRESS` already points to the server's IP **before** the containers start, and that ports 80+443 are open to the internet (not just in the local firewall - also check the security group/NSG if on a cloud) |
| `docker-standalone` is not reachable at all even though the containers are "Up" | Check that you did not put a port in `CADDY_SITE_ADDRESS` (e.g. `http://localhost:8080`) when `HTTP_PORT` was changed from the default - that makes Caddy listen on the port named INSIDE that address (so 8080 inside the container), while Docker only maps `HTTP_PORT:80`. Leave `CADDY_SITE_ADDRESS` without a port and set the host port via `HTTP_PORT`/`HTTPS_PORT` only |
| `docker-prod`/`docker-standalone` - `composer install` fails with `Class ... not found` (e.g. `Laravel\Pail\PailServiceProvider`) | `bootstrap/cache/*.php` from the host got copied into the image and refers to dev packages that are not installed under `--no-dev`. Fixed in `.dockerignore` + `Dockerfile` (copy the regenerated `bootstrap/cache` from the vendor stage) - if you still hit it, rebuild the image from scratch with `--build` |

---

## References

- Manual setup without Docker (2 apps on 1 device using `php artisan serve`): see the "Local Testing" section in [`INTEGRATION.md`](INTEGRATION.md)
- Non-Docker production deploy (manual EC2/VM over SSH): [`DEPLOY_AWS.md`](DEPLOY_AWS.md), [`DEPLOY_AZURE.md`](DEPLOY_AZURE.md)
- Docker production deploy, homelab/Proxmox with an existing Traefik: the "Production: Homelab" section above
- Docker production deploy, universal/standalone (single VPS, no Traefik, automatic HTTPS): the "Standalone: Universal" section above
