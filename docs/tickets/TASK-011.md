# TASK-011: My Account - Account Info & Change Password

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Create a self-service `/account` page for authenticated users. Contains account info (name, username, email, avatar, role, verification status) and a change password form. This is the user's personal area, separate from the admin dashboard.

Depends on: TASK-003

---

## DEV Response

- [x] Route group `/account` in `routes/web.php` with `auth` middleware - `routes/web.php:41`
- [x] `GET /account` -> `AccountController::show()` - passes `$user = $request->user()` to view - `app/Http/Controllers/Account/AccountController.php`
- [x] `POST /account/password` -> `AccountController::changePassword()` - validates and delegates to `ChangePasswordAction`
- [x] `ChangePasswordAction::execute()` - `Hash::check()` current password, ensures difference from new password, `$user->update(['password' => $newPassword])` (model hashed cast) - `app/Actions/Account/ChangePasswordAction.php`
- [x] `resources/views/account/show.blade.php` - avatar initials, info grid (email, role badge, email verification badge, account status badge), 3-field password change form with visibility toggles (Alpine.js x-data)
- [x] Layout `resources/views/layouts/account.blade.php` - desktop sidebar + mobile tab navigation (My Account / Active Sessions), sign out form

---

## QA Response

> **Method**: Static code review.

- [x] GET `/account` without authentication -> redirect to `/login` - route in `auth` middleware group (`routes/web.php:41`)
- [x] GET `/account` with authentication -> displays info for current user - `AccountController::show()` passes `$request->user()` to view
- [x] View `account/show.blade.php` exists
- [x] Change password with valid `current_password` + valid `new_password` -> update - `ChangePasswordAction::execute()`: `Hash::check()` passes -> `$user->update(['password' => $newPassword])` (model hash cast)
- [x] Change password with incorrect `current_password` -> ValidationException "Current password is incorrect." (`ChangePasswordAction:13-17`)
- [x] `new_password` identical to `current_password` -> ValidationException "New password cannot match current password..." (`ChangePasswordAction:19-23`)
- [x] `new_password` min:8 - validated in `AccountController:28`
- [x] Controller is thin - delegates to `ChangePasswordAction`
- [x] No `dd()`

**Status: Done**
