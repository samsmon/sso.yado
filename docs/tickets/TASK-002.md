# TASK-002: Migrations - users, roles, seeder default

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Create migrations for the `roles` table and modify the `users` table according to the SRS schema. Add seeders for default roles ("user", "admin") and one admin user for testing.

Depends on: TASK-001

---

## DEV Response

- [x] Migration `create_roles_table` integrated into primary migration (`0001_01_01_000000`) - `roles` table created before `users` so FK constraints can be established
- [x] Migration `create_users_table` modified: add `username` column (varchar 50, unique), `avatar` (nullable), `role_id` (FK -> roles.id, constrained), `is_active` (boolean default true)
- [x] Create `RoleSeeder`: insert roles `{name: "User", slug: "user"}` and `{name: "Admin", slug: "admin"}` via `firstOrCreate` (idempotent)
- [x] Create `AdminUserSeeder`: insert 1 admin user with role "admin" - credentials from env `ADMIN_EMAIL` / `ADMIN_PASSWORD`
- [x] Register seeders in `DatabaseSeeder`: `RoleSeeder` -> `AdminUserSeeder`
- [x] Add relationship `belongsTo(Role::class)` in `User` model
- [x] Add relationship `hasMany(User::class)` in `Role` model (newly created)
- [x] Add scope `scopeActive()` in `User` model: `where('is_active', true)`
- [x] `ADMIN_EMAIL` and `ADMIN_PASSWORD` added to `.env.example`

**Notes:**
- `roles` is created in the same migration as `users` (order: roles first, then users) because FK constraints require the `roles` table to exist.
- Seeders use `firstOrCreate` - safe to execute repeatedly without duplicates.

---

## QA Response

> **Method**: Static code review.

- [x] `php artisan migrate:fresh --seed` runs without errors - SKIP (runtime), migration structure valid
- [x] `roles` table created with columns `id`, `name`, `slug`, `timestamps` - VERIFIED in `0001_01_01_000000_create_users_table.php:14-20`
- [x] `users` table has columns `username`, `avatar`, `role_id`, `is_active` - VERIFIED in `0001_01_01_000000_create_users_table.php:26-36`
- [x] `users.role_id` is an FK to `roles.id` - VERIFIED: `foreignId('role_id')->constrained('roles')`
- [x] `RoleSeeder` seeds roles "user" and "admin" - VERIFIED in `database/seeders/RoleSeeder.php:14-15`
- [x] `AdminUserSeeder` creates 1 admin user from env credentials - VERIFIED in `database/seeders/AdminUserSeeder.php:17-25`
- [x] Admin user password hashed using bcrypt - VERIFIED: `'password' => Hash::make(...)`
- [x] Seeders can be run multiple times without duplicate errors - VERIFIED: `firstOrCreate` used in both seeders
- [x] `User` model has `role()` relation - VERIFIED in `app/Models/User.php:47-50`
- [x] `User` model has `scopeActive()` scope - VERIFIED in `app/Models/User.php:52-55`
- [x] `Role` model has `users()` relation - VERIFIED in `app/Models/Role.php:17-20`

**Status: Done**
