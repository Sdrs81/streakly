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

## Architecture
Never use Inertia. The backend is a JSON API only; keep all routes in `routes/api.php`.
Always authenticate with Sanctum Bearer tokens — never cookie- or session-based SPA auth.

## Conventions
Always authorize with Policies — never write manual user checks inside controllers.
Always validate with FormRequest classes and shape output with API Resources.
Always keep frontend state in Pinia.
Always send HTTP through the single Axios instance in `src/lib/api.ts`.

## Working Rules
Always use the `api-endpoint` skill when adding a new backend endpoint.
Always run tests through the `test-runner` subagent.
Never commit or push to `main`. The plan lives in `docs/plan.md`.
