# D-087 — Shared institutional setup + Program Head quota write

**Status:** FOUNDING DECISION — recorded 2026-08-06  
**Closes:** CR-009 (Program Head quotas), GF-04 CAP-025 REVIEW, D-ROLE-05 quota half, master §8.8 “no quota mutation by default”  
**OpenSpec:** `institutional-setup-readiness`

## Decision

### 1. Shared catalog setup (rooms, programs/courses, and related prep)

Registrar **and** Guidance may configure institutional catalog used before and during examination:

| Catalog | Who may write |
|---|---|
| Academic cycles / application windows | Registrar **or** Guidance (shared) |
| Programs / courses | Registrar **or** Guidance (shared) |
| Exam rooms / venues | Registrar **or** Guidance (shared) |
| Privacy / intake grade fields | Registrar **or** Guidance (shared) |
| Aptitude areas (test-sensitive) | **Test Administrator only** (Guidance Counselor) — see clarification below |

**Rationale:** On exam day neither office should be blocked waiting for the other to create a room or activate a course. Capability key remains `registrar.setup.manage` historically but is **shared institutional setup** — grant to registrar_admin, registrar_clerk, guidance_counselor, and guidance_clerk. Support staff do **not** get setup by default.

**UX:** One Setup readiness hub (legacy Admin/Setup feel) — not separate Registrar-only vs Guidance-only hubs.

### 2. Program quotas — Program Head write + Test Administrator path

| Actor | Quota authority |
|---|---|
| **Program Head** | **ALLOW** write on **PROGRAM-scoped** quotas for assigned program(s) |
| **Guidance Counselor** (inherent Test Administrator) | **ALLOW** write quotas (institution/program as scoped) with **audit** and **Program Head communication seam** (notice / activity on quota change — full messaging later) |
| Registrar | DENY routine quota mutation (unless later explicit grant) |
| Guidance Clerk / support | DENY unless explicitly granted |

**Timing:** Quotas are operational **before result release / pre-enrolment** — not after silent release. Setup hub marks quota readiness as important before release workflows.

**Both paths are valid:**

1. Program Head edits their program quota directly; or  
2. Test Administrator adjusts quota while coordinating with Program Head via the interface (audit + PH-visible change log / notice).

### 3. Explicit non-goals (this decision)

- Does not grant Program Head admission Accept/Dismiss or raw answer-sheet access  
- Does not merge Registrar intake authz into Guidance  
- Does not implement YuKee/quota optimization engines  
- Full in-app messaging between TA and PH remains a later seam; first cut = audited change + visible history on quota UI

## Downstream updates required

- GF-04 CAP-007/008 → shared setup ALLOW for Guidance clerk/counselor  
- GF-04 CAP-025 → Program Head ALLOW (PROGRAM); Guidance Counselor ALLOW; note TA↔PH communication  
- GF-04 ACT-06 Program Head — not read-only for quotas  
- access-control-inventory Program Head + use cases 6–8 / 37  
- GF-02 CR-009 → CLOSED by this decision  
- Master reference §8.8  
- CapabilityCatalog grants + Setup OpenSpec

## Founder clarification (2026-08-06) — aptitude is TA-only

D-087 shared setup covers **institutional** catalog (cycles, windows, programs, rooms, privacy).  
**Aptitude areas** (and later rating scales / result templates) remain **Test Administrator–sensitive** — Guidance Counselor only by default. Registrar may see hub readiness for those checks but must not edit them. Hub UX mirrors legacy: separate category cards with create + activate checks and one Configure link each.

