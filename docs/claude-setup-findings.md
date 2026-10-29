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
- **A procedure belongs in a skill, not in prose.** The review loop plus commit, push and PR is now the `finish-phase` skill, with one line in `CLAUDE.md`. This is still advisory: nothing enforces that the skill runs. A hook that blocks `gh pr create` until a review ran would, and we chose not to add one.
- **Keep the plan about what, not how.** File lists and limiter values went stale and needed a sync PR. The plan now holds scope, decisions and "Done when"; process lives in `CLAUDE.md`.
- **Windows trap:** a Python script without `encoding="utf-8"` truncated `plan.md` on a `→` character. Prefer the Edit tool for Markdown.
