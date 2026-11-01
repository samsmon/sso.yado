# TASK-016: Register - Optional Full Name (Fallback to Username)

Status: Done
Priority: Low
Created: 2026-07-20 21:30
Request: Make the "name" field in the registration form optional. If users do not provide a full name, the system automatically uses their username as their display name. Minor adjustment in validation and service layers.

Depends on: TASK-003

---

## DEV Response

- [x] `RegisterController`: change `name` validation from `required` -> `nullable|string|max:255` - `app/Http/Controllers/Auth/RegisterController.php:24`
- [x] `RegisterService`: add fallback logic `filled($data['name'] ?? null) ? $data['name'] : $data['username']` - `app/Services/Auth/RegisterService.php:16`
- [x] `resources/views/auth/register.blade.php`: label "Full Name" + `<span>` badge "optional", placeholder "Leave blank to use username"
- [x] Also: redirect post-registration to `/account` (user) or `/dashboard` (superadmin), matching post-login behavior - `RegisterController:32-35`

---

## QA Response

> **Method**: Static code review.

- [x] Registration with full name provided -> `filled($data['name'] ?? null)` true -> persists `$data['name']` (`RegisterService:16`)
- [x] Registration with empty full name (`name=''`) -> `filled('')` false -> falls back to `$data['username']`
- [x] Registration with whitespace-only full name -> `filled('   ')` false (Laravel `blank()` treats whitespace-only as blank) -> falls back to username
- [x] Other validation rules unaffected - `nullable` only alters `name`, rules for username/email/password remain unchanged
- [x] View: label "Full Name" + badge "optional" + placeholder "Leave blank to use username" (`register.blade.php:12-18`)
- [x] Post-registration redirect: `route('account.show')` or `route('dashboard.index')` for superadmin (`RegisterController:36-40`)

**Status: Done**
