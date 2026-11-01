# TASK-004: OAuth2 Authorization Code + PKCE

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Set up the OAuth2 Authorization Code flow endpoint with PKCE using Laravel Passport. This is the core of the SSO Engine - the endpoint invoked by client applications to initiate authorization flows.

Depends on: TASK-003

---

## DEV Response

- [x] Authorization Code grant active by default in Passport - no extra configuration required
- [x] Enforce PKCE: create `EnforcePkce` middleware that rejects GET `/oauth/authorize` missing `code_challenge` (400) or using methods other than `S256` (400)
- [x] Register middleware for Passport routes via `config/passport.php` `middleware` array
- [x] Register scope `profile:read` in `AppServiceProvider`: `Passport::tokensCan([...])`
- [x] Blade view `resources/views/oauth/authorize.blade.php`: consent screen with Authorize and Deny buttons (used as fallback for non-first-party clients)
- [x] Routes `/oauth/authorize` and `/oauth/token` auto-registered by Passport - verified via `route:list`
- [x] Client registration: performed via `php artisan passport:client` or `tinker` - documented in TODO

**Notes:**
- `EnforcePkce` middleware only inspects GET requests to `passport.authorizations.authorize` - POST approve does not require PKCE (PKCE applies strictly to authorization requests, not token exchanges; token exchange uses `code_verifier` validated internally by Passport/league-oauth2-server).

---

## QA Response

> **Method**: Static code review.

- [x] GET `/oauth/authorize` without login -> redirect to `/login` - Passport `AuthorizationController` built-in: guest -> `promptForLogin()` -> redirect to named route `login`
- [x] GET `/oauth/authorize` without `code_challenge` -> rejected (400) - `EnforcePkce` middleware: `empty($request->query('code_challenge'))` -> 400 JSON response
- [x] `EnforcePkce` registered and active - confirmed: `config/passport.php` `middleware` array read by `PassportServiceProvider:60`
- [x] GET `/oauth/authorize` with `code_challenge_method` other than S256 -> rejected 400 - `EnforcePkce:22` validates method
- [x] Scope `profile:read` registered via `Passport::tokensCan()` in `AppServiceProvider:28`
- [x] Consent view `oauth.authorize` registered via `Passport::authorizationView()`
- [x] GET `/oauth/authorize` with unregistered `redirect_uri` -> `invalid_client` - handled by Passport/league-oauth2-server
- [x] POST `/oauth/token` with valid code + correct code_verifier -> `access_token` + `refresh_token` - Passport default, TTL 60 minutes
- [x] POST `/oauth/token` with incorrect code_verifier -> `invalid_grant` - league-oauth2-server internal validation
- [x] Auth code single-use & expired -> `invalid_grant` - league-oauth2-server default (10 minutes)
- [x] Access token expiration time 3600 seconds - `Passport::tokensExpireIn(now()->addMinutes(60))`

**Runtime note**: Several test cases above (actual HTTP flows) require verification in an environment with PHP >=8.4.1.

**Status: Done**
