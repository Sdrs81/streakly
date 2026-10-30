---
name: finish-phase
description: Finishes a Streakly phase. Runs the checks and the review loop (review-pr, then /code-review high), fixes only bugs, security issues and broken conventions, then commits, pushes and opens a short PR. Use as soon as a phase's "Done when" holds, without asking first, or when asked to finish, wrap up or ship a phase.
effort: medium
allowed-tools: Bash(git add:*), Bash(git commit:*), Bash(gh pr create:*), Edit(docs/plan.md)
---

# Finishing a phase

Run from the phase's feature branch, never from `main`.

## Steps

1. **Checks.** Run the tests through the `test-runner` subagent, and at the same time run `bash scripts/phase-check.sh`. The script picks the sides the phase touched: backend `vendor/bin/pint --test` (and `route:list` when `routes/api.php` changed), frontend `npm run lint` and `npm run type-check`. It does not run tests. If the phase's "Done when" names a manual check (for example a curl with two users' tokens), run it, or say in the summary that it was not run. Fix any failure before reviewing.
2. **Review.** Mark the phase's new files with `git add -N <files>` so the diff shows them. Then run the `review-pr` skill and the `code-review` skill with args `high` (never `max` or `ultra` unless the user asks) in the same response: they are independent.
3. **Triage.** Act only on bugs, security issues and broken conventions from `CLAUDE.md`. Ignore everything else: style, refactors, nice-to-haves, wording. Keep two lists: what you fixed, and the findings you declined with the reason. Write the declined list to `declined-findings.md` in the scratchpad directory after each triage, so it survives a long session.
4. **Repeat.** If you fixed anything, go back to step 1 and run the whole loop again: checks, then `review-pr` and `code-review high`. Never skip `review-pr` on a later round. `code-review` raises declined findings again on every round: drop any finding that matches `declined-findings.md`, and do not count it as "not clean". Check a finding's factual claim against the code before acting on it. A round is clean when nothing is left that is a bug, a security issue or a broken convention. If 2 rounds still leave such findings, stop and ask the user. If a phase's "Done when" names a browser check, run it again with the `browser-check` skill after the last fix.
5. **Plan.** In `docs/plan.md`, set the phase's `Status` to `done` and make its `Files:` line match what was built.
6. **Summary, then wait.** Run no git command that writes (apart from the `git add -N` in step 2) until the user approves. Give the user: the branch, the changed files (`git status --short`, `git diff --stat`), what the phase built, the check results, what the review fixed, the proposed one-line commit message and PR title, and how to check locally (for example `php artisan serve` or `git diff`). End by asking for approval.
7. **Commit, push and PR (only after approval).** Stage only the files that belong to this phase, listed explicitly (never `git add -A`, never `.env`). Files the phase deleted are already staged by `git rm`: leave them out of the `git add` list, because one bad path makes `git add` stage nothing at all. Run `git diff --cached --stat` and compare it with the phase's file list before you commit. One-line commit message, no co-author trailer. Push with `git push -u origin <current-branch>`, and run `gh pr create` as a separate command. Base `main`, with a short body and no attribution line:
   - `## Built`: what the phase added, in a few bullets.
   - `## Review fixed`: the bugs, security issues and convention breaks the loop caught and you fixed (or "none").
8. **Stop.** The user merges. Do not merge.
