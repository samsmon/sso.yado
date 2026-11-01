# TASK-012: My Account - Active Sessions & Revoke Device

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Add an active sessions page at `/account/sessions`. Users can view active sessions/tokens (device, login time, last used) and revoke sessions from unrecognized devices, utilizing Passport's existing `oauth_access_tokens` table.

Depends on: TASK-011

---

## DEV Response

- [x] `GET /account/sessions` -> `AccountController::sessions()` - queries `$user->tokens()->where('revoked', false)->where('expires_at', '>', now()->toDateTimeString())->with('client')->orderByDesc('created_at')->get()` - `AccountController.php:49-54`
- [x] `DELETE /account/sessions/{tokenId}` -> `AccountController::revokeSession()` - delegates to `RevokeSessionAction`
- [x] `DELETE /account/sessions` (revoke all) -> `AccountController::revokeAll()` - loops over user active tokens, revoking each
- [x] `RevokeSessionAction::execute()` - verifies token belongs to user via `$user->tokens()->where('id', $tokenId)->first()` -> abort(403) if not owned, then `$token->revoke()` + `$token->refreshToken?->update(['revoked' => true])` - `app/Actions/Account/RevokeSessionAction.php`
- [x] `resources/views/account/sessions.blade.php` - empty state when no tokens exist, token list with client name (`client?->name ?? 'Unknown App'`), created time, expiry, scope badges, per-token Revoke button + Revoke All in header

---

## QA Response

> **Method**: Static code review.

- [x] GET `/account/sessions` -> queries `tokens()->where('revoked', false)->where('expires_at', '>', now())` - only active tokens displayed (`AccountController:49-54`)
- [x] Expired or revoked tokens do not appear - filtered by query
- [x] Revoke session -> `RevokeSessionAction::execute()` calls `$token->revoke()` + `refreshToken?->update(['revoked' => true])`
- [x] Tokens belonging to other users rejected - `$user->tokens()->where('id', $tokenId)->first()` -> abort(403) if null (`RevokeSessionAction:13-16`)
- [x] "Revoke All" -> `revokeAll()` revokes all OAuth tokens; web session cookies untouched
- [x] Route inside `auth` middleware group
- [x] `refreshToken` (singular, hasOne) - Passport 13 compatible
- [x] View `account/sessions.blade.php` exists

**Status: Done**
