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
| 5   | A review gate must review what it gates                         | Test the gate on its first real run. Cap the loop.          |

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

**Situation:** in Phase 1 of [plan.md](plan.md) the `review-pr` skill ran only after a PR existed, and the user had to remind Claude to run it before pushing. The rule was in the plan, not in `CLAUDE.md`.

**Task:** make the review run automatically before every commit and push, add `/code-review` after it, and loop on serious findings.

**Action:** wrote the rule in `CLAUDE.md` and `docs/plan.md`, then ran the new loop on its own change. It exposed that `review-pr` diffed only `main...HEAD`. Before the first commit that diff is empty, so the gate would have reported "ready to merge" on unreviewed work. Later rounds found two more gaps: new files are invisible to `git diff HEAD`, and `git status --short` collapses a new directory into one line. Fixed in the skill with `git diff HEAD` and `git status --short -uall`. Pinned `/code-review` to `high` because it reuses the last level typed, and capped the loop at 3 rounds.

**Result** (observed in the session, not logged): the gate now sees uncommitted and new files, though the last `-uall` fix was not re-verified by another round. The loop did not converge on its own: it reached the cap, stopped to ask the user, and was committed on the user's approval with the remaining findings accepted as minor. Neither reviewer labels severity, so Claude classifies "critical or important" by judgment. Adding Pint and type-check to the allow list was denied as a self-modification of permissions, so those commands may still prompt until the user adds them to `.claude/settings.json`.

**Lesson:** run a new gate on its own first change, because that is when it fails silently. Define the stopping rule (a round cap) before the loop, and expect a recall-tuned reviewer to keep producing findings.
