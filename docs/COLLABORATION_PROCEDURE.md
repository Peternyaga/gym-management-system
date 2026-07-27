# Implementation and Testing Collaboration Procedure

## Purpose

This procedure coordinates the implementation agent and the independent tester without simultaneous edits to application code. Both agents use `collaboration.md` as the single source of truth for state, ownership, findings, and estimated handoff time.

## Files

- Procedure: `C:\Users\HomePC\Documents\Gym management system\docs\COLLABORATION_PROCEDURE.md`
- Implementation plan: `C:\Users\HomePC\Documents\Gym management system\docs\IMPLEMENTATION_PLAN.md`
- Shared status: `C:\Users\HomePC\Documents\Gym management system\collaboration.md`
- Tester setup prompt: `C:\Users\HomePC\Documents\Gym management system\docs\TESTER_PROMPT.md`

## Roles

### Implementation agent

- Owns application source changes, migrations, tests, and build tooling.
- Updates `collaboration.md` at the start of each work phase, at material milestones, and at every handoff.
- Includes an absolute timestamp, current state, completed work, known risks, validation performed, and estimated time to the next handoff.
- Changes state to `READY_FOR_TEST` only after local automated checks pass.
- Stops application edits while state is `TESTING`.
- Reviews every tester finding and records its disposition before beginning the next implementation turn.

### Tester

- Does not modify application source, migrations, dependency manifests, or implementation tests.
- May use the running application, browser tools, read-only code inspection, and dedicated test notes.
- Updates only the tester sections of `collaboration.md`.
- When implementation state is not `READY_FOR_TEST`, records `TESTER_WAITING` and ends the turn.
- On handoff, changes state to `TESTING`, records the tested build/commit and an ETA, then tests the agreed scope.
- Records each finding with an identifier, severity, reproduction steps, expected behavior, actual behavior, and supporting evidence.
- Changes state to `TEST_COMPLETE` when findings are ready, adds recommendations and retest scope, then ends the turn.

## State machine

```text
IMPLEMENTING
  -> READY_FOR_TEST
  -> TESTING
  -> TEST_COMPLETE
  -> IMPLEMENTING_FIXES
  -> READY_FOR_RETEST
  -> TESTING
  -> VERIFIED
```

`BLOCKED` may be used by either role when external access or user input is required. The blocker and the exact required action must be recorded.

## Turn and file-lock rules

1. Only the role named in `Current owner` may change application source.
2. The tester may update its own sections in `collaboration.md` during `TESTING`; the implementation agent may update implementation sections during implementation states.
3. During a handoff, the active role writes all changes and saves the file before waking the other role.
4. The receiving role rereads the entire `collaboration.md` before acting.
5. No agent relies on an earlier message when the shared file contains newer information.
6. Each status update includes an ETA in minutes or an absolute local time. A waiting role should not be woken before that ETA unless explicitly notified.

## Tester finding format

```text
### FINDING-001 — Short title
- Severity: blocker | critical | high | medium | low
- Area:
- Preconditions:
- Steps:
  1.
  2.
- Expected:
- Actual:
- Evidence:
- Recommendation:
- Status: open | fixed-awaiting-retest | verified | rejected-with-reason
```

## Implementation response format

For every finding, add:

- Root cause
- Resolution or reason for rejection
- Files changed
- Automated coverage
- Manual retest instructions
- Status

## Test scope for this project

- Logout/back-button behavior and authenticated response caching.
- Tenant and role authorization, including direct URL access.
- Platform tenant-directory detail and management flows.
- Gym owner member, plan, payment, schedule, and check-in management.
- Theme draft/edit/asset/publish/rollback behavior across public, auth, owner, member, and PWA surfaces.
- Responsive navigation at phone, tablet, and desktop widths.
- Regression checks for signup, M-Pesa callback idempotency, booking capacity, tenant isolation, and PWA assets.

## Completion gate

Work is complete only when:

- All critical/high findings are verified.
- Medium findings are fixed or explicitly accepted with rationale.
- Automated CI checks pass.
- The tester marks the shared state `VERIFIED`.
- The final repository state is committed and pushed to the requested GitHub repository.
