#!/usr/bin/env bash
# PostToolUse formatter for Edit/MultiEdit/Write.
#
# Reads the tool call JSON from stdin and formats the touched file:
#   backend/**/*.php                          -> Laravel Pint
#   frontend/**/*.{vue,ts,js,css,json}        -> Prettier
#   **/*.md (whole project)                   -> Prettier, frontend config
# Formatting problems never block Claude; output is discarded.

# Without jq we cannot read the file path. Exit 1 is a non-blocking error
# whose stderr is shown to the user.
if ! command -v jq >/dev/null 2>&1; then
  echo "format.sh: jq is not installed, skipping formatting." >&2
  exit 1
fi

file=$(jq -r '.tool_input.file_path // ""')
[ -z "$file" ] && exit 0

# On Windows the path arrives as C:\...\backend\x.php; convert backslashes to
# forward slashes so the patterns below match.
file=${file//\\//}

# Never touch dependencies.
case "$file" in
  */vendor/*|*/node_modules/*) exit 0 ;;
esac

case "$file" in
  */backend/*.php)
    (cd "$CLAUDE_PROJECT_DIR/backend" && vendor/bin/pint "$file" >/dev/null 2>&1) ;;
  */frontend/*.vue|*/frontend/*.ts|*/frontend/*.js|*/frontend/*.css|*/frontend/*.json)
    (cd "$CLAUDE_PROJECT_DIR/frontend" && npx prettier --write "$file" >/dev/null 2>&1) ;;
  *.md)
    (cd "$CLAUDE_PROJECT_DIR/frontend" && npx prettier --config .prettierrc.json --write "$file" >/dev/null 2>&1) ;;
esac

exit 0
