# Integration Guide - SSO Engine (sso.yado.my.id)

A guide for developers who want to integrate an app with the yado.my.id SSO Engine as an OAuth2 client.

---

## What is SSO Engine?

`sso.yado.my.id` is the **Central Identity Provider** for the Yado.my.id ecosystem. All apps (Malas, Scribe, etc.) delegate authentication here - users log in once and can access every app without logging in again.

Protocol: **OAuth2 Authorization Code + PKCE** (RFC 6749 + RFC 7636). PKCE is mandatory - there is no fallback.

---

## Two Ways to Integrate

| Option | Best for | Estimated time |
|------|-------------|----------------|
| [Option A - Hand it to an AI](#option-a---hand-it-to-an-ai) | New projects or anyone in a hurry | 15-30 minutes |
| [Option B - Manual](#option-b---manual-step-by-step) | You want to understand every step | 1-2 hours |

---

## Prerequisites (required for both options)

Before starting, **ask a superadmin** to register your app at `sso.yado.my.id/dashboard/applications`:

1. The superadmin opens the dashboard → **Add Application**
2. Fill in the app name and **Redirect URI** (example: `https://malas.yado.my.id/auth/callback`)
      - HTTPS is required (except `localhost` for development)
      - The Redirect URI must be final - SSO whitelists it strictly
3. Click Create → the superadmin gets a **Quick Start panel** with ready-to-copy credentials

You will receive from the superadmin:
```env
SSO_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
SSO_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SSO_REDIRECT_URI=https://yourapp.yado.my.id/auth/callback
SSO_BASE_URL=https://sso.yado.my.id
```

> **Important**: `client_secret` is only shown once at creation. If it is lost, the superadmin must regenerate it.

---

## Option A - Hand It to an AI

The fastest way. Works for Laravel, Next.js, or any stack your AI knows.

**Steps:**

1. Get the credentials from the superadmin (see Prerequisites above)
2. Open an AI coding assistant (Claude, Cursor, Copilot, etc.)
3. Paste the following prompt:

```
Integrate Yado.my.id SSO into this project using the following guide:
[link to docs/AI_INTEGRATION.md in the SSO repo]

Credentials already available in .env:
SSO_CLIENT_ID=xxx
SSO_CLIENT_SECRET=xxx
SSO_REDIRECT_URI=xxx
SSO_BASE_URL=https://sso.yado.my.id
```

4. The AI will implement:
      - A local `users` table (no password, with an `sso_id` column)
      - The `/auth/redirect` and `/auth/callback` routes
      - PKCE generation and token exchange
      - Syncing the user profile from SSO to the local DB
      - Route protection middleware

5. Test: visit a protected page → it should redirect to SSO → log in → return to the app

Reference file for the AI: [`docs/AI_INTEGRATION.md`](AI_INTEGRATION.md)

---

## Option B - Manual (Step by Step)

### Flow Overview

```
User opens the app
    → no session yet → redirect to /auth/redirect
    → redirect to sso.yado.my.id/oauth/authorize
    → [SSO session active] → silent, gets a code immediately
    → [not logged in to SSO] → SSO login page is shown → log in
    → redirect to /auth/callback?code=xxx&state=xxx
    → app exchanges code + code_verifier → gets access_token + refresh_token
    → app hits GET /api/user → gets the user profile
    → upsert into the local users table (by sso_id)
    → create a local session → user is in
```

---

### Step 1 - Setup Environment

Add to `.env`:

```env
SSO_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
SSO_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SSO_REDIRECT_URI=https://yourapp.yado.my.id/auth/callback
SSO_BASE_URL=https://sso.yado.my.id
```

Create `config/sso.php`:

```php
return [
    'client_id'     => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri'  => env('SSO_REDIRECT_URI'),
    'base_url'      => env('SSO_BASE_URL', 'https://sso.yado.my.id'),
];
```

---

### Step 2 - Prepare the Users Table

Your app needs a `users` table with the columns from SSO. **No password column is needed** - auth is handled by SSO.

If there is no `users` table yet:

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('sso_id')->unique();
    $table->string('name');
    $table->string('username')->unique();
    $table->string('email')->unique();
    $table->string('avatar')->nullable();
    $table->string('role')->default('user');
    $table->rememberToken();
    $table->timestamps();
});
```

If a `users` table already exists (e.g. from Laravel's default), add the missing columns:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('sso_id')->unique()->after('id');
    $table->string('username')->after('name');
    $table->string('avatar')->nullable()->after('email');
    $table->string('role')->default('user')->after('avatar');
        $table->dropColumn('password'); // remove if there is no local auth
});
```

---

### Step 3 - Generate PKCE

PKCE is mandatory. Create a utility or put it inline in the controller.

```php
// Generate code_verifier (random string of 43-128 characters)
$codeVerifier = bin2hex(random_bytes(32)); // 64 hex characters

// Derive code_challenge (SHA-256 of the verifier, base64url encoded)
$codeChallenge = rtrim(
    strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
    '='
);
```

**Store `code_verifier` in the server-side session** - it is needed when exchanging the token.

---

### Step 4 - Redirect to SSO

```php
// GET /auth/redirect
public function redirect(): RedirectResponse
{
    $codeVerifier = bin2hex(random_bytes(32));
    $codeChallenge = rtrim(
        strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
        '='
    );
        $state = bin2hex(random_bytes(16)); // for CSRF protection

    session(['sso_code_verifier' => $codeVerifier, 'sso_state' => $state]);

    $query = http_build_query([
        'response_type'         => 'code',
        'client_id'             => config('sso.client_id'),
        'redirect_uri'          => config('sso.redirect_uri'),
        'scope'                 => 'profile:read',
        'code_challenge'        => $codeChallenge,
        'code_challenge_method' => 'S256',
        'state'                 => $state,
    ]);

    return redirect(config('sso.base_url') . '/oauth/authorize?' . $query);
}
```

If the user already has an active session at SSO, they **will not see the login page or the consent screen** - they are redirected straight back with an auth code (automatic silent SSO for internal ecosystem clients).

#### Optional: Force Re-Authentication / Add Account (`prompt=login`)
If your app supports multiple accounts ("Add Account") or wants to force the user to enter new credentials even though the SSO session is active, include the `prompt=login` parameter:

```php
$params = [
        // ... regular OAuth parameters ...
    'prompt' => 'login',
];
```
When this parameter is sent, SSO automatically terminates the active browser session at SSO and shows the login form, then passes the authentication of that new account back to your app's `redirect_uri`.

---

### Step 5 - Handle the Callback

```php
// GET /auth/callback
public function callback(Request $request): RedirectResponse
{
        // Validate state to prevent CSRF
    abort_if($request->state !== session('sso_state'), 403, 'Invalid state');

        // Exchange the auth code for tokens (server-to-server)
    $tokens = Http::asForm()->post(config('sso.base_url') . '/oauth/token', [
        'grant_type'    => 'authorization_code',
        'code'          => $request->code,
        'redirect_uri'  => config('sso.redirect_uri'),
        'client_id'     => config('sso.client_id'),
        'client_secret' => config('sso.client_secret'),
        'code_verifier' => session('sso_code_verifier'),
    ])->json();

        // Fetch the user profile
    $profile = Http::withToken($tokens['access_token'])
        ->get(config('sso.base_url') . '/api/user')
        ->json();

        // Upsert into the local users table
    $user = User::updateOrCreate(
        ['sso_id' => $profile['id']],
        [
            'name'     => $profile['name'],
            'username' => $profile['username'],
            'email'    => $profile['email'],
            'avatar'   => $profile['avatar'],
            'role'     => $profile['role']['slug'],
        ]
    );

        // Clear the PKCE session
    session()->forget(['sso_code_verifier', 'sso_state']);

        // Store tokens for refreshing later
    session([
        'sso_access_token'  => $tokens['access_token'],
        'sso_refresh_token' => $tokens['refresh_token'],
    ]);

    Auth::login($user, remember: true);

    return redirect()->intended('/dashboard');
}
```

> **Important**: Token exchange must happen server-side. Never expose `client_secret` to the browser.

---

### Step 6 - Routes and Middleware

```php
// routes/web.php
Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');
Route::post('/auth/logout', [SsoController::class, 'logout'])->name('sso.logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', ...);
        // all routes that require login
});
```

Point unauthenticated redirects to SSO (not to a local `/login`):

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthenticationException $e, Request $request) {
        if (! $request->expectsJson()) {
            return redirect()->route('sso.redirect');
        }
    });
})
```

---

### Step 7 - Logout

Two-step logout is mandatory:
1. Delete the local session in your app
2. Redirect to SSO logout - if skipped, the SSO session stays active and the user will be logged back in automatically without being asked for a password

```php
public function logout(Request $request): RedirectResponse
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

        // Required: redirect to SSO logout with redirect_uri so the SSO session is destroyed too.
        // Without this, clicking login again will auto-login without asking for a password.
    $redirectUri = urlencode(config('sso.redirect_uri_base', url('/')));
    return redirect(config('sso.base_url') . '/logout?redirect_uri=' . $redirectUri);
}
```

> **Important**: `redirect_uri` must be a domain registered in SSO as your client app's redirect URI. SSO only redirects to whitelisted domains.

---

### Step 8 - Refresh Token (Optional but Recommended)

The access token expires after **60 minutes**. Implement refresh so the session does not drop:

```php
private function refreshToken(): bool
{
    $response = Http::asForm()->post(config('sso.base_url') . '/oauth/token', [
        'grant_type'    => 'refresh_token',
        'refresh_token' => session('sso_refresh_token'),
        'client_id'     => config('sso.client_id'),
        'client_secret' => config('sso.client_secret'),
    ]);

    if ($response->failed()) {
        return false;
    }

    $tokens = $response->json();
    session([
        'sso_access_token'  => $tokens['access_token'],
                'sso_refresh_token' => $tokens['refresh_token'], // always store the new one
    ]);

    return true;
}
```

The refresh token is valid for **30 days** and is single-use - every refresh produces a new token.

---

## Available User Data

After login, `Auth::user()` returns the local User model already synced from SSO:

```php
Auth::user()->sso_id    // Unique ID at SSO - use it as the foreign key across apps
Auth::user()->name      // Full name
Auth::user()->username  // Unique username
Auth::user()->email
Auth::user()->avatar    // URL or null
Auth::user()->role      // "user" or "superadmin"
Auth::user()->theme     // "system" | "light" | "dark" - the user's theme preference
Auth::user()->locale    // "id" | "en" | "ja" - the user's language preference
```

Data is synced every time the user logs in. To update their profile, users go to `sso.yado.my.id/account`.

> **Cross-app theme & language**: SSO only stores and exposes these preferences - your app must read and apply them itself (SSO cannot style other apps). A complete implementation example is in [`AI_INTEGRATION.md`](AI_INTEGRATION.md), section "Theme & Language".

---

## Scopes

| Scope | Available data |
|-------|--------------------|
| `profile:read` | id, name, username, email, avatar, role |

Use `scope=profile:read` in the authorization URL. Additional scopes will be defined as the ecosystem needs them.

---

## API Reference

### GET /api/user

```
Authorization: Bearer {access_token}
```

Response 200:
```json
{
  "id": 1,
  "name": "Budi Santoso",
  "username": "budi",
  "email": "budi@yado.my.id",
  "avatar": null,
  "role": { "id": 1, "name": "User", "slug": "user" }
}
```

| Status | Condition |
|--------|---------|
| 200 | Token valid, scope sufficient |
| 401 | Token invalid, expired, or user deactivated |
| 403 | Token valid but scope insufficient |

### POST /oauth/token

| Parameter | Description |
|-----------|------------|
| `grant_type` | `authorization_code` or `refresh_token` |
| `code` | Auth code from the callback (for authorization_code) |
| `code_verifier` | The PKCE verifier produced in Step 3 |
| `refresh_token` | The stored refresh token (for the refresh_token grant) |
| `client_id` | From `.env` |
| `client_secret` | From `.env` - do not expose it to the frontend |
| `redirect_uri` | Must match exactly what was registered |

---

## Local Testing (SSO + Client App on 1 Device)

This is the most common setup and the one that confuses people most - SSO and your app run together on the same laptop.

1. **Ports must differ.** `php artisan serve` defaults to port 8000. If SSO already uses 8000, run the client app on another port:
   ```bash
      # Terminal 1 - SSO Engine
   cd sso.yado && php artisan serve --port=8000

      # Terminal 2 - your client app
   cd malas-app && php artisan serve --port=8001
   ```

2. **The Redirect URI must match exactly** - including the port. If `http://localhost:8001/auth/callback` is registered but your app runs on port 8002, you will hit `invalid_client` / a redirect mismatch.

3. **`APP_URL` in both apps must match their own ports.** SSO: `APP_URL=http://localhost:8000`, client: `APP_URL=http://localhost:8001`.

4. **Session cookies will not collide** as long as `APP_NAME` differs between the two apps (Laravel generates the cookie name from `APP_NAME`, not from the port). If you cloned the same starter for both apps and forgot to change `APP_NAME`, both may use the exact same session cookie and override each other - make sure `APP_NAME` is unique in each `.env`.

5. **Leave `SESSION_DOMAIN` as `null`** in both apps (the default). Browsers do not distinguish cookies by port if `Domain` is explicitly set to `localhost` - leaving it `null` scopes cookies automatically per origin (host + port + scheme).

6. Once both are running, open the client app at `http://localhost:8001` → click login → it should redirect to `http://localhost:8000/login` (SSO), not a connection error or a blank page.

---

## Common Errors

| Error | Cause | Solution |
|-------|----------|--------|
| `invalid_client` | Wrong `client_id` or `client_secret` | Check `.env` |
| `invalid_grant` | `code_verifier` does not match or the code was already used | Make sure `code_verifier` is stored in the server session |
| `invalid_request` | `code_challenge` is missing | PKCE is mandatory - make sure `redirect()` generates and sends `code_challenge` |
| 403 Invalid state | `state` in the callback does not match | Do not store `state` in a cookie/localStorage |
| 401 from `/api/user` | Token expired | Implement refresh token |
| 429 | Rate limit reached | Wait 1 minute, maximum 5 requests/minute |

---

## Integration Checklist

- [ ] Receive `client_id`, `client_secret`, `redirect_uri` from the superadmin
- [ ] Add 4 env vars to `.env`
- [ ] Create `config/sso.php`
- [ ] Prepare the `users` table with the columns `sso_id`, `username`, `avatar`, `role`
- [ ] Implement `SsoController` with `redirect()`, `callback()`, `logout()`
- [ ] Register the routes `/auth/redirect`, `/auth/callback`, `/auth/logout`
- [ ] Point unauthenticated redirects to `route('sso.redirect')`
- [ ] Test: visit a protected route → redirect to SSO → log in → return to the app ✓
- [ ] Test: visit again after login → silent SSO, no new login requested ✓
- [ ] Test: logout → local session + SSO session destroyed ✓

---

## References

- [AI Integration Brief](AI_INTEGRATION.md) - for integration via an AI assistant
- [SRS - full API contract](SRS.md)
- [PRD - ecosystem architecture](PRD.md)
