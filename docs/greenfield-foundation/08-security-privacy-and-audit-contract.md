# Security, Privacy, and Audit Contract

**Artifact:** NEXIAM-GF-08  
**Status:** DRAFT — REVIEW REQUIRED  
**Scope:** Controlled capstone demonstration baseline; pilot and production require separate institutional clearance.  
**Security claim:** Defense in depth and tamper evidence under an approved threat model—not “tamper-proof.”

## 1. Security objectives

1. Only authenticated actors access protected functions.
2. Authorization is capability- and resource-scoped, not merely route-role-scoped.
3. Applicants can access only their own approved data.
4. Staff cannot cross office, program, session, or purpose boundaries without an approved capability.
5. Credentials, tokens, secrets, and integration keys are never stored or logged in recoverable plaintext.
6. Critical state changes are attributable, reviewable, and tamper-evident under the declared threat model.
7. Untrusted files, documents, prompts, provider responses, and model outputs never become trusted code or authority.
8. Data collection, provider sharing, retention, and deletion are purpose-limited and explicit.

## 2. Assets and trust boundaries

| Asset | Primary risks | Required boundary |
|---|---|---|
| Applicant identity/contact data | Unauthorized disclosure, duplicate identity, account takeover | Applicant-own and approved office scope; encrypted transport; minimized provider sharing |
| Credentials/setup/reset challenges | Replay, theft, brute force, identifier-as-secret misuse | Hash-only challenge storage, expiry, atomic one-time use, rate limiting |
| Scores, counseling decisions, release results | Unauthorized change/disclosure, silent correction, autonomous decision | Scoped commands, versioned correction, human authority, mandatory audit |
| OMR sheets/images | Sensitive-content exposure, malicious upload, wrong-sheet attribution | Private storage, type/size/decompression limits, checksums, chain of custody, retention rule |
| Institutional documents | Stale/unauthorized corpus, prompt injection | Versioned owner-approved corpus; citations; no document text treated as executable instructions |
| Audit key/checkpoints | Key compromise, forged history, deletion | Separate key custody/version, append-only privileges, external checkpoint option |
| Integration credentials | Secret leakage and provider abuse | Secret store/environment injection; no UI/log echo; rotation procedure |
| Generated CSV/XLSX/DOCX/PDF/HTML | Formula injection, template injection, XSS, data leakage | Typed placeholders, context encoder, template validation, export neutralization |
| Backups/logs | Long-lived leakage, incomplete deletion, untested restore | Encryption/access control, retention, off-machine backup, restore and destruction procedure |

## 3. Authentication contract

### Applicant

Locked default (D-025 + **D-085**, 2026-08-05):

- Applicant No/Applicant Reference is an identifier, not a bearer secret;
- successful public submission mints a high-entropy **access key** immediately (before Registrar acceptance); store **hash only**;
- confirmation shows the plaintext key once and may offer browser save; email delivers the same key as backup when enabled;
- ongoing login requires **access key + birthdate** (both required); applicants do not invent a free-form password;
- result access remains gated by the authoritative ResultRelease;
- account/access provisioning requires a real approved delivery/recovery channel and never a fabricated fallback address;
- failed attempts are rate-limited; recovery responses are non-enumerating;
- birthday alone is never sufficient; access key alone is never sufficient;
- E2 recovery is registrar-assisted key reissue (email and/or F2F) that revokes the prior key hash; Nexi forgot-key is deferred;
- sessions rotate on authentication and privilege change;
- sensitive actions require recent authentication where justified.

See [`decisions/D-085-applicant-access-credential.md`](decisions/D-085-applicant-access-credential.md).

### Applicant access-challenge implementation direction (research-backed)

The greenfield implementation should use a dedicated applicant-access challenge record, or Laravel’s password-broker primitives only where the ApplicantAccount contract genuinely matches the broker contract. The design is based on Laravel 12’s password-reset implementation and OWASP’s Forgot Password and Authentication guidance:

- generate a high-entropy random challenge in application memory;
- store only a verifier/hash, never the raw challenge;
- bind the challenge to the ApplicantIdentity and, for initial setup, the submitted AdmissionAttempt;
- include purpose (`initial_setup` or `recovery`), issued time, expiry, delivery channel/reference, consumed time, and an idempotency/request reference;
- consume it atomically so a race cannot use it twice;
- expire it and run cleanup; Laravel’s password broker exposes configurable expiry/throttle behavior and an expired-token cleanup command;
- throttle challenge issuance per ApplicantIdentity/identifier and delivery channel, and use generic/non-enumerating responses;
- do not use Applicant No/Reference as the access key; Applicant No is a lookup/binding identifier only; birthdate is the second login factor with the access key (D-085), never a sole authenticator;
- a Laravel temporary signed URL may protect URL integrity and expiry, but it is not sufficient by itself for one-time use; a consumed challenge record remains required;
- issue the initial challenge in an after-commit effect after public submission, not after Registrar acceptance; delivery failure leaves the submission durable and creates a retry/recovery state;
- after first successful key+birthdate authentication, establish a durable ApplicantAccount session binding; released-result visibility still requires ResultRelease authorization;
- use Referrer-Policy protection and HTTPS for challenge URLs, do not log challenge values, and provide a bounded staff-assisted recovery path when the approved delivery channel is unavailable;
- personal facts such as birthday may be an additional assisted-recovery check, never the sole reset mechanism.

Research sources:

- Laravel 12 password reset documentation: `https://raw.githubusercontent.com/laravel/docs/12.x/passwords.md`
- Laravel 12 `DatabaseTokenRepository`: `https://raw.githubusercontent.com/laravel/framework/12.x/src/Illuminate/Auth/Passwords/DatabaseTokenRepository.php`
- Laravel 12 temporary signed URLs: `https://raw.githubusercontent.com/laravel/docs/12.x/urls.md`
- OWASP Forgot Password Cheat Sheet: `https://raw.githubusercontent.com/OWASP/CheatSheetSeries/master/cheatsheets/Forgot_Password_Cheat_Sheet.md`
- OWASP Authentication Cheat Sheet: `https://raw.githubusercontent.com/OWASP/CheatSheetSeries/master/cheatsheets/Authentication_Cheat_Sheet.md`

### Staff

- unique named accounts; no shared office credentials;
- password policy and approved recovery;
- optional institutional OAuth only after verified-email, trusted-domain, linking, consent, throttling, and takeover behavior are specified;
- deactivation immediately prevents new sessions and revokes active sessions where feasible.

### Break-glass

If Super Admin is retained:

- no routine operational use of business actions;
- full audit-scope user view is permitted as the master audit review surface, including cross-office/user/event filtering;
- strong authentication;
- explicit incident/support reason;
- time-bounded access;
- alert to designated reviewer;
- mandatory post-use review; audit-view access is itself an audit event and cannot be hidden by the viewer.

## 4. Authorization contract

- Policies/application authorization enforce the approved `04-role-capability-and-data-scope-matrix.md`.
- Route middleware may establish authentication/coarse capability but cannot replace resource ownership checks.
- Proctor access requires a current ExamSession assignment; it does not require or grant a permanent Proctor role.
- Test Administrator is an audited capability overlay held by an authorized Guidance Counselor, Guidance Clerk, or Guidance Support Staff account; it is not a generic office role.
- Registrar Support Staff is not Registrar Clerk-equivalent: it may accept/dismiss within explicit Registrar delegation and view statuses, but cannot use institutional/reference/setup pages.
- Program Head access is program-scoped and read-only by default.
- Super Admin audit visibility does not imply routine authority to change scores, counseling decisions, admissions, or releases.
- Every permission grant/revoke, duty assignment/revoke, denied protected action, and break-glass use produces audit evidence.
- Every protected applicant-record, result-sheet, converted-score, consultation-history, or program-projection view records the actor, target applicant/aggregate, scope, timestamp, purpose/category, and outcome; a future applicant-facing access-history view may expose approved privacy-safe entries without weakening the underlying audit record.
- Every accepted/dismissed application, application-window change/late override, slot-reservation open/close/finalize, manual assignment, direct-assessment start, exam publication, force-open/close, OMR identity/mark correction, offline replay/conflict resolution, assistant task proposal/confirmation/result, and audit-query/export emits attributable evidence.
- Frontend hiding is never an authorization control.

## 5. Privacy and data-handling contract

### Required data-classification fields

For each data class, the future approved lifecycle matrix must name:

- purpose;
- data owner/custodian;
- subjects;
- sensitivity;
- collection source;
- allowed roles/scopes;
- external providers and transfer content;
- active retention and archive period;
- correction/restriction/export/deletion handling;
- backup/log copies;
- destruction verification;
- incident owner.

### Defaults before institutional approval

- Controlled demonstrations use synthetic or expressly approved sanitized fixtures.
- No real applicant sheet/image or personal record is uploaded to external AI/vector/model providers without approved handling.
- Prompts and inference envelopes minimize personal data; use pseudonymous attempt IDs where possible.
- Raw OMR images have stricter access and a working default of 90 days after accepted import/finalization and correction/appeal-hold closure; finalized scoring provenance follows the assessment/audit retention class.
- Working defaults are five years after academic-cycle closure for applicant/admission/exam/score/consultation/result records and seven years for audit/security evidence; institutional/privacy approval, legal hold, and the retention controller can override these before pilot/production.
- The retention controller evaluates the lifecycle/data-class reference and active `retention_holds` before archive, anonymization, or purge. Placing or releasing a hold is an approved, audited command; releasing a hold does not itself delete data.
- Logs contain identifiers only where operationally necessary and never credentials/secrets/full sensitive payloads.
- Formal NPC certification and completed PIA must not be claimed.

## 6. Upload, import, and document security

### File intake

Every importer/processor must enforce:

- explicit allowlisted media/file types validated by content, not extension alone;
- size, row/page, decompression, and processing-time limits;
- private quarantine before parsing;
- random internal storage names and checksums;
- no path traversal or user-controlled executable path;
- no external URL fetching unless an adapter enforces allowlist, DNS/IP/redirect/size/time controls;
- idempotent import key and row-level error report;
- atomic authoritative import or explicit partial-result contract.

### Spreadsheet and export

- Neutralize cells beginning with formula control characters in every CSV/XLSX export.
- Reject or neutralize external links/macros where applicable.
- Preserve original row and source identifiers in error reports without exposing secrets.

### Template rendering

- Placeholders are typed: escaped text, trusted audited HTML, document text, table row, numeric/date value.
- User/applicant text never selects rendering context.
- HTML output enforces CSP and escaping.
- DOCX/PDF/CSV/XLSX have format-specific encoders and malicious-fixture tests.

## 7. RAG and model security

- Authorization and data retrieval occur before prompt construction.
- The LLM/model never decides which records the caller may access.
- Institutional corpus sources are owner-approved, versioned, attributable, and revocable.
- Retrieved content is untrusted data; embedded prompt-like text cannot override system policy.
- Responses return citations/evidence or refuse below the approved support threshold.
- Placement/admission advice is outside RAG authority.
- Policy answers may link the user to the governing policy page; policy text is explanatory content, never an executable instruction.
- Staff task assistance is limited to an allowlisted catalog of typed UI-equivalent commands. Authorization, state/window/capability checks, mutation confirmation, idempotency, and audit happen in the application service after retrieval and before execution.
- The model cannot call the database, select arbitrary routes, invent an unlisted command, bypass frontend/request gates, or retrieve records outside the server-resolved scope.
- Provider timeout/outage returns a bounded degraded response and staff fallback.
- Arbitrary manifests, external URLs, Python classes, or UI component paths cannot be registered/executed by routine administrators.
- Fixed approved adapters are the capstone default; future extensibility requires signed/allowlisted deployment governance.

## 8. Audit threat-model contract

### Protected claims

The audit system may claim:

- critical application events are append-only for routine application roles;
- each canonical event is linked to prior sequence/hash state under one approved serialization model;
- verification detects covered modification, reordering, insertion, or deletion after a trusted checkpoint;
- key versions and checkpoints allow historical verification.

It may not claim protection against an attacker who simultaneously controls the database, application runtime, active HMAC keys, checkpoint destination, and verifier unless the architecture explicitly addresses that attacker.

### Canonical audit event

Minimum fields:

- event ID and sequence;
- occurred-at and recorded-at timestamps;
- actor type/ID and effective role/capability;
- action and category;
- aggregate/subject type and ID, nullable for system events;
- cycle/office/program/session scope where applicable;
- command/correlation/idempotency ID;
- reason/override marker;
- redacted canonical old/new values or event payload;
- previous hash, current hash, key version;
- checkpoint reference where applicable.

### Mandatory critical-event families

- login/setup/reset, lockout, staff OAuth link/unlink;
- account creation/deactivation and role/capability/duty assignment;
- protected data view/export where approved;
- report view/export and document preview/generation/distribution/physical-print evidence where protected;
- application acceptance/dismissal/reopen/archive/correction;
- exam publication/start/cancel/reopen, assignment, attendance/submission;
- application-window and slot-reservation lifecycle, manual late assignment, direct-assessment start, and schedule publication/notification;
- score import/edit/finalize/reopen;
- OMR processing/review/import, identity binding, mark correction, and raw-artifact retention/purge state;
- offline outbox creation/replay/conflict/reconciliation;
- counseling decision/override/defer;
- release/unrelease/document generation;
- corpus/settings/integration/key changes;
- assistant exchange failure/retry, history clear, persona change, task proposal/confirmation/result, and confirmed refusal-to-inquiry creation where retained;
- break-glass access and verifier/checkpoint failure.
- retention-hold placement/release, retention-policy evaluation, and purge/archive/destruction outcome.

### Failure policy

- Mandatory audit append failure aborts the owning critical command.
- External checkpoint failure does not rewrite committed history; it creates an observable security/operations incident and blocks stronger assurance claims until recovered.
- Verification status is health evidence, not a dashboard decoration.

## 9. Security evidence gates

| Gate | Required proof |
|---|---|
| SEC-G1 Identity | Expiry, reuse, race, enumeration, throttling, session rotation, deactivation tests |
| SEC-G2 Authorization | Allowed/denied/cross-scope matrix, Proctor revocation, Program Head isolation, own-data isolation |
| SEC-G3 File/document | Malformed/oversized/decompression fixtures, path/URL controls, formula injection, XSS/template tests |
| SEC-G4 RAG/model | Data-scope isolation, prompt-injection fixtures, citation/refusal, provider outage, untrusted-output handling |
| SEC-G5 Audit | Canonicalization, concurrent append, tamper/delete/reorder detection, key rotation, checkpoint, append-only privilege |
| SEC-G6 Privacy/operations | Data classification, provider map, backup/restore/destruction, incident and access-review process |
| SEC-G7 Offline proctoring | Encrypted local scope, operation identity, outbox durability, replay idempotency, stale/conflict handling, human reconciliation, and degraded-state visibility |

## 10. Pilot/production blockers

- unapproved real-data purpose/consent/retention;
- unresolved role or cross-office scope;
- identifier treated as a secret;
- plaintext/reusable setup/reset tokens;
- untrusted upload or arbitrary endpoint/plugin execution;
- no representative OMR/RAG/model technical evidence while claims remain;
- no representative offline-proctoring/replay/conflict evidence while the committed capability is claimed;
- no approved audit threat model while cryptographic assurance is claimed;
- no backup/restore and incident ownership;
- no provider data-handling inventory.

## 11. Decisions

- D-AUTH-01 applicant credential and recovery model.
- D-AUTH-02 staff OAuth inclusion and linking rules.
- D-ROLE-04 break-glass/Super Admin retention.
- D-PRIV-01 data classes, lawful/institutional purpose, consent evidence.
- D-PRIV-02 retention and erasure/restriction rules.
- D-PRIV-03 external provider data sharing.
- D-AUD-01 threat model, key custody, serialization, checkpoints, failure behavior.
- D-UPLOAD-01 accepted upload channels and storage/quarantine.
- D-RAG-02 prompt/exchange retention and staff fallback.
- D-073/D-074 public intake/account activation and canonical admission vocabulary.
- D-075/D-076 exam-close/reopen and grading/import integrity.
- D-077/D-078 release/publication and document authority.
- D-079/D-080 RAG inquiry fallback and protected reporting/privacy semantics.
