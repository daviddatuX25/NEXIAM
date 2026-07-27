# Phase 1: Application Create/Edit Validation Plan

## Validation Strategy

This phase implements staff/admin application create and edit functionality. Validation occurs via automated tests and manual browser verification.

## Automated Test Plan

### Backend Tests (PHPUnit)

| Test File | Covers | Command |
|-----------|--------|---------|
| `tests/Feature/ApplicationCreateEditTest.php` | REQ-APP-01 through REQ-APP-04 | `php artisan test --filter ApplicationCreateEditTest` |

**Test Cases to Implement:**

1. **Staff Create Application**
   - Authenticated staff can access `/admin/applications/create`
   - Form submission creates application with correct data
   - `processed_by` is set to authenticated user
   - `processed_at` is set
   - Reference number is generated

2. **Staff Create Bypasses Window**
   - Application created when window is closed
   - No 422 error from window check

3. **Staff Edit Application**
   - Authenticated staff can access `/admin/applications/{id}/edit`
   - Form pre-populates with existing data
   - Update modifies only provided fields
   - `processed_by` and `processed_at` are updated

4. **Role Authorization**
   - `super_admin` role can create/edit
   - `staff` role can create/edit
   - `registrar_administrator` role can create/edit
   - Unauthorized roles receive 403

### Frontend Tests

| Check | Method |
|-------|--------|
| Svelte compiles without errors | `npm run build 2>&1 | head -30` |
| Route registration | `php artisan route:list --name=applications` |

## Manual Verification Checklist

### Plan 01 (Backend) - Execute Phase

- [ ] `php -l app/Http/Requests/UpdateApplicationRequest.php` - syntax valid
- [ ] `php artisan route:list --name=applications` - admin routes registered
- [ ] `php artisan test --filter ApplicationCreateEditTest` - all tests pass

### Plan 02 (Frontend) - Execute Phase

- [ ] Visit `/admin/applications/create` as staff - form renders
- [ ] Course dropdowns filter correctly
- [ ] Status dropdown visible on create
- [ ] Submit create form - application created
- [ ] Visit `/admin/applications/{id}/edit` - form pre-populated
- [ ] Rejection reason textarea visible
- [ ] Submit edit form - changes saved
- [ ] `processed_by` and `processed_at` updated in database

## Validation Gates

| Wave | Gate | Criteria |
|------|------|----------|
| 1 | Backend complete | All backend tests pass, routes registered |
| 2 | Frontend complete | Forms render, manual verification approved |
| Phase | Release | Full test suite passes |

## Known Limitations

- Browser testing requires local development environment
- E2E tests not included in this phase (separate phase if needed)
