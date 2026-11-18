# Issue tracker: GitHub

Use GitHub Issues for Ask Matt implementation tickets. Use the `gh` CLI; the repository is inferred from the Git remote. Approved NEXIAM OpenSpec changes remain the implementation authority; an issue organizes work but does not independently authorize code changes.

## Conventions

- Create: `gh issue create --title "..." --body "..."` (use a heredoc for multiline bodies).
- Read: `gh issue view <number> --comments` and fetch labels.
- List: `gh issue list --state open --json number,title,body,labels,comments` with appropriate filters.
- Comment: `gh issue comment <number> --body "..."`.
- Apply/remove labels: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`.
- Close: `gh issue close <number> --comment "..."`.

## Pull requests as a triage surface

No. External PRs are not treated as issue requests by default.

## When a skill says “publish to the issue tracker”

Create a GitHub issue. When it says “fetch the relevant ticket,” run `gh issue view <number> --comments`.

## Blocking links

Use GitHub issue dependencies for blocker edges where available. Otherwise, add `Blocked by: #<n>` to the ticket body. A ticket is unblocked only when every blocker is closed.
