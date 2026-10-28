---
name: finish-phase
description: Finishes a Streakly phase. Runs the checks and the review loop (review-pr, then /code-review high), fixes only bugs, security issues and broken conventions, then commits, pushes and opens a short PR. Use when a phase is ready, or when asked to finish, wrap up or ship a phase.
effort: medium
---

# Finishing a phase

Run from the phase's feature branch, never from `main`.

## Steps

1. **Checks.** Run the tests through the `test-runner` subagent. For every side the phase touched: backend `vendor/bin/pint --test` in `backend/`; frontend `npm run lint` and `npm run type-check` in `frontend/`. Fix any failure before reviewing.
2. **Review.** Mark the phase's new files with `git add -N <files>` so the diff shows them. Then run the `review-pr` skill, then the `code-review` skill with args `high` (never `max` or `ultra` unless the user asks).
3. **Triage.** Act only on bugs, security issues and broken conventions from `CLAUDE.md`. Ignore everything else: style, refactors, nice-to-haves, wording. Keep a list of what you fixed.
4. **Repeat.** If you fixed anything, go back to step 1. After 3 rounds that are still not clean, stop and ask the user.
5. **Commit and push.** Stage only the files that belong to this phase, listed explicitly (never `git add -A`, never `.env`). One-line commit message, no co-author trailer. Push with `git push -u origin <current-branch>`, and run `gh pr create` as a separate command.
6. **PR.** Base `main`, with a short body and no attribution line:
   - `## Built`: what the phase added, in a few bullets.
   - `## Review fixed`: the bugs, security issues and convention breaks the loop caught and you fixed (or "none").
7. **Stop.** The user merges. Do not merge.
