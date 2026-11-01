# TASK-006: Userinfo Endpoint (GET /api/user)

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Create the `GET /api/user` endpoint returning the user profile corresponding to a Bearer access token. This endpoint is used by all client applications to fetch user data after receiving an access token. The response format must adhere to the API contract in the SRS.

Depends on: TASK-004

---

## DEV Response

- [x] Create `UserController` in `app/Http/Controllers/Api/UserController.php`
  - Method `show(Request $request): UserResource`
  - Retrieve user from `$request->user()`, load `role` relationship
  - Return `UserResource`
- [x] Route in `routes/api.php`:
  ```php
  Route::middleware(['auth:api', 'scope:profile:read', 'check.user.active'])
      ->get('/user', [UserController::class, 'show']);
  ```
- [x] Create `UserResource` in `app/Http/Resources/UserResource.php` - structure matches SRS API contract (id, name, username, email, avatar, role{id,name,slug})
- [x] Register `routes/api.php` in `bootstrap/app.php` via `->withRouting(api: ...)`
- [x] Register middleware aliases in `bootstrap/app.php`:
  - `scope` -> `CheckForAnyScope` (Passport)
  - `scopes` -> `CheckScopes` (Passport)
  - `check.user.active` -> `CheckUserActive`
- [x] Create `CheckUserActive` middleware: if `is_active = false` -> return 401

**Response shape:**
```json
{
  "id": 1,
  "name": "...",
  "username": "...",
  "email": "...",
  "avatar": null,
  "role": { "id": 1, "name": "User", "slug": "user" }
}
```

---

## QA Response

> **Method**: Static code review.

- [x] GET `/api/user` without token -> HTTP 401 `{"message": "Unauthenticated."}` - `auth:api` middleware + `shouldRenderJsonWhen` in `bootstrap/app.php:24` ensures JSON responses for `api/*`
- [x] GET `/api/user` with expired token -> HTTP 401 - Passport `auth:api` guard rejects expired tokens
- [x] GET `/api/user` with valid token having `profile:read` scope -> HTTP 200 with all fields matching SRS - `UserResource` returns: id, name, username, email, avatar, role{id,name,slug}. `withoutWrapping()` active -> no `data` wrapper, matching SRS
- [x] Response does not leak `password` or `remember_token` - both in `$hidden` in `User.php:28-31`, omitted from `UserResource::toArray()`
- [x] GET `/api/user` with scope other than `profile:read` -> HTTP 403 - `scope:profile:read` middleware alias to `CheckForAnyScope` in `bootstrap/app.php:19`
- [x] User `is_active = false` after token issuance -> GET `/api/user` returns 401 - `CheckUserActive` middleware: `!$user->is_active` -> `response()->json(['message' => 'Unauthenticated.'], 401)`
- [x] Null avatar -> field `avatar` returns `null` - `$this->avatar` in `UserResource`, column is nullable with no string coercion

**Additional checks:**
- [x] Controller is thin: `UserController::show()` only loads relation + returns resource
- [x] Route `check.user.active` alias registered in `bootstrap/app.php:18`
- [x] `scope` alias -> `CheckForAnyScope`, `scopes` alias -> `CheckScopes` - both registered

**Status: Done**
