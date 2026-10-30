# Streakly - Habit-tracking app.

## Tech Stack

PHP 8.4 · Laravel 13 · Sanctum · SQLite · Pest
Vue 3 · TypeScript 6 · Vite 8 · Pinia 4 · Vue Router 5 · Tailwind CSS 4 · Axios · Vitest

## Commands

```
cd backend && php artisan serve              # API on port 8000
cd frontend && npm run dev                   # SPA on port 5173, /api proxied to 8000
cd backend && php artisan test               # backend tests
cd frontend && npm run test:unit -- --run    # frontend tests (single run)
cd frontend && npm run lint                  # lint
```

## Dev Environment

Required on PATH for Claude Code tooling to work:

- Python 3.10+ (python.org install, not the Microsoft Store alias). The `security-guidance` plugin's hooks need it and fail silently without it.
- `jq` and `bash` (Git Bash on Windows). `.claude/hooks/guard-bash.sh` blocks every shell command if `jq` is missing; `format.sh` skips formatting.
- PHP 8.4 (the `laravel-boost` MCP server runs `php backend/artisan boost:mcp`) and Node `^22.18 || >=24.12`.
- Run `composer install` in `backend/` and `npm install` in `frontend/` first: `format.sh` uses `vendor/bin/pint` and Prettier, and `laravel/boost` is a Composer dependency.

## Architecture

Never use Inertia. The backend is a JSON API only; keep all routes in `routes/api.php`.
Always authenticate with Sanctum Bearer tokens — never cookie- or session-based SPA auth.

## Conventions

Always authorize with Policies — never write manual user checks inside controllers.
Always validate with FormRequest classes (an endpoint with no input needs none) and shape output with API Resources.
Always keep frontend state in Pinia.
Always send HTTP through the single Axios instance in `src/lib/api.ts`.

## Working Rules

Always use the `api-endpoint` skill when adding a new backend endpoint.
Always use the `frontend-feature` skill when adding or changing frontend code (view, component, store, router, API call).
Use the `browser-check` skill to run a frontend phase's "Done when" flow in real Chrome, and run it again after the last fix of the phase.
Before writing version-sensitive code, check current docs first: Boost `search-docs` for Laravel, Sanctum and Pest; context7 for Pinia, Vue Router, Tailwind and Vitest.
Always run tests through the `test-runner` subagent.
For any command outside the `allow` list and the `finish-phase` skill, say in one sentence what it does, why it is needed and whether it writes files. Do not run checks the user did not ask for; ask first.
Before committing a finished phase, always use the `finish-phase` skill: it runs the checks and the review loop, then commits, pushes and opens a short PR.
Never commit or push to `main`. The plan lives in `docs/plan.md`.
