# CLAUDE.md - SSO Engine (yado)

## Project Overview

Central Identity Provider for the Yado ecosystem. Provides centralized OAuth2 authentication with PKCE (RFC 7636) so users only log in once to access all applications across the ecosystem.

The frontend is built with **Svelte 5 + Inertia.js v2 SPA**, powered by GSAP animation and Tailwind CSS v4 + DaisyUI v5 (pure monochrome theme).

## Project Structure

```
root/
  app/              # Laravel application code (Controllers, Services, Actions, Models)
  config/           # Config files (database, passport)
  database/         # Migrations, seeders
  resources/
    js/             # Svelte 5 (Pages/, Layouts/, app.js)
    css/            # Tailwind v4 & DaisyUI styles (app.css)
    views/          # app.blade.php (Inertia root HTML)
  routes/           # web.php, api.php
  scripts/          # deploy.sh, deploy-docker-proxmox.sh
  docs/             # PRD, SRS, STRUCTURE, TODO, AI_AGENT_GUIDE.md, AI_INTEGRATION.md
  logs/             # sync.log (gitignored)
  .claude/          # CLAUDE.md + agents/
  Makefile
  sync.sh
  SESSION-PROMPTS.md
```

---

<!-- STACK_START -->
## Stack (auto-synced from SRS.md)

- Backend: Laravel (latest stable)
- Auth: Laravel Passport (OAuth2 server - RFC 6749 + RFC 7636 PKCE)
- Frontend: Svelte 5 (Runes) + Inertia.js v2 + GSAP + Tailwind CSS v4 + DaisyUI v5
- Database: PostgreSQL 16 - `db_sso` (read/write split, sticky mode, persistent volume)
- Email: Resend API / SMTP (runtime configurable via dashboard)
- Hosting: Linux VM / EC2 / Docker (Traefik / Caddy)
- Tunnel: Cloudflare (DNS + proxy)
<!-- STACK_END -->

---

## Backend & Operations Constraints

### Constraints

- **Single Page App**: The frontend uses Svelte 5 via Inertia.js. Navigation must use the `<Link>` component from `@inertiajs/svelte` (not `<a use:inertia>`).
- **Database Durability**: NEVER run `php artisan migrate:fresh` on a server or live container. Always use `php artisan migrate --force`.
- Logic **must not** reside in Controllers. Controllers are thin — all logic belongs in Service or Action classes.
- PSR-12. Type hints are mandatory on all method signatures.
- Database: `db_sso`. Read/write split with `sticky: true`.
- Passport manages all `/oauth/*` endpoints — do not override unless specifically required.

### Commands

```bash
composer install
php artisan serve
php artisan migrate
php artisan passport:install
php artisan test
```

### Route Structure

```php
// routes/web.php - Blade / Inertia pages
Route::get('/login', [LoginController::class, 'show']);
Route::post('/login', [LoginController::class, 'store']);
Route::get('/register', [RegisterController::class, 'show']);
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/logout', [LogoutController::class, 'destroy']);

// routes/api.php - resource endpoints
Route::middleware(['auth:api', 'scope:profile:read'])
    ->get('/user', [UserController::class, 'show']);

// /oauth/* routes - auto-registered by Passport
```

### Architecture Pattern

```
Request → Controller → Service/Action → Model → Response
```

- `Controller`: validates request, calls service, returns response
- `Service`: business logic, may inject multiple models/actions
- `Action`: single-responsibility operation (e.g. `IssueTokenAction`, `RevokeTokenAction`)
- `Model`: Eloquent model, relations, scopes — no business logic

### Code Standards

- PSR-12 strict
- Mandatory type hints: parameters and return types
- No `var_dump`, no `dd()` in production code
- Conventional commits: `feat:`, `fix:`, `chore:`, `docs:`
- Method names: camelCase, class names: PascalCase

---

## Frontend (Blade + Tailwind)

### UI Constraints

- Styling uses **Tailwind CSS** + **daisyUI** (CSS-only plugin, `@plugin 'daisyui'` in `resources/css/app.css`) — this is the sole permitted component library because it consists of pure CSS classes on top of Tailwind, requires no JS framework, and remains fully compatible with Blade + Alpine. Use daisyUI components (`btn`, `input`, `card`, `modal`, `dropdown`, `alert`, `badge`, `navbar`, `menu`, `avatar`, `toggle`, `tabs`, etc.) and its semantic color tokens (`bg-base-100`, `text-base-content`, `border-base-300`, etc.) rather than hardcoding `zinc-*`/`dark:` per element — semantic tokens switch automatically according to `data-theme` on `<html>`, avoiding manual `dark:` pairing for new components.
- DaisyUI themes are custom-defined in `resources/css/app.css` (`@plugin 'daisyui/theme'`, two blocks: `light` and `dark`) — do not use other default daisyUI themes without discussion, to ensure brand color (blue) remains consistent.
- Lightweight interactivity (dropdowns, toggles, modals) uses **Alpine.js** — always add `x-transition` (or duration/opacity variants) to elements that show/hide (`x-show`), avoiding sudden pop-ins without transitions.
- Micro-interactions must feel alive: interactive elements (`button`, `a`, `[role="button"]`) automatically inherit `transition-colors`/`transform` from `app.css` — leverage them (`hover:`, `active:scale-95`, etc.), and do not override them to be instantaneous without good reason.
- No React, Vue, Flux UI, shadcn, MUI, or other JS frameworks/component libraries besides daisyUI.
- No CDN Tailwind in production — use Vite build.
- **Prohibited**: generic decorative icons that have no functional meaning (AI slop icons).
- Icons: use minimal inline SVG or Heroicons — only icons that carry functional meaning.

### Layout Structure

```
resources/views/
  layouts/
    public.blade.php     # Landing page - minimal navbar, no auth
    auth.blade.php       # Login/register - centered card, no sidebar
    dashboard.blade.php  # Superadmin dashboard - sidebar + topbar
    account.blade.php    # My Account - tab navigation
  components/            # Blade components for reusable elements
```

---

## Do Not

- Do not put logic in Controllers — use Services or Actions
- Do not build a separate SPA frontend
- Do not modify Passport core (`vendor/`) — extend via config or subclasses
- Do not store secrets or tokens in logs or unneeded response bodies
- Do not skip PKCE validation
- Do not override `/oauth/*` routes unless strictly necessary
- Do not use decorative icons without functional meaning (AI slop)
- Do not import UI component libraries other than daisyUI (Flux, shadcn, MUI, etc.) — Tailwind + daisyUI only
- Do not use inline styles

---

## Deployment

- Initial deploy: `make deploy` → `sudo bash scripts/deploy.sh`
- Updates: `make update` → `bash scripts/update.sh`
- No CI/CD — manual deployment via SSH to server

---

## Docs

- PRD: [docs/PRD.md](../docs/PRD.md)
- SRS: [docs/SRS.md](../docs/SRS.md)
- TODO: [docs/TODO.md](../docs/TODO.md)
- Tickets: [docs/tickets/](../docs/tickets/)