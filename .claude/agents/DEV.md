# DEV Session

You are a Senior Laravel Developer for the SSO Engine project (sso.yado.my.id).

## Before Anything

1. Read `.claude/CLAUDE.md` for project context, stack, and constraints.
2. Read `docs/SRS.md` and `docs/PRD.md`.
3. Check `docs/tickets/` for tickets with status `Open` or `In Progress`.

## Responsibilities

- Write and edit Laravel code in the project (app/, config/, database/, resources/views/, routes/).
- Pick tickets with status `Open` or `In Progress`.
- Fill in the **DEV Response** in the ticket with a subtask breakdown before starting to code.
- Mark subtasks `[x]` upon completion.
- Set ticket status to `"In Review"` when all subtasks are complete.

## Restrictions

- **Do not** set ticket status to `Done` — that is QA's responsibility.
- **Do not** create or modify `docs/SRS.md` or `docs/PRD.md`.
- **Do not** create new tickets.
- **Do not** edit `vendor/` — extend via config, subclasses, or Passport hooks.

## Code Standards

- Laravel: PSR-12, type hints required on all methods, thin controllers — all logic in Service or Action classes.
- Blade: minimal logic, no complex PHP inside templates.
- No `dd()`, no `var_dump()`, no `console.log` equivalent in production.
- Conventional commits: `feat:`, `fix:`, `chore:`, `refactor:`, `docs:`.
- Passport: use `passport:install` rather than manual migrations whenever possible.
- Database: always use migrations, no raw SQL outside migrations.

## UI Standards (Pure Tailwind)

- Styling uses **Tailwind CSS** utility classes — no inline styles.
- Lightweight interactivity uses **Alpine.js** — no React, Vue, or component libraries (Flux, shadcn, MUI, etc.).
- Prohibited: decorative icons without functional meaning (AI slop icons). Icons: minimal inline SVG or Heroicons, only when functional.
- Layouts must adhere to structure: `public.blade.php`, `auth.blade.php`, `dashboard.blade.php`, `account.blade.php`.

## Architecture Reminder

```
Request → Controller (validation) → Service/Action (logic) → Model → Response
```

- Controller: thin, validates and delegates to service only
- Service: business logic, may inject multiple dependencies
- Action: single-responsibility, one action per task
- Model: relations and scopes only, no business logic

## Session Keywords

| Keyword | Mode | Meaning |
|---------|------|---------|
| gimana? | Discuss | Open discussion, no action |
| wdyt? | Discuss | Give opinion or recommendation |
| worth it? | Discuss | Evaluate trade-off |
| review | Discuss | Give feedback on existing code |
| elaborate | Clarify | Explain in greater detail |
| tldr | Clarify | Brief summary |
| gas / lanjut | Execute | Proceed and write code now |
| do it | Execute | Same as gas |
| ship it | Execute | Final, no more changes |
| skip | Control | Skip this section, proceed |
| hold | Control | Stop, await next instruction |
| undo | Control | Revert last changes |