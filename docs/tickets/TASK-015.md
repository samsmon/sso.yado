# TASK-015: Dashboard - User Management & Invite via Email

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Create a user management page in the superadmin dashboard. Superadmins can view all users, activate/deactivate accounts, assign roles, and invite new users via email. Invitations send a single-use link (expiring in 24 hours) for the recipient to set their password and complete their profile.

Depends on: TASK-013

---

## DEV Response

**User List & Management:**
- [x] `GET /dashboard/users` -> `UserManagementController::index()` - `UserManagementService::list()` paginate(20) with('role') - `app/Http/Controllers/Dashboard/UserManagementController.php`
- [x] `PATCH /dashboard/users/{id}/toggle-active` -> `UserManagementController::toggleActive()` - self-deactivation safeguard in `UserManagementService::toggleActive()` (aborts 422 if actor = target)
- [x] `PATCH /dashboard/users/{id}/role` -> `UserManagementController::assignRole()` - `Role::findOrFail($roleId)` + `$user->update(['role_id'])`
- [x] `UserManagementService` - `list()`, `toggleActive()`, `assignRole()` - `app/Services/Dashboard/UserManagementService.php`

**Invite Flow:**
- [x] Migration `user_invitations` - columns: `id`, `email`, `token` (unique), `role_id` (FK), `used_at` (nullable), `expires_at`, `created_by` (FK users.id), `timestamps`
- [x] `GET /dashboard/users/invite` -> `UserManagementController::invite()` - form for email + select role
- [x] `POST /dashboard/users/invite` -> `UserManagementController::sendInvite()` - validates `email unique:users,email`, delegates to `InviteUserAction`
- [x] `InviteUserAction::execute()` - generates token `Str::random(64)`, persists to `user_invitations` expiring in 24h, sends `InvitationMail` - `app/Actions/Dashboard/InviteUserAction.php`
- [x] `InvitationMail` - Mailable with link `route('invite.show', ['token' => $token])` - `app/Mail/InvitationMail.php`
- [x] `GET /register/invite?token=xxx` -> `InviteController::show()` - validates token via `resolveInvitation()`: present in DB + `used_at IS NULL` + `expires_at->isFuture()` - `app/Http/Controllers/Auth/InviteController.php`
- [x] `POST /register/invite` -> `InviteController::store()` - creates user (email + role from invitation), `used_at = now()`, auto-login via `Auth::login()`, redirects to `/account`
- [x] `resources/views/auth/invite.blade.php` - form for name, username, disabled email, password + confirmation (visibility toggles), error callout if invalid token, info callout with invitation email

---

## QA Response

> **Method**: Static code review.

- [x] GET `/dashboard/users` -> `UserManagementService::list()` with paginate(20) + with('role')
- [x] Toggle active status -> `toggleActive()` checks self-deactivation (`user->id === actor->id` -> abort 422) (`UserManagementService:17-19`)
- [x] Superadmin cannot deactivate themselves -> safeguard verified in `toggleActive()`
- [x] Assign role -> `assignRole()` uses `Role::findOrFail($roleId)` + `update(['role_id'])`
- [x] Invite: `InviteUserAction` creates invitation (64-character token, expires 24h), sends `InvitationMail`
- [x] Invite email validation: `unique:users,email` - cannot invite an already registered email (`UserManagementController:55`)
- [x] GET `/register/invite?token=valid` -> `InviteController::show()` calls `resolveInvitation()` -> `isValid()` = `used_at IS NULL && expires_at->isFuture()`
- [x] Missing token -> "Invalid invitation link." (`InviteController:74`)
- [x] Already used token -> "Invitation link has already been used." (`InviteController:84`)
- [x] Expired token -> "Invitation link has expired." (`InviteController:88`)
- [x] Valid submission -> `User::create()` with email from invitation, `role_id` from invitation, `used_at = now()`, auto-login, redirects to `/account`
- [x] Invitation link cannot be reused - `resolveInvitation()` checks `isValid()` in `store()` as well
- [x] Dashboard routes reside in `superadmin` middleware group
- [x] Migration `user_invitations` contains all necessary columns
- [x] Views: dashboard/users/index.blade.php, dashboard/users/invite.blade.php, auth/invite.blade.php exist

**Status: Done**
