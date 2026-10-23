# Publish current NEXIAM application source on master

## Founder-approved scope

The founder requested browsable current application source on the existing default GitHub branch `master`, with accurate setup documentation, application/migrations/seeders/data/assets/tests/lockfiles, no credentials or model weights, preserved existing GitHub history, no force push, and no automatic deployment. An encrypted backup alone does not satisfy the request.

## Publication boundary

Prepare a clean source snapshot in an isolated worktree based on remote master. Do not merge unpublished local history containing an oversized model. Preserve the live bind-mounted workspace, databases, runtime configuration, keys, workers and selectively published assets unchanged. Preserve private backups and the encrypted remote backup branch.

## Bounded corrections

- Enforce the existing Web Push environment-key contract (`web-push-pwa-lifecycle/design.md`, keys configured via environment); remove the embedded fallback credential, generate test keys at runtime, and provide truthful unavailable behavior when unconfigured.
- Restore the existing academic-history value binding and accessible suggestion-option semantics (`simple-application-suggestion-lists`, explicit choice fills existing history field; existing intake accessibility contract). No new UI or admission rule.

Existing domain work is carried as source, not reclassified as complete, approved for production, or physically qualified. Source push does not authorize migration, seeding, model use, or deployment.
