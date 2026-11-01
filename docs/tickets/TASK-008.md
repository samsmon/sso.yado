# TASK-008: Logout + Token Revocation

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Implement a completely clean logout flow: destroy web sessions, revoke all active access tokens and refresh tokens belonging to the user. Post-logout, legacy tokens must not be accepted at any endpoint.

Depends on: TASK-003, TASK-004

---

## DEV Response

- [x] Create `RevokeTokenAction` in `app/Actions/Auth/RevokeTokenAction.php`
  - Method `execute(User $user): void`
  - Fetch all user tokens via `$user->tokens`
  - Revoke each token via `$token->revoke()`
  - Revoke all associated refresh tokens via `$token->refreshTokens()->update(['revoked' => true])`
- [x] Update `LogoutController` in `app/Http/Controllers/Auth/LogoutController.php`
  - Inject `RevokeTokenAction` via constructor
  - Sequence: revoke tokens -> `Auth::logout()` -> invalidate session -> regenerate CSRF token -> redirect `/login`
- [x] Route `POST /logout` already present in `routes/web.php` with `auth` middleware
- [x] CSRF protection active (Laravel default for all web routes)

---

## QA Response

> **Method**: Static code review.

- [x] POST `/logout` -> session destroyed, redirect to `/login` - `LogoutController::destroy()`: `Auth::logout()` -> `invalidate()` -> `regenerateToken()` -> `redirect()->route('login')`
- [x] Accessing protected page post-logout -> redirect to `/login` - session cleared, `auth` middleware redirects to named route `login`
- [x] GET `/api/user` with legacy access token post-logout -> 401 - `RevokeTokenAction` calls `$token->revoke()` on all tokens; `auth:api` guard rejects revoked tokens
- [x] POST `/oauth/token` with legacy refresh token post-logout -> `invalid_grant` - `$token->refreshTokens()->update(['revoked' => true])` in `RevokeTokenAction:13`
- [x] POST `/logout` without CSRF -> HTTP 419 - Laravel default CSRF middleware active for all web routes
- [x] Unauthenticated POST `/logout` -> redirect, no 500 error - route uses `auth` middleware -> redirects to `/login` before reaching controller
- [x] User with multiple tokens -> all revoked - `foreach ($user->tokens as $token)` iterates all tokens in `RevokeTokenAction:11`

**Additional checks:**
- [x] `RevokeTokenAction` is a dedicated Action class, injected into `LogoutController` constructor - thin controller
- [x] Logout sequence order is correct: revoke tokens -> logout -> invalidate -> regenerateToken
- [x] No `dd()`

**Status: Done**
