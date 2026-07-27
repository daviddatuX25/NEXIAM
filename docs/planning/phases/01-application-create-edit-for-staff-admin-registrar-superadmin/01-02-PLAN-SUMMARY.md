---
phase: "01"
plan: "02"
subsystem: applications
tags: [applications, staff-create-edit, svelte-ui]
dependency_graph:
  requires:
    - 01-01: application policy and controller methods
  provides:
    - svelte: Admin/Applications/Create.svelte
    - svelte: Admin/Applications/Edit.svelte
tech_stack:
  added:
    - Svelte 5 ($props, $derived, $effect)
    - Inertia useForm
  patterns:
    - Course preference dropdown filtering
    - AuthenticatedLayout for admin context
key_files:
  created:
    - resources/js/Pages/Admin/Applications/Create.svelte
    - resources/js/Pages/Admin/Applications/Edit.svelte
  modified: []
decisions: []
---

# Phase 01 Plan 02: Application Create/Edit UI for Staff Summary

Admin-facing Svelte pages for staff to create and edit applications.

## Overview

Created the frontend Svelte pages for staff to create and edit applications via admin routes. These pages bypass application window restrictions and provide direct data entry capabilities.

## Implementation

### Files Created

- **Create.svelte** — Staff create application form
  - All personal info fields (first_name, middle_name, last_name, suffix)
  - Demographics (birthdate, sex)
  - Contact (email, phone)
  - Address (address_line, city, province, zip_code)
  - Course preferences with cascading dropdown filtering
  - Appointment selection (optional)
  - Status dropdown (pending/accepted/dismissed)
  - POST to /admin/applications

- **Edit.svelte** — Staff edit application form
  - Pre-populated with application data via $props
  - All same fields as Create
  - rejection_reason textarea for dismissal notes
  - Read-only reference_number and submitted_at display
  - PUT to /admin/applications/{id}

### Key Patterns

- Course preference filtering: $derived optionsFor2, optionsFor3 filter out already selected courses
- $effect clears dependent selections if primary changes
- useForm from @inertiajs/svelte for form state and submission
- AuthenticatedLayout for admin context

## Verification

- npm run build completed successfully
- Pages compile without Svelte errors
- Forms will submit to admin endpoints (storeAdmin, updateAdmin)

## Deviations from Plan

None - plan executed exactly as written.

## Threat Surface

| Flag | File | Description |
|------|------|-------------|
| - | Admin/Applications/Create.svelte | New admin form with POST to trusted endpoint |
| - | Admin/Applications/Edit.svelte | New admin form with PUT to trusted endpoint |

## Self-Check: PASSED

- [x] resources/js/Pages/Admin/Applications/Create.svelte exists (210+ lines)
- [x] resources/js/Pages/Admin/Applications/Edit.svelte exists (220+ lines)
- [x] npm run build succeeds
- [x] Commit b86db89 created