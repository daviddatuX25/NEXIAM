# Evidence: Nexi Exam Session Assistant (OpenSpec 8)

**Date:** 2026-08-10  
**OpenSpec:** `openspec/changes/nexi-exam-session-assistant/` (ACTIVE — implementation proven; founder PASS pending)  
**Model lock:** `docs/project-context.md` §0.1 + OpenSpec proposal/design vocabulary

## What was proven

Pest `tests/Feature/Ai/NexiExamSessionAssistantTest.php` — **6 passed / 55 assertions**:

1. Exam show pack exposes `assistant` mode, `assistant_available`, and `skill_start` for `schedule.exam_session@1`
2. Skill allowlist rejects `knowledge.sources.list@1` even for guidance staff
3. Confirm path open → assign → publish uses Kernel → Examination Actions (booking open, roster assign, published)
4. Stale `expected_version` invalidates proposal on confirm
5. Cross-user confirm denied (`proposal_actor_mismatch`)
6. Assistant turn NL “open reservation” → `skill.plan/tool/result/proposal` events → confirm applies Action

## Founder context preserved in plans

| Artifact | Lock content |
|---|---|
| `docs/project-context.md` §0.1 | One auth, one core, capability matrix, skill≠MD, multi-step engine, projections, persistence |
| `openspec/.../proposal.md` | Same six non-negotiables + pilot skill |
| `openspec/.../design.md` | Vocabulary table aligned to founder wording |
| `openspec/.../HANDOFF.md` | Model lock + seeds |
| `docs/GREENFIELD-MASTER-REFERENCE.md` §13 | Active OS8; OpenSpec 1–7 PASS |

## Runtime notes

- Compose image bake (no bind mount): rebuild `app`/`queue` after PHP changes; `docker compose cp` used during iteration
- Fake gateway OK; mutations are Kernel/Action, not LLM writes
- AssistPanel confirm/cancel call `/nexi/proposals/{id}/confirm|cancel`; skill start on Assistant mode for exam packs

## Remaining before archive PASS

- Founder manual walk on `:18000` guidance → session Show → Assistant → “open reservation” → Confirm
- Archive when founder PASSes
