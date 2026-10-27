# Claude setup audit

Date: 2026-10-27 · Branch: `setup/claude-tooling`

Tools used: the Florian Bruniaux audit prompt (v5.0, fixed script, read-only) and Claude Code `/doctor`.

Not used: claudeaudit.dev. It was last updated about 6 months ago and may not recognize current Claude Code fields (`effort`, `disallowed-tools`), so its results would be unreliable.

## Summary

| Tool                        | Result                                                                  |
| --------------------------- | ----------------------------------------------------------------------- |
| Florian audit, before fixes | 71/100 (Established)                                                    |
| Florian audit, after fixes  | 80/100 (Optimized)                                                      |
| After adding Context7       | Not re-measured                                                         |
| `/doctor`                   | Healthy. Nothing broken or unused, Claude Code is up to date (2.1.311). |

## Round 1: findings

| Finding                                                      | Decision | Status                                                  |
| ------------------------------------------------------------ | -------- | ------------------------------------------------------- |
| `api-endpoint` skill had no `effort` or `allowed-tools`      | Fix      | Done                                                    |
| `.env.*`, `*.pem`, `*credentials*` were not denied for reads | Fix      | Done (`settings.json` deny list)                        |
| No workflow command                                          | Fix      | Added the `review-pr` skill                             |
| No MCP for frontend library docs                             | Fix      | Added Context7 (`.mcp.json`)                            |
| No `/canary`, `/land-and-deploy`, `/qa` (Dimension 7)        | Disagree | Local SQLite app, no deploy flow                        |
| No global `~/.claude/CLAUDE.md`                              | Defer    | No personal preferences to write, would only add points |
| No sandbox config                                            | Defer    | Windows setup not verified                              |

Most of the remaining 20 points come from these three deliberate decisions and the audit skills that are not installed.

## `/doctor` results

- Resident context is small: CLAUDE.md about 480 tokens, `clean-code.md` about 1.1K (loaded only for matching paths).
- All settings files, `.mcp.json`, agents and skills are valid. No name collisions.
- Native install, current on the `latest` channel. `autoUpdates` is `false`.
- Hooks are local and light.
- Too little usage data (6 sessions) to judge `review-pr`, Context7 or `laravel-boost`. No removals suggested.

`/doctor` made two suggestions, both declined:

| Suggestion                                                     | Decision | Reason                                                                         |
| -------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------ |
| Remove the "Tech Stack" block from CLAUDE.md (about 60 tokens) | Disagree | Gain is tiny, and the stack is useful to read in the file                      |
| Set `defaultMode: "auto"` in `~/.claude/settings.json`         | Disagree | Affects every project. Project-level `deny` and `ask` rules are enough for now |

## Problems with the audit prompt

The prompt was not run as written. Fixed:

- Nested single quotes inside `bash -c '...'` broke the script.
- `--include-global` is a prompt flag, not a shell argument, so `"$@"` was always empty.
- The MCP check never matched Windows paths in `~/.claude.json` and never read the project's `.mcp.json`.
- `~` was not expanded inside quotes.
- Dimension 7 scored deploy-oriented commands that do not apply here.

## Known limits

- `allowed-tools` pre-approves tools for one turn, it does not restrict them. `disallowed-tools` does, and `review-pr` uses it.
- VS Code warns about `effort`, `allowed-tools` and `disallowed-tools` because they are not in its own schema. They are valid in Claude Code, so the warning is harmless.
- Context7 is a third-party (Upstash) remote server. It works without a key, with lower rate limits.
- The skills have had little real use. If an `allowed-tools` list is too narrow, widen it.

## Round 2: end of project

...
