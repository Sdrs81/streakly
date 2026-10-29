---
name: finish-phase
description: Finishes a Streakly phase. Runs the checks and the review loop (review-pr, then /code-review high), fixes only bugs, security issues and broken conventions, then commits, pushes and opens a short PR. Use as soon as a phase's "Done when" holds, without asking first, or when asked to finish, wrap up or ship a phase.
effort: medium
allowed-tools: Bash(git add:*), Bash(git commit:*), Bash(gh pr create:*)
---

# Finishing a phase

Run from the phase's feature branch, never from `main`.

## Steps

1. **Checks.** Run the tests through the `test-runner` subagent. For every side the phase touched: backend `vendor/bin/pint --test` in `backend/`; frontend `npm run lint` and `npm run type-check` in `frontend/`. Backend phases that add routes also run `php artisan route:list --path=api`. If the phase's "Done when" names a manual check (for example a curl with two users' tokens), run it, or say in the summary that it was not run. Fix any failure before reviewing.
2. **Review.** Mark the phase's new files with `git add -N <files>` so the diff shows them. Then run the `review-pr` skill, then the `code-review` skill with args `high` (never `max` or `ultra` unless the user asks).
3. **Triage.** Act only on bugs, security issues and broken conventions from `CLAUDE.md`. Ignore everything else: style, refactors, nice-to-haves, wording. Keep a list of what you fixed.
4. **Repeat.** If you fixed anything, go back to step 1 and run the whole loop again: checks, then `review-pr`, then `code-review high`. Never skip `review-pr` on a later round. After 3 rounds that are still not clean, stop and ask the user.
5. **Plan.** In `docs/plan.md`, set the phase's `Status` to `done` and make its `Files:` line match what was built.
6. **Summary, then wait.** Run no git command that writes (apart from the `git add -N` in step 2) until the user approves. Give the user: the branch, the changed files (`git status --short`, `git diff --stat`), what the phase built, the check results, what the review fixed, the proposed one-line commit message and PR title, and how to check locally (for example `php artisan serve` or `git diff`). End by asking for approval.
7. **Commit, push and PR (only after approval).** Stage only the files that belong to this phase, listed explicitly (never `git add -A`, never `.env`). One-line commit message, no co-author trailer. Push with `git push -u origin <current-branch>`, and run `gh pr create` as a separate command. Base `main`, with a short body and no attribution line:
   - `## Built`: what the phase added, in a few bullets.
   - `## Review fixed`: the bugs, security issues and convention breaks the loop caught and you fixed (or "none").
8. **Stop.** The user merges. Do not merge.
