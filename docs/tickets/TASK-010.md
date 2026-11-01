# TASK-010: Landing Page (/)

Status: Done
Priority: Medium
Created: 2026-07-20 21:00
Request: Create a public landing page at root URL (/) explaining the SSO Engine for yado.my.id. This is the first page visitors see — not a login form or dashboard. Must be accessible without authentication, with links to /login and /register.

---

## DEV Response

- [x] Route `GET /` in `routes/web.php:17` - no auth middleware, publicly accessible
- [x] `HomeController::index()` - redirect authenticated users (superadmin -> `dashboard.index`, user -> `account.show`), guest -> return view `home` - `app/Http/Controllers/HomeController.php`
- [x] `resources/views/home.blade.php` - extends `layouts.public`, hero + 4 feature cards (SSO, OAuth2+PKCE, Self-service registration, Secure Token)
- [x] `resources/views/layouts/public.blade.php` - base layout in pure Tailwind + Alpine.js via Vite, conditional navbar (Dashboard/My Account/Sign Out if logged in, Sign In/Register if guest)
- [x] Conditional CTAs: superadmin -> "Open Dashboard", standard user -> "View My Account", guest -> "Sign In" + "Register Now"
- [x] Authenticated users redirect to `account.show` (user) or `dashboard.index` (superadmin) - fixed via BUG-004, `HomeController.php`

---

## QA Response

> **Method**: Static code review.

- [x] GET `/` without authentication -> landing page displays, no redirect (`routes/web.php:17`)
- [x] GET `/` while authenticated as user -> redirect to `/account` - fixed via BUG-004
- [x] GET `/` while authenticated as superadmin -> redirect to `/dashboard` - fixed via BUG-004
- [x] Button "Sign In" -> `route('login')` (`home.blade.php`)
- [x] Button "Register Now" -> `route('register')` (`home.blade.php`)
- [x] No auth middleware on route `/`
- [x] No sensitive data on page

**Status: Done**
