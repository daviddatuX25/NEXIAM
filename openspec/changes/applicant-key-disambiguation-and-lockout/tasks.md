## 1. Access Key Shape & Ambiguity Disambiguation

- [x] 1.1 Update `ApplicantAccessKey::isValidShape` in `system/app/Admission/Support/ApplicantAccessKey.php` to strictly enforce the 32-character Crockford alphabet (`/^[A-HJ-KM-NP-Z2-9]{8}$/`).
- [x] 1.2 Add `ApplicantAccessKey::containsAmbiguousChars` to identify when input contains `0`, `O`, `1`, `I`, or `L`.
- [x] 1.3 Update `ApplicantLoginRequest` in `system/app/Http/Requests/Applicant/ApplicantLoginRequest.php` to surface clear validation feedback when ambiguous characters are detected.

## 2. Rate Limiting & Birthdate Guessing Lockout

- [x] 2.1 Update `AuthenticateApplicantWithAccessKey` in `system/app/Admission/Actions/AuthenticateApplicantWithAccessKey.php` to use constant-time `hash_equals` for birthdate verification.
- [x] 2.2 Implement per-key 3-strikes lockout (15-minute decay) and per-IP throttling in `ApplicantPortalController::login`.
- [x] 2.3 Clear throttling keys on successful authentication.

## 3. Testing & Verification

- [x] 3.1 Add feature tests in `system/tests/Feature/Admission/ApplicantAccessTest.php` covering:
  - Exact Crockford alphabet validation (rejecting `0`, `O`, `1`, `I`, `L`).
  - Helpful validation messages on ambiguous input.
  - Per-key 3-strike lockout and automatic cooldown window.
  - Constant-time verification and non-enumerating generic failure messages.
- [x] 3.2 Run the full test suite and verify zero regressions.
