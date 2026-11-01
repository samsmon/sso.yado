# TASK-009: Login Page Context - App Name Banner

Status: Done
Priority: Medium
Created: 2026-07-20 20:00
Request: Add context awareness to the login page. When users arrive from an OAuth flow (client_id in session), display a banner indicating the application requesting access. If users access /login directly without OAuth context, display an informational notice directing them to access via client applications.

---

## DEV Response

- [x] `LoginController::show()` - add `resolveClientName()` private method:
  - Read `session('url.intended')` - Passport preserves `/oauth/authorize?...` URL here on redirect to login
  - Parse `client_id` from query string via `parse_url` + `parse_str`
  - Query `oauth_clients.name` via `DB::table('oauth_clients')->where('id', $clientId)->value('name')`
  - Return `null` if no OAuth context or client not found
  - Pass `$clientName` (nullable string) to view
- [x] `resources/views/auth/login.blade.php` - conditional UI:
  - `$clientName` present -> blue banner: *"You are signing in to [Client Name]. Please log in to continue."*
  - `$clientName` null -> neutral notice: *"To sign in to Yado.my.id ecosystem applications, please access through the respective application."*
- [x] Page subtitle updates accordingly: *"Sign in to continue to [Client Name]"* vs *"SSO Engine"*
- [x] No changes required in Service/Action layer

**Notes:**
- Passport does not preserve `authRequest` in session while guest (only preserved post-authentication). On redirect to login, only `url.intended` is available — which suffices because `client_id` exists in the query string of `/oauth/authorize`.
- If `client_id` is missing from DB or null -> falls back to regular notice without errors.

---

## QA Response

> **Method**: Static code review.

- [x] GET `/login` via OAuth flow -> session `url.intended` contains `/oauth/authorize?client_id=...` -> `resolveClientName()` parses URL -> queries `oauth_clients.name` -> passes `$clientName` to view (`LoginController:41-61`)
- [x] Blade: `$clientName` present -> banner displayed - `login.blade.php:7-11`
- [x] GET `/login` directly (no OAuth context) -> `url.intended` empty -> `$clientName = null` -> neutral notice displayed - `login.blade.php:12-17`
- [x] Page subtitle: `"Sign in to continue to {$clientName}"` vs `'SSO Engine'` - `login.blade.php:4`
- [x] Post-login from OAuth flow -> `redirect()->intended($default)` routes to `/oauth/authorize` -> silent SSO remains operative (`LoginController:38`)
- [x] Client not found in DB -> `DB::table(...)->value('name')` returns `null` -> fallback notice, no 500 error
- [x] No `dd()` or debug code

**Status: Done**
