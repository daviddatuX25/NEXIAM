# Phase 5: AI Companion Edge Cases & Security Hardening - Research

**Researched:** 2026-04-14
**Domain:** Laravel AI Chat Security, Rate Limiting, Input Validation
**Confidence:** MEDIUM

## Summary

This phase focuses on comprehensive security hardening for the AI Companion chat feature. The current implementation has basic protection (2000 char limit, 20 message history), but lacks per-user rate limiting, content safety, prompt injection protection, and proper edge case handling.

**Primary recommendation:** Implement Laravel's built-in RateLimiter for per-user rate limiting, add input validation warnings, and implement guardrails in the service layer without external dependencies.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- [None explicitly stated - this is a new phase]

### Claude's Discretion
Research and recommend appropriate security hardening approaches for AI Companion

### Deferred Ideas (OUT OF SCOPE)
- None identified

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel RateLimiter | Built-in | Per-user rate limiting | Native Laravel feature, no extra dependencies |
| Laravel Validation | Built-in | Input validation | Framework standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP `strip_tags()` | Built-in | Input sanitization | Basic XSS prevention |
| Laravel Log | Built-in | Audit logging | Security event tracking |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|-----------|-----------|----------|
| Laravel RateLimiter | Cloudflare Rate Limiting | Less control, external dependency |
| Custom rate limiting | Redis-based limiter | More complex, requires Redis |
| Content safety API | Local keyword filtering | Simpler, no external calls |

**Installation:**
No new packages required - all features are Laravel built-ins.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Http/
│   ├── Middleware/
│   │   └── ThrottleAiCompanionRequests.php  # Rate limit middleware
│   └── Requests/
│       └── AiCompanionChatRequest.php        # Enhanced validation
├── Services/
│   └── AiCompanionService.php               # Security guardrails here
└── Models/
    └── AiCompanionMessage.php               # Message history management
```

### Pattern 1: Per-User Rate Limiting
**What:** Apply rate limits per authenticated applicant for AI chat requests
**When to use:** Prevent abuse and resource exhaustion
**Example:**
```php
// In AppServiceProvider or RouteServiceProvider
RateLimiter::for('ai-companion', function (Request $request) {
    /** @var Applicant $applicant */
    $applicant = $request->user('applicant');
    $key = $applicant?->id ?? $request->ip();
    return Limit::perMinute(10)->by($key)->response(function () {
        return response()->json(['message' => 'Too many requests. Please wait.'], 429);
    });
});

// In routes/web.php
Route::post('/portal/ai-companion/chat')
    ->middleware(['auth:applicant', 'throttle:ai-companion'])
    ->...
```

### Pattern 2: Input Validation with Warnings
**What:** Warn users before they hit limits, not just block at limits
**When to use:** Better UX while maintaining security
**Example:**
```php
// In AiCompanionChatRequest
public function messages(): array
{
    return [
        'message.required' => 'Please enter a message.',
        'message.string' => 'Message must be text.',
        'message.max:2000' => 'Message cannot exceed 2000 characters.',
        // Warning (not rule) - handled in controller/service
    ];
}

// In service - check approaching limit
$messageLength = mb_strlen($userMessage);
$warning = null;
if ($messageLength > 1800) {
    $warning = 'You are approaching the 2000 character limit.';
}
```

### Pattern 3: History Limit Warnings
**What:** Inform users when they're approaching history limit so they can clear
**When to use:** When history context affects AI responses
**Example:**
```php
// In controller, return warning in response
$historyCount = AiCompanionMessage::where('applicant_id', $applicant->id)->count();
$warning = null;
if ($historyCount >= AiCompanionService::DEFAULT_MAX_HISTORY - 3) {
    $warning = 'You are approaching the message history limit. Consider clearing history for better responses.';
}
return response()->json(['reply' => $reply, 'warning' => $warning]);
```

### Pattern 4: Code Generation Guardrails
**What:** Detect and block requests asking for code generation
**When to use:** Prevent AI from generating potentially harmful code
**Example:**
```php
// In AiCompanionService - add before chat()
private function containsCodeGenerationRequest(string $message): bool
{
    $patterns = [
        '/write\s+(a\s+)?(php|javascript|python|sql|html|css)\s+(code|function|script)/i',
        '/generate\s+(a\s+)?(php|javascript|python|sql|html|css)\s+(code|function|script)/i',
        '/create\s+(a\s+)?(program|script|exploit|payload)/i',
        '/how\s+to\s+(hack|exploit|bypass|inject)/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    return false;
}

// In chat() method
if ($this->containsCodeGenerationRequest($userMessage)) {
    return ['reply' => 'I\'m sorry, but I cannot help with code generation requests. I can only assist with questions about the application process, courses, and admission requirements.'];
}
```

### Pattern 5: Prompt Injection Detection
**What:** Detect attempts to manipulate the AI's system prompt
**When to use:** Prevent users from trying to override system instructions
**Example:**
```php
// In AiCompanionService
private function containsPromptInjection(string $message): bool
{
    $patterns = [
        '/ignore\s+(previous|all|above)\s+(instructions?|rules?|prompts?)/i',
        '/system\s*:/i',
        '/<\|.*\|>/',  // Token manipulation attempts
        '/\[SYSTEM\]/i',
        '/\#\#\#\s*SYSTEM/i,
        '/override\s+(your\s+)?(instructions?|rules?)/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    return false;
}
```

### Pattern 6: Content Safety (Basic)
**What:** Basic keyword-based content filtering
**When to use:** Simple safety without external APIs
**Example:**
```php
// In AiCompanionService
private function containsUnsafeContent(string $message): bool
{
    $unsafePatterns = [
        '/violence/i',
        '/threat/i',
        '/self[-\s]?harm/i',
    ];
    foreach ($unsafePatterns as $pattern) {
        if (preg_match($pattern, $message)) {
            return true;
        }
    }
    return false;
}
```

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Rate limiting | Custom Redis counter | Laravel RateLimiter | Tested, documented, configurable |
| Content safety API | External API wrapper | Simple keyword filtering | No external dependencies, sufficient for basic use |
| Session handling | Custom session management | Laravel session + auth | Built-in security features |

## Common Pitfalls

### Pitfall 1: Rate Limiting Without User Context
**What goes wrong:** Rate limiting only by IP allows attackers to bypass by changing IP
**Why it happens:** Using `throttle:60,1` without custom key
**How to avoid:** Use per-user rate limiter with applicant ID as key
**Warning signs:** Unusual patterns from single IP with different sessions

### Pitfall 2: Blocking Without Explanation
**What goes wrong:** Users hit rate limit and get generic error
**Why it happens:** Default Laravel throttle response
**How to avoid:** Custom response with helpful message and retry time
**Warning signs:** Support tickets about "AI not working"

### Pitfall 3: Storing Sensitive Data in Messages
**What goes wrong:** AI Companion messages might contain PII inadvertently
**Why it happens:** Users might paste personal info in messages
**How to avoid:** Add privacy notice, consider input sanitization
**Warning signs:** Large message content, patterns like SSN, phone numbers

### Pitfall 4: History Limit Without Warning
**What goes wrong:** Old messages silently dropped, AI loses context
**Why it happens:** Default behavior without notification
**How to avoid:** Warn users before limit, offer clear history option
**Warning signs:** AI responses seem to "forget" earlier conversation

### Pitfall 5: Error Information Leakage
**What goes wrong:** Detailed errors expose internal implementation
**Why it happens:** Propagating raw exception messages
**How to avoid:** Generic error messages in production, detailed logs server-side
**Warning signs:** Error messages showing file paths, database structure

## Code Examples

### Enhanced Request Validation with Warnings
```php
// Source: Laravel 12 Form Requests
// app/Http/Requests/Portal/AiCompanionChatRequest.php

class AiCompanionChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('applicant') !== null;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Message cannot exceed :max characters.',
        ];
    }

    // Additional validation for warning thresholds
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $message = $this->input('message', '');
            $length = mb_strlen($message);
            
            if ($length > 1800 && $length <= 2000) {
                // Add warning (not error) - handled in controller
                $this->merge(['_warning' => 'Approaching character limit']);
            }
        });
    }
}
```

### Rate Limiter Configuration
```php
// Source: Laravel 12 Rate Limiter
// app/Providers/AppServiceProvider.php or RouteServiceProvider

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // AI Companion: 10 requests per minute per applicant
    RateLimiter::for('ai-companion', function (Request $request) {
        $applicant = $request->user('applicant');
        
        if (! $applicant) {
            return Limit::perMinute(5)->by($request->ip());
        }
        
        return Limit::perMinute(10)->by('ai-companion:' . $applicant->id);
    });

    // Clear history: 5 requests per minute to prevent abuse
    RateLimiter::for('ai-companion-clear', function (Request $request) {
        return Limit::perMinute(5)->by($request->user('applicant')?->id ?? $request->ip());
    });
}
```

### Enhanced Service with Guardrails
```php
// Source: Custom implementation based on best practices
// app/Services/AiCompanionService.php additions

class AiCompanionService
{
    public const DEFAULT_MAX_HISTORY = 20;
    public const WARNING_THRESHOLD_HISTORY = 17; // Warn at 17+ messages
    public const WARNING_THRESHOLD_LENGTH = 1800; // Warn at 1800+ chars

    public function chat(Applicant $applicant, string $userMessage, int $maxHistory = self::DEFAULT_MAX_HISTORY): array
    {
        // 1. Input sanitization
        $userMessage = $this->sanitizeInput($userMessage);

        // 2. Guardrails - code generation
        if ($this->containsCodeGenerationRequest($userMessage)) {
            return [
                'reply' => 'I\'m sorry, but I cannot help with code generation. I can assist with questions about our courses, admission requirements, and application process.',
                'blocked' => true,
            ];
        }

        // 3. Guardrails - prompt injection
        if ($this->containsPromptInjection($userMessage)) {
            return [
                'reply' => 'I noticed an unusual request. Let\'s focus on helping with your application questions.',
                'blocked' => true,
            ];
        }

        // 4. Guardrails - unsafe content
        if ($this->containsUnsafeContent($userMessage)) {
            return [
                'reply' => 'I\'m here to help with admission-related questions. Please rephrase your question.',
                'blocked' => true,
            ];
        }

        // ... rest of existing chat logic
    }

    private function sanitizeInput(string $message): string
    {
        // Basic XSS prevention - strip dangerous HTML
        return strip_tags($message);
    }

    // ... guardrail methods
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No rate limiting | Laravel RateLimiter per user | This phase | Prevents abuse |
| Max 2000 chars hard limit | Soft warnings at 1800 chars | This phase | Better UX |
| No history warnings | Warning at 17 messages | This phase | Users can manage context |
| No content filtering | Basic guardrails | This phase | Safety improvement |
| Generic errors | Detailed logging, user-friendly messages | This phase | Debugging + UX |

**Deprecated/outdated:**
- IP-only rate limiting: Replaced with per-user + IP fallback

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Laravel's built-in RateLimiter is sufficient for this use case | Rate Limiting | LOW - well-documented Laravel feature |
| A2 | Keyword-based content filtering is sufficient for basic safety | Content Safety | MEDIUM - may need external API for production-grade safety |
| A3 | No external content safety API is required | Content Safety | MEDIUM - depends on compliance requirements |
| A4 | The 10 requests/minute rate limit is appropriate | Rate Limiting | LOW - can be adjusted based on usage data |

## Open Questions

1. **Content Safety API**
   - What we know: Basic keyword filtering can catch obvious cases
   - What's unclear: Whether regulatory requirements need external content moderation API
   - Recommendation: Start with local filtering, evaluate need for external API based on usage

2. **Rate Limit Thresholds**
   - What we know: 10 requests/minute is standard for chat APIs
   - What's unclear: May need adjustment based on actual usage patterns
   - Recommendation: Start with 10/minute, monitor and adjust

3. **History Limit Warning Timing**
   - What we know: 20 message default limit
   - What's unclear: Whether 3-message warning threshold is appropriate
   - Recommendation: Start with warning at 17 messages (3 before limit)

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Laravel RateLimiter | Per-user rate limiting | ✓ | Built-in Laravel 12 | — |
| PHP strip_tags | Input sanitization | ✓ | PHP 8.2 | — |
| Laravel Log | Audit logging | ✓ | Built-in Laravel 12 | — |

**Missing dependencies with no fallback:**
- None identified

**Missing dependencies with fallback:**
- None identified

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=AiCompanion` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SEC-01 | Rate limiting per applicant | Unit | `php artisan test --filter=testRateLimiting` | Need to create |
| SEC-02 | Message length validation | Unit | `php artisan test --filter=testMessageValidation` | Need to create |
| SEC-03 | Code generation guardrails | Unit | `php artisan test --filter=testCodeGenerationBlock` | Need to create |
| SEC-04 | Prompt injection detection | Unit | `php artisan test --filter=testPromptInjectionBlock` | Need to create |
| SEC-05 | History limit warnings | Integration | `php artisan test --filter=testHistoryWarning` | Need to create |
| SEC-06 | Content safety | Unit | `php artisan test --filter=testContentSafety` | Need to create |

### Sampling Rate
- **Per task commit:** Quick run with `--filter` for specific test
- **Per wave merge:** Full suite with `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Portal/AiCompanionSecurityTest.php` — covers all security features
- [ ] `tests/Unit/Services/AiCompanionGuardrailsTest.php` — unit tests for guardrails
- [ ] No new framework required - existing PHPUnit setup is sufficient

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | Yes | Laravel auth:applicant guard |
| V3 Session Management | Yes | Laravel session + auth |
| V4 Access Control | Yes | Policy-based authorization |
| V5 Input Validation | Yes | Form Request + Service validation |
| V6 Cryptography | No | N/A - no cryptographic operations |

### Known Threat Patterns for AI Chatbots

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Prompt injection | Tampering | Detect and block injection patterns in input |
| Rate limit bypass | Denial of Service | Per-user rate limiting with fallback |
| Code generation abuse | Tampering | Keyword detection and refusal |
| Data exfiltration via AI | Information Disclosure | System prompt limits, input filtering |
| History manipulation | Tampering | Immutable message storage, no edit capability |

## Sources

### Primary (HIGH confidence)
- Laravel 12 Documentation - Rate Limiting
- Laravel 12 Documentation - Form Requests
- Project's existing AiCompanionService implementation

### Secondary (MEDIUM confidence)
- OWASP AI Security Top 10 - Prompt injection patterns
- Laravel Best Practices skill - Security rules

### Tertiary (LOW confidence)
- General AI chatbot security best practices (not specific to Laravel)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All features are Laravel built-ins
- Architecture: HIGH - Follows existing project patterns
- Pitfalls: MEDIUM - Based on general AI security knowledge

**Research date:** 2026-04-14
**Valid until:** 30 days (Laravel features are stable)