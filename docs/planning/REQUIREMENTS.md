---
title: SecureCAT Requirements
version: 1.0
status: active
---

# Requirements

## Application Management (REQ-APP)

### REQ-APP-01: Staff Create Application
**Description:** Staff can create applications via admin routes bypassing application window
**Type:** Functional
**Priority:** High

### REQ-APP-02: Staff Edit Application
**Description:** Staff can edit existing applications via admin routes
**Type:** Functional
**Priority:** High

### REQ-APP-03: Role-Based Authorization
**Description:** All role-based access enforced via policies
**Type:** Security
**Priority:** Critical

### REQ-APP-04: Validation Rules
**Description:** Validation rules match StoreApplicationRequest with nullable fields for edit
**Type:** Validation
**Priority:** High

## Result Release (REQ-REL)

### REQ-REL-01: Mode-Aware Release Page
**Description:** The release page layout adapts to `release_mode` system setting. Online mode shows a read-only consultation data table with a single "Release All" header button. F2F mode shows a checkbox table with side panel for individual consultation notes and bulk release action. Both mode uses tab-based views (Online tab + F2F tab).
**Type:** Functional
**Priority:** High

### REQ-REL-02: Online One-Click Release All
**Description:** When release_mode is 'online' or on the Online tab in 'both' mode, a "Release All" button in the page header releases all unreleased consultation summaries in a single action. No consultation notes are required. Confirmation dialog shown before executing.
**Type:** Functional
**Priority:** High

### REQ-REL-03: F2F Release with Consultation Notes
**Description:** When release_mode is 'f2f' or on the F2F tab in 'both' mode, each row has a side panel for entering/editing recommended course and counselor comments before individual release. Bulk release is available for summaries that already have notes filled.
**Type:** Functional
**Priority:** High

### REQ-REL-04: F2F Notification Support
**Description:** F2F release sends in-app + email notification to the applicant with F2F-specific wording (e.g., "Your results are available for face-to-face consultation. Please visit the guidance office."). Currently F2F release sends zero notifications.
**Type:** Functional
**Priority:** High

### REQ-REL-05: Online Release Notification
**Description:** Online release continues sending the existing `ResultReleased` notification (in-app + email with "View in Portal" action) for each released applicant.
**Type:** Functional
**Priority:** Medium