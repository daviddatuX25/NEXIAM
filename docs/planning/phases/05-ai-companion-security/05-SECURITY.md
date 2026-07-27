---
phase: 05
slug: ai-companion-security
status: verified
threats_open: 0
asvs_level: 1
created: 2026-04-14
---

# Phase 05 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| applicant → API | Untrusted input crosses here | User message to AI Companion |
| applicant → AI Service | Untrusted user message crosses here | Sanitized input to LLM |
| Browser → Server | User message crosses here | JSON payloads with chat messages |

---

## Threat Register

| Threat ID | Category | Component | Disposition | Mitigation | Status |
|-----------|----------|-----------|-------------|------------|--------|
| T-05-01 | Denial of Service | ai-companion/chat endpoint | mitigate | RateLimiter::for('ai-companion') with Limit::perMinute(10) in AppServiceProvider.php | closed |
| T-05-02 | Information Disclosure | Rate limit response | accept | Generic error message, no sensitive data exposed - see AppServiceProvider.php | closed |
| T-05-03 | Tampering | containsCodeGenerationRequest | mitigate | Private method in AiCompanionService.php with 4 regex patterns blocking code generation requests | closed |
| T-05-04 | Tampering | containsPromptInjection | mitigate | Private method in AiCompanionService.php with 6 regex patterns blocking prompt injection attempts | closed |
| T-05-05 | Information Disclosure | containsUnsafeContent | mitigate | Private method in AiCompanionService.php with 3 regex patterns blocking violence/threat/self-harm | closed |
| T-05-06 | Tampering | sanitizeInput | mitigate | Private method in AiCompanionService.php using strip_tags() for HTML/script removal | closed |
| T-05-07 | Information Disclosure | Privacy notice | accept | Privacy notice displayed in AiCompanion.svelte and AiCompanionChatWidget.svelte - informational | closed |

*Status: open · closed*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-05-01 | T-05-02 | Rate limit responses return generic message with retry_after - no applicant data exposed | System | 2026-04-14 |
| AR-05-02 | T-05-07 | Privacy notice is informational - reminds users not to share sensitive data; no technical control | System | 2026-04-14 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-04-14 | 7 | 7 | 0 | gsd-secure-phase |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-04-14