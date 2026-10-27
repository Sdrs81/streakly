---
description: Clean code standards for naming, functions, comments, and structure in Streakly
paths:
  - backend/app/**
  - backend/tests/**
  - frontend/src/**
  - frontend/**/*.spec.ts
  - frontend/**/*.test.ts
---

# Clean Code Standards

## Naming

Always name things after what they mean in the domain, e.g. `completedHabitCount`, `isStreakActive`.

- Names that need a comment to explain them are bad names.

Never use vague or abbreviated names: `data`, `tmp`, `val`, `res`, `mgr`, `x`.

- Abbreviations force the reader to guess; a longer name costs nothing.

Always prefix booleans with `is`, `has`, `can`, or `should`.

- `if ($habit->isArchived)` reads like a sentence; `if ($habit->archived)` does not.

Always name a variable after its role at that point, not its final value: a counter that moves is `dayToCheck`, not `streakEnd`.

- A name that stops matching what the variable does is worse than no comment.

Never reuse a name from an outer scope for a different variable.

- Shadowed names make it unclear which value is meant.

Always name functions with a verb that says what they do: `calculateStreak()`, `markHabitComplete()`.
Always name classes and components with a noun: `StreakCalculator`, `HabitCard.vue`.

Always follow the language convention: PHP `camelCase` methods and `PascalCase` classes, TypeScript `camelCase` variables and `PascalCase` types and components.

- Mixed conventions make the code look unreliable and slow down scanning.

## Comments

Always comment the **why**: a business rule, a non-obvious constraint, a workaround, or a trade-off.

- The code already shows what it does; only the author knows why it does it that way.

Never comment what the code already says: `// increment counter`, `// loop over habits`.

- Redundant comments go stale and lie; rename or extract instead.

Never leave commented-out code or untracked `TODO`s.

- Git keeps history; dead code only creates doubt.

Always rewrite the code before adding a comment that explains confusing code.

- A clearer name or a small extracted function beats a comment.

## Functions

Always keep a function doing one thing, at one level of abstraction.

- Small focused functions are easy to name, test, and reuse.

Never accept more than 3 parameters; pass an object or DTO instead.
Never use boolean flag parameters that switch behavior: split into two functions.

- A flag means the function does two things.

Always return early for guard conditions instead of nesting `if` blocks.

- Flat code keeps the happy path readable.

## Structure

Always extract a repeated block once it appears a third time, not before.

- Premature abstraction is harder to undo than duplication.

Never use magic numbers or strings; give them a named constant or enum.

- `MAX_HABITS_PER_USER` explains itself; `20` does not.

Never hardcode dates or other values at a call site, such as a literal `'2026-10-27'` for "today"; derive them or name them.

- Hardcoded values go stale and hide what they stand for.

Always handle singular and plural in user-facing text: "1 day", "3 days".

Always keep files focused on one responsibility; split when a file mixes unrelated concerns.
Always delete unused code, imports, and variables.

- Dead code misleads readers and hides real dependencies.

## Tests

Always name a test after the behavior and the condition: `it('resets the streak when a day is missed')`.

- A failing test name should tell you what broke without opening the file.

Always test one behavior per test, with the arrange, act, assert steps visibly separated.

- Single-purpose tests point straight at the cause of a failure.

Never assert on implementation details such as private methods or internal state; assert on output and visible behavior.

- Tests tied to internals break on every refactor without catching real bugs.

Never share mutable state between tests.

- Order-dependent tests fail intermittently and are painful to debug.

## Types and Errors

Always declare explicit types: PHP parameter and return types, TypeScript types with no `any`.

- Types are documentation the compiler enforces .

Never swallow exceptions with an empty `catch`.

- Silent failures turn small bugs into hard-to-trace ones.

Always validate external input, such as date strings, before using it; never let a bad value silently become `NaN` or `0`.

- A wrong result that looks valid is harder to catch than an error.

Always fail with a specific, meaningful error message that names what went wrong.
