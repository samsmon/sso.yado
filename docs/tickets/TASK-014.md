# TASK-014: Dashboard - Applications (OAuth Client Management)

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Create an OAuth client application management interface in the superadmin dashboard. Superadmins can view registered clients, register new clients, inspect client_id & client_secret, edit name/redirect URI, and delete/revoke clients without relying on tinker or database GUIs.

Depends on: TASK-013

---

## DEV Response

- [x] `GET /dashboard/applications` -> `ApplicationController::index()` - `app/Http/Controllers/Dashboard/ApplicationController.php`
- [x] `GET /dashboard/applications/create` -> `ApplicationController::create()`
- [x] `POST /dashboard/applications` -> `ApplicationController::store()` - delegates to `ApplicationService::create()`, redirects to show with `->with('new_secret', $client->plainSecret)`
- [x] `GET /dashboard/applications/{id}` -> `ApplicationController::show()`
- [x] `PATCH /dashboard/applications/{id}` -> `ApplicationController::update()` - validation `name required`, `redirect_uri url|max:1000`
- [x] `DELETE /dashboard/applications/{id}` -> `ApplicationController::destroy()` - delegates to `ApplicationService::delete()`
- [x] `ApplicationService` - `list()`: `Client::orderByDesc('created_at')->get()`, `create()`: `ClientRepository::create(userId: null, ...)`, `update()`: `$client->update(['name', 'redirect'])`, `delete()`: revokes all tokens + refresh tokens for client then `$clients->delete($client)` - `app/Services/Dashboard/ApplicationService.php`
- [x] Client secret flashed via `session('new_secret')` - displayed once on creation with amber warning callout
- [x] Views: `dashboard/applications/index.blade.php` (table), `create.blade.php` (form), `show.blade.php` (credentials + edit form)

---

## QA Response

> **Method**: Static code review.

- [x] GET `/dashboard/applications` -> `ApplicationService::list()` returns all clients
- [x] Add client: `ApplicationService::create()` uses `ClientRepository::create(userId: null, ...)` -> first-party client
- [x] `store()` redirects to show route with `->with('new_secret', $client->plainSecret)` - secret only exists in session flash (displayed once) (`ApplicationController:38`)
- [x] Refreshing show page -> flashed `new_secret` cleared -> secret no longer displayed (one-time session flash)
- [x] Edit: validation `name required`, `redirect_uri url|max:1000` - invalid URL triggers validation error (`ApplicationController:31`)
- [x] Delete: `ApplicationService::delete()` iterates `$client->tokens`, revokes + refreshToken, then `clients->delete($client)`
- [x] All /dashboard/applications routes in `auth` + `superadmin` middleware group
- [x] Views: index, create, show exist

**Status: Done**
