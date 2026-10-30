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

Date: 2026-10-31 · Branch: `docs/audit-round-2`

Same two tools, run read-only after the last phase. The Florian script was the fixed version from Round 1, project scope only. Scores are scaled to each dimension's maximum (for example D1 11/14 becomes 15.7/20), which reproduces the Round 1 total.

### Summary

| Tool          | Result                                                                           |
| ------------- | -------------------------------------------------------------------------------- |
| Florian audit | About 80/100 (Optimized), unchanged from Round 1                                 |
| `/doctor`     | Healthy. One finding: Claude Code is 3 versions behind (2.1.311, latest 2.1.314) |

| Dimension          | Score | Max | Key finding                                                           |
| ------------------ | ----- | --- | --------------------------------------------------------------------- |
| 1 Memory & Context | 15.7  | 20  | About 9.4K tokens fixed context. No global CLAUDE.md                  |
| 2 Rules Hygiene    | 10    | 10  | `clean-code.md` has frontmatter and `paths`, 128 lines                |
| 3 Skills Quality   | 7.5   | 10  | `effort` on all 6 skills, `allowed-tools` missing on 2                |
| 4 Agents/Commands  | 10    | 10  | Frontmatter and description on all 7 files                            |
| 5 Security         | 16.5  | 20  | `.env*`, `*.pem`, `*credentials*` denied, PreToolUse hook. No sandbox |
| 6 MCP              | 8     | 10  | Boost and Context7, no DB MCP. Guide MCP not installed                |
| 7 Workflow         | 2     | 10  | Only `/review-pr`. Deploy commands do not apply here                  |
| 8 Freshness        | 10    | 10  | No deprecated model names, recent commits                             |

No critical gaps. The points still missing are the Round 1 decisions (global CLAUDE.md, sandbox, Dimension 7) plus the audit skills that are not installed.

### Findings and decisions

| Finding                                                                 | Decision | Status                                                                   |
| ----------------------------------------------------------------------- | -------- | ------------------------------------------------------------------------ |
| `browser-check` and `phase` have no `allowed-tools`                     | Defer    | Not changed, both orchestrate many tools. Widen if prompts appear        |
| No global CLAUDE.md, no sandbox                                         | Defer    | Same reasons as Round 1                                                  |
| Dimension 7 deploy commands missing                                     | Disagree | Local SQLite app, no deploy flow                                         |
| Guide MCP not installed                                                 | Disagree | Not needed for this project                                              |
| Florian audit skills (`token-audit`, `eval-*`, `security-check`) absent | Disagree | Remote code, not installed. Fallback scoring used                        |
| Claude Code 2.1.311, latest 2.1.314                                     | Defer    | `autoUpdates` is `false` by choice. Update manually with `claude update` |

### `/doctor` results

- Native install, matches `installMethod`. No leftover npm or `~/.claude/local` install.
- All settings files, `.mcp.json`, agents and skill frontmatter parse and validate.
- Resident context is small (about 1.9K tokens). Hooks (`guard-bash.sh`, `format.sh`) are local. No timing data, because silent hooks are not logged.
- Usage window: 23 sessions, 26 to 31 October. All 6 project skills and `security-guidance` were used. `context7` was used (4 calls), `laravel-boost` was not.
- 15 denied tool calls (12 `permission-rule`, 2 `automode-blocked`, 1 `user-rejected`). None was a read-only command worth an allow rule.
- Subagents already use `haiku`. `/code-review` is a built-in skill and cannot be edited from the project.

`/doctor` suggestions, all declined or left as is:

| Suggestion                                                    | Decision | Reason                                                                |
| ------------------------------------------------------------- | -------- | --------------------------------------------------------------------- |
| Update with `claude update`                                   | Defer    | Optional, manual                                                      |
| Disable `laravel-boost` in this project                       | Disagree | Zero token cost (deferred), and `CLAUDE.md` names it for Laravel docs |
| Use `/code-review medium` instead of `high` in `finish-phase` | Disagree | The project is finished, no cost to save                              |
| Auto mode as default                                          | Disagree | Declined in Round 1, still applies to every project                   |

### Conclusion

Both audits agree the setup is in good shape at the end of the project. The score did not move between rounds because the remaining gaps are deliberate. Nothing in `.claude/` or `settings.json` was changed in Round 2.
