## Context

Under Decision `D-085`, applicant authentication uses a two-factor model: an 8-character access key (`XXXX-XXXX`) and a birthdate (`YYYY-MM-DD`). 

Two security and usability gaps exist in the current implementation:
1. **Character Ambiguity in Access Keys**: The access key generator mints keys from a 32-character Crockford alphabet (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`) that excludes `0`, `O`, `1`, `I`, and `L`. However, `ApplicantAccessKey::isValidShape` used `/^[A-Z2-9]+$/`, which mistakenly accepted `0`, `1`, `I`, `O`, and `L`. When applicants type or misread these characters on mobile or paper, no clarifying guidance was surfaced.
2. **Birthdate Brute-Force Vulnerability**: A typical applicant cohort has a narrow birthdate range (~10 years = ~3,650 possible dates). Without a per-key failed attempt limit, an attacker possessing an access key could automate testing all 3,650 dates in seconds.

## Goals / Non-Goals

**Goals:**
- Enforce strict 32-character Crockford shape validation on access keys (`/^[A-HJ-KM-NP-Z2-9]{8}$/`).
- Provide immediate, actionable user feedback when ambiguous characters (`0/O`, `1/I/L`) are detected in input.
- Prevent birthdate guessing by implementing a 3-strikes lockout mechanism per access key hash (15-minute decay).
- Maintain an IP rate limit of 10 requests/minute to mitigate distributed stuffing.
- Use timing-safe string comparison (`hash_equals`) for birthdate evaluation.
- Ensure non-enumerating error messages across all failure modes.

**Non-Goals:**
- Changing the access key minting length (retaining the approved 8-character `XXXX-XXXX` format).
- Altering the cryptographic hash format ($\text{SHA-256}$).
- Changing staff authentication or staff roles.

## Decisions

### 1. Strict Alphabet Regex & Ambiguity Detection
- **Choice**: Update `isValidShape()` to check `/^[A-HJ-KM-NP-Z2-9]{8}$/` on normalized input. Add `containsAmbiguousChars()` checking `/[01IOL]/i`.
- **Rationale**: Rejects invalid characters at the request validation layer before hashing or database queries, while giving helpful feedback (`"Access keys do not contain ambiguous characters (0/O, 1/I/L)..."`).
- **Alternatives Considered**: 
  - *Automatic silent substitution (e.g. mapping `0` to `O`)*: Rejected because neither `0` nor `O` is in the mint alphabet, so guessing which character was intended would produce wrong hashes.

### 2. Dual-Layer Rate Limiting (Per-Key Lockout + Per-IP Throttle)
- **Choice**: Use Laravel's `RateLimiter` service:
  - Per-Key: `RateLimiter::hit('applicant-login:key:'.$keyHash, 900)` with max 3 attempts.
  - Per-IP: `RateLimiter::hit('applicant-login:ip:'.$ip, 60)` with max 10 attempts.
- **Rationale**: 3 strikes effectively eliminates birthdate brute-forcing (an attacker gets only 3 guesses per 15 minutes, requiring ~12 days to test 3,650 dates). Legitimate applicants have their count reset on successful login.
- **Alternatives Considered**: 
  - *IP-only rate limiting*: Insufficient against botnets or proxy rotation trying dates against one key.
  - *Database-backed lockout table*: Cache/RateLimiter is fast, non-blocking, and handles expiration automatically.

### 3. Constant-Time Birthdate Comparison
- **Choice**: Use `hash_equals($storedBirthdate, $birthdate)`.
- **Rationale**: Prevents timing side-channel attacks when comparing date strings.

## Risks / Trade-offs

- **Risk**: A malicious actor could lock out an applicant by deliberately submitting 3 wrong birthdates for a known access key.
  - **Mitigation**: Lockout is bounded to 15 minutes (not permanent). Legitimate applicants can also request a registrar key reissue if their key is compromised.
- **Risk**: Legitimate applicants with typos might hit the 3-strike limit.
  - **Mitigation**: Error messages warn the applicant of remaining attempts before lockout. Clear ambiguous character detection prevents typos from consuming attempts.
