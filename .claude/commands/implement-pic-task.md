# Implement PIC Task

Implement exactly one actionable user-story slice assigned to the requested PIC from Google Sheets. A Sheet row may be a layer-level subtask; use it as the entry point, then include only its directly required sibling rows/dependencies to complete one observable end-to-end flow. Never implement unrelated rows or an entire feature group. Follow every phase in order. Keep all decisions and edits visible in the main session.

## Required inputs

Ask for any missing value before proceeding:

- `spreadsheetId`
- sheet/tab name or range
- active PIC value
- optional task ID when multiple rows match

Never guess credentials, sheet names, headers, PIC identity, task status, user story, or acceptance criteria.

## Phase 1 — Scan task

1. Inspect repository instructions and current branch.
2. Use Google Sheets MCP metadata/value reads to inspect the requested tab/range.
3. Identify the header row. Normalize header whitespace; support common labels such as task ID, PIC, status, title, user story, and acceptance criteria only after confirming actual headers.
4. Normalize PIC whitespace and compare case-insensitively.
5. Keep only rows assigned to the requested PIC with an actionable status. Do not infer what an unknown status means.
6. If zero rows match, report that and stop. If multiple rows match without a task ID, show a compact candidate list and ask the user to choose. If required fields are empty or ambiguous, stop and ask for clarification.
7. Resolve the selected row's user story against the User Stories tab and display the complete acceptance criteria from that tab. If the mapping is wrong or ambiguous, stop and show the conflict; never guess.
8. If the tab is layer-oriented, group only the selected row's directly required sibling tasks/dependencies for that same user story into this one vertical slice. Do not pull in unrelated rows or an entire feature group. Display the grouped scope. Do not write to the sheet.

The selected row is an entry point, not permission to implement one layer in isolation. The slice is complete only when its observable end-to-end behavior is verified.

## Phase 2 — Branch preflight

1. Run `git status --short`. Require a clean worktree.
2. Verify local branch `development` exists. Do not use `master` as the base.
3. Build a safe branch name: `feature/<task-id>-<slug>`. Sanitize task ID and slug; preserve enough identity for review.
4. If the target branch already exists, stop. Never reset, delete, overwrite, force-update, merge, rebase, or commit to resolve it.
5. Create the branch from `development` and verify the active branch. If the worktree is dirty or the base is unavailable, stop.

## Phase 3 — Test matrix gate

Before production code, create and show a test matrix:

| Acceptance criterion | Test name | Test file | Expected assertion |
| --- | --- | --- | --- |

Map every criterion to at least one test. Add only relevant cases for:

- happy path
- input validation and boundary values
- authentication and authorization
- persistence and relationships
- expected failure states
- duplicate/concurrency behavior when applicable

Use existing Laravel conventions: feature tests under `tests/Feature`, `RefreshDatabase`, factories, `actingAs`, named routes, response/session/database assertions, and Inertia page/prop assertions. If a criterion conflicts with another, lacks observable behavior, or cannot be tested without a product decision, stop and ask the user.

## Phase 4 — Red/green implementation

Write tests first where practical. Implement the smallest **vertical slice** required by the approved task: close the minimum end-to-end flow from UI/input through route, controller, validation, persistence, and response/output covered by the acceptance criteria. Do not implement the feature as separate layer-only work (for example, backend first and frontend later) unless the acceptance criteria intentionally cover only part of the flow.

1. migration and model/factory when persistence is required;
2. FormRequest and/or middleware only when validation or cross-request policy requires it;
3. thin controller;
4. named route in the appropriate existing route file;
5. matching page in `resources/js/Pages/`, reusing existing layout/components and `useForm`.

Keep route names, `Inertia::render()` page names, Vue paths, and test names aligned. Preserve authorization, CSRF, escaping, accessibility, and existing auth behavior. Do not add speculative abstractions, packages, or unrelated refactors.

Treat each acceptance criterion as a complete behavior slice: its test must exercise the observable end-to-end path rather than validating isolated layers only, where the criterion spans multiple layers.

## Phase 5 — Refactor and verify

Refactor only for a concrete reason: requirement compliance, real duplication, testability, correctness, or an established repository convention. Then run, as applicable:

```bash
php artisan test --filter=<focused-test>
php artisan test
npm run build
php artisan route:list
./vendor/bin/pint --test
```

Use only commands/dependencies available in the repository. Fix failures rather than hiding, weakening, or deleting tests. Report exact failures for commands that cannot run.

## Phase 6 — Review-only handoff

Inspect:

```bash
git diff --check
git diff
git status --short
```

Report:

- selected task, PIC, user story, and acceptance criteria;
- branch and base branch;
- criterion-to-test matrix;
- changed files and why;
- verification commands and exact results;
- known gaps or blocked checks;
- explicit statement: no commit, push, sheet update, merge, or rebase performed.

Stop. Wait for the user to inspect and explicitly say `oke`. `oke` permits the next review step only; do not assume it permits push, merge, or any other outward-facing action. Never commit in the same turn as implementation.
