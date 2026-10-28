---
name: review-pr
description: Reviews the current branch diff against Streakly conventions (Policies, FormRequests, API Resources, Pinia, single Axios instance). Use when asked to review a PR, a branch, or the current changes.
effort: medium
allowed-tools: Read, Grep, Glob, Bash(git diff:*), Bash(git log:*), Bash(git status:*)
disallowed-tools: Write, Edit, NotebookEdit
---

# Reviewing a PR

Read-only: report findings, never edit files.

## Steps

1. Run `git diff main...HEAD`, `git log main..HEAD --oneline` and `git diff HEAD` (uncommitted changes) to see what changed. Run `git status --short -uall` too and read every untracked (`??`) file, since neither diff shows new files.
2. Check the diff against the Conventions in `CLAUDE.md`:
   - Backend: JSON API only, routes in `routes/api.php`, Sanctum Bearer tokens, authorization through Policies (no manual user checks in controllers), validation through FormRequests, output through API Resources.
   - Frontend: state in Pinia, HTTP only through the Axios instance in `src/lib/api.ts`.
3. Check that new behavior has a Pest or Vitest test.
4. Check for leaked secrets, `.env` changes, and commits that target `main`.

## Output

List findings most severe first, each as `file:line: problem, why it matters, suggested fix`. End with a one-line verdict: ready to merge, or what blocks it.
