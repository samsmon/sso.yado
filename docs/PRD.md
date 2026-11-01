# PRD - SSO Engine (sso.yado.my.id)

## Problem Statement

The Yado project consists of several microservices and apps (currently: Malas, Scribe) that each need authentication. Without a centralized identity provider, every app has to maintain its own auth stack - duplicated code, users must log in again per app, and session management is scattered. SSO Engine is one of Yado's microservices that solves this by providing a single OAuth2 authentication point for the entire Yado.my.id ecosystem.

## Target Users

**End users** - individuals who have a yado.my.id account and access one or more apps in the ecosystem.

**Superadmin** - the ecosystem manager who manages user accounts and registered OAuth client apps.

**Client app developers** - the internal team building new apps in the ecosystem who need auth integration.

## Scope

This project builds the Central SSO Engine (`sso.yado.my.id`) along with its management dashboard. The client apps (Malas, Scribe, etc.) are **consumers** of this SSO - they are not built here. The OAuth2 + PKCE architecture was chosen because the ecosystem will keep growing and each new app only needs to register as an OAuth client.

## Core User Stories

### End User
- I want to log in once at sso.yado.my.id, so I can enter every app in the ecosystem without logging in again.
- I want to register a new account directly at sso.yado.my.id, without having to open Malas or Scribe first.
- When my session is still active and a client app redirects to SSO, I want to be passed straight on to the app without entering my password again (silent SSO).
- I want to see my account profile page - account info, verification status, change password, and the list of active sessions.
- I want to be able to revoke sessions from unfamiliar devices I do not recognize.

### Superadmin
- I want to view and manage all user accounts - activate/deactivate, assign roles.
- I want to invite new users via email (rather than self-registration).
- I want to manage registered OAuth client apps - add, edit, view credentials (client_id/secret), delete.
- I want to be able to revoke a specific OAuth client without touching the database.

### Client App
- I want to be able to request userinfo (name, email, avatar, role) using a valid access token.

---

## Features

### P0 - Done ✓

- **Local register**: a register form with name, username, email, password - accessible directly at `/register`
- **Local login**: a login form with email + password, cookie-based session
- **Login context banner**: when logging in via the OAuth flow, show the name of the app requesting access; for direct access, show a guidance notice
- **OAuth2 Authorization Code + PKCE**: the `/oauth/authorize` and `/oauth/token` endpoints
- **Silent SSO**: if a session is active when `/oauth/authorize` is called, redirect straight back with a code
- **Auto-approve consent**: trusted first-party clients do not need to confirm a consent screen
- **Userinfo endpoint**: `GET /api/user` - returns the profile based on the access token (Bearer)
- **Refresh token flow**: clients can refresh the access token without user interaction
- **Scope-based authorization**: initial scope `profile:read`
- **Token revocation**: logout revokes the session and all active tokens

### P1 - Done ✓

- **Landing page** (`/`): a public page explaining SSO Engine - OAuth2 + PKCE, self-service register, the Yado.my.id ecosystem. Links to `/login` and `/register`.
- **My Account** (`/account`): a self-service page for logged-in users
    - Account info: name, username, email, avatar, role, email verification status
    - Change password
    - Active sessions: a list of active devices/sessions, with a "Revoke" button per session to revoke from unfamiliar devices
- **Superadmin Dashboard** (`/dashboard`):
    - Only accessible to users with the `superadmin` role
    - **Applications**: list registered OAuth clients, add a new client, a Quick Start panel with ready-to-copy credentials, redirect URI validation (HTTPS + block private IPs), edit name/redirect URI, revoke/delete a client
    - **Users**: list all users, activate/deactivate accounts, assign roles, invite new users via email
- **Invite user via email**: the superadmin enters an email → the system sends an email with a one-time link to set a password and complete the profile
- **Forgot password**: the user enters an email → a reset link via Resend, expires in 60 minutes, single-use
- **Optional full name on register**: if empty, falls back to the username

### P2 - Should Have (Next)

- **Avatar upload**
- **Audit log** of login events (who logged in when, from where)
- **Email verification** - verify the email after registering

### P3 - Nice to Have

- **Multi-factor authentication (TOTP)**
- **Webhook** notifications to client apps when a user is deactivated
- **Social login** (Google, GitHub)

---

## Out of Scope

- Frontend/UI for client apps (Malas, Scribe, etc.)
- Any business features beyond identity & access management
- Multi-tenant / organizations
- SAML / OpenID Connect Discovery endpoint
- Subscription plan management (decided: skip)

---

## Security Requirements

- PKCE is mandatory for every authorization code flow
- The client secret is only for confidential (server-side) clients and is never exposed to the frontend
- Access token TTL: 1 hour; Refresh token TTL: 30 days
- HTTPS enforced on all endpoints
- Password: bcrypt, minimum 8 characters
- Rate limiting on the login and token endpoints
- CSRF protection on Blade forms
- Invite link: single-use, expires in 24 hours
- Superadmin dashboard: `role:superadmin` middleware, not accessible to other roles

---

## Success Metrics

- SSO login completes in < 2 seconds (p95) for an already-active session
- Zero cross-app session leakage
- Every client app can integrate with only OAuth client credentials (no custom auth code)
- A superadmin can register a new OAuth client without touching the terminal/database
