# TASK-003: Local Register & Login (Blade)

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Create session-based registration and login flows using Blade. This is the user entry point before OAuth flows begin. After successful login, redirect the user to their intended URL (typically back to `/oauth/authorize`).

Depends on: TASK-002

---

## DEV Response

**Register:**
- [x] `RegisterController`: methods `show()` (GET /register) and `store()` (POST /register)
- [x] `RegisterService`: assigns default role "user", hashes password via model cast, persists user
- [x] Blade view `resources/views/auth/register.blade.php`: form fields for name, username, email, password, password_confirmation
- [x] Validation: name required, username unique/regex `[a-z0-9_]+`, email unique, password min 8 confirmed
- [x] After registration: auto-login and redirect to intended URL or `/`

**Login:**
- [x] `LoginController`: methods `show()` (GET /login) and `store()` (POST /login)
- [x] `LoginService`: attempts authentication, checks `is_active`, throws `ValidationException` on failure
- [x] Blade view `resources/views/auth/login.blade.php`: form fields for email, password, remember me
- [x] If `is_active = false`: reject login with message "Your account is inactive."
- [x] Rate limiting: `throttle:5,1` on POST /login
- [x] After login: redirect to `redirect()->intended('/')`
- [x] CSRF middleware active across all web routes (Laravel default)

**Logout:**
- [x] `LogoutController`: `destroy()` - `Auth::logout()`, invalidate session, regenerate CSRF token, redirect to `/login`

**Routes (web.php):**
- [x] `GET /login`, `POST /login` (throttle:5,1)
- [x] `GET /register`, `POST /register`
- [x] `POST /logout` -> `LogoutController@destroy` (auth middleware)
- [x] `guest` middleware for login and register

**Layout:**
- [x] `resources/views/layouts/auth.blade.php` - base layout with Tailwind CDN

---

## QA Response

> **Method**: Static code review.

- [x] Register with valid data -> user persisted, session created, redirect to `/` - `RegisterController::store()` calls `RegisterService::register()`, `Auth::login()`, `session()->regenerate()`, `redirect()->intended('/')`
- [x] Register with duplicate email -> validation error - `unique:users,email` rule in `RegisterController:26`
- [x] Register with existing username -> validation error - `unique:users,username` rule in `RegisterController:25`
- [x] Login with valid credentials -> session created, redirect to intended URL - `LoginService::attempt()` + `redirect()->intended('/')`
- [x] Login with incorrect password -> error message, no session created - `Auth::attempt()` failure -> `ValidationException` in `LoginService:18`
- [x] Login with `is_active = false` user -> error "Your account is inactive." - VERIFIED in `LoginService:26-30`: Auth::logout() called, ValidationException with exact message
- [x] POST /login failed more than 5 times in 1 minute -> HTTP 429 - `throttle:5,1` in `routes/web.php:10`
- [x] Missing CSRF token in POST form -> HTTP 419 - Laravel default CSRF middleware active
- [x] Access `/login` while authenticated -> redirect - `middleware('guest')` in route group `routes/web.php:8`
- [x] POST /logout -> session destroyed, redirect to `/login` - `LogoutController::destroy()`: `Auth::logout()`, `invalidate()`, `regenerateToken()`, `redirect()->route('login')`

**Additional checks:**
- [x] Controller is thin - logic resides in LoginService & RegisterService, not Controller
- [x] No `dd()` or debug code
- [x] `POST /register` has no throttle - SRS did not require it, acceptable for now

**Status: Done**
