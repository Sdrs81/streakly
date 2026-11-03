# Claude setup: findings and lessons

Date: 2026-10-28, updated 2026-11-03 (snapshot: token counts and file contents change over time)

Observations collected while configuring Claude Code for this project. Only findings about the Claude tooling (`CLAUDE.md`, rules, skills, hooks, subagents, plugins, permissions, MCPs) are kept. Related: [audit.md](audit.md) (scores and decisions from the formal audit).

## Summary

| #   | Finding                                          | Takeaway                                                         |
| --- | ------------------------------------------------ | ---------------------------------------------------------------- |
| 1   | `/doctor` wants the "Tech Stack" block removed   | Declined. The saving (about 60 tokens) is not worth the loss.    |
| 2   | Rules files are scoped `CLAUDE.md` files         | Use them for path-specific guidance. They are still advice.      |
| 3   | Prose is a request, hooks are the control        | Block the effect, not one tool. Guard every path.                |
| 4   | Optimization suggestions never run out           | Set a stopping rule first.                                       |
| 5   | A review gate must review what it gates          | Test the gate on its own first change. Act only on what matters. |
| 6   | Advisory steps get skipped                       | Put them in the procedure Claude follows, and expect misses.     |
| 7   | Claude cannot edit its own permissions and hooks | It hands over a tested patch instead.                            |
| 8   | Hooks and plugins are hard to see                | Only visible side effects prove a run.                           |

## 1. `/doctor` suggests removing the Tech Stack block

`/doctor` flagged the "Tech Stack" section of `CLAUDE.md` as removable (about 60 tokens, see [audit.md](audit.md)). The stack can be derived from `composer.json` and `package.json`, but the block is also a quick summary for humans and states versions the manifests do not make obvious (PHP 8.4, TypeScript 6, Vite 8).

**Decision:** kept. **Lesson:** an optimizer judges by tokens only. Decline a suggestion when the gain is negligible.

## 2. Rules files act like scoped `CLAUDE.md` files

A file in `.claude/rules/` is instruction text, like `CLAUDE.md`, but with a `paths:` list it loads only when matching files are involved. `clean-code.md` (about 1.1K tokens) is paid only in code work.

- `CLAUDE.md`: short, always relevant (stack, commands, architecture, non-negotiable conventions).
- Rules files: topic- or path-specific guidance.
- Skills: procedures run on demand (`api-endpoint`, `review-pr`).

**Limits**

- A rule is a request, not a guarantee (see finding 3).
- A path-scoped rule loads after the first matching file, not before. `clean-code.md` appeared only after the first frontend files were written, and they had empty `catch` blocks it forbids. Read the rule before writing when the work is clearly in its scope.
- Do not repeat a scoped rule in a skill and do not `@import` it into `CLAUDE.md`, which would load its tokens always. Keep it in one place.

## 3. Prose is a request, hooks are the control

`CLAUDE.md` and rules ask Claude to behave. Hooks make the harness enforce it.

- Phase 1, first hook setup: a blocked `Bash` call was followed by the same operation in PowerShell (reported by the user, not a logged test). The guard in [settings.json](../.claude/settings.json) now matches `Bash|PowerShell`, with the logic in [guard-bash.sh](../.claude/hooks/guard-bash.sh).
- Phase 1, writing docs: [format.sh](../.claude/hooks/format.sh) only matched PHP and frontend extensions, so Markdown stayed unformatted. It now covers `.md`.
- Phase 1, opening the PR: the guard also blocked a harmless `gh pr create --base main` chained after `git push`, because once a command contains `git push` it searches the whole text for `main`.
- Phase 1, re-testing the patched hook: a deny-list is enumeration and gets bypassed. The guard was bypassed with quotes, `$(...)` and line continuations, and a loosened version still passed `git push origin x; git push origin main` because of a stray `| head -n 1`. For a guard, prefer an allow-list or fail closed on input it cannot parse. A deny-list is fine for low-stakes hooks such as formatting.
- When loosening a guard, test what it must still block, not only what it should now allow. Test commands containing blocked text live in a script file.

**Lesson:** treat the hook as the control and the prose as the explanation. Check a hook's patterns against every file type and tool the project uses.

## 4. Optimization suggestions do not end

Every time Claude is asked how the setup could be improved, it finds more: another skill, MCP server, hook, global setting. The question has no stopping point, and a score that counts present features can push toward adding configuration (an inference, not measured).

- Sort every suggestion into Fix, Defer or Disagree, with a reason. [audit.md](audit.md) does this.
- Ignore points from features this project does not use (deploy commands for a local SQLite app).
- Configuration has a cost: resident context, more surfaces to break, more to maintain.

**Lesson:** decide up front what "good enough" means, then stop.

## 5. A review gate must review what it gates

**Situation:** in Phase 1 `review-pr` ran only after a PR existed, and the user had to remind Claude to run it before pushing.

**Action:** the rule went into `CLAUDE.md` and was run on its own change. That showed `review-pr` diffed only `main...HEAD`, which is empty before the first commit and misses new files. Fixed with `git diff HEAD` and `git status --short -uall`.

**Review-loop lessons**

- A recall-tuned reviewer never runs out of suggestions. In Phases 2, 4 and 5 `code-review high` returned 7 to 10 findings per round, with real bugs in only some rounds (3, 2, 3, 0, then 3, 3, 0). The rest repeated declined items.
- So the rule is limited to bugs, security issues and broken conventions, with a round cap (now 2), a declined-findings list in `finish-phase`, and a clean round defined as "no bug, security issue or broken convention left".
- Rerun `review-pr` and `code-review` after every fix, not only the tests. A fix can carry its own bug or leave a sibling path open, and the loop caught both. Say in the step what repeats, because round 2 once ran only `code-review`.
- Check a reviewer's claim against the code before acting. In Phase 5, round 3 claimed ids were shared across users, which was wrong.
- Phase 1, auth tests: fixture values that read as jokes or memes passed every check, because `review-pr`, `code-review` and `clean-code.md` look at bugs and conventions. A line in `review-pr` ("flag jokes or memes in fixtures") would cover it. Use neutral placeholders (`user1@example.com`).

## 6. Advisory steps get skipped

- **Docs first** (Phases 2 to 5, writing Laravel and Vue code; Boost `search-docs`, context7) was skipped in the plan, then in `CLAUDE.md`, then partly in a skill, then completely in Phase 5. A hook cannot enforce it because it cannot see whether Claude read the docs. What helped: a step 0 in `api-endpoint` and `frontend-feature`, now requiring one context7 query per library or a stated reason. Lint and type-check caught the mistakes the docs would have shown. Still advisory.
- A rule kept only in `plan.md` was skipped, as was `review-pr` until the user reminded Claude. Rules that must hold go in `CLAUDE.md`, and only a hook enforces them.
- Phase 2: a skill that asks "should I run?" wastes a turn. The `finish-phase` description says to run it once "Done when" holds. The approval gate stays before any commit, push or PR.
- Phase 2: "Done when" items that are not tests get skipped (`route:list`, the two-user curl check). The skill now runs or reports them.
- Phase 4, first frontend phase: a manual check should become a script the first time. The browser check is now the `browser-check` skill and rerun after the last fix.
- A fix on one side leaves the other open. "Docs first" covered only the backend, so the frontend got its own skill.
- Phase 1: put the human gate before the git writes. `finish-phase` gives a summary (files, checks, review fixes, proposed commit message and PR title) before any commit, then commits, pushes and opens the PR after approval. One decision point replaces two, and a change request costs no amend.
- A procedure belongs in a skill, not in prose. A hook blocking `gh pr create` until a review ran would enforce it; we chose not to add one.
- Keep the plan about what, not how. Process lives in `CLAUDE.md`.

## 7. Permissions and self-modification

- The auto-mode classifier denied Claude's edits to `settings.json` and `guard-bash.sh` as self-modification (Phases 1, 4, 5). Claude did the rest and handed over a tested patch or exact list (`git apply --check` plus a test script). The user applied it.
- Phase 5, renaming across a file: Claude worked around a denied edit. Three `Edit` calls were denied, then it ran `sed` on the same file. That ignores the denial. Stop and report instead of switching tools. The `phase` skill says so.
- Phase 4, long unattended loop: a denied edit is hard to attribute. `Edit` on `docs/plan.md` was denied once and passed on repeat, with no matching rule. The fix was an `Edit(docs/plan.md)` allow entry.
- Approval is a permissions matter. Keep the `allow` list narrow, and let `finish-phase` pre-approve only its own commands with `allowed-tools` (not `git push`, which stays on `ask`). Check the permission mode in `/permissions`, since the user cannot see whether the classifier approved a command.
- Phase 3: a narrow `allow` list produces explanation noise, so `Bash(ls:*)` was added. `git rm` stays off the list on purpose.
- Phase 5, flagged by `security-guidance`: allow-listing a script makes its arguments part of the attack surface. `browser-check.sh` took any argument, so `../../x.mjs` would have been run with `node`. It now accepts only `--migrate` and an existing `e2e-<name>.mjs`. Prefer allowing the exact no-argument form. `Bash(bash scripts/browser-check.sh:*)` is still the wide form, and Claude cannot change it.
- Broad write entries (`backend/app`, `tests`, `database`, `routes`) plus `php artisan test` let Claude run code it just wrote. Kept on purpose to cut prompt noise. The `.env` deny rules, the guard hook and the `git push` prompt remain.

## 8. Git write mistakes in `finish-phase`

- Phase 3: a staging command is a git write. `git rm` ran before approval, although only `git add -N` is allowed until then.
- Phase 4, committing: one bad path makes `git add` stage nothing. The file list included already-staged deletions, `git add` failed, and a `;` let `git commit` run anyway. Step 7 now leaves deletions out and checks `git diff --cached --stat` first.
- Phase 4, switching branches: an interrupted tool call may already have run. A chained `git checkout`, `git pull` and `git checkout -b` completed after an interrupt. Check `git branch` and `git status` before continuing.
- Phase 1: a Python script without `encoding="utf-8"` truncated `plan.md` on a `→` character. Prefer the Edit tool for Markdown.

## 9. Subagents, hooks and plugins

- Phase 1: a test subagent keeps context clean and caught a real config bug (`tests/Unit` missing from git). It cannot run lint or type-check (`npm run lint` uses `--fix`, `vue-tsc --build` writes a cache), so those run in the main session.
- Phases 4 and 5: subagent hand-backs arrive as messages from "another Claude session" and carry no user authority. None was treated as approval.
- `security-guidance` reviews commits and speaks up after the push, with its message injected into the session. After Phase 1 it reported two MEDIUM findings: the push check bypassable with quotes, `$(...)` or continuations, and a `pint --test:*` rule that also approved `--repair`. Both held up when we ran the old and new hook side by side, and the hook now refuses push arguments it cannot parse. After Phase 5 it flagged the new `allow` entries (see finding 7).
- Hook and plugin runs are hard to prove. `format.sh` leaves evidence (notices that it modified a file). `guard-bash.sh` and `security-guidance` showed no output in any phase, which does not prove they ran. `security-guidance` runs through its own `sg-python.sh` wrapper, so the Microsoft Store `python3` alias does not block it.
- The guard blocks `rm -r`, so the e2e cleanup deletes database rows instead of files.
