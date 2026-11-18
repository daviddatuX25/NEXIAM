## Issue / OpenSpec / Task

- **GitHub Issue:** #<number> (confirm all `Blocked by` dependencies are closed)
- **Governing OpenSpec:** `openspec/changes/<change-name>/` (`proposal.md` / `specs/` / `tasks.md`)
- **Task ID(s):** `<e.g. 7.7 / G2>`

## Expected Behavior & Out-of-Scope Work

- **Expected behavior:**
  - <Concise summary of what this PR delivers>
- **Explicitly out of scope:**
  - <Unrelated features, held routes, live/VPS mutations, or sibling slices left untouched>

## Baseline & Impacted Paths

- **Pinned base ref / SHA:** `<e.g. origin/master @ 4321e30b12a09722569bb4f3766bf8f84f633f07>`
- **Branch / worktree:** `<branch-name>` (`<worktree-path>`)
- **Impacted paths:**
  - `<path/to/file>`

## Verification Commands, Results & CI Run Link

- **Disposable test target:** `<e.g. SQLite :memory: via system/phpunit.xml | isolated Compose project <name> | N/A (docs-only)>`
- **Commands & observed results** (report actual counts; never mask or skip failures):
  - `<command>` → `<observed outcome>`
- **Hosted CI run link:** `<paste URL from gh pr checks <pr> or gh run view <run-id> once pushed>`

## Data, Schema & Security Impact

- **Migrations / schema changes:** `<None | list migrations and forward/backward compatibility notes>`
- **Seeds / fixtures / custody:** `<None | disposable synthetic profile only; confirm no live/shared DB or custody mutation>`
- **Security & secrets check:**
  - [ ] No `.env`, private keys, credentials, dumps, model weights, or protected applicant/staff records added
  - [ ] No unpublished local/private workstation history merged into public `master`
  - [ ] `legacy_codebase/` treated as read-only evidence (or separate authorization noted)

## Release & Rollback Notes

- **Artifact / manifest / worker / config impact:** `<N/A for docs-only | describe release, worker, or config coordination>`
- **Rollback path:** `<N/A | revert commit / prior immutable release reference>`

## Unresolved Items & Open Holds

- <List any pre-existing failing baseline checks, deferred paths, or pending owner approvals — or state "None">

## Checklist

- [ ] Changes stay strictly within the assigned slice and OpenSpec scope
- [ ] Evidence is linked (issue, OpenSpec `HANDOFF.md`, CI run) rather than duplicated across docs
- [ ] Issue will be closed only after required acceptance evidence is verified
