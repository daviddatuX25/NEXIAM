# Spatie Laravel Permission — E1 Compatibility Spike

**Date:** 2026-08-04  
**Package:** `spatie/laravel-permission` **8.3.0**  
**Runtime:** Laravel 13.23 / PHP 8.4 / PostgreSQL 16  

## Decision

**GO WITH CONDITION — use as persistence/helper only.**

## Compatibility

| Check | Result |
|---|---|
| Composer constraints (`illuminate/* ^12\|^13`, `php ^8.3`) | Pass |
| Install into `system/` | Pass |
| Migrations + config publish | Pass |
| Discover / About panel shows Spatie Permissions 8.3.0 | Pass |

## Conditions (non-negotiable)

1. **GF-21 `CapabilityAuthorizer` is the decision authority** for allow/deny, reason codes, scope, duty, break-glass, and audit.
2. Spatie `register_permission_check_method` is **false** — Spatie must not register Laravel Gate checks.
3. **No legacy role middleware** and no `role:` route middleware as authorization authority.
4. Spatie **roles** are optional assignment helpers; role *labels* are not authorization decisions.
5. Spatie **permissions** store capability grant keys (e.g. `foundation.probe`); resource-scope (office/program/cycle/Proctor duty) is resolved in GF-21 code, not by Spatie alone.
6. Exit path: remove package + migrations/tables if helper proves harmful; capability keys remain portable.

## Exit path

- Drop `spatie/laravel-permission`, regenerate composer.lock.
- Replace grant persistence with first-party tables keeping the same capability key vocabulary.
- Keep `CapabilityAuthorizer` API stable for policies/commands/middleware.

## Evidence hooks

- Pest: `tests/Feature/Authorization/CapabilityAuthorizerTest.php`
- Middleware alias: `capability:` → `EnsureCapability` (delegates to authorizer + audit on deny)
