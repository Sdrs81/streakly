# Claude structure

Date: 2026-11-03 (snapshot of `.claude/`, `.mcp.json`, `scripts/` and `CLAUDE.md`)

Every Claude tool this project uses: what it is for, when it was added and how it is configured. "Setup" means the `setup/claude-tooling` work on 2026-10-27, before Phase 1. Why each choice was made is in [claude-setup-findings.md](claude-setup-findings.md) and [audit.md](audit.md). The phases are in [plan.md](plan.md).

## Overview

| Type              | Tools                                                                                     |
| ----------------- | ----------------------------------------------------------------------------------------- |
| `CLAUDE.md`       | 1 file                                                                                    |
| Rules             | `clean-code`                                                                              |
| Skills            | `api-endpoint`, `review-pr`, `finish-phase`, `frontend-feature`, `browser-check`, `phase` |
| Agents            | `test-runner`                                                                             |
| Hooks             | `guard-bash.sh` (PreToolUse), `format.sh` (PostToolUse)                                   |
| `settings.json`   | permissions (`allow`, `deny`, `ask`), hooks, plugin                                       |
| MCP servers       | `laravel-boost`, `context7`                                                               |
| Plugins           | `security-guidance`                                                                       |
| Scripts           | `scripts/phase-check.sh`, `scripts/browser-check.sh`                                      |
| Built-in commands | `/code-review` (review loop), `/doctor` (health check)                                    |

## `CLAUDE.md`

| Added | Purpose                                                                                                                                     |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| Setup | Always-loaded context (about 480 tokens at the first audit): stack, commands, dev environment, architecture and conventions, working rules. |

- **Stack and commands:** the six run, test and lint commands.
- **Dev environment:** what must be on PATH for the tooling (Python, `jq`, Git Bash, PHP 8.4, Node).
- **Architecture:** JSON API only, Sanctum Bearer tokens, no Inertia, no cookie auth.
- **Conventions:** Policies for authorization, FormRequests for validation, API Resources for output, Pinia for state, one Axios instance.
- **Working rules:** grew by phase. Docs first (Phases 2 and 3), `finish-phase` before commits (Phase 1), `frontend-feature` and `browser-check` (Phase 4), `phase`, the two scripts and "explain commands outside `allow`" (Phase 5). Never commit to `main`.
- **Not used:** a global `~/.claude/CLAUDE.md` (deferred in the audit).

## Rules

| Rule         | Added | Purpose                                           |
| ------------ | ----- | ------------------------------------------------- |
| `clean-code` | Setup | Naming, function size, comments, no magic numbers |

- **Scope:** `paths:` frontmatter limits it to `backend/app`, `backend/tests`, `frontend/src` and the spec and test files. It costs about 1.1K tokens, only in code work.
- **Limit:** it loads after the first matching file, not before.

## Skills

All use `effort: medium`. Located in `.claude/skills/<name>/SKILL.md`.

| Skill              | Added   | Purpose                                                                              | Configuration                                                                                                                                                                              |
| ------------------ | ------- | ------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `api-endpoint`     | Setup   | Adds a backend endpoint: route, FormRequest, Policy, controller, Resource, Pest test | `allowed-tools`: file tools, `Agent`, `php artisan make:*` and `route:list`. Step 0 "Docs first" (Boost `search-docs`) added in Phase 3                                                    |
| `review-pr`        | Setup   | Read-only review of the branch diff against the project conventions                  | `allowed-tools`: read tools and `git diff/log/status`. `disallowed-tools`: `Write`, `Edit`, `NotebookEdit`. Diffs `git diff HEAD` plus untracked files since Phase 1                       |
| `finish-phase`     | Phase 1 | Checks, review loop, summary, then commit, push and a short PR                       | `allowed-tools`: `git add`, `git commit`, `gh pr create`, `Edit(docs/plan.md)`. `git push` stays on `ask`. Review capped at 2 rounds, declined-findings list, summary before any git write |
| `frontend-feature` | Phase 3 | Adds or changes frontend code: view, component, store, router, API call, Vitest test | `allowed-tools`: file tools, `Agent`, context7. Step 0 requires a context7 query per library touched                                                                                       |
| `browser-check`    | Phase 4 | Runs a frontend phase's "Done when" flow in real Chrome against the live API         | No `allowed-tools`. Ships `e2e-auth.mjs` (Phase 4) and `e2e-habits.mjs` (Phase 5). Driven by `scripts/browser-check.sh`                                                                    |
| `phase`            | Phase 5 | Runs a whole phase from a fresh branch to the summary, and stops before the commit   | No `allowed-tools`. Calls the other skills. Stops and reports on a denied edit instead of switching tools                                                                                  |

## Agents

| Agent         | Added | Purpose                                                                                            | Configuration                                                                    |
| ------------- | ----- | -------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `test-runner` | Setup | Runs Pest or Vitest and reports only the outcome and failing tests, to keep the main context clean | `model: haiku`, `tools: Bash, Read, Grep, Glob`. Does not run lint or type-check |

## Hooks

Both are scripts in `.claude/hooks/`, wired in `settings.json`, and both need `jq`.

| Hook            | Event and matcher                     | Added | Purpose                                                  | Configuration                                                                                                                                                                                                                |
| --------------- | ------------------------------------- | ----- | -------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `guard-bash.sh` | PreToolUse, `Bash\|PowerShell`        | Setup | Blocks dangerous commands (exit 2 feeds the reason back) | Blocks recursive deletes, `git push` to `main` or while on `main`, force pushes and commits on `main`. Fails closed without `jq` or on push arguments it cannot parse. Patched in Phase 1 (PowerShell matcher, push parsing) |
| `format.sh`     | PostToolUse, `Edit\|MultiEdit\|Write` | Setup | Formats the file Claude just wrote                       | PHP with Pint, frontend files with Prettier, every `.md` with Prettier (added in Phase 1). Skips dependencies. Never blocks                                                                                                  |

## `settings.json`

`.claude/settings.json`, committed.

| Section          | Added                | Purpose                               | Configuration                                                                                                                                                                                                                                                                                                                                 |
| ---------------- | -------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `deny`           | Setup                | Protect secrets and history           | Reading, editing and writing `.env` and `.env.*`; reading `*.pem` and `*credentials*`; `git clean`                                                                                                                                                                                                                                            |
| `ask`            | Setup                | Keep one human gate on publishing     | `git push`                                                                                                                                                                                                                                                                                                                                    |
| `allow`          | Setup, grew by phase | Remove prompt noise for safe commands | Test, lint and type-check commands and `pint --test` (Setup); `git status/diff/log` and `ls` (Phase 3); `Edit(docs/plan.md)` (Phase 4); `phase-check.sh`, `browser-check.sh`, `route:list`, `migrate:status`, `git add -N` and prompt-free `Edit` and `Write` under `frontend/src`, `backend/app`, `routes`, `database` and `tests` (Phase 5) |
| `hooks`          | Setup                | Registers the two hooks above         | See Hooks                                                                                                                                                                                                                                                                                                                                     |
| `enabledPlugins` | Setup                | Turns on `security-guidance`          | See Plugins                                                                                                                                                                                                                                                                                                                                   |

Claude cannot edit this file (the classifier blocks it as self-modification), so every change here was applied by the user.

## MCP servers

`.mcp.json`, committed.

| Server          | Added                 | Purpose                                                         | Configuration                                                                                                                         |
| --------------- | --------------------- | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `laravel-boost` | Setup                 | Laravel, Sanctum and Pest docs, DB schema and queries, app info | Local: `php backend/artisan boost:mcp`. A Composer dependency. Used through `search-docs`, `database-connections`, `application-info` |
| `context7`      | Setup (audit round 1) | Docs for Pinia, Vue Router, Tailwind, Vitest, Axios             | Remote HTTP, `https://mcp.context7.com/mcp`. Third party (Upstash), works without a key with lower rate limits                        |

## Plugins

| Plugin              | Added | Purpose                                                           | Configuration                                                                                                                         |
| ------------------- | ----- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `security-guidance` | Setup | Reviews commits and speaks up after a push with security findings | From `claude-plugins-official`. Needs Python 3.10+. Runs through its own `sg-python.sh` wrapper. Raised findings after Phases 1 and 5 |

## Scripts

Plain scripts in `scripts/`, called by skills and pre-approved in `allow`.

| Script             | Added   | Purpose                                                                | Configuration                                                                                                                               |
| ------------------ | ------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `phase-check.sh`   | Phase 5 | Pint, `route:list`, lint and type-check for the sides a phase touched  | Takes an optional base branch. No tests (those go through `test-runner`). `npm run lint` uses `--fix`, so it can rewrite files              |
| `browser-check.sh` | Phase 5 | Starts both servers, runs the `e2e-*.mjs` scripts in Chrome, cleans up | Accepts only `--migrate` or an existing `e2e-<name>.mjs`. Stops on pending migrations, refuses busy ports, deletes the e2e users it created |

## Built-in commands

| Command        | Used for                                                                                         |
| -------------- | ------------------------------------------------------------------------------------------------ |
| `/code-review` | Second reviewer in the `finish-phase` loop (`high`). Built in, cannot be edited from the project |
| `/doctor`      | Health check, run in both audit rounds                                                           |

## What we would prepare next time

Written from [claude-setup-findings.md](claude-setup-findings.md), [audit.md](audit.md) and [plan.md](plan.md). The goal: faster, fewer approval prompts, same quality.

### Where time and prompts went

- **Tooling arrived phase by phase.** `finish-phase` came in Phase 1, `frontend-feature` in Phase 3, `browser-check` in Phase 4, `phase` and the scripts in Phase 5. Each phase paid for the gap before its tool existed (manual checks, reminders, repeated explanations).
- **The `allow` list grew by hand.** Prompts for safe commands (`ls`, `git status`, writes inside the source folders, one `plan.md` edit) were found one by one, and Claude could not add them itself.
- **Guard hook bugs cost rounds.** The PowerShell bypass, the `gh pr create --base main` false positive and the quote and `$(...)` bypasses were found after commits, not before.
- **Advisory steps were skipped.** Docs first was missed in four phases, and `review-pr` ran late until the user reminded Claude.
- **The review loop returned noise.** Most `code-review high` rounds repeated declined items, and the first loop had no cap.
- **Phase 1 setup mistakes cost the most:** an empty diff in `review-pr`, a missing `tests/Unit` in git, and Markdown not formatted.

### Prepare before Phase 1

1. **Write the full `settings.json` once.** Include the whole `allow` list that Phase 5 ended with, the narrow-by-design entries (exact command, not a wildcard, for scripts) and the `deny` and `ask` lists. Check the permission mode in `/permissions`. Claude cannot do this itself, so it is one session of the user's time instead of five.
2. **Build the guard hook as an allow-list or fail-closed from the start, with a test script.** Cover Bash and PowerShell, quotes, `$(...)`, line continuations and chained commands, and test what it must still block after every change. Put the test script in `scripts/` so the hook is testable.
3. **Ship the phase workflow on day one:** `phase`, `finish-phase`, `review-pr`, `test-runner`, `phase-check.sh`. Test `review-pr` on its own first change and on an uncommitted diff before the first real phase.
4. **Write both "Docs first" skills at the start** (`api-endpoint` and `frontend-feature`) with the per-library context7 requirement, and keep the plan free of process rules.
5. **Set the review-loop rules up front:** only bugs, security issues and broken conventions; a 2-round cap; a declined-findings list; a clean round defined; check a reviewer's claim against the code. Add the "neutral fixtures" line to `review-pr`.
6. **Create `browser-check` with the first frontend phase,** and the e2e script as part of that phase's tasks, not as a fix after the first manual run.
7. **Define "Done when" so a script can check it,** including `route:list` and the multi-user check. Items that are only prose get skipped.
8. **Set the optimization stopping rule** (finding 4) and run `/doctor` and the audit once, at the start and at the end.

### Claude tools that would have helped

| Need                                                | Tool                                                                                                                |
| --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Fewer permission prompts without hand-editing       | `fewer-permission-prompts` skill, run after the first phase to build the `allow` list from real usage               |
| Settings and hook changes Claude cannot make itself | `update-config` skill, with the user applying the result                                                            |
| A phase that runs while the user is away            | `phase` skill plus auto mode, once the guard hook and `deny` list are trusted                                       |
| Enforcing the review gate                           | A `PreToolUse` hook on `gh pr create` that checks a marker file written by `finish-phase` (we chose not to add one) |
| Checking Docs first                                 | No hook can see it. Keep the skill step, and make the final report list the queries                                 |
| Proving hooks and plugins ran                       | A log line in each hook (`guard-bash.sh` blocks, `format.sh` runs) so a session can show evidence                   |
| A faster, cheaper review of simple phases           | `/code-review medium` for small diffs, `high` only for risky ones                                                   |

### What to keep as it is

- One human gate, before the commit. `git push` stays on `ask`.
- Secrets in `deny`, and no self-edits to permissions or hooks.
- The `test-runner` subagent on `haiku` for tests, with lint and type-check in the main session.
- Small always-loaded context (`CLAUDE.md` about 480 tokens, rules scoped by `paths:`).
- One branch and one PR per phase, a plan that holds scope and "Done when", and process in `CLAUDE.md` and skills.

### Order of work

1. Settings, guard hook and its test script.
2. `CLAUDE.md`, `clean-code`, both docs-first skills, MCP servers.
3. `test-runner`, `phase-check.sh`, `review-pr`, `finish-phase`, `phase`.
4. Run the whole workflow once on a trivial change and fix what breaks.
5. Start Phase 1, adding `browser-check` with the first frontend phase.
