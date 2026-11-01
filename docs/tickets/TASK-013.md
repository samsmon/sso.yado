# TASK-013: Superadmin Dashboard - Gate & Layout

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Set up the foundation for the superadmin dashboard — middleware gate for superadmin role access, dashboard layout, and index page. Prerequisite for TASK-014 and TASK-015.

Depends on: TASK-002

---

## DEV Response

- [x] Role `superadmin` in `RoleSeeder` - `database/seeders/RoleSeeder.php:14`
- [x] `EnsureSuperadmin` middleware - checks `$request->user()->role?->slug === 'superadmin'` -> abort(403) otherwise - `app/Http/Middleware/EnsureSuperadmin.php`
- [x] Alias `superadmin` registered in `bootstrap/app.php:21`
- [x] Route group `/dashboard` with middleware `['auth', 'superadmin']` in `routes/web.php`
- [x] `DashboardController::index()` - passes `$stats` (users_active, users_total, clients count) + `$clients` list to view - `app/Http/Controllers/Dashboard/DashboardController.php`
- [x] `resources/views/layouts/dashboard.blade.php` - fixed sidebar with navigation (Overview, Applications, Users) + inline Heroicons SVG, mobile hamburger menu via Alpine.js, mobile topbar, sign out form
- [x] `resources/views/dashboard/index.blade.php` - 3-column stats grid + quick actions card

---

## QA Response

> **Method**: Static code review.

- [x] GET `/dashboard` without authentication -> redirect to `/login` - route inside `auth` + `superadmin` middleware group
- [x] GET `/dashboard` with `user`/`admin` role -> HTTP 403 - `EnsureSuperadmin`: `role->slug !== 'superadmin'` -> abort(403) (`EnsureSuperadmin.php:15`)
- [x] Superadmin middleware alias registered in `bootstrap/app.php:21`
- [x] `superadmin` role exists in `RoleSeeder:14`
- [x] `DashboardController::index()` passes `stats` (users_active, users_total, clients) and `clients` list to view
- [x] View `dashboard/index.blade.php` exists
- [x] Layout `dashboard.blade.php` exists
- [x] `EnsureSuperadmin` uses optional chaining `role?->slug` - safe if user lacks a role

**Status: Done**
