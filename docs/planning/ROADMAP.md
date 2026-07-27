# SecureCAT-v2 Roadmap

## Current Milestone: E9 - Application Management

### Phase E1: Core User Management
- [x] User authentication
- [x] Role-based access control
- [x] Admin user CRUD

### Phase E2: Exam Scheduling
- [x] Create/edit exam sessions
- [x] Room assignment
- [x] Scheduling conflict detection

### Phase E3: Proctor Management
- [x] Proctor assignment
- [x] Session roster
- [x] Attendance tracking

### Phase E4: Grading System
- [x] Score entry
- [x] Grade computation
- [x] Result sheet generation

### Phase E5: Test Administration
- [x] Test management
- [x] Question banks
- [x] Randomization

### Phase E6: AI Integration
- [x] Knowledge documents
- [x] RAG system
- [x] AI companion chat

### Phase E7: Monitoring & Analytics
- [x] Real-time monitoring
- [x] Dashboard analytics
- [x] Reporting

### Phase E8: Application UI Improvements
- [x] UI fixes
- [x] Navigation consistency
- [x] Responsive design

### Phase E9: Application Management
- [ ] Application create/edit for staff/admin

### Phase E10: Bulk Applicant Data Import
- [ ] Import applicant records via CSV/spreadsheet
- [ ] Reusable import service infrastructure

### Phase E11: Bulk Score Import
- [ ] Import applicant scores via CSV/spreadsheet

### Phase E12: Bulk Import UX Improvements
- [ ] Column mapping UI - user maps CSV headers to fields
- [ ] Preview step - show parsed data before importing
- [ ] Selective import - skip invalid rows, import valid only
- [ ] Validation flags on UI - highlight missing/invalid fields

### Phase 1: application create edit for staff admin registrar superadmin

**Goal:** Enable staff/admin to create and edit applications bypassing public application window restrictions
**Requirements:** REQ-APP-01, REQ-APP-02, REQ-APP-03, REQ-APP-04
**Depends on:** Phase 0
**Plans:** 2/2 plans complete

Plans:
- [x] 01-01-PLAN.md — Backend: UpdateApplicationRequest, policy create(), routes, controller methods
- [x] 01-02-PLAN.md — Frontend: Admin Create.svelte and Edit.svelte pages

### Phase 2: toast notification system with smooth sound

**Goal:** Brief, auto-dismissing toast notifications with optional sound for key events
**Requirements:** D-01, D-02, D-03, D-04, D-05, D-06, D-07
**Depends on:** Phase 1
**Plans:** 3/3 plans complete

Plans:
- [x] 02-01-PLAN.md — Infrastructure: svelte-french-toast package, sound, toast CSS
- [x] 02-02-PLAN.md — Frontend: ToastManager component, layouts integration
- [x] 02-03-PLAN.md — Integration: Polling triggers toast with edge cases

### Phase 3: add toast feedback to admin create edit pages

**Goal:** Add success toast notifications to admin create/edit form submissions
**Requirements:** T3-01, T3-02, T3-03
**Depends on:** Phase 2
**Plans:** 1/1 plan complete

Plans:
- [x] 03-01-PLAN.md — Add onSuccess toast callback to 18 admin CREATE/EDIT pages

### Phase 4: Applicant AI Companion chat interface - floating expandable chat area for applicants to chat with AI Companion when enabled

**Goal:** A floating expandable chat widget that appears on all applicant portal pages when AI Companion mode is enabled. Applicants can chat with the AI assistant from anywhere in the portal.
**Requirements:** 04-FW-01, 04-FW-02
**Depends on:** Phase 3
**Plans:** 3/3 plans

Plans:
- [x] 04-01-PLAN.md — Create AiCompanionChatWidget.svelte (FAB + expand panel)
- [x] 04-02-PLAN.md — Integrate widget into PortalLayout.svelte
- [x] 04-03-PLAN.md — Fix ai_companion_enabled to include consultation status check

### Phase 5: AI Companion Edge Cases & Security Hardening

**Goal:** Implement comprehensive security hardening and edge case handling for AI Companion: abuse prevention (code generation guardrails, prompt injection protection), input validation (message length warnings, history limit warnings), rate limiting per user, content safety, history management improvements, session handling, error handling, and data privacy enhancements.
**Requirements:** Security hardening for AI Companion feature
**Depends on:** Phase 4
**Plans:** 3/3 plans complete

### Phase 6: QA Audit Fixes — Admin CRUD gaps

**Goal:** [To be planned]
**Requirements**: TBD
**Depends on:** Phase 5
**Plans:** 0 plans

Plans:
- [ ] TBD (run /gsd-plan-phase 6 to break down)

### Phase 13: Exam Session Workflow & Notification Enhancements

**Goal:** Overhaul exam session lifecycle (publish->in-progress->complete one-way flow), enhance notification system (status change alerts for all roles, lighter context-aware sound, mobile sizing), fix email handling (acceptance/rejection only), and redesign My Sessions page for proctor/test_admin with proper authorization.
**Requirements:** Notification sound, email triggers, exam status workflow, role-based restrictions, My Sessions redesign, auth fix, mobile notification UI
**Depends on:** Phase 2
**Plans:** 4 plans in 2 waves

Plans:
- [ ] 13-01-PLAN.md — Backend: Workflow transitions, policy methods, notification classes, email scope fix, reminder env config
- [ ] 13-02-PLAN.md — Frontend: Two-tier sound system, mobile notification dropdown sizing
- [ ] 13-03-PLAN.md — My Sessions pages: Proctor and test_admin with date grouping, status badges, quick actions
- [ ] 13-04-PLAN.md — Integration: Notification dispatch wiring, sound tiers in UI, proctor redirect, workflow tests

### Phase 14: Release Page Redesign

**Goal:** Redesign release page with mode-aware layout (online/F2F/both tabs), add ReleaseAll endpoint for online mode, create ResultReleasedF2F notification, and support bulk release with F2F notifications
**Requirements:** REQ-REL-01, REQ-REL-02, REQ-REL-03, REQ-REL-04, REQ-REL-05
**Depends on:** Phase 13
**Plans:** 2/2 plans complete

Plans:
- [x] 14-01-PLAN.md — Backend: ResultReleasedF2F notification, releaseAll endpoint, context-aware dispatch, mode-aware index payload
- [x] 14-02-PLAN.md — Frontend: Mode-aware release page with tabs, Release All dialog, F2F side panel release button

---
## Future Milestones

### Phase F1: Student Portal
- Student registration
- Self-service profile management

### Phase F2: Notification System
- [ ] In-app notifications for key events
- [ ] Bell icon with dropdown UI
- [ ] Poll-based notification delivery
- [ ] Application, grading, scheduling triggers

### Phase F3: Audit Logging
- Complete audit trail
- Activity logs