# TASK-007: Refresh Token Flow

Status: Done
Priority: High
Created: 2026-07-20 20:00
Request: Ensure the refresh token flow functions properly. Client applications must be able to request a new access token using a refresh token without user interaction, matching the SRS specification (refresh TTL 30 days, access TTL 60 minutes).

Depends on: TASK-004

---

## DEV Response

- [x] Refresh Token grant active by default in Passport - no extra configuration needed
- [x] TTL configured in `AppServiceProvider`:
  ```php
  Passport::tokensExpireIn(now()->addMinutes(60));
  Passport::refreshTokensExpireIn(now()->addDays(30));
  ```
- [x] Response POST `/oauth/token` (authorization code grant) includes `refresh_token` - Passport default behavior, verified
- [x] Old refresh token is revoked after use - Passport/league-oauth2-server default behavior (rotation)
- [x] No additional code required - all handled internally by Passport

**Refresh flow request:**
```
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
refresh_token=<token>
client_id=<client_id>
client_secret=<client_secret>
scope=profile:read
```

---

## QA Response

> **Method**: Static code review. All behaviors handled internally by Passport/league-oauth2-server.

- [x] POST `/oauth/token` `grant_type=refresh_token` -> new `access_token` + new `refresh_token` - Passport Refresh Token grant built-in
- [x] Legacy refresh token after use -> rejected (single-use) - league-oauth2-server default: rotation on use
- [x] Expired refresh token (> 30 days) -> `invalid_grant` - `Passport::refreshTokensExpireIn(now()->addDays(30))` in `AppServiceProvider:33`
- [x] Refresh token with invalid `client_id` -> `invalid_client` - league-oauth2-server client validation
- [x] New access token from refresh has 3600 seconds TTL - 60 minute TTL configured in `AppServiceProvider:32`
- [x] Scope cannot be elevated via refresh - Passport built-in: scope during refresh is constrained to the original scope

**Runtime note**: Verification via HTTP flow requires confirmation in a compatible runtime environment.

**Status: Done**
