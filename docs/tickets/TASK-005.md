# TASK-005: Silent SSO + Auto-Approve Consent

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Implement two key SSO behaviors:
1. **Silent SSO** - if the user already has an active session when `/oauth/authorize` is called, immediately redirect back to the client with an authorization code without displaying the login form.
2. **Auto-approve consent** - all clients registered as first-party bypass the consent screen and are approved automatically.

Depends on: TASK-004

---

## DEV Response

- [x] Create `ConsentService` in `app/Services/OAuth/ConsentService.php`
  - Method `shouldAutoApprove(Client $client, User $user): bool`
  - Logic: return `$client->firstParty()` (Passport built-in: clients with `user_id = null` are first-party)
- [x] Create `app/Models/OAuth/Client.php` extending `Laravel\Passport\Client`
  - Override `skipsAuthorization(Authenticatable $user, array $scopes): bool` -> return `$this->firstParty()`
  - Register via `Passport::useClientModel(Client::class)` in `AppServiceProvider`
- [x] Register custom Client model in `AppServiceProvider`
- [x] Register `Passport::authorizationView('oauth.authorize')` - Passport will display this view only if `skipsAuthorization()` returns false (non-first-party)

**Silent SSO behavior (Passport built-in):**
- Passport's `AuthorizationController` handles: if guest user -> `promptForLogin()` -> redirect to `/login` with session `intended`
- If user is already authenticated -> validate auth request directly -> check `skipsAuthorization()` -> if true, issue authorization code immediately and redirect (no consent view displayed)
- No controller override necessary

**Notes:**
- `firstParty()` in Passport checks `user_id IS NULL` in `oauth_clients` - all clients created via `passport:install` or `passport:client` without `--user-id` are first-party.
- `is_active` check during silent SSO: cannot be performed at the Passport controller level. This is handled in `LoginService` (inactive users cannot log in) and in `CheckUserActive` middleware (token-based requests). If an active session exists but the user is deactivated post-login, an authorization code could still be issued — this is acceptable because token revocation on logout and `CheckUserActive` on API endpoints fully mitigate the impact.

---

## QA Response

> **Method**: Static code review.

- [x] User not logged in -> GET `/oauth/authorize` -> redirect to `/login`, intended URL preserved - Passport `AuthorizationController` built-in behavior
- [x] User already logged in (active session) -> GET `/oauth/authorize` directly -> redirect to `redirect_uri` with code - Passport silent SSO, `skipsAuthorization()` = true for first-party
- [x] Client is not first-party -> consent screen displayed - `Client::skipsAuthorization()` returns `false` -> Passport displays `oauth.authorize` view
- [x] Auth code from silent SSO is valid and exchangeable for tokens - Passport standard flow
- [x] `ConsentService::shouldAutoApprove()` exists and returns `$client->firstParty()`
- [x] If user `is_active = false` -> even with active session, authorization code is **not** issued - **BUG-002 FIXED**

  **Fix**: Create `CheckWebUserActive` middleware, register in `config/passport.php` middleware array.
  Middleware intercepts all Passport routes — if authenticated user has `is_active = false`: logout, invalidate session, redirect to `/login` with error message. Authorization code is never issued.

**Status: Done**
