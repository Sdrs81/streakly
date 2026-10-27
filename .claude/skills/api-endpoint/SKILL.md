---
name: api-endpoint
description: Adds a new REST endpoint to the Streakly Laravel backend (route, FormRequest, Policy, controller, API Resource, Pest test). Use when asked to "add an endpoint", "create an API route", "add a POST/GET/PUT/DELETE /api/... endpoint", or "expose X through the API".
---

# Adding a new API endpoint

All paths are relative to `backend/`.

## Steps

Follow in order:

1. **Route**: `routes/api.php`. Protected endpoints go inside the `auth:sanctum` group. For resource-based endpoints, use `Route::apiResource` and rely on route model binding.
2. **Validation**: `php artisan make:request {Action}{Model}Request` → `app/Http/Requests/`. If the request needs authorization, have `authorize()` delegate to the Policy; otherwise return `true`.
3. **Authorization**: add or update the Policy method in `app/Policies/` (`php artisan make:policy` if missing). In the controller, call `Gate::authorize()` or `$this->authorize()`.
4. **Controller**: `app/Http/Controllers/`. Keep it thin. Business logic belongs in the model or a service class.
5. **Output**: `php artisan make:resource {Model}Resource` → `app/Http/Resources/`. Always return a Resource or Resource collection.
6. **Test**: `tests/Feature/{Model}{Action}Test.php` (Pest).
7. **Run tests** with the `test-runner` subagent and report the result.

## Rules

- Never write manual user checks in controllers (e.g. `$habit->user_id !== $request->user()->id`). Authorization goes through Policies only.
- Never validate inside the controller. Always use a FormRequest.
- Never return a model or raw array. Always use an API Resource.
- Scope queries to the authenticated user (e.g. `$request->user()->habits()`) so one user can never list or modify another user's data.
- Never add routes outside `routes/api.php`, and never use session/cookie auth. Sanctum Bearer tokens only.
- Never run `php artisan test` directly. Use the `test-runner` subagent.

## Required test coverage

Every endpoint test file must include:

| Case                  | Expected                               |
| --------------------- | -------------------------------------- |
| Successful request    | Correct status code and JSON structure |
| Invalid payload       | 422 with field errors                  |
| No token              | 401                                    |
| Another user's record | 403 or 404                             |

## Expected output

Controller action shape:

```php
public function store(StoreCompletionRequest $request, Habit $habit): CompletionResource
{
    Gate::authorize('update', $habit);

    $completion = $habit->completions()->create($request->validated());

    return new CompletionResource($completion);
}
```

Test shape:

```php
it('returns 401 for guests', function () {
    $this->postJson("/api/habits/1/completions")->assertUnauthorized();
});

it("forbids completing another user's habit", function () {
    $habit = Habit::factory()->create();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson("/api/habits/{$habit->id}/completions", ['completed_on' => today()->toDateString()])
        ->assertForbidden();
});
```
