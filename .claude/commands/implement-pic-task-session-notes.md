# Implement PIC Task — Session Notes

## Completed

- Auth UI aligned with `doc/index.html`; branding: Sparta Ledger.
- Commit: `a9c0723 style(auth): align login and register UI with prototype`.
- Password reset now invalidates database sessions.
- US-AUTH-07B daily-worker roster implemented and committed:
  - Branch: `feature/US-AUTH-07B-employee-roster-worker`
  - Commit: `6da0491 feat(auth): add daily worker roster without login account (US-AUTH-07B)`
  - Focused tests: 7 passed, 42 assertions.
- US-SUB-02 manual payment flow implemented and committed:
  - Branch: `feature/US-SUB-02-manual-payment`
  - Commit: `76e0cb5 feat(subscription): add manual payment flow (US-SUB-02)`
  - Focused tests: 9 passed, 44 assertions.
  - Full suite: 38 passed, 124 assertions.
  - Frontend build, route check, targeted Pint, and `git diff --check` passed.

## US-SUB-02 scope

- Subscription page with Free/Paid status.
- Paid price: Rp99.000/bulan.
- Manual BCA transfer instructions.
- Owner-only proof upload.
- Accepted proof: JPG, JPEG, PNG, WebP; maximum 1 MB.
- Proof stored on private local disk under `payment-proofs`.
- Payment persisted as `pending`.
- Amount and `company_id` are server-owned.
- Pending payment does not change `companies.paid_until`.
- Owner-only `Langganan` navigation, desktop and responsive.

## Known gaps

- AC2 says admin receives a notification. Current slice persists a pending payment row; explicit email/dashboard notification is deferred to the admin/payment-notification slice.
- `payments.approved_by` is nullable without a foreign key because the `admins` table does not yet exist; complete it with the Super Admin slice.
- No Sheet status/writeback, push, merge, or rebase performed.

## Workflow rules

- Read and resolve the Google Sheet task and all acceptance criteria before implementation.
- Implement one actionable PIC slice as an end-to-end vertical slice.
- Base branches on local `development`.
- Keep implementation uncommitted until review unless the user explicitly requests a commit.
- Never write back to the Sheet unless explicitly requested.
- Never expose credentials or tokens.
- Before handoff run focused tests, full tests, frontend build, route check, formatter, `git diff --check`, and `git status --short` as applicable.
- After handoff wait for explicit `oke`; do not infer permission to push or merge.

## Next task

Resume by reading the Google Sheet again and selecting the next actionable task assigned to PIC Dibya. Do not assume the previous task status in the Sheet was updated. Likely dependency order: Super Admin payment approval/notification work before login-enabled Employee account creation, but verify against the Sheet and User Stories first.
