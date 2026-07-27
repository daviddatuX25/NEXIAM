# Codebase Concerns

**Analysis Date:** 2026-04-13

## Tech Debt

**Exam Scheduling Assistant Service:**
- Issue: Multiple whereHas queries executed in loops, potential N+1 patterns
- Files: `app/Services/ExamSchedulingAssistantService.php`
- Impact: Performance degradation with large datasets
- Fix approach: Refactor to eager load relationships or use subqueries

**Knowledge Retrieval Service:**
- Issue: Fallback to MySQL when Mixedbread API fails - potential data inconsistency
- Files: `app/Services/KnowledgeRetrievalService.php`
- Impact: Users may get outdated or incomplete information
- Fix approach: Implement proper sync status indicators, consider queue-based sync

**Dashboard Service:**
- Issue: Multiple empty return patterns (return null, return [], return {})
- Files: `app/Services/DashboardService.php` (4 occurrences)
- Impact: Unclear error handling, potential silent failures
- Fix approach: Return typed responses with error metadata

**AI Companion Service:**
- Issue: Model configuration hardcoded with fallback to 'openrouter/free'
- Files: `app/Services/AiCompanionService.php` (line 116)
- Impact: Unexpected behavior if config is missing
- Fix approach: Throw configuration exception rather than silently falling back

## Known Bugs

**Session Roster Time Override:**
- Symptoms: Time override can be set but not logged to audit trail
- Files: `app/Http/Controllers/Proctor/SessionRosterController.php` (line 258)
- Trigger: When proctor overrides session start time
- Workaround: Manual audit logging until feature is implemented

**Knowledge Document Sync:**
- Symptoms: UI shows error feedback when retrySync fails but no detailed error info
- Files: `app/Http/Controllers/Admin/KnowledgeDocumentController.php`
- Trigger: When sync to Mixedbread fails
- Workaround: Check server logs for details

## Security Considerations

**Password Handling:**
- Risk: Manual Hash::make usage in controllers
- Files: `app/Http/Controllers/PortalAuthController.php`, `app/Http/Controllers/Admin/UserController.php`
- Current mitigation: Uses Laravel Hash facade
- Recommendations: Consider using model observers for consistent password handling

**OAuth Configuration:**
- Risk: Empty config checks without exception throwing
- Files: `app/Support/GoogleOAuthConfig.php`
- Current mitigation: Checks config presence before use
- Recommendations: Add validation at boot time

**Rate Limiting:**
- Current: Login throttling configured in AppServiceProvider
- Files: `app/Providers/AppServiceProvider.php`
- Notes: Demo mode allows configuration override

## Performance Bottlenecks

**Exam Session Queries:**
- Problem: Complex whereHas chains in ExamSessionController
- Files: `app/Http/Controllers/Admin/ExamSessionController.php` (9 occurrences)
- Cause: Multiple nested relationship queries without eager loading
- Improvement path: Add with() eager loading for relationships

**Application Queries:**
- Problem: Multiple get()/first() calls in loops
- Files: `app/Http/Controllers/ApplicationController.php` (5 occurrences)
- Cause: Iterating over collections without batch loading
- Improvement path: Use whereIn with collected IDs

**Grading Print Queries:**
- Problem: Heavy use of get()/first() in GradingPrintController
- Files: `app/Http/Controllers/Grading/GradingPrintController.php` (11 occurrences)
- Cause: N+1 queries in printing logic
- Improvement path: Implement batch queries and caching

## Fragile Areas

**Route Definitions:**
- Files: `routes/web.php`
- Why fragile: Large file with many route definitions, single point of failure
- Safe modification: Use route groups and name prefixes consistently

**Middleware Stack:**
- Files: `app/Http/Middleware/HandleInertiaRequests.php`
- Why fragile: Global middleware affects all pages, changes can cascade
- Safe modification: Test thoroughly, use feature flags for gradual rollout

**Form Request Validation:**
- Files: Multiple in `app/Http/Requests/`
- Why fragile: Validation logic centralized, changes affect all endpoints
- Safe modification: Add new rules rather than modifying existing ones

## Scaling Limits

**Database:**
- Current capacity: Unknown without load testing
- Limit: Eloquent queries without proper indexing
- Scaling path: Add database indexes, implement query optimization

**File Storage:**
- Current capacity: Local filesystem only
- Limit: Disk space and single-server deployment
- Scaling path: Implement S3-compatible storage driver

**Session Management:**
- Current capacity: File-based sessions (default)
- Limit: Single-server, no horizontal scaling
- Scaling path: Migrate to Redis or database sessions

## Dependencies at Risk

**OpenRouter:**
- Risk: External API dependency, potential rate limits
- Impact: AI Companion and Exam Scheduling Assistant fail
- Migration plan: Implement local fallback or alternative providers

**Mixedbread:**
- Risk: Vector store API dependency
- Impact: Knowledge retrieval fails
- Migration plan: Use alternative vector DB or MySQL full-text fallback

**Tailwind CSS v4:**
- Risk: New major version, potential breaking changes
- Impact: UI rendering issues
- Migration plan: Monitor upgrade path, test extensively

## Missing Critical Features

**Audit Logging:**
- Problem: No centralized audit trail for sensitive actions
- Blocks: Compliance requirements, forensic analysis
- Priority: High

**Error Monitoring:**
- Problem: No error tracking service (Sentry, etc.)
- Impact: Unknown production issues
- Priority: Medium

**API Versioning:**
- Problem: No formal API version strategy
- Blocks: Third-party integrations
- Priority: Low

## Test Coverage Gaps

**Services:**
- What's not tested: MixedbreadService, OpenRouterChatService
- Files: `app/Services/MixedbreadService.php`, `app/Services/OpenRouterChatService.php`
- Risk: External API failures go undetected
- Priority: High

**Controllers:**
- What's not tested: GradingPrintController, AdmissionSlipPrintController
- Files: `app/Http/Controllers/Grading/GradingPrintController.php`, `app/Http/Controllers/AdmissionSlipPrintController.php`
- Risk: Print generation failures not caught
- Priority: Medium

**Policies:**
- What's not tested: ExamSessionPolicy (9 authorization methods)
- Files: `app/Policies/ExamSessionPolicy.php`
- Risk: Authorization bypasses undetected
- Priority: High

---

*Concerns audit: 2026-04-13*