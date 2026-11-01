# SRS - SSO Engine (sso.yado.my.id)

## Tech Stack

| Layer | Choice |
|-------|--------|
| Backend | Laravel (latest stable) |
| Auth | Laravel Passport (OAuth2 server - RFC 6749 + RFC 7636 PKCE) |
| Frontend | Svelte 5 (Runes) + Inertia.js v2 + GSAP + Tailwind CSS v4 + DaisyUI v5 |
| Database | PostgreSQL 16 - `db_sso` (read/write split, sticky mode, persistent volume) |
| Hosting | Linux VM / EC2 / Docker (Traefik / Caddy) |
| Email | Resend API / SMTP (runtime configurable via dashboard) |
| Tunnel | Cloudflare (DNS + proxy) |

---

## Project Structure

```
root/
  app/
    Http/
      Controllers/
        Auth/
          LoginController.php
          RegisterController.php
          LogoutController.php
        OAuth/
          AuthorizationController.php   # extends Passport's built-in
        Api/
          UserController.php            # GET /api/user userinfo
      Middleware/
        EnsureTokenHasScope.php
    Models/
      User.php
      Role.php
    Services/
      Auth/
        LoginService.php
        RegisterService.php
      OAuth/
        ConsentService.php              # auto-approve logic
    Actions/
      Auth/
        IssueTokenAction.php
        RevokeTokenAction.php
  config/
    passport.php
    database.php                        # read/write split config
  database/
    migrations/
  resources/
    js/
      Pages/                            # Svelte 5 pages (Auth, Dashboard, Account)
      Layouts/                          # Layouts (AuthLayout, DashboardLayout, AccountLayout)
      app.js                            # Inertia Svelte 5 app initialization
    css/                                # app.css (Tailwind CSS v4 & DaisyUI v5)
    views/
      app.blade.php                     # Inertia root layout
  routes/
    web.php                             # Inertia web routes
    api.php                             # /api/user
    oauth.php                           # /oauth/* (Passport)
  docs/
  scripts/
  .claude/
```

---

## Database Schema

### Table: `users`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | |
| name | varchar(255) | |
| username | varchar(50) unique | |
| email | varchar(255) unique | |
| email_verified_at | timestamp nullable | |
| password | varchar(255) | bcrypt |
| avatar | varchar(255) nullable | path or URL |
| role_id | bigint unsigned FK | → roles.id |
| is_active | boolean | default true |
| remember_token | varchar(100) nullable | |
| created_at / updated_at | timestamp | |

### Table: `roles`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | |
| name | varchar(100) | e.g. "User", "Admin" |
| slug | varchar(100) unique | e.g. "user", "admin" |
| created_at / updated_at | timestamp | |

### OAuth Tables (automatically managed by Passport)

- `oauth_clients`
- `oauth_access_tokens`
- `oauth_refresh_tokens`
- `oauth_auth_codes`
- `oauth_personal_access_clients`

---

## Database Config (Read/Write Split)

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [env('DB_READ_HOST', env('DB_HOST', '127.0.0.1'))],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1'))],
    ],
    'sticky' => true,
    'driver' => 'mysql',
    'database' => env('DB_DATABASE', 'db_sso'),
    // ...
],
```

Sticky mode guarantees that writes are immediately read from the write connection within the same request.

---

## OAuth2 Flow

### Authorization Code + PKCE

```
Client App                         SSO Engine
    |                                  |
    |-- GET /oauth/authorize ---------->|
    |   ?response_type=code             |
    |   &client_id=xxx                  |
    |   &redirect_uri=xxx               |
    |   &scope=profile:read             |
    |   &code_challenge=xxx             |
    |   &code_challenge_method=S256     |
    |                                   |
    |   [if session is active]          |
    |<-- 302 redirect + code -----------|  ← silent SSO
    |                                   |
    |   [if not logged in]              |
    |<-- show login.blade.php ----------|
    |-- POST /login ------------------->|
    |<-- 302 /oauth/authorize (retry) --|
    |<-- 302 redirect + code -----------|
    |                                   |
    |-- POST /oauth/token ------------->|
    |   grant_type=authorization_code   |
    |   code=xxx                        |
    |   code_verifier=xxx               |
    |<-- { access_token, refresh_token }|
    |                                   |
    |-- GET /api/user ----------------->|  Bearer token
    |<-- { id, name, username, email,   |
    |       avatar, role }              |
```

### Refresh Token

```
POST /oauth/token
  grant_type=refresh_token
  refresh_token=xxx
  client_id=xxx
  client_secret=xxx

Response: { access_token, refresh_token, expires_in }
```

---

## API Contract

### GET /api/user

Requires `Authorization: Bearer {access_token}` with scope `profile:read`.

**Response 200**
```json
{
  "id": 1,
  "name": "Budi Santoso",
  "username": "budi",
  "email": "budi@yado.my.id",
  "avatar": "https://sso.yado.my.id/avatars/budi.jpg",
  "role": {
    "id": 1,
    "name": "User",
    "slug": "user"
  }
}
```

**Response 401** - invalid / expired token
```json
{ "message": "Unauthenticated." }
```

**Response 403** - valid token but insufficient scope
```json
{ "message": "Invalid scope(s) provided." }
```

### POST /login (Web)

```
email: string required
password: string required
remember: boolean optional
```

Redirects to `/oauth/authorize` if an `intended` URL is stored in session, or to `/` otherwise.

### POST /register (Web)

```
name: string nullable max:255   # optional - falls back to username if empty
username: string required unique max:50 regex:[a-z0-9_]
email: string required email unique
password: string required min:8 confirmed
```

---

## Scopes

| Scope | Access |
|-------|--------|
| `profile:read` | id, name, username, email, avatar, role |

Additional scopes will be defined in dedicated tickets as needed.

---

## Client Registration

OAuth clients are registered as **confidential clients** with:
- `client_id` - UUID auto-generated by Passport
- `client_secret` - hashed, returned once upon creation
- `redirect_uri` - whitelist URI
- `is_first_party` - flag for auto-approve consent (custom field or via Passport `personal_access_client`)

---

## Token TTL

| Token | TTL |
|-------|-----|
| Access Token | 60 minutes |
| Refresh Token | 30 days |
| Auth Code | 10 minutes |

---

## Security Spec

- Mandatory PKCE (`S256`) — reject requests missing `code_challenge`
- CSRF middleware active across all web routes (login/register forms)
- Rate limiting: `throttle:5,1` on `POST /login` and `POST /oauth/token`
- Password minimum 8 characters, default bcrypt cost
- HTTPS enforced via Cloudflare + Laravel `forceScheme('https')` in production
- `is_active = false` → all requests rejected, including those with valid tokens

---

## Acceptance Criteria

| Feature | Criteria |
|---------|----------|
| Register | New users can sign up, default role "user" assigned automatically |
| Login | Session created, redirects to intended URL |
| Silent SSO | Active session → `/oauth/authorize` redirects immediately without login form |
| Auth code | `code` valid for 10 minutes, single-use only |
| Token exchange | POST /oauth/token with code + verifier → receives access & refresh tokens |
| Userinfo | GET /api/user with valid token having `profile:read` scope → returns profile |
| Refresh | Refresh token → returns new access token, legacy refresh token revoked |
| Logout | Session destroyed, active tokens revoked |
| PKCE | Request missing code_challenge is rejected with 400 |
| Rate limit | > 5 failed logins within 1 minute → 429 |