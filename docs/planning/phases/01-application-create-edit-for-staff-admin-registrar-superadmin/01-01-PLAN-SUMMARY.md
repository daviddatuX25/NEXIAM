---
phase: "01"
plan: "01"
subsystem: applications
tags: [applications, staff-create-edit, authorization]
dependency_graph:
  requires: []
  provides:
    - routes: admin.applications.create
    - routes: admin.applications.store-admin
    - routes: admin.applications.edit
    - routes: admin.applications.update
    - policy: ApplicationPolicy.create
tech_stack:
  added: []
  patterns:
    - FormRequest with nullable validation
    - Role-based policy authorization
    - Appointment booking count management
key_files:
  created:
    - app/Http/Requests/UpdateApplicationRequest.php
  modified:
    - app/Policies/ApplicationPolicy.php
    - app/Http/Controllers/ApplicationController.php
    - routes/web.php
decisions: []
---

# Phase 01 Plan 01: Application Create/Edit for Staff Summary

Staff admin create and edit functionality for applications, bypassing application window restrictions.

## Overview

Built backend infrastructure enabling staff to create/edit applications via admin routes without application window constraints. Validation rules allow partial updates while enforcing referential integrity.

## Implementation

### Files Created

- **UpdateApplicationRequest.php** — Form request with nullable fields for edit operations
  - All personal info fields nullable (edit can be partial)
  - Course preferences still enforce `different` rule when present
  - Includes status and rejection_reason for staff edits

### Files Modified

- **ApplicationPolicy.php** — Added `create()` method for staff authorization
- **ApplicationController.php** — Added `storeAdmin()`, `edit()`, `updateAdmin()` methods; updated `create()` for window bypass
- **routes/web.php** — Added admin create/edit routes with role middleware

## Verification

- Routes registered: `admin.applications.create`, `admin.applications.store-admin`, `admin.applications.edit`, `admin.applications.update`
- Policy method: `create()` returns true for `super_admin`, `staff`, `registrar_administrator`
- Controller methods bypass application window checks
- `processed_by` and `processed_at` set by staff operations

## Deviations from Plan

None - plan executed exactly as written.

## Threat Surface

| Flag | File | Description |
|------|------|-------------|
| - | routes/web.php | New admin routes protected by role middleware |
| - | ApplicationController | Window bypass only applies to authenticated staff |