# Phase 1: Application Create/Edit Requirements

## Requirements Overview

| ID | Description | Priority |
|----|-------------|----------|
| REQ-APP-01 | Staff can create application via admin routes | MUST |
| REQ-APP-02 | Staff can edit existing applications via admin routes | MUST |
| REQ-APP-03 | Staff create/edit bypasses application window restrictions | MUST |
| REQ-APP-04 | Role-based access enforced via ApplicationPolicy | MUST |

## Detailed Requirements

### REQ-APP-01: Staff Can Create Application
Staff users with `super_admin`, `staff`, or `registrar_administrator` roles can create applications on behalf of applicants via dedicated admin routes.

**Acceptance Criteria:**
- Admin create route exists at `/admin/applications/create`
- Form includes all application fields including status dropdown
- Submission creates application without application window check
- `processed_by` is set to authenticated user ID
- `processed_at` is set to current timestamp
- Reference number is generated automatically

### REQ-APP-02: Staff Can Edit Existing Applications
Staff users can edit any existing application via dedicated admin routes.

**Acceptance Criteria:**
- Admin edit route exists at `/admin/applications/{id}/edit`
- Form pre-populates with existing application data
- All fields are nullable (partial updates allowed)
- Rejection reason textarea is available
- Submission updates application without window check
- `processed_by` and `processed_at` are updated

### REQ-APP-03: Staff Create/Edit Bypasses Window Restrictions
Staff operations do not check whether the application window is open.

**Acceptance Criteria:**
- `storeAdmin()` method has no `isApplicationWindowOpen()` check
- `updateAdmin()` method has no `isApplicationWindowOpen()` check
- Staff can create/edit during closed windows
- No window-related error messages shown to staff

### REQ-APP-04: Role-Based Access Enforcement
Authorization is enforced at both middleware and policy levels.

**Acceptance Criteria:**
- Admin routes protected by `role:super_admin,staff,registrar_administrator` middleware
- `ApplicationPolicy::create()` returns true for allowed roles
- `ApplicationPolicy::update()` returns true for allowed roles
- Public routes remain protected by existing window checks
