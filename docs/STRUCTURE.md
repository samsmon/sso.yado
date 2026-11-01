# STRUCTURE - SSO Engine

This document describes the project folder structure and naming conventions.

## Root

```
sso.yado/
  app/                    # Laravel application code
  config/                 # Laravel config files
  resources/
    js/                   # Svelte 5 pages (Pages/), layouts (Layouts/), app.js
    css/                  # Tailwind CSS v4 & DaisyUI styles (app.css)
    views/app.blade.php   # Inertia HTML root template
  routes/                 # web.php, api.php, oauth.php (Passport)
  docs/                   # Project documentation (PRD, SRS, tickets)
  scripts/                # deploy.sh, deploy-docker-proxmox.sh
  docker/                 # Dockerfile, entrypoint.sh, nginx/caddy configs, production compose
    php/                  # Dockerfile + entrypoint.sh
    nginx/                # default.conf (used by compose.prod.yml)
    caddy/                # Caddyfile (used by compose.standalone.yml)
    compose.prod.yml      # Production Docker Compose (homelab/Proxmox + Traefik)
    compose.standalone.yml # Universal Docker Compose (Caddy, self-contained, local OR public)
  docker-compose.yml      # Dev Docker Compose: app, nginx, postgres (remains at root - default Docker Compose lookup)
  logs/                   # sync.log, deploy.log (gitignored)
  .claude/                # Claude Code config (CLAUDE.md + agents)
  Makefile                # Shortcut commands
  sync.sh                 # Sync stack from SRS to CLAUDE.md
  SESSION-PROMPTS.md      # Copy-paste prompts for each agent session
```

## docs/

```
docs/
  AI_AGENT_GUIDE.md   # Authoritative operating manual for AI coding agents & DevOps
  PRD.md              # Product Requirements Document
  SRS.md              # Software Requirements Specification
  STRUCTURE.md        # This file
  TODO.md             # Backlog and informal notes
  INTEGRATION.md      # Integration guide for client app developers (manual)
  AI_INTEGRATION.md   # Integration brief for AI assistants (feed to Claude/Cursor)
  API.md              # HTTP API endpoint reference & JSON schemas
  DEPLOY_AZURE.md     # Deployment guide for Azure (VM or App Service)
  DEPLOY_AWS.md       # Deployment guide for AWS (EC2 or Elastic Beanstalk)
  DOCKER.md           # Running via Docker for local development & production
  tickets/            # Internal workflow tickets
    TASK-XXX.md       # Feature tickets
    bugs/
      BUG-XXX.md      # Bug tickets
```

## .claude/

```
.claude/
  CLAUDE.md         # Project context & constraints for Claude
  settings.local.json
  agents/
    PM.md           # Persona & rules for PM sessions
    DEV.md          # Persona & rules for DEV sessions
    QA.md           # Persona & rules for QA sessions
```

## Ticket Conventions

- Feature tickets: `docs/tickets/TASK-001.md`, `TASK-002.md`, etc.
- Bug tickets: `docs/tickets/bugs/BUG-001.md`, etc.
- Sequential numbering; do not reuse existing numbers.

## Code File Conventions

- Controller: `app/Http/Controllers/{Domain}/{Name}Controller.php`
- Service: `app/Services/{Domain}/{Name}Service.php`
- Action: `app/Actions/{Domain}/{Name}Action.php`
- Model: `app/Models/{Name}.php`
- Request: `app/Http/Requests/{Name}Request.php`
- Middleware: `app/Http/Middleware/{Name}.php`