# Epics and Stories — Staff Personnel Lifecycle

**OpenSpec:** `openspec/changes/staff-personnel-lifecycle/`  
**Role key:** `hr_administrator` (not inventory alias `hr_staff_admin`)

## Epic P-E1 — Personnel accounts

### P-S01 — HR lists and creates staff
- **FR/BR:** FR-076; BR-012; CAP-030
- **Pages:** `/staff/personnel`, create
- **Disposition:** PRESERVE management-list UX; REWRITE authz/ceilings
- **GWT:** HR opens list; creates user with confirmed password + allowed role; audit `hr.staff.created`; registrar_clerk denied 403

### P-S02 — Office creates support staff within ceiling
- **FR/BR:** FR-076; D-020
- **GWT:** Registrar admin creates `registrar_support_staff` only; cannot create `guidance_counselor`; Guidance counselor creates `guidance_support_staff` only

### P-S03 — Edit assignment and roles
- **GWT:** Update name/email/office/optional password; sync roles within ceiling; refuse empty roles; refuse TA/Proctor helpers

### P-S04 — Deactivate / activate
- **GWT:** Deactivate blocks staff login; activate restores; cannot deactivate self; cannot deactivate last HR manager

### P-S05 — Nav + page states
- **GWT:** Personnel nav appears only when capability present; empty/denied/validation states shown
