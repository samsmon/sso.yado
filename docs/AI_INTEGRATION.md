# SSO Integration Brief - yado.my.id

> Use this file as context for the AI: *"Integrate Yado.my.id SSO into this project using the following guide."*

---

## Context

`sso.yado.my.id` is the Central Identity Provider for the Yado.my.id ecosystem.
Protocol: **OAuth2 Authorization Code + PKCE** (mandatory, no fallback).
All apps in the ecosystem delegate auth here - there is no need to build your own login.

---

## Prerequisites

A superadmin has registered this app at `sso.yado.my.id/dashboard/applications` and provided:

```env
SSO_CLIENT_ID=<uuid>
SSO_CLIENT_SECRET=<secret>
SSO_REDIRECT_URI=https://<this-app-domain>/auth/callback
SSO_BASE_URL=https://sso.yado.my.id
```

Add these 4 variables to the app's `.env`.

---

## What Must Be Implemented

### 1. Local users table

The app needs a local `users` table with the columns from SSO. **No password column is needed** - auth is handled by SSO.

```
sso_id          string unique   # primary key from SSO, use this as the foreign key
name            string
username        string
email           string unique
avatar          string nullable
role            string          # "user" or "superadmin"
created_at
updated_at
```

If a `users` table already exists with a `password` column, add `sso_id` as a new column (nullable first, then migrate the data if needed).

### 2. Two auth routes

```
GET /auth/redirect   → start the OAuth flow (generate PKCE, redirect to SSO)
GET /auth/callback   → receive the code from SSO, exchange the token, fetch the profile, log the user in
```

### 3. Protection middleware

Every route that requires login → redirect to `/auth/redirect` if there is no session yet.

---

## Full Implementation (Laravel)

### Migration

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('sso_id')->unique()->after('id');
    $table->string('username')->after('name');
    $table->string('avatar')->nullable()->after('email');
    $table->string('role')->default('user')->after('avatar');
        // Remove $table->string('password') if it exists and is not needed
});
```

### Controller

```php
// app/Http/Controllers/Auth/SsoController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SsoController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
            '='
        );
        $state = bin2hex(random_bytes(16));

        session([
            'sso_code_verifier' => $codeVerifier,
            'sso_state'         => $state,
        ]);

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

    public function callback(Request $request): RedirectResponse
    {
        abort_if($request->state !== session('sso_state'), 403, 'Invalid state');

        $tokenResponse = Http::asForm()->post(config('sso.base_url') . '/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $request->code,
            'redirect_uri'  => config('sso.redirect_uri'),
            'client_id'     => config('sso.client_id'),
            'client_secret' => config('sso.client_secret'),
            'code_verifier' => session('sso_code_verifier'),
        ])->json();

        $profile = Http::withToken($tokenResponse['access_token'])
            ->get(config('sso.base_url') . '/api/user')
            ->json();

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

        session()->forget(['sso_code_verifier', 'sso_state']);
        session([
            'sso_access_token'  => $tokenResponse['access_token'],
            'sso_refresh_token' => $tokenResponse['refresh_token'],
        ]);

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
```

### Config

```php
// config/sso.php
return [
    'client_id'     => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri'  => env('SSO_REDIRECT_URI'),
    'base_url'      => env('SSO_BASE_URL', 'https://sso.yado.my.id'),
];
```

### Routes

```php
// routes/web.php
Route::get('/auth/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/callback', [SsoController::class, 'callback'])->name('sso.callback');

// Protect every route that requires login:
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', ...);
        // all other routes
});
```

### Middleware redirect to SSO (optional - replaces Laravel's default auth redirect)

In `bootstrap/app.php` or `AppServiceProvider`, point unauthenticated redirects to SSO:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (AuthenticationException $e, Request $request) {
        return redirect()->route('sso.redirect');
    });
})
```

---

## Refresh Token (optional but recommended)

The access token expires after **60 minutes**. Implement refresh so the session does not drop:

```php
// Check and refresh the token if it has expired (e.g. in a middleware or scheduled job)
$tokenResponse = Http::asForm()->post(config('sso.base_url') . '/oauth/token', [
    'grant_type'    => 'refresh_token',
    'refresh_token' => session('sso_refresh_token'),
    'client_id'     => config('sso.client_id'),
    'client_secret' => config('sso.client_secret'),
])->json();

session([
    'sso_access_token'  => $tokenResponse['access_token'],
    'sso_refresh_token' => $tokenResponse['refresh_token'],
]);
```

The refresh token is valid for **30 days** and is single-use - always store the new token from the response.

---

## Logout

Logout is **mandatory in two steps**: delete the local session, then redirect to SSO logout.
If the second step is skipped, the SSO session stays active - clicking login again will auto-login without asking for a password.

```php
public function logout(Request $request): RedirectResponse
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

        // Must redirect to SSO logout with redirect_uri so the SSO session is destroyed too.
        // redirect_uri must be a domain registered in SSO as this client's redirect URI.
    $redirectUri = urlencode(url('/'));
    return redirect(config('sso.base_url') . '/logout?redirect_uri=' . $redirectUri);
}
```

Logout route on the client (make sure both POST and GET are supported):

```php
Route::post('/auth/logout', [SsoController::class, 'logout'])->name('sso.logout');
Route::get('/auth/logout', [SsoController::class, 'logout']);  // for redirects from SSO
```

---

## Available User Data

After login, `Auth::user()` returns the local User model already synced from SSO:

```php
Auth::user()->sso_id    // ID at SSO (use it if you need cross-references)
Auth::user()->name
Auth::user()->username
Auth::user()->email
Auth::user()->avatar    // avatar URL or null
Auth::user()->role      // "user" atau "superadmin"
Auth::user()->theme     // "system" | "light" | "dark"
Auth::user()->locale    // "id" | "en" | "ja"
```

Data is synced every time the user logs in. To update their profile, users must go to `sso.yado.my.id/account`.

---

## Theme & Language (Optional, Cross-App)

SSO stores the `theme` and `locale` preferences per user and exposes them via `/api/user`. **SSO cannot force styling or language in your app** - that is a separate domain. SSO only provides the single source of truth for user preferences; your app applies them itself.

If you want to stay in sync with the preferences the user sets in SSO:

### Theme

```php
// Store the theme from the SSO API response in the session/DB during callback()
session(['theme' => $profile['theme']]);
```

Apply it in your layout using the same strategy as SSO - class-based dark mode via `.dark` on `<html>`, set with an inline script before the CSS loads (avoids FOUC):

```html
<script>
    var stored = localStorage.getItem('theme') || '{{ session('theme', 'system') }}';
    var isDark = stored === 'dark' || (stored === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', isDark);
</script>
```

### Language

Laravel has built-in i18n (`__()`, `lang/{locale}/*.php` files). Read `locale` from SSO and set it via middleware:

```php
App::setLocale($profile['locale'] ?? 'id');
```

If your app is not Laravel, apply the equivalent pattern in your framework - the point is to read `theme`/`locale` from `/api/user`, store it locally (session/DB), then apply it at render time.

> This sync is best-effort, not realtime. If a user changes language in SSO after their login session has started, your app only gets the new value at the next login (unless you re-fetch `/api/user` periodically).

---

## Implementation Checklist

- [ ] Add 4 env vars (`SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, `SSO_REDIRECT_URI`, `SSO_BASE_URL`)
- [ ] Create/update the `users` table with the columns `sso_id`, `username`, `avatar`, `role`
- [ ] Create `config/sso.php`
- [ ] Create `SsoController` with the `redirect()` and `callback()` methods
- [ ] Register the `/auth/redirect` and `/auth/callback` routes
- [ ] Point unauthenticated requests to `route('sso.redirect')` instead of `/login`
- [ ] Test: visit a protected page → redirect to SSO → log in → return to the app → session active
- [ ] Test: visit again after SSO login → silent (no new login needed)
- [ ] Test: logout → local session destroyed + SSO session destroyed → click login again → the SSO login page appears (not auto-login)

---

## Local Testing (SSO + Client App on 1 Device)

- SSO and the client app **must use different ports** (`php artisan serve --port=8000` for SSO, `--port=8001` for the client)
- The `redirect_uri` registered in SSO must match exactly, including the port
- `APP_URL` in each `.env` must match each app's own port
- `APP_NAME` in the client app must differ from SSO - Laravel's session cookie name is derived from `APP_NAME`, and if both are identical the two apps can override each other's session cookie
- Leave `SESSION_DOMAIN` as `null` in both apps (the default) - do not set it explicitly to `localhost`
- If your app runs in a **Docker container separate** from SSO, `localhost` cannot be used for server-to-server requests (token exchange, profile fetch) - containers do not know each other via `localhost`. Split the config into `base_url` (for browser redirects, stays `localhost`) and `internal_url` (for server-to-server HTTP calls, using `host.docker.internal`). Full details + code example: [`DOCKER.md`](DOCKER.md)

---

## Common Errors

| Error | Cause | Fix |
|-------|----------|-----|
| `invalid_client` | Wrong `client_id` or `client_secret` | Check `.env` |
| `invalid_grant` | `code_verifier` does not match or the code was already used | Make sure `code_verifier` is stored in the server-side session |
| 403 Invalid state | `state` in the callback does not match the session | Do not store `state` in a cookie/localStorage |
| `invalid_request` | `code_challenge` is missing | PKCE is mandatory - make sure `redirect()` generates and sends `code_challenge` |
| Token expired (401) | Access token older than 60 minutes | Implement the refresh token flow |
| Auto-login after logout | The SSO session was not destroyed | Make sure logout redirects to `/logout?redirect_uri=...` and does not only clear the local session |
| GET method not allowed (logout) | The client has a POST-only logout route but is hit via a GET redirect | Add `Route::get('/auth/logout', ...)` on the client |
| Cannot integrate on 1 device (local) | Port collision, `redirect_uri` does not match the port, or `APP_NAME` is the same in both apps (cookie collision) | See the "Local Testing" section above - make sure the ports, `redirect_uri`, and `APP_NAME` differ |

---

## References

- Full API contract: `docs/SRS.md`
- Manual guide (for humans): `docs/INTEGRATION.md`
- SSO Engine repo: `sso.yado.my.id`
