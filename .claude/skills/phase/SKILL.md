---
name: phase
description: Runs a whole Streakly phase from docs/plan.md end to end, from a fresh branch to the summary that waits for approval. Use when asked to "start phase N", "do phase N" or "run the phase". Stops for the user before any commit, push or PR.
effort: medium
---

# Running a phase

The single human gate is the commit: everything before it runs without asking, and `finish-phase` stops for approval there. Take the phase number from the arguments.

## Steps

1. **Read the phase.** Read its section in `docs/plan.md` (tasks, files, tests, "Done when") and the Decisions at the top. If the phase is already marked `done`, or an earlier phase it builds on is not done, say so and stop.
2. **Branch.** `git checkout main`, `git pull`, then create the phase's branch from `main`. Never work on `main`.
3. **Build.** Use the skill that matches the work: `api-endpoint` for each new backend endpoint, `frontend-feature` for frontend code. Follow their docs-first step (context7 for Pinia, Vue Router, Tailwind, Vitest; Boost `search-docs` for Laravel, Sanctum, Pest). Write the tests the plan lists.
4. **Checks.** Run the tests through the `test-runner` subagent and `bash scripts/phase-check.sh` in the same response. Fix failures before going on.
5. **Browser check (frontend phases).** Add the phase's "Done when" flow as `e2e-<name>.mjs` next to `e2e-auth.mjs`, then run `bash scripts/browser-check.sh` with `SCRATCH_DIR` set to the scratchpad directory. If it stops for pending migrations, say so in one sentence (it writes the dev database) and re-run with `--migrate`. Look at the screenshots.
6. **Finish.** Invoke the `finish-phase` skill. It runs the review loop, updates `docs/plan.md`, gives the summary and waits for approval. Do not commit, push or open a PR before the user approves.

## Rules

- A run is not done while a check fails, a skipped step is unexplained, or the browser check was not repeated after the last fix.
- Anything you could not do (a denied permission, a skipped docs lookup, a command you did not run) goes in the summary, plainly.
- Never work around a denied permission with another tool. Stop and tell the user what you needed.
