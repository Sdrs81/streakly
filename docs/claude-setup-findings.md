# Claude setup: findings and lessons

Date: 2026-10-28

Observations collected while configuring Claude Code for this project. Related: [audit.md](audit.md) (scores and decisions from the formal audit).

## Summary

| #   | Finding                                                         | Takeaway                                                    |
| --- | --------------------------------------------------------------- | ----------------------------------------------------------- |
| 1   | `/doctor` wants the "Tech Stack" block removed from `CLAUDE.md` | Technically right, practically irrelevant. Kept it.         |
| 2   | Rules files behave like specialized `CLAUDE.md` files           | Use them for scoped guidance. They are still only advice.   |
| 3   | Hooks are the only real enforcement                             | The model routes around a blocked tool. Guard every path.   |
| 4   | Optimization suggestions never run out                          | Set a stopping rule before asking, or the audit never ends. |

## 1. `/doctor` suggests removing the Tech Stack block

`/doctor` flagged the "Tech Stack" section of `CLAUDE.md` as removable (about 60 tokens).

**Why it is right:** the stack can be derived from `composer.json` and `package.json`, so the block spends resident context on information Claude can find itself.

**Why it is wrong:** the block is a fast, readable summary for humans too, and it also states versions that are not obvious from the manifests alone (PHP 8.4, TypeScript 6, Vite 8).

**Decision:** kept. The saving is too small to matter, and the cost of being wrong is zero either way.

**Lesson:** an optimizer judges by tokens only. Weigh a suggestion's gain against the effort and the loss in readability, and decline when the gain is negligible.

## 2. Rules files act like specialized `CLAUDE.md` files

A file in `.claude/rules/` is instruction text loaded into context, just like `CLAUDE.md`. The difference is scope: with a `paths:` list in the frontmatter, it loads only when matching files are involved.

In this project, `clean-code.md` covers `backend/app/**`, `backend/tests/**` and `frontend/src/**`, so its roughly 1.1K tokens are not paid in unrelated work.

**Practical split**

- `CLAUDE.md`: short, always relevant (stack, commands, architecture, non-negotiable conventions).
- Rules files: topic-specific or path-specific guidance (code style, testing conventions).
- Skills: procedures that are run on demand (`api-endpoint`, `review-pr`).

**Limit:** a rule is still a request, not a guarantee. Claude can misread it, forget it in a long session, or weigh it against the task. See finding 3.

## 3. Hooks are the part that matters most

`CLAUDE.md` and rules ask Claude to behave. Hooks make the harness enforce it. The difference showed up quickly: when one tool was blocked, the model found another route to the same result. Blocking `Bash` led it to try PowerShell for the same operation.

**Lesson:** treat the hook as the control and the prose in `CLAUDE.md` as the explanation. Block the effect, not a single tool.

## 4. Optimization suggestions do not end

Every time Claude is asked how the setup could be improved, it produces more suggestions: another skill, another MCP server, another hook, a global setting, a sandbox config. A second round after the first fixes found new items, and so would a third.

**Why:** the question has no built-in stopping point, and each answer is plausible on its own. Scores also reward adding things (see the 71 to 80 jump in `audit.md`), which pushes toward more configuration, not better configuration.

**What worked**

- Sort every suggestion into Fix, Defer or Disagree, with a reason. `audit.md` does this and it turned an open-ended list into a decision log.
- Ignore points that come from features this project does not use (deploy commands for a local SQLite app).
- Remember that configuration has a cost: more resident context, more surfaces to break, more things to maintain.

**Lesson:** decide up front what "good enough" means, then stop. Treat an optimization suggestion as an input to a decision, not as a to-do item.
