#!/usr/bin/env bash
# PreToolUse guard for the Bash and PowerShell tools.
#
# Claude Code passes the tool call as JSON on stdin. Exit code 2 blocks the
# call and feeds stderr back to Claude; exit code 0 lets it through.
#
# Blocks:
#   1. Recursive deletes (rm -r/-rf/-fr/-f -r/--recursive, rmdir, rd, del /s,
#      find -delete, Remove-Item -Recurse)
#   2. git push to main, or any git push while main is checked out
#   3. git push --force / -f
#   4. git commit while main is checked out (CLAUDE.md: never commit to main)

# Fail closed: without jq we cannot read the command, so refuse instead of
# silently allowing everything.
if ! command -v jq >/dev/null 2>&1; then
  echo "Blocked: jq is not installed, so the command cannot be inspected." >&2
  exit 2
fi

cmd=$(jq -r '.tool_input.command // ""')

# Block a command: print the reason to stderr (shown to Claude) and exit 2.
block() {
  echo "Blocked: $1 ($cmd)" >&2
  exit 2
}

# Case-insensitive match against the full command text.
matches() {
  printf '%s\n' "$cmd" | grep -Eiq "$1"
}

# Start of a command: beginning of line or after a separator/whitespace.
start='(^|[;&|(`[:space:]])'

# --- 1. Recursive deletes -------------------------------------------------
# rm with a recursive flag anywhere in its arguments: -r, -rf, -fr, -f -r, --recursive
if matches "${start}rm[[:space:]]+([^;&|]*[[:space:]])?(-[a-z]*r[a-z]*|--recursive)([[:space:]]|\$)"; then
  block "recursive delete is not allowed. Ask the user to do it themselves if really needed"
fi
# rmdir / rd (cmd.exe) and PowerShell's Remove-Item -Recurse (or its ri/rd aliases)
if matches "${start}(rmdir|rd)[[:space:]]" \
  || matches "${start}(remove-item|ri)[[:space:]][^;&|]*-recurse"; then
  block "directory delete is not allowed. Ask the user to do it themselves if really needed"
fi
# cmd.exe recursive delete (del /s) and find -delete
if matches "${start}del[[:space:]]+([^;&|]*[[:space:]])?/s([[:space:]]|\$)" \
  || matches "${start}find[[:space:]][^;&|]*-delete"; then
  block "recursive delete is not allowed. Ask the user to do it themselves if really needed"
fi

# --- 2-4. Git safety --------------------------------------------------------
# "git" followed by anything before the subcommand, so "git -C dir push" is caught too.
git_cmd="${start}git[[:space:]]+([^;&|]*[[:space:]])?"

if matches "${git_cmd}(push|commit)([[:space:]]|\$)"; then
  # symbolic-ref also works on a repo without commits (rev-parse HEAD would fail).
  branch=$(git -C "$CLAUDE_PROJECT_DIR" symbolic-ref --short HEAD 2>/dev/null)

  if matches "${git_cmd}push([[:space:]]|\$)"; then
    # Only the push itself counts: "git push ... && gh pr create --base main" must pass.
    push_args=$(printf '%s\n' "$cmd" | grep -Eio "${git_cmd}push[^;&|]*")
    # Quotes, backticks, backslashes and command substitution can hide a refspec from the checks below, so refuse what cannot be analyzed.
    if printf '%s\n' "$push_args" | grep -Fq -e '"' -e "'" -e '`' -e '\' -e '$('; then
      block "push arguments with quotes, backticks, backslashes or command substitution cannot be checked. Write the push plainly"
    fi
    # main as a refspec target: "git push origin main", "HEAD:main", "+main"
    if [ "$branch" = "main" ] || printf '%s\n' "$push_args" | grep -Eiq '([[:space:]:+]|/)main([[:space:]]|$)'; then
      block "push to main. Push a feature branch and open a PR instead"
    fi
    # --force or -f (including combined short flags like -uf); --force-with-lease is allowed
    if matches "${git_cmd}push[^;&|]*[[:space:]](--force|-[a-z]*f[a-z]*)([[:space:]]|\$)"; then
      block "force push is not allowed"
    fi
  fi

  if matches "${git_cmd}commit([[:space:]]|\$)" && [ "$branch" = "main" ]; then
    block "commit on main. Create a feature branch first"
  fi
fi

exit 0
