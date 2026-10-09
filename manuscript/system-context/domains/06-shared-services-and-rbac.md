# Domain 06: Shared Services, RBAC & AI Assistant Gateway

## 1. Domain Responsibility & Security Architecture

The Shared Services, Role-Based Access Control (RBAC) & Assistant Gateway domain establishes the institutional security perimeter, identity management, fine-grained capability evaluation, and safe AI assistance across NEXIAM.

In accordance with `GF-08` (Security, Privacy, and Audit Contract) and `GF-21` (Authorization Enforcement Design), NEXIAM enforces zero trust across all internal boundaries: **no legacy role middleware strings, no client-side permission checks, and no autonomous AI database writes**.

---

## 2. Staff Authentication & Resilient Onboarding

Implemented under `SRC-SPEC-06` (`staff-authentication-and-onboarding`):

```
┌────────────────────────────────────────────────────────────────────────┐
│                   STAFF ONBOARDING & ACTIVATION LIFECYCLE              │
├────────────────────────────────────────────────────────────────────────┤
│  [1. HR Voucher Generation]                                            │
│  Campus HR issues an immutable activation voucher.                     │
│  • QR Token: 16 CSPRNG bytes (32 hex characters in URL fragment).      │
│  • Fallback Code: 8 RFC4648 Base32 characters (XXXX-XXXX), Argon2id.  │
│                                                                        │
│  [2. Staff Voucher Redemption]                                         │
│  Staff navigates to `/staff/activate`, scans QR or enters fallback     │
│  code, and sets a strong personal password (Argon2id).                 │
│                                                                        │
│  [3. Mandatory Two-Factor Authentication (2FA) Enrollment]             │
│  Staff configures Time-based One-Time Password (TOTP) via Google2FA:   │
│  • Anti-replay prevention: Replaying the same 30s code step fails.     │
│  • 8 backup recovery codes (16-byte SHA-256 digests) generated.        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Capability-Based Authorization: `CapabilityAuthorizer`

NEXIAM deprecates legacy Laravel role middleware (`role:guidance_counselor`). In greenfield architecture:

1. **Spatie Roles/Permissions:** Used strictly as low-level database persistence helpers.
2. **`CapabilityAuthorizer` (`system/app/Authorization/`):** The **sole decision authority** for all HTTP requests, UI view rendering, and domain command executions.
3. **Data Scopes:** Evaluates contextual duty and resource scope:
   - *Campus Scope:* An actor cannot modify data belonging to another ISPSC campus.
   - *Office Scope:* A Registrar staff member cannot edit Guidance counseling records.
   - *Session Duty Scope:* A Proctor cannot modify sessions they are not actively assigned to.

---

## 4. The 4-Tier Office Hierarchy & Duty Scopes

As locked during `SRC-OMP-02`:

$$\text{Rank 1: Support Staff} < \text{Rank 2: Clerk} < \text{Rank 3: Office Lead} < \text{Rank 4: Campus HR}$$

| Office | Permanent Role / Rank | Assignable Duties & Capabilities | Strict Boundary Rules |
|---|---|---|---|
| **Guidance** | Guidance Counselor (Lead, Rank 3) | Holds full assessment catalog, scoring, and consultation capabilities. Delegates TA. | May delegate full **Test Administrator** capability set to Clerk/Support. |
| **Guidance** | Guidance Clerk / Support (Ranks 1–2) | Result printing, template management, case messaging. | May monitor exam sessions **only when assigned as Proctor**. |
| **Registrar** | Registrar Admin (Lead, Rank 3) | Full applicant intake, catalog configuration, admission clearance sign-off, case reopening. | Highest authority for entrance eligibility and pre-enrollment. |
| **Registrar** | Registrar Clerk (Rank 2) | Intake review, document verification, clearance sign-off. | Cannot reopen closed clearance cases without Admin authorization. |
| **Registrar** | Registrar Support (Rank 1) | Data entry assistance, physical enrollment slip handoff. | **Cannot confirm clearance.** Strictly barred from exam proctoring. |
| **Academic** | Program Head / Dean | Program quota management, interview advising notes, endorsement receipts. | **Strictly barred from exam scheduling, monitoring, and OMR scoring.** |

---

## 5. Xiam Assistant Architecture & Tool Gateway

Implemented under `SRC-SPEC-07` and `xiam-assistant-architecture-reset`:

### A. "One Auth, One Core" Principle
- Human web controllers (Inertia/Svelte) and the Xiam AI Assistant share the **exact same cookie session principal and GF-21 capability gates**.
- Prompts, provider parameters, or browser payloads cannot spoof actor, office, scope, or consent.

### B. Assistant Tool Gateway
- Xiam possesses **zero autonomous domain write paths**.
- The model interacts with the system exclusively through typed **Read Tools** (which return an authorized `EvidenceBundle`) and **Proposal Tools**.

### C. The Two-Step Propose $\rightarrow$ Confirm Pattern
1. **Propose:** When an authorized user asks Xiam to perform an action (e.g., *"Open reservation for Saturday's exam"*), Xiam computes parameters and presents a typed `XiamProposalCard`.
2. **Confirm:** The human user explicitly clicks "Confirm Action" in the browser UI. The server freshly re-authorizes the principal and executes the standard domain Action (`CreateExamSession::handle()`) with a transactional, idempotent receipt.

---

## 6. Implementation & Verification Status

- **Status:** `[IMPLEMENTED-TESTED]` & `[OBSERVED-EVIDENCE]`
- **Authentication Evidence:**
  - `tests/Feature/Auth/StaffAuthenticationTest.php` (Passing)
  - `tests/Feature/Auth/StaffAccountSetupTest.php` (70 assertions passing)
  - `tests/Feature/Auth/StaffGoogleAuthenticationTest.php` (154 assertions passing)
- **AI Gateway Evidence:**
  - `tests/Feature/Ai/XiamFullPipelineScenarioEvaluationTest.php` (113 assertions passing)
  - `tests/Feature/Ai/XiamProposalSafetyTest.php` (95 assertions passing)
  - Full AI feature suite: 185 passed / 1 skipped / 2,896 assertions passing.
- **Security Invariant:** Headless Fortify integration with custom pipeline `ValidateStaffPrimaryCredentials` enforces 100% 2FA enrollment before dashboard routing.
