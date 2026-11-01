# TODO - SSO Engine

Informal notes, backlog items, and decisions to be made. Not a replacement for tickets — once clear enough, create a TASK in docs/tickets/.

---

## Immediate (Pre-Deploy)

- [x] Ran `composer update`, `composer.lock` is in sync — replaced google2fa-laravel with `pragmarx/google2fa` (framework-agnostic) because its Laravel wrapper did not yet support this project's Laravel version
- [x] Local migrations run cleanly (`settings`, `audit_logs`, avatar/2FA columns, theme/locale, backfilling verification for legacy users)
- [x] Basic local smoke tests — 66 routes loaded without errors, public pages (`/`, `/login`, `/register`, `/forgot-password`, `/reset-password`, `/register/invite`, `/two-factor-challenge`, `/up`) all return valid responses after `view:clear` (earlier hit 500 due to stale compiled view cache from before Flux removal — not a code bug, resolved)
- [ ] **Interactive runtime verification** (requires browser, cannot be tested via curl alone): register → verify email → enable 2FA (scan QR) → log in using TOTP code; upload avatar; `/dashboard/settings`; `/dashboard/audit-log`
- [ ] Runtime verification of full cross-app OAuth flows over actual HTTP (not yet performed)
- [ ] SMTP and S3 in Settings have not been tested with live send/upload (requires real credentials)
- [ ] Setup Resend: verify domain `yado.my.id` on Cloudflare + Resend dashboard, fill `RESEND_API_KEY` in production `.env` (or via dashboard Settings now)
- [ ] Migrate + first deploy to **production server** (only tested locally, not on server)

## Post-Deploy

- [ ] Register first client app (Malas or Scribe) via dashboard
- [ ] Test end-to-end SSO across apps in staging/production
- [ ] Verify user invite email functions (Resend live)
- [ ] Verify forgot password email is delivered and link is valid

## Decisions Made & Pending

- ~~Avatar storage: local disk or S3?~~ → **Decided**: both supported, admin chooses via `/dashboard/settings`
- ~~Email verification after register~~ → **Decided**: mandatory, implemented
- Refresh token: on the client app side, implement auto-refresh or handle 401 on-demand?

## Theming & i18n - Status

Theming infrastructure (system/light/dark) and multilanguage (id/en/ja) are fully operational: `theme`/`locale` migrations on `users`, `SetLocale` middleware, toggle buttons in all layouts, lang files `lang/{id,en,ja}/*.php`, custom Alpine dropdown (not native `<select>`) used consistently across all filters/selectors.

All application pages have been converted (strings + `dark:` classes): 4 layouts, auth (login, register, forgot-password, reset-password, invite), oauth authorize, account (show, sessions), dashboard (index, applications CRUD, users, sessions, logs), invitation email.

**Remaining (nice-to-have, non-blocker):**

- [ ] Default Laravel validation messages (requires `php artisan lang:publish` then translating `lang/{id,en,ja}/validation.php`) — currently uses framework default English
- [ ] Default Laravel pagination view lacks `dark:` classes (styling defaults to light color even when dark mode is active) — requires `php artisan vendor:publish --tag=laravel-pagination` and adding dark classes manually
- [ ] Invitation emails are sent using the session locale of the inviting superadmin, not the recipient's locale (recipient does not have an account yet so no preference exists) — reasonable for now

## New Features (Implemented, Pending Runtime Testing)

- [x] **Admin Settings** (`/dashboard/settings`) — mail (Resend/SMTP) and avatar storage (local/S3) are now configurable from the dashboard rather than hardcoded in `.env`. Legacy `.env` data is automatically migrated to the `settings` table once during initial deployment. Includes a "Send Test Email" button.
- [x] **Mandatory email verification** — new users must click the verification link before accessing `/account`, `/dashboard`, or proceeding with the OAuth flow (`/oauth/authorize`). Users created via invite or seeder are automatically verified (already vouched).
- [x] **Avatar upload** (`/account`) — uploads to the disk configured in Settings (local or S3). The full avatar URL is stored so it remains valid if the disk is changed later.
- [x] **My Devices** (`/account/sessions`) — users can view all browsers/devices logged into SSO (not just OAuth tokens of other apps), with device labels (e.g. "Chrome on Windows"), and can log out a specific device or all other devices at once.
- [x] **Audit Log** (`/dashboard/audit-log`) — records security events & data modifications (login/logout, change password, role changes, invites, OAuth client CRUD, etc. — not every page view). Separate from `/dashboard/logs` (raw application logs).
- [x] **Two-Factor Authentication / TOTP** (`/account/two-factor`) — compatible with Google Authenticator, Microsoft Authenticator, or any TOTP app. Optional per user, includes recovery codes (8 single-use codes).
- [x] Cleanup: removed `resources/views/flux/` (dead code, ~400 files, prohibited in CLAUDE.md) and `welcome.blade.php` (never rendered)

**Implementation notes to verify during runtime testing:**
- Exact API of `pragmarx/google2fa-laravel` (`generateSecretKey`, `getQRCodeUrl`, `verifyKey`) is assumed to match standard documentation — pending runtime validation because PHP was not in the environment during coding
- `php artisan storage:link` runs automatically in `deploy.sh` and Docker entrypoint for the local avatar disk — ensure the symlink is created properly
- SMTP as an alternative to Resend in Settings has not been tested with live email delivery
- S3 as avatar storage has not been tested with live uploads (requires bucket + real credentials)

## Backlog (P2/P3)

- [ ] Webhook notification to client apps when a user is deactivated
- [ ] Social login (Google, GitHub) — not MFA, but primary login using Google/GitHub accounts
- [ ] OpenID Connect Discovery endpoint (`/.well-known/openid-configuration`)

## Notes

- Valid roles: `user` and `superadmin` only — the `admin` role has been removed (migration + seeder updated)
- Current ecosystem: Malas, Scribe — both will be OAuth clients
- For integrating new client apps: see `docs/INTEGRATION.md` or `docs/AI_INTEGRATION.md`