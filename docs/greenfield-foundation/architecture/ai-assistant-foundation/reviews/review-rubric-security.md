---
review: rubric-security-final-pass
target: ../ARCHITECTURE-SPINE.md
companion: ../READINESS-PLAN.md
date: 2026-08-09
verdict: pass
---

# Rubric + Security Review — AI Assistant Foundation

## Verdict

**PASS:** no high or critical architecture-spine finding remains. The deterministic spine linter reports zero findings.

## Finding closure

- **H-1 closed:** server-owned context epochs prevent sensitive history replay across narrowed scopes.
- **H-2 closed:** new epochs default to Inquiry; Assistant requires bound, expiring consent.
- **H-3 closed:** effective policy is deny-dominant and overrides cannot create authority.
- **H-4 closed:** protected reads require bounded invocation grants and fail-closed evidence before release.
- **H-5 closed:** proposal bytes bind the rule dependency set, preflight, complete constraint evidence, projection/privacy version, and evidence time. The final Action or queue worker repeats those checks, resolves the active `RulePublication`, locks affected state, reruns commit-relevant domain rules, and returns a non-commit conflict on drift.
- **H-6 closed:** revocation immediately denies the source predicate and invalidates retrieval caches, context epochs, in-flight/replayable citations, and answer artifacts. Reconnect and conversation continuation revalidate source eligibility before provider calls or durable citation completion.

## Security rubric result

- Domain Actions, policies, and shared evaluators remain canonical; prompts and provider output are guidance/candidates only.
- Atomic `RulePublication` prevents mixed registry, pack, skill, prompt, and evaluator artifacts; delayed invalidation cannot preserve authority because the kernel independently rejects stale dependencies.
- One `KnowledgeRouter` and versioned source registry enforce policy-RAG versus deterministic own-data/operational-query isolation.
- Proposal confirmation, queued execution, source lifecycle, cache invalidation, replay, and conversation continuation now have explicit fail-closed behavior.

## Gate conclusion

The spine is a safe, enforceable build substrate for the foundation OpenSpec. Provider, retention, index, quota, and operational values remain correctly gated or deferred rather than becoming hidden implementation choices.
