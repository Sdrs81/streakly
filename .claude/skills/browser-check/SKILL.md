---
name: browser-check
description: Runs the Streakly SPA in real Chrome against the live API to check a frontend phase's "Done when" flow. Use when a phase asks for a browser check, or when asked to test the app in the browser. Not for unit tests (use the test-runner subagent).
effort: medium
---

# Browser check

`chromium-cli` is not available on this Windows machine. Drive the installed Chrome with `playwright-core` instead. It needs no browser download.

## Steps

1. **Servers.** Start both in the background, from the repo root. Neither writes project files:
   - `cd backend && php artisan serve --port=8000`
   - `cd frontend && npm run dev`
   - Poll until the Vite proxy reaches Laravel. A single check can catch Laravel still booting and return 502: `timeout 60 bash -c 'until [ "$(curl -s -o /dev/null -w %{http_code} -H "Accept: application/json" http://localhost:5173/api/user)" = 401 ]; do sleep 1; done'`
2. **Driver.** In the scratchpad directory, not the repo:
   - `npm init -y` and `npm install playwright-core@1.49.1`
   - Copy `.claude/skills/browser-check/e2e-auth.mjs` there.
3. **Run.** From the scratchpad directory: `node e2e-auth.mjs`. Set `CHROME_PATH` if Chrome is not at `C:/Program Files/Google/Chrome/Application/chrome.exe`, and `SHOTS_DIR` to choose where the screenshots go. The script exits 1 if any check fails.
4. **Look at the screenshots** (`login.png`, `register-error.png`, `habits.png`). A blank page is a failure even when the checks pass.
5. **Stop the servers** by killing the listeners on ports 5173 and 8000: `netstat -ano | grep ":<port> " | grep LISTENING`, then `taskkill //PID <pid> //F //T`. Do not use `pkill -f`. Confirm that both ports no longer answer.
6. **Report** each PASS/FAIL line and any console error other than the expected 422 and 401 responses.

## Notes

- Every run registers a user `e2e-<timestamp>@example.com` in the dev SQLite database. Mention it in the report; delete it only if the user asks.
- `e2e-auth.mjs` covers the Phase 4 auth flow. For a later phase, add that phase's "Done when" steps to a new script next to it (for example `e2e-habits.mjs`) instead of changing the auth one.
- Run this check again after the last code change of a phase, not only before the review loop. Review fixes can change behavior.
