# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Changed
- Ecosystem name updated (from "White Archive" to "Yado").
- Primary domain reference updated from `*.suryatmaja.dev` to `*.yado.my.id`.
- Database engine migrated from MySQL to **PostgreSQL** — all connections (dev, prod, standalone) now use `pgsql`, with read/write split + sticky preserved. `php artisan db:migrate-to-pgsql` is provided to migrate all data from the legacy MySQL database (accessed via the `mysql`/`MYSQL_LEGACY_*` connection) to Postgres in a single run, safe to re-run (truncates destination tables prior to insertion).

### Added
- **Silent Authorization for Internal Passport Clients**: `App\Models\OAuth\Client::skipsAuthorization()` returns `! $this->revoked`, eliminating the Blade consent screen ("Authorize Application") for official Yado ecosystem client apps.
- **Middleware `HandleOAuthPrompt` (`prompt=login`)**: Supports the standard OAuth parameter `prompt=login` on `/oauth/authorize`. If the user is already authenticated, the middleware logs out the active SSO session and clears the `prompt` parameter before redirecting to the login page, preventing logout loops on intended sessions.
- **Username or Email Login Support**: The SSO login form input (`Login.svelte`) was changed to `type="text"` with the label "Email Address or Username", instructional placeholder, and attribute `autocomplete="username"`, allowing users to log in using either a username (e.g. `sehnauoi`) or email address without triggering HTML5 browser email validation blocks.
- **Docker Compose Standalone (`docker/compose.standalone.yml`, `make docker-standalone-deploy`)** — self-contained using Caddy, a single file for both local testing (`http://localhost`) and public deployment (automatic HTTPS via Let's Encrypt by updating `CADDY_SITE_ADDRESS`), requiring no Traefik or external infrastructure.
- Admin Settings (`/dashboard/settings`) — configure mail (Resend/SMTP) and avatar storage (local/S3) directly from the dashboard, instead of hardcoding `.env`.
- Mandatory email verification prior to accessing `/account`, `/dashboard`, or OAuth flows.
- Avatar upload (`/account`) — stored to local disk or S3 according to Settings.
- My Devices (`/account/sessions`) — view and log out browsers/devices logged into SSO, with device labels.
- Audit Log (`/dashboard/audit-log`) — security events and data modifications (login, password change, role update, invite, client CRUD, etc.).
- Two-Factor Authentication / TOTP (`/account/two-factor`) — compatible with Google Authenticator, Microsoft Authenticator, and recovery codes.
- Theme toggle (system/light/dark) — persisted per user, single-click theme change, no FOUC via inline script.
- Multilanguage: Indonesian, English, Japanese — switcher in My Account; `theme`/`locale` exposed via `/api/user` for optional synchronization by client apps.
- Docker Compose setup for local development (`make docker-fresh`) — PHP 8.4-FPM, Nginx, PostgreSQL 16.
- Production Docker Compose for homelab (`docker/compose.prod.yml`, `make docker-prod-deploy`) — Traefik reverse-proxy ready, Postgres with required password, `migrate` (not fresh).
- Dashboard: log viewer (`/dashboard/logs`) — filter by level (custom dropdown, auto-apply), search, expandable stack trace.
- Health check endpoint `/up` (built-in Laravel).
- Comprehensive rate limiting — `/oauth/*`, `/api/user`, all guest pages, and all authenticated routes (per-user), with stricter limits for sensitive actions (password change, send invite, etc.).
- Public landing page at `/` — explains SSO Engine with links to login/register.
- Superadmin dashboard — role access gate, sidebar + topbar layout.
- Dashboard: OAuth client apps management (view, add, edit, delete clients).
- Dashboard: user management — activate/deactivate, assign roles, invite via email.
- My Account (`/account`) — account information, password change.
- My Account: active sessions & device revocation via `oauth_access_tokens`.
- Login page context awareness — application name banner when arriving from an OAuth flow.
- Forgot password flow via email (Resend) — single-use reset link expiring in 60 minutes.
- Registration: optional full name field, falls back to username if omitted.

### Changed
- Changing password now automatically revokes all active OAuth tokens for the user (logging out all client apps).
- SSO logout supports `redirect_uri` — client apps can redirect back after logout (whitelisted to registered domains).

### Fixed
- **Post-Login Cross-Origin Navigation for OAuth**: `LoginController` and `TwoFactorChallengeController` use `Inertia::location($intended)` (HTTP 409) rather than `redirect()->intended()`, preventing CORS failures on Inertia AJAX requests when redirecting the browser back to ecosystem client app domains.
- **429 Rate Limit on Login Form**: Added `$middleware->trustProxies(at: '*')` in `bootstrap/app.php` and separated login throttling into a named limiter `RateLimiter::for('login')` (10 requests/min per email + original client IP). Previously, GET/POST requests shared the same anonymous key and all client IPs collapsed into the Docker gateway IP (`172.20.0.1`).
- Docker image (`docker/php/Dockerfile`) lacked the `sodium` extension required by Passport (via `lcobucci/jwt`), causing `composer install` failures inside the container. Added `libsodium-dev` + `docker-php-ext-install sodium`.
- `home.blade.php` (public landing page) lacked dark mode and multilanguage support despite being rendered; fully converted.
- Several dropdown icons were missing `dark:` variants and two checklist icons used inconsistent `stroke-width` (2 vs 1.5 elsewhere).

### Removed
- `resources/views/flux/` — unused Flux UI components (prohibited in `.claude/CLAUDE.md`), along with `livewire/flux` dependency.
- `welcome.blade.php` — default Laravel landing page that was never rendered.

---

## [0.1.0] - 2026-07-20

Initial SSO Engine implementation — OAuth2 foundation + local authentication.

### Added

**Local Auth**
- User registration with validation: username, email, password (bcrypt, minimum 8 characters).
- Login via email/password using Laravel sessions.
- Logout: destroy session + revoke all active access tokens.
- Role system: `user` and `superadmin`, seeded automatically.
- Middleware `CheckUserActive` — rejects users with `is_active = false` across all endpoints.

**OAuth2 Authorization Code + PKCE**
- `GET /oauth/authorize` — OAuth2 flow entry point via Laravel Passport.
- Mandatory PKCE (`S256`) — requests without `code_challenge` are rejected.
- Silent SSO — active session redirects directly with authorization code without showing the login form.
- Auto-approve consent for first-party clients via `skipsAuthorization()`.
- `POST /oauth/token` — exchange authorization code + code_verifier for access token & refresh token.

**Token Management**
- Access token TTL: 60 minutes.
- Refresh token TTL: 30 days, single-use (rotation on use).
- `RevokeTokenAction` — revokes all tokens + refresh tokens upon logout.
- Refresh token flow: `grant_type=refresh_token` via `/oauth/token`.

**Userinfo Endpoint**
- `GET /api/user` — returns user profile (id, name, username, email, avatar, role).
- Protected by `auth:api` + scope `profile:read`.
- Flat response without wrappers (matching SRS API contract).

**Database**
- `users` table with columns: name, username, email, avatar, role_id, is_active.
- `roles` table with seeders: user (id=1), superadmin (id=2).
- Read/write split with `sticky: true`, database `db_sso`.
- All OAuth tables managed automatically by Passport.

**Security**
- CSRF protection active on all web routes.
- Rate limiting: `throttle:5,1` on `POST /login`, `throttle:3,1` on `/oauth/token`.
- HTTPS enforced via `forceScheme('https')` in production.
- Passport keys are not committed to the repository.

**Infrastructure**
- `scripts/deploy.sh` — first-time server setup.
- `scripts/update.sh` — git pull + migrate + cache clear.
- `Makefile` — shortcuts `make deploy`, `make update`, `make sync`.
- `sync.sh` — auto-sync tech stack from SRS.md to CLAUDE.md.

---

[Unreleased]: https://github.com/srytmj/sso.yado/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/srytmj/sso.yado/releases/tag/v0.1.0