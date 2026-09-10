---
description: 
alwaysApply: true
---

# Agent instructions

Project-specific Cursor rules live in **`.cursor/rules/`**:

- `general-project.mdc` — habits (always applies in this repo)
- `laravel.mdc` — PHP / API conventions
- `laravel-boost.mdc` — Laravel Boost guidelines (scoped to PHP paths)
- `project.mdc` — ticketvmapi boundaries (API + Sanctum; SPA in `ticketvm`)
- `tailwind.mdc` — Tailwind v4 for `resources/**`
- `vue.mdc` / `inertia.mdc` — for future Inertia frontend in this repo

Reusable copies of the stack rules are in `%USERPROFILE%\.cursor\stacks\laravel-inertia\`.
