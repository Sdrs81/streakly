---
name: frontend-feature
description: Adds or changes frontend code in the Streakly Vue SPA (view, component, Pinia store, router, API call, Vitest test). Use when asked to "add a page", "create a component", "add a store", "call the API from the frontend", or to work on a frontend phase.
effort: medium
allowed-tools: Read, Write, Edit, Grep, Glob, Agent, mcp__context7__resolve-library-id, mcp__context7__query-docs
---

# Adding or changing frontend code

All paths are relative to `frontend/`.

## Steps

Follow in order:

0. **Docs first**: before writing version-sensitive code, look it up with context7 (`resolve-library-id`, then `query-docs`): Pinia, Vue Router, Tailwind, Vitest. Do not write it from memory. These versions are very new (Pinia 4, Vue Router 5, Vite 8, TypeScript 6). Make at least one context7 query for each library the change touches before the first `Write`. If you skip one, say which and why in the final report.
1. **Types**: add or update the response types in `src/types/api.ts`. No `any`.
2. **HTTP**: call the API only through the single Axios instance in `src/lib/api.ts`. Never create another Axios instance or use `fetch`.
3. **State**: keep shared state in a Pinia store in `src/stores/`. Components read from the store and call its actions.
4. **UI**: views in `src/views/`, reusable pieces in `src/components/`. Style with Tailwind utility classes only, with no component library. Show field errors for a 422 and one generic message for any other failure.
5. **Router**: add routes in `src/router/index.ts`. Set `meta.requiresAuth` or guest-only as needed.
6. **Test**: Vitest, next to the code in a `__tests__/` folder (e.g. `src/stores/__tests__/habits.spec.ts`). Mock `@/lib/api`. Test behavior (state and rendered output), not internals.
7. **Run checks**: run the tests with the `test-runner` subagent, and `bash scripts/phase-check.sh` for lint and type-check. Report the result.

## Rules

- Never import the store into `src/lib/api.ts`. Use `setAuthToken` and `onUnauthorized` to avoid a circular import.
- Never keep shared state outside Pinia (no module-level refs, no component-only copies of server data).
- Never send HTTP from a component or view directly. Go through a store action.
- Never use `any`, and never leave unused code or imports.
- Always handle singular and plural in user-facing text ("1 day", "3 days").
- Give every `vi.fn()` a type parameter (`vi.fn<(token: string | null) => void>()`): the lint rule `require-mock-type-parameters` fails without it.
- A store that uses the auth store (directly or through another store) makes every test that mocks `@/lib/api` need `setAuthToken` in that mock.
- A store outlives a logout. Anything keyed to the user (lists, error banners, in-flight responses) must reset when `auth.token` changes.
