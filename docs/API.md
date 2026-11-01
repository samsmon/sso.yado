# API Reference - SSO Engine (sso.yado.my.id)

Technical reference for all HTTP endpoints that are called programmatically (not the SPA web portal pages). For a step-by-step integration guide, see [docs/INTEGRATION.md](INTEGRATION.md), [docs/AI_INTEGRATION.md](AI_INTEGRATION.md), or [docs/AI_AGENT_GUIDE.md](AI_AGENT_GUIDE.md).

**Base URL**: `https://sso.yado.my.id` (production) - adjust to match `APP_URL` in your environment.

---

## Authentication

There are two different auth models in this project, do not mix them up:

- **OAuth2 Bearer token** (`Authorization: Bearer <access_token>`) - used by client apps to call `GET /api/user` after the user finishes logging in through `/oauth/authorize`. Obtained through the token exchange at `/oauth/token`.
- **Session cookie** (regular web portal login) - used for all Svelte SPA pages (`/login`, `/account/*`, `/dashboard/*`). Outside the scope of this document because it is handled by Inertia.js and the Laravel session cookie.

---

## Health Check

### `GET /health`

Public, **no authentication**, for external monitoring (the Yado landing page). A minimal DB query (`SELECT 1`) keeps the response fast - do not expect it to validate every dependency (mail, storage, etc.), only DB connectivity.

Rate limit: 60 requests/minute per IP.

**Healthy response - `200 OK`**
```json
{
  "status": "ok",
  "service": "sso-engine",
  "timestamp": "2026-08-27T17:28:46+00:00"
}
```

**Unhealthy response - `503 Service Unavailable`**
```json
{
  "status": "error",
  "service": "sso-engine",
  "error": "SQLSTATE[HY000] [2002] Connection refused"
}
```

### `GET /up`

Laravel's built-in health check (a pure liveness probe - checks that the app can boot & serve a response, without checking external dependencies). The response is HTML, not JSON. Used internally (load balancers, simple uptime monitors) - for a monitoring dashboard that needs structured JSON, use `/health`.

---

## OAuth2 (Laravel Passport)

Protocol: **Authorization Code + PKCE** (RFC 6749 + RFC 7636). PKCE is **mandatory** for all clients, with no fallback to a flow without PKCE. Rate limit: 30 requests/minute per IP for all `/oauth/*` endpoints.

### `GET /oauth/authorize`

Entry point of the OAuth2 flow. Redirect the user here from the client app with this query string:

```
GET /oauth/authorize
    ?client_id=<uuid>
        &redirect_uri=<registered url>
    &response_type=code
    &scope=profile:read
        &state=<random string, CSRF protection>
    &code_challenge=<PKCE challenge>
    &code_challenge_method=S256
        &prompt=login             <optional, force re-auth / add account>
```

- **Silent Authorization**: For internal Yado ecosystem apps (`skipsAuthorization() == true`), once authenticated the user is redirected straight back to the `redirect_uri` without going through a manual consent screen.
- **`prompt=login`**: If included, the active login session at SSO is terminated and the user is forced to enter new credentials (useful for multi-account linking in client apps).
- If the user is not authenticated, they are sent to the `/login` form (with a banner showing the target app), then returned to the authentication flow. After success, they are redirected to the `redirect_uri` with `?code=<auth code>&state=<state>`. The auth code is valid for ~10 minutes and is single-use.

### `POST /oauth/token`

Exchange an authorization code (or refresh token) for an access token.

**Authorization Code exchange:**
```
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&client_id=<uuid>
&client_secret=<secret>
&redirect_uri=<registered url, must match exactly>
&code=<auth code from the callback>
&code_verifier=<PKCE verifier>
```

**Refresh token exchange:**
```
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
&client_id=<uuid>
&client_secret=<secret>
&refresh_token=<refresh token>
```

**Response - `200 OK`**
```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ...",
  "refresh_token": "def502..."
}
```

- The access token is valid for **60 minutes**.
- The refresh token is valid for **30 days**.
- Available scope: `profile:read` - read the user profile (name, username, email, avatar, role). Only one scope at the moment, there are no other scopes.

---

## Resource API

### `GET /api/user`

The profile of the logged-in user, identified by the access token.

**Auth**: `Authorization: Bearer <access_token>` - requires the `profile:read` scope. Rate limit: 60 requests/minute per user.

```
GET /api/user
Authorization: Bearer eyJ...
```

**Response - `200 OK`**
```json
{
  "id": 12,
  "name": "Budi Santoso",
  "username": "budi",
  "email": "budi@example.com",
  "avatar": "https://sso.yado.my.id/storage/avatars/12.jpg",
  "theme": "system",
  "locale": "id",
  "role": {
    "id": 2,
    "name": "Member",
    "slug": "member"
  }
}
```

**Error responses**

| Status | When it happens |
|--------|----------------|
| `401 Unauthorized` | Token missing, invalid, expired, or already revoked (user logged out/revoked the device) |
| `403 Forbidden` | Token valid but lacks the `profile:read` scope |
| `403 Forbidden` | The related user was deactivated by a superadmin (`is_active = false`) - checked via the `check.user.active` middleware |
| `429 Too Many Requests` | Rate limit exceeded |

---

## Rate Limit - Summary

| Endpoint | Limit |
|----------|-------|
| `GET /health` | 60/minute per IP |
| `/oauth/*` (authorize + token) | 30/minute per IP |
| `GET /api/user` | 60/minute per user |
| All other guest pages (`/login`, `/register`, etc.) | 60/minute per IP (form submits are stricter, see `routes/web.php`) |
| All authenticated routes (dashboard, account) | 120/minute per user |

The response when the limit is hit is always `429 Too Many Requests` with a `Retry-After` header.

---

## See Also

- [docs/INTEGRATION.md](INTEGRATION.md) - manual step-by-step integration guide for developers
- [docs/AI_INTEGRATION.md](AI_INTEGRATION.md) - a brief to paste into an AI assistant so it auto-generates the integration code
- [docs/SRS.md](SRS.md) - full tech spec & DB schema
