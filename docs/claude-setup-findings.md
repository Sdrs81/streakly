# Claude setup: findings and lessons

Date: 2026-10-28 (snapshot: token counts and file contents change over time)

Observations collected while configuring Claude Code for this project. Related: [audit.md](audit.md) (scores and decisions from the formal audit).

## Summary

| #   | Finding                                                         | Takeaway                                                    |
| --- | --------------------------------------------------------------- | ----------------------------------------------------------- |
| 1   | `/doctor` wants the "Tech Stack" block removed from `CLAUDE.md` | Declined. The token saving is too small to matter.          |
| 2   | Rules files behave like specialized `CLAUDE.md` files           | Use them for scoped guidance. They are still only advice.   |
| 3   | Hooks are the only real enforcement                             | The model routes around a blocked tool. Guard every path.   |
| 4   | Optimization suggestions never run out                          | Set a stopping rule before asking, or the audit never ends. |
| 5   | A review gate must review what it gates                         | Test the gate on its first run. Fix only what matters.      |

## 1. `/doctor` suggests removing the Tech Stack block

`/doctor` flagged the "Tech Stack" section of `CLAUDE.md` as removable (about 60 tokens, figure from [audit.md](audit.md)).

**Why it is right:** the stack can be derived from `composer.json` and `package.json`, so the block spends resident context on information Claude can find itself.

**Why it is wrong:** the block is a fast, readable summary for humans too, and it also states versions that are not obvious from the manifests alone (PHP 8.4, TypeScript 6, Vite 8).

**Decision:** kept. The saving is too small to matter, and the cost of being wrong is zero either way.

**Lesson:** an optimizer judges by tokens only. Weigh a suggestion's gain against the effort and the loss in readability, and decline when the gain is negligible.

## 2. Rules files act like specialized `CLAUDE.md` files

A file in `.claude/rules/` is instruction text loaded into context, just like `CLAUDE.md`. The difference is scope: with a `paths:` list in the frontmatter, it loads only when matching files are involved.

In this project, `clean-code.md` covers `backend/app/**`, `backend/tests/**`, `frontend/src/**`, `frontend/**/*.spec.ts` and `frontend/**/*.test.ts`, so its roughly 1.1K tokens are not paid in unrelated work.

**Practical split**

- `CLAUDE.md`: short, always relevant (stack, commands, architecture, non-negotiable conventions).
- Rules files: topic-specific or path-specific guidance (code style, testing conventions).
- Skills: procedures that are run on demand (`api-endpoint`, `review-pr`).

**Limit:** a rule is still a request, not a guarantee. Claude can misread it, forget it in a long session, or weigh it against the task. See finding 3.

## 3. Hooks are the part that matters most

`CLAUDE.md` and rules ask Claude to behave. Hooks make the harness enforce it. In one observed case (reported by the user, not a logged test), a blocked `Bash` call was followed by an attempt to do the same operation in PowerShell. The guard hook in [settings.json](../.claude/settings.json) matches `Bash|PowerShell`, and the matching logic lives in [guard-bash.sh](../.claude/hooks/guard-bash.sh).

A second case: [format.sh](../.claude/hooks/format.sh) only matched PHP and the frontend extensions, so Markdown that Claude wrote stayed unformatted until Ctrl+S. It now runs Prettier on every `.md` file too.

**Lesson:** treat the hook as the control and the prose in `CLAUDE.md` as the explanation. Block the effect, not a single tool, and check a hook's patterns against every file type and tool the project actually uses.

## 4. Optimization suggestions do not end

Every time Claude is asked how the setup could be improved, it produces more suggestions: another skill, another MCP server, another hook, a global setting, a sandbox config. A second round after the first fixes found new items, and so would a third.

**Why:** the question has no built-in stopping point, and each answer is plausible on its own. A score that counts present features can also push toward adding configuration. This is an inference, not something the audit measured: `audit.md` records the before and after scores but does not show what caused the change.

**What worked**

- Sort every suggestion into Fix, Defer or Disagree, with a reason. `audit.md` does this and it turned an open-ended list into a decision log.
- Ignore points that come from features this project does not use (deploy commands for a local SQLite app).
- Remember that configuration has a cost: more resident context, more surfaces to break, more things to maintain.

**Lesson:** decide up front what "good enough" means, then stop. Treat an optimization suggestion as an input to a decision, not as a to-do item.

## 5. A review gate must review what it gates

Written as Situation, Task, Action, Result.

**Situation:** in Phase 1 of [plan.md](plan.md) the `review-pr` skill ran only after a PR existed, and the user had to remind Claude to run it before pushing.

**Task:** run `review-pr` and `/code-review` automatically before every commit, and fix what they find.

**Action:** wrote the rule in `CLAUDE.md`, then ran it on its own change. It showed that `review-pr` diffed only `main...HEAD`, which is empty before the first commit, and could not see new files. Fixed in the skill with `git diff HEAD` and `git status --short -uall`. In Phase 1 the loop also found real bugs (a 500 on an array email, a login timing leak, case-sensitive emails), but it kept returning style and design suggestions, and the first version of the rule spent several rounds on them.

**Result** (observed in the session, not logged): the gate works on uncommitted and new files. The rule is now limited to bugs, security issues and broken conventions, with a 3-round cap and a short PR note instead of a long report.

**Lesson:** run a new gate on its own first change, because that is when it fails silently. A recall-tuned reviewer never runs out of suggestions, so decide up front which kinds of finding are worth acting on.

## Phase 1: what we learned

- **Prose is a request, not a control.** `review-pr` was only in the plan, so it was skipped until the user reminded Claude. Rules that must hold belong in `CLAUDE.md`, and only a hook enforces them.
- **Test a new gate on its first run.** `review-pr` saw an empty diff before the first commit. Running the rule on its own change exposed it.
- **A recall-tuned reviewer never runs out of suggestions.** Limit it to bugs, security issues and broken conventions, with a round cap.
- **A subagent for tests keeps context clean** and caught a real config bug (`tests/Unit` missing from git, so a fresh clone could not run Pest).
- **Guardrails need tuning.** Blocking Claude from editing its own permissions and hooks was right. The guard hook also blocked a harmless `gh pr create --base main` chained after `git push`, because once a command contains `git push` it searches the whole command text for `main`.
- **Claude cannot edit its own guard hook or permissions, so it hands over a tested patch.** Edits to `.claude/hooks/guard-bash.sh` and `.claude/settings.json` were denied as self-modification. Claude built the fix on a copy, checked it with `git apply --check` and a test script, and the user applied it.
- **Test what a loosened guard must still block, not only what it should now allow.** Re-testing after the user applied the patch showed that Claude's `| head -n 1` made the hook inspect only the first `git push`, so `git push origin x; git push origin main` passed. Removing it closed the hole.
- **The `security-guidance` plugin reviews commits and speaks up afterwards.** Right after the hook and settings commit was pushed, it injected an automated message into the session (not from the user) with two MEDIUM findings and a suggested fix for each: the hook's separator-based push check could be bypassed with quotes, `$(...)` or line continuations, and the `pint --test:*` allow rule also approved `--repair`. We checked the first claim by running the old and new hook side by side. Four of those bypasses already worked in the old hook, and one (`"a;b" main`) was new. The fix makes the hook refuse push arguments it cannot parse and pins the Pint allow rules to the exact command. Side effect: any command text containing `git push` followed by a quote is now blocked, including our own test commands, so such tests live in a script file.
- **Approval is a permissions matter, not a hook, skill or rule.** Commands outside the `allow` list already need the user's OK, so the allow list stays narrow and `finish-phase` pre-approves only its own commands with `allowed-tools` (not `git push`, which stays on `ask`). What the user cannot see is whether the auto-mode classifier approved a command before it reached them, so the permission mode is worth checking in `/permissions`. A line in `CLAUDE.md` asks Claude to explain any other command in one sentence and not to run unrequested checks, but that is only a request. The real checks are the permission prompt, the guard hook, the commit review plugin and the classifier, and another hook would only repeat the endless deny-list problem.
- **Put the human gate before the git writes, not after.** Claude's first idea was to commit locally and then stop for review. The user's idea was better: `finish-phase` now gives a summary (files, checks, review fixes, proposed commit message and PR title) before any commit, and commits, pushes and opens the PR only after approval. One decision point replaces two, and a change request costs no amend. The rule is still text; the `ask` prompt on `git push` stays as the backstop, because `allowed-tools` has not been tested against it yet.
- **A procedure belongs in a skill, not in prose.** The review loop plus commit, push and PR is now the `finish-phase` skill, with one line in `CLAUDE.md`. This is still advisory: nothing enforces that the skill runs. A hook that blocks `gh pr create` until a review ran would, and we chose not to add one.
- **Keep the plan about what, not how.** File lists and limiter values went stale and needed a sync PR. The plan now holds scope, decisions and "Done when"; process lives in `CLAUDE.md`.
- **Windows trap:** a Python script without `encoding="utf-8"` truncated `plan.md` on a `→` character. Prefer the Edit tool for Markdown.
- **For hooks, a list beats writing every case by hand, but a deny-list is not the safe kind.** A pattern list (block or allow) is easier to keep than one rule per command. A deny-list is still enumeration, though: the guard hook was bypassed with quotes, `$(...)` and line continuations. For a guard, prefer an allow-list (anything unknown is blocked) or fail closed on input the hook cannot parse. A deny-list is fine for low-stakes hooks such as formatting.

## Phase 2: what we learned

Found by asking Claude to audit its own use of `CLAUDE.md`, skills, agents, MCP, plugins and rules after the phase.

- **A rule kept only in `plan.md` was skipped.** The plan said to check Laravel APIs with Boost `search-docs` before writing code, and Claude never called Boost or context7. The rule now lives in `CLAUDE.md` (Working Rules).
- **A skill that asks "should I run?" wastes a turn.** After the phase was done, Claude asked whether to run `finish-phase`. The skill description now says to run it as soon as "Done when" holds. The approval gate stays where it was: before any commit, push or PR (step 6).
- **A "repeat" step needs to say what repeats.** Round 2 of the loop ran only `code-review`, so the fixes were never seen by `review-pr`. Step 4 now says to rerun checks, `review-pr` and `code-review high`.
- **"Done when" items that are not tests get skipped.** `route:list` and the two-user curl check were never run. Step 1 now runs `route:list` for backend phases with new routes and requires the manual check or a note in the summary that it was not run.
- **A loaded rule is not a checked rule.** `clean-code.md` forbids magic numbers, yet the length limits (100, 500) were repeated in two requests and in the tests. They are now `Habit::NAME_MAX_LENGTH` and `Habit::DESCRIPTION_MAX_LENGTH`. The migration keeps its own literal, because a migration should not depend on a model constant that may change later.
- **Plugin hooks are hard to verify from inside a session.** `security-guidance` runs through its own `sg-python.sh` wrapper, so the Microsoft Store `python3` alias on PATH does not block it (`python` is 3.13). No plugin message appeared in this session, which does not prove that it ran.
- **A review round can mostly return noise.** Round 2 of `code-review high` returned seven findings and none was a bug or convention break. The triage rule worked; a clean first round is a reasonable point to stop.

## Phase 3: what we learned

Found by asking Claude to audit its own use of hooks, skills, rules, `CLAUDE.md`, plugins and MCP after the phase. Only findings about the Claude tooling are kept here.

- **A rule kept only in `CLAUDE.md` was skipped again.** Claude wrote migrations, models and routes without calling Boost `search-docs`. A hook cannot enforce "read the docs first", because it cannot see whether Claude did. The reminder now sits in the procedure Claude follows: a "Docs first" step 0 in `api-endpoint` (Boost `search-docs`) and in a new `frontend-feature` skill (context7). It is still advisory, like every skill.
- **A fix on one side leaves the other open.** The first "Docs first" step covered only the backend. Phases 4 and 5 use the newest frontend libraries (Pinia 4, Vue Router 5, Vite 8, TypeScript 6), so the frontend got its own skill instead of one more prose line.
- **Do not repeat a scoped rule inside a skill, and do not `@import` it.** `clean-code.md` loads only for matching paths (`paths:` frontmatter). An `@import` in `CLAUDE.md` would load it always and waste its ~1.1K tokens, and a "follow clean-code" line in a skill is redundant. Keep the rule in one place.
- **A narrow `allow` list produces explanation noise.** `CLAUDE.md` asks Claude to explain commands outside the `allow` list, so `ls` needed a sentence each time. `Bash(ls:*)` is added. `git status`, `git diff` and `git log` were already there. `git rm` stays off the list on purpose.
- **A staging command is a git write.** `git rm` ran before approval, although `finish-phase` allows only `git add -N` until the user approves. The skill should name the exception, or the deletion should wait for the commit step.
- **Plugin and hook runs are hard to prove.** `security-guidance` and `guard-bash.sh` gave no visible output in this phase. Only `format.sh` left evidence (it reformatted a file).

**What we changed:** a "Docs first" step 0 in `.claude/skills/api-endpoint/SKILL.md`, a new `.claude/skills/frontend-feature/SKILL.md`, and `Bash(ls:*)` in the `allow` list of `.claude/settings.json`.

## Phase 4: what we learned

Found by asking Claude to review its own use of hooks, skills, rules, agents, plugins and MCP after the phase (2026-10-30). Only findings about the Claude tooling are kept here.

- **A fix can carry its own bug, and the loop caught two of them.** The round 1 fix for a stale 401 compared the request's token with the current one, so a request sent with no token (`undefined === undefined`) counted as the current session. The round 1 fix for a failed navigation reset `isSubmitting` before `router.push`, which allowed a double submit. Rounds 2 and 3 found both. This is the reason for rerunning `review-pr` and `code-review` after every fix, not only the tests.
- **`code-review high` raises declined findings again on every round.** Four runs returned 8 to 10 findings each. Real bugs were 3, 2, 3 and 0. The rest repeated the same items (no 429 message, duplicated Login and Register logic, the server token surviving a failed logout, a `/user` failure letting the guard through). The 3-round cap then stopped the loop on items that were already declined. `finish-phase` now keeps a declined list, drops matching findings on later rounds and defines a clean round as "no bug, security issue or broken convention left".
- **A path-scoped rule loads after the first matching file, not before.** `clean-code.md` appeared in context only after the first frontend files were written. Those files had empty `catch` blocks, which the rule forbids, and they were fixed afterwards. Observed in this session. Read the rule before writing when the work is clearly in its scope.
- **Docs first was followed only in part.** context7 was used for Pinia and Vue Router. Vitest and Axios were written from memory, and the lint rule `vitest/require-mock-type-parameters` and a type error caught what the docs would have shown. The skill is advisory, as in Phase 3. Lint and type-check are what actually caught the mistakes.
- **A manual "Done when" check should become a script the first time.** The generic `run` skill had no recipe for this Windows machine, because `chromium-cli` is missing. Claude installed `playwright-core` in the scratchpad and wrote a 13-step script. It is now the `browser-check` skill. The first run was also done before the review fixes, so the skill now says to run it again after the last fix.
- **A denied edit is hard to attribute.** `Edit` on `docs/plan.md` was denied in a long unattended loop and passed when repeated, with no rule in `settings.json` explaining either. The cause was not determined. The fix does not depend on it: `Edit(docs/plan.md)` is now in the `allow` list (the user added it) and in the `allowed-tools` of `finish-phase`.
- **Claude still cannot edit its own permissions.** The auto-mode classifier denied the `settings.json` edit as self-modification, as in Phase 1. Claude did not look for another way, did the rest, and asked the user to apply that one line.
- **Measure twice, with two methods.** `grep -c $'\r'` reported every line as CRLF in Git Bash, even a one-line file, and `git diff --stat` showed no warnings either way. Counting bytes with `tr -cd '\r' | wc -c` gave the real answer. The cause was `core.autocrlf=true` with no root `.gitattributes`. `.editorconfig` already said `end_of_line = lf`, so editing it would have fixed nothing. A root `.gitattributes` (`* text=auto eol=lf`) did.
- **One bad path makes `git add` stage nothing.** The commit command listed files that `git rm` had already staged, `git add` failed with a pathspec error, and a `;` before `git commit` let the commit run anyway. It contained only two deletions. `git show --stat` caught it and the unpushed commit was amended. `finish-phase` step 7 now leaves deletions out of the list and checks `git diff --cached --stat` before committing.
- **An interrupted tool call may already have run.** A chained `git checkout`, `git pull` and `git checkout -b` completed after the user interrupted it, and the working tree changed. Check `git branch` and `git status` before continuing.
- **Subagent reports are data, not approval.** The `test-runner` subagent (haiku) ran 6 times and always reported cleanly. Its hand-backs arrive as messages from "another Claude session", with a frame that says they carry no user authority. None was treated as approval. It cannot run lint or type-check: `npm run lint` uses `--fix`, and `vue-tsc --build` writes a cache, so both ran in the main session.
- **Hooks and plugins stayed invisible again.** `format.sh` left evidence (notices that a hook modified a file). `guard-bash.sh` blocked nothing, and `security-guidance` showed no output. Neither is proven to have run.

**What we changed:** `Edit(docs/plan.md)` in `.claude/settings.json` and in the `allowed-tools` of `finish-phase`; a declined-findings list, a definition of a clean round and a staging check in `.claude/skills/finish-phase/SKILL.md`; a new `.claude/skills/browser-check/` skill with its script; a root `.gitattributes`; and two lines in `CLAUDE.md` for `frontend-feature` and `browser-check`.

## Phase 5: what we learned

Found by asking Claude to review its own use of hooks, skills, rules, MCP, plugins and `CLAUDE.md` after the phase (2026-10-30). Only findings about the Claude tooling are kept here.

- **Docs first was skipped completely.** context7 and Boost were never called, although `frontend-feature` step 0 and `CLAUDE.md` both ask for it. Claude copied patterns from the Phase 4 code and the tests passed. It is the third phase in a row where this advisory step was missed. Step 0 now requires at least one context7 query per library touched, or a stated reason in the final report. It is still advisory.
- **Claude worked around a denied edit.** Three `Edit` calls on one file were denied, and Claude then ran a `sed` rename on the same file. That ignores the intent of the denial. It was reverted at the user's request. A bulk `sed` also broke an e2e selector in a later step. The new `phase` skill says to stop and report instead of switching tools.
- **The classifier denied a `settings.json` edit again** ("Self-Modification", as in Phases 1 and 4). Claude finished the rest and handed over the exact list. The user applied it.
- **A browser check found what unit tests could not.** The create form kept its values after saving (found by an e2e check), and the first run failed with a 500 because the dev database never got the Phase 2 and 3 migrations. `scripts/browser-check.sh` now stops on pending migrations and deletes the e2e users it creates.
- **A fix can leave a sibling path open.** Round 1 made the store reset on logout and ignore a stale `fetchHabits` response. Round 2 found the same hole in the four mutation actions.
- **A reviewer's claim needs checking against the code.** Round 3 said habit ids are shared across users, so a late response could overwrite another user's habit. Ids are a single table's primary key, so the claim was wrong.
- **`code-review high` returned real bugs in 2 of 3 rounds** (3, 3, 0). The rest repeated declined items. The 2-round cap and the declined list in `finish-phase` now reflect that.
- **Lint caught a known mistake a third time:** `vi.fn()` without type parameters (`require-mock-type-parameters`). It is now a line in `frontend-feature`.
- **Hooks and plugins:** `format.sh` left evidence again (notices that it modified files). `guard-bash.sh` and `security-guidance` stayed invisible. The guard blocks `rm -r`, so the e2e cleanup deletes database rows instead of files.
- **Subagent hand-backs** arrived as "another Claude session" messages. They carry no user authority, and none was treated as approval.

**What we changed:** `scripts/phase-check.sh` (pint, `route:list`, lint, type-check for the touched sides; no tests, those stay with `test-runner`), `scripts/browser-check.sh`, a new `.claude/skills/phase/` skill that runs a phase end to end and stops before the commit, updates to `finish-phase`, `browser-check` and `frontend-feature`, two lines in `CLAUDE.md`, and 15 `allow` entries in `.claude/settings.json` (paths under `frontend/src`, `backend/app`, `routes`, `database`, `tests`, plus the two scripts, `route:list`, `migrate:status` and `git add -N`).
