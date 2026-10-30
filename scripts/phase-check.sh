#!/usr/bin/env bash
# Runs the non-test checks for the sides a phase touched, in one command.
#
# Usage: bash scripts/phase-check.sh [base-branch]   (default base: main)
#
# - backend/ changed:  vendor/bin/pint --test, plus route:list when routes/api.php changed
# - frontend/ changed: npm run lint, npm run type-check
# Tests are NOT run here: CLAUDE.md requires them to go through the test-runner subagent.
# Note: `npm run lint` runs oxlint and eslint with --fix, so it can rewrite files.
# Prints one PASS/FAIL line per check and the output tail of each failure. Exit 1 if any failed.

set -u

root="$(cd "$(dirname "$0")/.." && pwd)"
base="${1:-main}"
cd "$root" || exit 2

# Committed, uncommitted and untracked changes, so it works before and after the commit.
changed_files="$(
  git diff --name-only "$base"...HEAD
  git diff --name-only HEAD
  git ls-files --others --exclude-standard
)"

touches() {
  printf '%s\n' "$changed_files" | grep -q "$1"
}

output_file="$(mktemp)"
failed_count=0

run_check() {
  local label="$1" directory="$2"
  shift 2

  if (cd "$directory" && "$@") >"$output_file" 2>&1; then
    echo "PASS $label"
  else
    echo "FAIL $label"
    tail -n 25 "$output_file" | sed 's/^/     /'
    failed_count=$((failed_count + 1))
  fi
}

if touches '^backend/'; then
  run_check "backend pint" backend vendor/bin/pint --test
  if touches '^backend/routes/api.php'; then
    run_check "backend route:list" backend php artisan route:list --path=api
  fi
fi

if touches '^frontend/'; then
  run_check "frontend lint" frontend npm run lint
  run_check "frontend type-check" frontend npm run type-check
fi

if ! touches '^backend/' && ! touches '^frontend/'; then
  echo "No backend or frontend changes against $base: nothing to check."
fi

rm -f "$output_file"
[ "$failed_count" -eq 0 ]
