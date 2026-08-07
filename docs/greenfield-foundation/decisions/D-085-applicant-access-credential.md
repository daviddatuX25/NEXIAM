# D-085 — Applicant portal credential (refines D-025 / D-AUTH-01)

**Status:** GO WITH CONDITION — approved by David on 2026-08-05  
**Canonical matrix:** GF-13 D-085 (alias D-AUTH-01)  
**Supersedes for E2 UX:** free-form applicant password setup (`CompleteApplicantCredentialSetup` / `/applicant/setup` password form)

## Decision

Applicant portal authentication uses **two required factors**:

1. **Access key** — paper-friendly secret minted at public submission (`ABCD-EFGH`, 8 unambiguous chars); stored **hash-only** (normalized); shown once on confirmation **hidden by default** (password field + Show); emailed as backup; may be remembered in the browser (`localStorage`) via “Save on this device.”
2. **Birthdate** — knowledge factor matching `ApplicantIdentity.birthdate`.

| Rule | Binding |
|---|---|
| Applicant No / Reference | Non-secret identifier only — never sufficient alone |
| Birthday alone | **NO-GO** (unchanged from D-025) |
| Access key alone | **NO-GO** |
| Free-form applicant password | **REJECT for E2** — do not ask applicants to invent a password |
| Confirmation UX | Must allow first portal entry **without opening email** |
| Email | Backup delivery of the same access key |
| Browser save | Key only — do not persist birthdate in local storage by default |
| E2 recovery | Registrar-assisted reissue (email and/or F2F) that revokes prior key hash |
| Nexi “forgot key” | **DEFER** past E2 |

## Product flow (E2)

```text
Submit application
  → confirmation shows reference + access key once
  → “Save on this device” (optional)
  → enter birthdate + continue → applicant portal
Later visits
  → /applicant/login with saved or pasted key + birthdate
Portal session
  → ApplicantLayout with visible Sign out → POST /logout → applicant login
```

## Security invariants (GF-08)

- Never store or log plaintext access keys after the one-time confirmation/email handoff.
- Rate-limit login attempts; generic failure messages (no identity enumeration).
- Result visibility remains gated by ResultRelease independently of login.
- Staff login (`/login`) stays separate from applicant login.

## Implementation targets

- Command: `AuthenticateApplicantWithAccessKey`
- Pages: `Admission/Confirmation`, `Applicant/Login` (replaces password Setup UX)
- Routes: `GET/POST /applicant/login`; confirmation continues into login
- Tests: key+birthdate success; key-only fail; birthdate-only fail; hashed storage; portal own-record scope
