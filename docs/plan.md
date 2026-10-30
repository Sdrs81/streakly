# Streakly — Implementation Plan

## Context

Streakly is a small habit tracker. It is a learning project for Claude Code workflows and a portfolio piece, with a budget of 1–1.5 days. The goal is a simple app that works end to end: auth, habit CRUD, a daily "done" toggle, and a streak counter. The streak logic and the authorization are proven by tests.

**Current state (already in the repo):**

- `backend/`: a Laravel 13 skeleton. Sanctum is installed: `HasApiTokens` is on `User`, the `personal_access_tokens` migration exists, and `routes/api.php` has the default `/user` closure. `bootstrap/app.php` already renders JSON for `api/*`. Pest 5 and Pint are installed. `RefreshDatabase` is commented out in `tests/Pest.php`. `config/app.php` timezone is `UTC`.
- `frontend/`: a Vue 3 + TS + Vite 8 scaffold. Pinia, Router (empty routes), Tailwind 4 (`@import 'tailwindcss'`), Axios (installed but unused) and the `/api → 127.0.0.1:8000` proxy are set up. Placeholder `stores/counter.ts`, `App.vue` and `__tests__/App.spec.ts` are still there. `src/lib/api.ts` does not exist yet.
- Tooling: the `api-endpoint` skill (route → FormRequest → Policy → thin controller → Resource → Pest test), the `test-runner` subagent, `.claude/rules/clean-code.md`, Laravel Boost MCP and context7 MCP.

**Decisions made:**

- **"Today"** is computed in a fixed timezone, `Europe/Istanbul`, set as a dedicated key `config('streakly.timezone')`. `app.timezone` stays `UTC`, so timestamps (`created_at`, token expiry) are still stored in UTC. Only the habit calendar uses the local day. The README notes that the app supports a single timezone. Tests pin time with `travelTo()`.
- **Another user's habit → 403** (from `HabitPolicy`), not 404. This is a deliberate choice: it reveals that a habit ID exists, which is acceptable for this app. The README documents it.
- **Tokens and emails:** Sanctum tokens expire after 30 days (`SANCTUM_TOKEN_EXPIRATION_MINUTES`) and are pruned daily, 24 hours after expiry. Emails are trimmed and lowercased by `EmailNormalizer`, because SQLite compares them case-sensitively.
- **Git:** one branch and one PR per phase, branched from an up-to-date `main`. Never commit directly to `main`.
- **`.env` files can't be read by Claude** (the `settings.json` deny list). Check DB and app config with `php artisan about` or the Boost `database-connections` / `application-info` tools instead.

## Working rules

Process rules (the `api-endpoint` skill, the `test-runner` subagent, `finish-phase`) live in `CLAUDE.md`. Specific to this plan:

- Before you write version-sensitive code, check current APIs: Laravel, Sanctum and Pest through Boost `search-docs`; Pinia 4, Vue Router 5, Tailwind 4 and Vitest through context7.
- A phase is done when its "Done when" holds, tests are green, Pint (backend) or lint and type-check (frontend) are clean and the app runs. Then use the `finish-phase` skill.

---

## Database schema

| Table                             | Column                                         | Type / constraint                                                                                                              |
| --------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `users` (exists)                  | id, name, email (unique), password, timestamps | unchanged                                                                                                                      |
| `personal_access_tokens` (exists) | Sanctum default                                | unchanged                                                                                                                      |
| `habits`                          | `id`                                           | PK                                                                                                                             |
|                                   | `user_id`                                      | `foreignId()->constrained()->cascadeOnDelete()`, **indexed**                                                                   |
|                                   | `name`                                         | `string(100)`, required                                                                                                        |
|                                   | `description`                                  | `text`, nullable (validated max 500)                                                                                           |
|                                   | `created_at`, `updated_at`                     | timestamps                                                                                                                     |
| `habit_completions`               | `id`                                           | PK                                                                                                                             |
|                                   | `habit_id`                                     | `foreignId()->constrained()->cascadeOnDelete()`                                                                                |
|                                   | `completed_on`                                 | `date` column holding a canonical `Y-m-d` string (calendar day in `streakly.timezone`). **No Eloquent date cast**, see Phase 3 |
|                                   | `created_at`, `updated_at`                     | timestamps                                                                                                                     |
|                                   | **unique(`habit_id`, `completed_on`)**         | Prevents double marks. It also serves as the lookup index for "completions of habit X by date".                                |

**Relationships:** `User hasMany Habit`, `Habit belongsTo User`, `Habit hasMany HabitCompletion`, `HabitCompletion belongsTo Habit`. Deletes cascade: user → habits → completions.

## API endpoints

All endpoints are in `routes/api.php` under the `/api` prefix and return JSON through API Resources. Auth is a Sanctum Bearer token.

| Method    | Path                                    | Auth             | Description                                                                                   | Success |
| --------- | --------------------------------------- | ---------------- | --------------------------------------------------------------------------------------------- | ------- |
| POST      | `/api/register`                         | guest, throttled | Create a user and return `{data: user, token}`                                                | 201     |
| POST      | `/api/login`                            | guest, throttled | Check credentials and return `{data: user, token}`. Bad credentials → 422                     | 200     |
| POST      | `/api/logout`                           | token            | Revoke the **current** token                                                                  | 204     |
| GET       | `/api/user`                             | token            | Current user (`UserResource`)                                                                 | 200     |
| GET       | `/api/habits`                           | token            | List the user's own habits, oldest first, each with `current_streak` and `is_completed_today` | 200     |
| POST      | `/api/habits`                           | token            | Create a habit (`name` required, `description` optional)                                      | 201     |
| GET       | `/api/habits/{habit}`                   | token + policy   | Show one habit                                                                                | 200     |
| PUT/PATCH | `/api/habits/{habit}`                   | token + policy   | Update name/description                                                                       | 200     |
| DELETE    | `/api/habits/{habit}`                   | token + policy   | Delete the habit and its completions                                                          | 204     |
| PUT       | `/api/habits/{habit}/completions/today` | token + policy   | Mark today done (idempotent). Returns the updated `HabitResource`                             | 200     |
| DELETE    | `/api/habits/{habit}/completions/today` | token + policy   | Unmark today (idempotent). Returns the updated `HabitResource`                                | 200     |

The UI's "toggle" calls PUT or DELETE depending on `is_completed_today`. Idempotent verbs make double clicks harmless. The server decides what "today" is, so the client never sends a date. Another user's habit returns **403** from `HabitPolicy` (a deliberate choice, see Decisions).

---

## Phase 1 — Backend foundation & auth

**Branch:** `feat/backend-auth` · **Estimate:** ~2h · **Status:** done

**Tasks**

1. Check with `php artisan about` (or Boost `database-connections`) that the default connection is SQLite. Do not read `.env`. Create `database/database.sqlite` if missing and run `php artisan migrate`.
2. `tests/Pest.php`: enable `RefreshDatabase` for `Feature`. Delete the placeholder `ExampleTest`s.
3. Add auth routes with the `api-endpoint` skill: register, login, logout, user. Replace the `/user` closure with a controller action. Throttle with named limiters in `AppServiceProvider`: `register` (10/min per IP) and `login` (10/min per IP + email, 30/min per IP).
4. `AuthController` (register, login, logout, show current user). Keep it thin. Login failure throws `ValidationException` on `email`. Logout calls `$request->user()->currentAccessToken()->delete()`.
5. `RegisterRequest` (name, email unique, password confirmed with `Password::defaults()`) and `LoginRequest`.
6. `UserResource` (id, name, email). Auth responses use `UserResource::make($user)->additional(['token' => ...])`.

**Files:** `routes/api.php`, `app/Http/Controllers/AuthController.php`, `app/Http/Requests/{Register,Login}Request.php`, `app/Http/Resources/UserResource.php`, `app/Providers/AppServiceProvider.php`, `app/Support/EmailNormalizer.php`, `config/sanctum.php`, `routes/console.php`, `tests/Pest.php`, `tests/Unit/.gitkeep`

**Tests:** `tests/Feature/Auth/`

- `RegisterTest`: returns 201 with a token; rejects a duplicate email (422); rejects a missing or unconfirmed password (422).
- `LoginTest`: returns a token for valid credentials; rejects a wrong password (422 on `email`).
- `LogoutTest`: revokes the current token; guest → 401. **Use a real token:** create it with `$user->createToken('test')->plainTextToken` and send it with `withToken()`. `actingAs($user, 'sanctum')` leaves `currentAccessToken()` null, so `->delete()` throws a 500. `Sanctum::actingAs()` uses a mock token, so a deletion check would prove nothing. Assert that the `personal_access_tokens` row is gone. Then call `$this->app['auth']->forgetGuards()` and assert that the same token now gets 401.
- `CurrentUserTest`: returns the authenticated user; guest → 401; an expired token → 401.
- Also covered in `LoginTest` and `RegisterTest`: login and register throttling (per email, per IP, and register not blocked by failed logins), and email casing and whitespace normalization.

**Done when:** the auth tests pass through test-runner, Pint is clean, `php artisan route:list --path=api` shows the 4 routes, and a manual register → login → `GET /api/user` with the Bearer token works.

## Phase 2 — Habit CRUD + authorization

**Branch:** `feat/habit-crud` · **Estimate:** ~1.5–2h · **Status:** done

**Tasks** (through the `api-endpoint` skill)

1. Migration `create_habits_table`, `Habit` model (fillable name and description), `HabitFactory`, `User::habits()`.
2. `HabitPolicy`: `view`, `update` and `delete` all check that the user owns the habit. Index and store don't need a policy, because index is scoped through `$request->user()->habits()` and store creates through the relation.
3. `StoreHabitRequest` and `UpdateHabitRequest`: `name` required|string|max:100, `description` nullable|string|max:500. In `UpdateHabitRequest`, `authorize()` delegates to the policy, so another user gets 403 before any 422.
4. `HabitResource` (id, name, description, created_at).
5. `HabitController` with `Route::apiResource('habits', ...)` in the `auth:sanctum` group. Use `Gate::authorize()` for show and destroy. Index returns `$request->user()->habits()->oldest()` so the order is deterministic.

**Files:** `database/migrations/*_create_habits_table.php`, `app/Models/Habit.php`, `app/Models/User.php`, `database/factories/HabitFactory.php`, `app/Policies/HabitPolicy.php`, `app/Http/Requests/{Store,Update}HabitRequest.php`, `app/Http/Resources/HabitResource.php`, `app/Http/Controllers/HabitController.php`, `routes/api.php`

**Tests:** `tests/Feature/Habits/`: `HabitIndexTest`, `HabitStoreTest`, `HabitShowTest`, `HabitUpdateTest`, `HabitDeleteTest`. Each file covers the success case, guest → 401 and, where relevant, invalid payload → 422. **Authorization tests (required):**

- The index lists only your own habits, never another user's, oldest first.
- Viewing, updating or deleting another user's habit returns 403, and the database row is unchanged or still exists.
- Store ignores a `user_id` sent in the payload. The habit always belongs to the caller.

**Done when:** all habit tests pass and the CRUD works with curl or an HTTP client using two different users' tokens.

## Phase 3 — Daily completion & streak

**Branch:** `feat/habit-streaks` · **Estimate:** ~2–2.5h · **Status:** done

**Tasks**

1. `config/streakly.php`: `'timezone' => env('STREAKLY_TIMEZONE', 'Europe/Istanbul')`. Leave `app.timezone` as `UTC`.
2. `app/Support/HabitCalendar.php`: `today(): CarbonImmutable` returns `CarbonImmutable::now(config('streakly.timezone'))->startOfDay()`. This is the only place that decides what "today" is.
3. Migration `create_habit_completions_table` (with the unique index), `HabitCompletion` model, factory, `Habit::completions()`. **`completed_on` has no date cast.** It is always written and queried as a `Y-m-d` string (`HabitCalendar::today()->toDateString()`). Why: a `date` cast rewrites the value to `Y-m-d 00:00:00` on save, but `firstOrCreate` and `where` compare against whatever you pass in. A `Y-m-d` lookup would miss the stored row, try to insert again, and hit the unique index (500). With a plain string, the writes, lookups and unique index all use the same value.
4. `app/Services/StreakCalculator.php`, a pure class with `calculate(iterable<string> $completedDates, CarbonImmutable $today): int`:
   - Build a set of `Y-m-d` strings.
   - `dayToCheck = today`. If today isn't in the set, start from yesterday.
   - While `dayToCheck` is in the set: increment the count and step `dayToCheck` back one day.
5. `Habit` methods: `currentStreak()` (delegates to the calculator with `HabitCalendar::today()`) and `isCompletedToday()`.
6. `HabitResource`: add `current_streak` and `is_completed_today`. `HabitController@index` eager-loads `completions:id,habit_id,completed_on` to avoid N+1 queries.
7. `HabitCompletionController` (through the `api-endpoint` skill), with `Gate::authorize('update', $habit)`:
   - `store`: `$habit->completions()->firstOrCreate(['completed_on' => HabitCalendar::today()->toDateString()])`.
   - `destroy`: `$habit->completions()->where('completed_on', <same string>)->delete()`.
   - Both return a fresh `HabitResource`. There is no body to validate, so no FormRequest. This is an intended exception, not a convention break.
8. Routes: `PUT` and `DELETE /habits/{habit}/completions/today`.

**Watch out for:** never add a `date` or `immutable_date` cast to `completed_on`, and never compare it against a Carbon object. Always use a `Y-m-d` string. Feature tests #11 and #14 run against the real SQLite DB and assert the stored value.

**Files:** `config/streakly.php`, `app/Support/HabitCalendar.php`, `database/migrations/*_create_habit_completions_table.php`, `app/Models/HabitCompletion.php`, `app/Models/Habit.php`, `database/factories/HabitCompletionFactory.php`, `app/Services/StreakCalculator.php`, `app/Http/Resources/HabitResource.php`, `app/Http/Controllers/{Habit,HabitCompletion}Controller.php`, `routes/api.php`

**Tests:** `tests/Unit/StreakCalculatorTest.php` (pure, no DB) and `tests/Feature/Habits/HabitCompletionTest.php`. See the edge-case table below. Delete `tests/Unit/.gitkeep` once the first unit test exists.

**Done when:** the streak edge-case tests and the completion feature tests pass, and `GET /api/habits` shows a correct streak and today flag after marking.

### Streak edge cases → tests

| #   | Scenario                                                                                                               | Expected                                                                                                                        | Test                                                                       |
| --- | ---------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| 1   | No completions                                                                                                         | 0                                                                                                                               | Unit: `returns zero when the habit was never completed`                    |
| 2   | Only today                                                                                                             | 1                                                                                                                               | Unit: `counts today when only today is completed`                          |
| 3   | Only yesterday, today not yet marked                                                                                   | 1                                                                                                                               | Unit: `keeps the streak alive from yesterday when today is not marked yet` |
| 4   | Today + previous 2 days                                                                                                | 3                                                                                                                               | Unit: `counts consecutive days ending today`                               |
| 5   | Yesterday + day before, today unmarked                                                                                 | 2                                                                                                                               | Unit: `counts consecutive days ending yesterday`                           |
| 6   | Last completion 2 days ago (yesterday missed)                                                                          | 0                                                                                                                               | Unit: `resets the streak when yesterday was missed`                        |
| 7   | Today done, yesterday missing, older 5-day run                                                                         | 1                                                                                                                               | Unit: `stops counting at the first gap`                                    |
| 8   | Run crosses a month/year boundary (Dec 30 → Jan 1)                                                                     | 3                                                                                                                               | Unit: `counts across a year boundary`                                      |
| 9   | Dates given unordered or duplicated                                                                                    | Same as sorted                                                                                                                  | Unit: `ignores input order and duplicates`                                 |
| 10  | Future-dated completion exists                                                                                         | Ignored                                                                                                                         | Unit: `ignores completions after today`                                    |
| 11  | Marking today twice                                                                                                    | One row, streak unchanged                                                                                                       | Feature: `marking today is idempotent`                                     |
| 12  | Unmark today after marking                                                                                             | Streak falls back to the run ending yesterday                                                                                   | Feature: `unmarking today removes only today's completion`                 |
| 13  | Unmark when not marked                                                                                                 | 200, no error                                                                                                                   | Feature: `unmarking an unmarked day is a no-op`                            |
| 14  | Midnight in `streakly.timezone` (mark at 20:30 UTC = 23:30 Istanbul, check at 21:10 UTC = 00:10 Istanbul the next day) | Stored `completed_on` is the Istanbul date, not the UTC date. On the next day `is_completed_today` is false and the streak is 1 | Feature: `uses the habit timezone to decide what today is`                 |
| 15  | Another user's habit                                                                                                   | 403, no row created or deleted                                                                                                  | Feature: `forbids marking another user's habit` / `...unmarking...`        |
| 16  | Guest                                                                                                                  | 401                                                                                                                             | Feature                                                                    |
| 17  | Habit deleted                                                                                                          | Completions cascade-deleted                                                                                                     | Feature: `deleting a habit removes its completions`                        |

Unit tests pass explicit `CarbonImmutable` dates with named variables (e.g. `$today`), never inline literals at call sites. They never call `now()` or `today()`: `tests/Unit` doesn't boot Laravel, so the default timezone there comes from the machine's `php.ini` (Istanbul locally, possibly UTC in CI). Feature tests use `$this->travelTo(...)` with UTC instants, because `app.timezone` is UTC.

## Phase 4 — Frontend foundation & auth screens

**Branch:** `feat/frontend-auth` · **Estimate:** ~2–2.5h · **Status:** done

**Tasks**

1. Remove the scaffolding: `stores/counter.ts`, the placeholder `App.spec.ts`, and the `App.vue` content.
2. `src/lib/api.ts`: the single Axios instance (`baseURL: '/api'`, `Accept: application/json`). Export `setAuthToken(token | null)` to set or clear the `Authorization` header, and `onUnauthorized(handler)` for a response interceptor on 401. This avoids importing the store into `api.ts` (no circular import).
3. `src/types/api.ts`: `User`, `Habit`, `AuthResponse`, `ResourceResponse<T>`, `ValidationErrorResponse`.
4. `src/lib/validationErrors.ts`: maps a 422 response to `Record<field, string>`.
5. `src/stores/auth.ts` (Pinia setup store): `user`, `token` (persisted to `localStorage` and rehydrated on start), `isAuthenticated`, `register`, `login`, `logout`, `fetchCurrentUser`. Register the 401 handler so it clears auth and redirects to `/login`.
6. Router: `/login` and `/register` (guest only), `/` → habits (`meta.requiresAuth`), with a `beforeEach` guard.
7. Views: `LoginView.vue` and `RegisterView.vue`. Show field errors for 422, and a single generic message (e.g. "Something went wrong, please try again.") for any other failure (network, 5xx). `App.vue` gets a minimal header (app name + logout) and a `RouterView`. Style with Tailwind utility classes only.
8. A placeholder `HabitsView.vue` for `/` (heading + "Your habits will appear here"), so this phase ends in a working state. Phase 5 replaces its content.

**Files:** `src/lib/api.ts`, `src/lib/validationErrors.ts`, `src/types/api.ts`, `src/stores/auth.ts`, `src/router/index.ts`, `src/main.ts` (wires the 401 handler to the store and router), `src/views/{Login,Register,Habits}View.vue`, `src/components/AppHeader.vue`, `src/App.vue`

**Tests (Vitest, mocking `@/lib/api`):** `src/stores/__tests__/auth.spec.ts` (login stores the token and user; logout clears both; a token in `localStorage` is rehydrated), `src/router/__tests__/guards.spec.ts` (a guest is redirected from `/` to `/login`; a logged-in user is redirected away from `/login`; a stored token loads the user, and a rejected token redirects to login), `src/lib/__tests__/validationErrors.spec.ts`, `src/lib/__tests__/api.spec.ts` (bearer header, and the 401 handler only fires for the current token).

**Done when:** tests, lint and type-check pass. In the browser, register lands on `/`, a refresh keeps the session, and logout returns to `/login` and the old token gets 401.

## Phase 5 — Habit screens

**Branch:** `feat/frontend-habits` · **Estimate:** ~2.5–3h

**Tasks**

1. `src/stores/habits.ts`: `habits`, `isLoading`, `errorMessage` (set on non-422 failures and shown as a banner in `HabitsView`), `fetchHabits`, `createHabit`, `updateHabit`, `deleteHabit`, `toggleToday(habit)`. The toggle picks PUT or DELETE from `is_completed_today` and replaces the habit with the server response.
2. `src/lib/formatDayCount.ts`: "1 day" / "N days".
3. `HabitsView.vue` (replaces the Phase 4 placeholder): list, empty state, error banner, and a create form at the top.
4. `HabitCard.vue`: name, description, streak badge, a "Done today" toggle button, and edit/delete actions. Delete asks with `window.confirm`.
5. `HabitForm.vue`: reused for create and inline edit, and shows 422 field errors.

**Files:** `src/stores/habits.ts`, `src/lib/formatDayCount.ts`, `src/views/HabitsView.vue`, `src/components/{HabitCard,HabitForm}.vue`, `src/router/index.ts`

**Tests:** `src/stores/__tests__/habits.spec.ts` (the toggle calls the right verb and updates state; delete removes the habit from state; a failed request sets `errorMessage`), `src/components/__tests__/HabitCard.spec.ts` (renders the streak label; emits toggle), `src/lib/__tests__/formatDayCount.spec.ts`.

**Done when:** all tests, lint and type-check pass, and the full flow works in the browser: register → create, edit and delete a habit → mark and unmark today → the streak updates → logout. A second user doesn't see the first user's habits.

## Phase 6 — README & wrap-up (cuttable)

**Branch:** `docs/readme` · **Estimate:** ~1h

- `README.md`: what the app does, setup commands, the test commands, the API table, design decisions and trade-offs (Bearer tokens kept in `localStorage`, which is exposed to XSS but acceptable here; a single fixed habit timezone with UTC storage; idempotent completion endpoints; 403 rather than 404 for other users' habits), a "Known limitations" note (`/api/register` reveals whether an email is taken; no trusted-proxy config, so behind a proxy the per-IP throttles share one bucket; token pruning needs `schedule:run` in production), a screenshot, and a short "built with Claude Code" section (skills, subagent, hooks, MCP).

---

## Time risks & cut list

**Risks**

- **The total estimate is tight.** The phase estimates add up to about 13h. Per-phase overhead (commit, PR, review loop, fixes, merge) adds about 15–20 min × 6 ≈ 1.5–2h, for roughly 15h in total. That is at the top of the 1.5-day budget. Expect to use the cut list, and decide at the end of Phase 3 whether to start cutting.
- **Very new versions** (Laravel 13, Pest 5, Pinia 4, Vue Router 5, Vite 8, TS 6): APIs may differ from memory. _Mitigation:_ check Boost `search-docs` / context7 before writing code in each area.
- **Date and timezone bugs:** a time part sneaking into `completed_on`, and the midnight boundary. _Mitigation:_ plain `Y-m-d` strings with no cast, a single `HabitCalendar::today()`, the pure calculator, and tests #8, #11 and #14.
- **Frontend auth plumbing:** token persistence, the 401 interceptor, the circular import between the store and the API layer. _Mitigation:_ the `setAuthToken` / `onUnauthorized` design in Phase 4.
- **UI polish creep:** the largest hidden time sink. Keep to plain Tailwind utilities with no component library.
- **Windows tooling:** the hooks need `jq`, Python and Git Bash. Check once at the start of Phase 1.

**Cut in this order if behind schedule**

1. Phase 6 shrinks to a minimal README.
2. `GET /api/habits/{habit}` (show): the UI doesn't use it.
3. Inline edit UX: use a simple reused form instead.
4. Frontend component tests: keep the store and `formatDayCount` tests.
5. Throttling on the auth routes, and the global 401 auto-logout.

**Never cut:** the streak unit and feature tests, the authorization (403) tests, and the auth flow.

---

## Verification (end to end)

1. Backend: run the full Pest suite with the `test-runner` subagent, check `vendor/bin/pint --test`, and run `php artisan route:list --path=api`.
2. Frontend: run Vitest with the `test-runner` subagent, then `npm run lint` and `npm run type-check`.
3. Manual: start `php artisan serve` and `npm run dev`, then go through the Phase 5 "done" flow with two users in two browsers (one normal, one private window).
4. Optionally inspect the `habit_completions` rows with the Laravel Boost `database-query` tool after a mark and unmark.

## Before Phase 1

1. Commit `docs/plan.md` on the current `docs-update` branch, open a PR, and merge it into `main`. Otherwise the plan won't exist on the feature branches.
2. Update local `main`, then create `feat/backend-auth` from it and start Phase 1.
