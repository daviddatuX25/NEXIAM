## Why

Applicant access keys generated during admission submission can suffer from character ambiguity (confusing `0` with `O`, or `1` with `I`/`L`) when applicants read from physical paper or type on mobile devices. Additionally, without per-key attempt limits, an attacker who acquires an access key could brute-force the applicant's birthdate within minutes due to the small search space (~3,650 dates for typical college cohorts). This change tightens access key disambiguation and enforces multi-tier rate limiting and lockout defenses on applicant authentication.

## What Changes

- **Strict Crockford Alphabet Shape Validation**: Update `ApplicantAccessKey::isValidShape` from a loose regex to the strict 32-character Crockford alphabet (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`), excluding `0`, `O`, `1`, `I`, and `L`.
- **Ambiguous Character Feedback**: Add `ApplicantAccessKey::containsAmbiguousChars` and informative validation error messages in `ApplicantLoginRequest` when users type ambiguous characters.
- **Per-Key Lockout Defense (3 Strikes → 15 Minutes)**: Implement automated lockout throttling on failed login attempts per access key hash to prevent birthdate brute-forcing.
- **IP-Based Rate Limiting**: Limit applicant login attempts to 10 requests per minute per IP address.
- **Timing-Safe Birthdate Verification**: Use constant-time `hash_equals` comparison for birthdate matching in `AuthenticateApplicantWithAccessKey`.
- **Generic Non-Enumerating Error Messages**: Ensure all failed authentication attempts return an identical generic error message without leaking key existence.

## Capabilities

### New Capabilities
- `applicant-key-disambiguation-and-lockout`: Covers strict Crockford access key shape validation, ambiguous character rejection with user feedback, per-key exponential lockout to prevent birthdate guessing, IP throttling, and timing-safe authentication.

### Modified Capabilities
<!-- None -->

## Impact

- **Security**: Completely closes the birthdate guessing vulnerability by capping failed attempts at 3 strikes per key with a 15-minute cooldown.
- **Usability**: Reduces applicant login failures by clearly explaining when an ambiguous character (e.g. `0` vs `O`) was mistyped.
- **Backward Compatibility**: All existing valid access keys and seed fixtures remain 100% valid; no database schema or hash algorithm changes are required.
