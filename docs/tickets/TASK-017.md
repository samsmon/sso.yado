# TASK-017: Forgot Password via Email (Resend)

Status: Done
Priority: Medium
Created: 2026-07-20 21:30
Request: Implement a forgot password flow using Laravel built-in password resets with Resend as the email driver. User enters email -> receives reset link via email -> clicks link -> sets new password. Single-use link expiring in 60 minutes.

---

## DEV Response

**Setup Resend:**
- [x] `composer require resend/resend-laravel` - installed, package:discover DONE
- [x] `.env.example` updated: `MAIL_MAILER=resend`, `MAIL_FROM_ADDRESS=noreply@yado.my.id`, `MAIL_FROM_NAME="SSO Yado.my.id"`, `RESEND_API_KEY=re_xxx`
- [ ] Verify domain `yado.my.id` on Resend dashboard (Cloudflare DNS record) - runtime step, performed on server

**Password Reset Flow (Laravel built-in):**
- [x] Migration `password_reset_tokens` already exists (Laravel default) - confirmed via `config/auth.php:103`
- [x] `ForgotPasswordController` - `show()` GET /forgot-password, `store()` POST /forgot-password via `Password::sendResetLink()` - `app/Http/Controllers/Auth/ForgotPasswordController.php`
- [x] `ResetPasswordController` - `show()` GET /reset-password?token&email, `store()` POST /reset-password via `Password::reset()` + event `PasswordReset` - `app/Http/Controllers/Auth/ResetPasswordController.php`
- [x] Routes in `routes/web.php` (inside `guest` middleware group):
  - `GET /forgot-password` -> `password.request`
  - `POST /forgot-password` -> `password.email` (throttle:3,1)
  - `GET /reset-password` -> `password.reset`
  - `POST /reset-password` -> `password.update`
- [x] `resources/views/auth/forgot-password.blade.php` - email form + success callout via `session('status')`
- [x] `resources/views/auth/reset-password.blade.php` - password + password_confirmation form, token + email hidden
- [x] Link "Forgot password?" in `login.blade.php` -> route `password.request`
- [x] `config/auth.php` `passwords.users.expire = 60` - already 60 (Laravel default)
- [x] Throttle `throttle:3,1` on POST `/forgot-password`

**Notes:**
- `Password::reset()` in `ResetPasswordController::store()` uses `$user->forceFill(['password' => $password])`. `forceFill` bypasses fillable guards but honors model casts. The `'password' => 'hashed'` cast is applied during `$user->save()`. Therefore `Hash::make()` is not needed.
- `RESEND_API_KEY` must exist in production `.env` before forgot password can be tested at runtime.

---

## QA Response

> **Method**: Static code review. Runtime items (email delivery) marked SKIP - requires `RESEND_API_KEY` on server.

- [x] GET `/forgot-password` -> `ForgotPasswordController::show()` returns view `auth.forgot-password`
- [x] POST `/forgot-password` registered email -> `Password::sendResetLink()`; `ForgotPasswordController::store()` always returns `back()->with('status', ...)` - does not reveal email existence
- [x] POST `/forgot-password` unregistered email -> identical success message (email enumeration prevented)
- [x] POST `/forgot-password` throttle:3,1 - `routes/web.php:31`
- [x] `ResetPasswordController::show()` passes token + email from query string to view
- [x] `ResetPasswordController::store()` validation: token required, email valid email, password min:8 confirmed
- [x] Password reset: `$user->forceFill(['password' => $password])` - `forceFill` bypasses fillable but applies `hashed` model cast upon `$user->save()` -> no double-hashing
- [x] Post-reset -> redirect `route('login')` with `status` flash (`ResetPasswordController:42`)
- [x] Single-use token expiring in 60 minutes - Laravel `password_reset_tokens` default, `passwords.users.expire = 60`
- [x] Link "Forgot password?" in `login.blade.php:52` -> `route('password.request')`
- [x] Routes in `guest` middleware group
- [x] SKIP - email delivery (requires RESEND_API_KEY + domain verification on Resend)

**Status: Done** (runtime email test pending server setup)
