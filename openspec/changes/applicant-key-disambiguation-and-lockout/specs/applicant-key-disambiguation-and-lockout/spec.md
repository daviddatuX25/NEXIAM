## ADDED Requirements

### Requirement: Strict Crockford Access Key Shape Validation
The system SHALL validate that any applicant access key contains exactly 8 characters from the Crockford alphabet (`ABCDEFGHJKLMNPQRSTUVWXYZ23456789`) and rejects ambiguous characters (`0`, `O`, `1`, `I`, `L`).

#### Scenario: Valid access key format
- **WHEN** an applicant provides an access key consisting of 8 valid Crockford characters (e.g., `ABCD-EFGH` or `ABCDEFGH`)
- **THEN** shape validation succeeds.

#### Scenario: Ambiguous character rejected with helpful feedback
- **WHEN** an applicant enters an access key containing `0`, `O`, `1`, `I`, or `L`
- **THEN** validation fails with a specific message informing the user that access keys do not contain ambiguous characters (0/O, 1/I/L).

---

### Requirement: Per-Key Failed Login Lockout
The system SHALL enforce a maximum of 3 consecutive failed login attempts per access key hash, triggering a 15-minute lockout upon the 3rd failed attempt.

#### Scenario: Successful login clears attempt counter
- **WHEN** an applicant enters a valid access key and matching birthdate
- **THEN** authentication succeeds and any failed attempt counter for that key is cleared.

#### Scenario: 3 failed birthdate attempts locks the access key
- **WHEN** 3 consecutive failed authentication attempts occur for the same access key within 15 minutes
- **THEN** subsequent login attempts for that key are blocked with a lockout error message and the remaining cooldown duration.

---

### Requirement: IP-Based Login Rate Limiting
The system SHALL rate-limit login attempts from a single IP address to a maximum of 10 requests per minute.

#### Scenario: Excessive requests from same IP
- **WHEN** more than 10 login requests are received from the same IP address in a 60-second window
- **THEN** the request is throttled with a standard rate limit message.

---

### Requirement: Timing-Safe Birthdate Verification
The system SHALL compare the submitted birthdate against the stored applicant identity birthdate using a constant-time comparison algorithm.

#### Scenario: Constant-time birthdate match
- **WHEN** evaluating the submitted birthdate against the identity record
- **THEN** comparison executes via `hash_equals` to eliminate timing side-channel leaks.
