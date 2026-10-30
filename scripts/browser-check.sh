#!/usr/bin/env bash
# Runs the e2e browser scripts against both dev servers, then cleans up after itself.
#
# Usage: bash scripts/browser-check.sh [--migrate] [e2e-script.mjs ...]
#   Default scripts: every .claude/skills/browser-check/e2e-*.mjs
#   --migrate: run pending migrations on the dev SQLite database. Without it, pending
#              migrations stop the check (a missing table would only show up as a 500).
# Env: SCRATCH_DIR (where playwright-core and screenshots live, default: a temp dir),
#      CHROME_PATH (default: C:/Program Files/Google/Chrome/Application/chrome.exe)
#
# Writes: the dev database (e2e users, removed again on exit) and files in SCRATCH_DIR.
# Refuses to start if port 5173 or 8000 already answers, so it never kills your own servers.

set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
skill_dir="$root/.claude/skills/browser-check"
scratch_dir="${SCRATCH_DIR:-$(mktemp -d)}"
should_migrate=false
scripts=()

for argument in "$@"; do
  case "$argument" in
    --migrate) should_migrate=true ;;
    *) scripts+=("$argument") ;;
  esac
done

if [ "${#scripts[@]}" -eq 0 ]; then
  for script_path in "$skill_dir"/e2e-*.mjs; do scripts+=("$(basename "$script_path")"); done
fi

answers() {
  [ "$(curl -s -o /dev/null -m 2 -w '%{http_code}' "http://localhost:$1/" || true)" != "000" ]
}

stop_listener() {
  local pid
  for pid in $(netstat -ano | grep ":$1 " | grep LISTENING | awk '{print $5}' | sort -u); do
    taskkill //PID "$pid" //F //T >/dev/null 2>&1
  done
}

# Removes only users the e2e scripts create, with their habits, completions and tokens.
delete_e2e_users() {
  (cd "$root/backend" && php artisan tinker --execute='
    $ids = App\Models\User::where("email", "like", "e2e-%@example.com")
      ->orWhere("email", "like", "dbg-%@example.com")->pluck("id");
    Illuminate\Support\Facades\DB::transaction(function () use ($ids) {
      $habitIds = App\Models\Habit::whereIn("user_id", $ids)->pluck("id");
      App\Models\HabitCompletion::whereIn("habit_id", $habitIds)->delete();
      App\Models\Habit::whereIn("user_id", $ids)->delete();
      Illuminate\Support\Facades\DB::table("personal_access_tokens")
        ->where("tokenable_type", App\Models\User::class)->whereIn("tokenable_id", $ids)->delete();
      App\Models\User::whereIn("id", $ids)->delete();
    });
    echo "removed e2e users: ".$ids->count().PHP_EOL;' 2>&1 | tail -n 1)
}

started_servers=false
cleanup() {
  if [ "$started_servers" = true ]; then
    stop_listener 5173
    stop_listener 8000
  fi
  delete_e2e_users
}

if answers 5173 || answers 8000; then
  echo "Port 5173 or 8000 is already in use. Stop those servers first." >&2
  exit 2
fi

if (cd "$root/backend" && php artisan migrate:status 2>&1 | grep -q 'Pending'); then
  if [ "$should_migrate" = true ]; then
    (cd "$root/backend" && php artisan migrate) || exit 2
  else
    echo "Pending migrations on the dev database. Re-run with --migrate to apply them." >&2
    exit 2
  fi
fi

trap cleanup EXIT

(cd "$root/backend" && php artisan serve --port=8000 >"$scratch_dir/laravel.log" 2>&1 &)
(cd "$root/frontend" && npm run dev >"$scratch_dir/vite.log" 2>&1 &)
started_servers=true

# A single check can catch Laravel still booting and return 502, so wait for the proxied 401.
if ! timeout 90 bash -c 'until [ "$(curl -s -o /dev/null -w %{http_code} -H "Accept: application/json" http://localhost:5173/api/user)" = 401 ]; do sleep 1; done'; then
  echo "The dev servers did not become ready. See $scratch_dir/laravel.log and vite.log." >&2
  exit 2
fi

cd "$scratch_dir" || exit 2
if [ ! -d node_modules/playwright-core ]; then
  npm init -y >/dev/null 2>&1
  npm install playwright-core@1.49.1 >/dev/null 2>&1 || { echo "playwright-core install failed" >&2; exit 2; }
fi

failed_count=0
for script in "${scripts[@]}"; do
  cp "$skill_dir/$script" "$scratch_dir/$script" || { echo "Unknown script: $script" >&2; exit 2; }
  echo "== $script"
  if ! SHOTS_DIR="$scratch_dir" node "$script"; then
    failed_count=$((failed_count + 1))
  fi
done

echo "Screenshots and logs: $scratch_dir"
[ "$failed_count" -eq 0 ]
