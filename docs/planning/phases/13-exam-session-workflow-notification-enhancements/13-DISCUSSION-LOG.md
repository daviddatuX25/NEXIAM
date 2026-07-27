# Phase 13: Exam Session Workflow & Notification Enhancements - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-19
**Phase:** 13-exam-session-workflow-notification-enhancements
**Areas discussed:** Workflow transitions, Notification delivery, Email scope, My Sessions redesign

---

## Workflow transitions

| Option | Description | Selected |
|--------|-------------|----------|
| Strict one-way | publish → in-progress → completed, no backward | |
| One-way + unpublish | Same forward flow, but allow unpublish for typo fixes | ✓ |

**User's choice:** One-way + unpublish
**Notes:** Allow published → draft for corrections before exam day

---

## Transition control

| Option | Description | Selected |
|--------|-------------|----------|
| Role-based | Proctor/test_admin can start, test_admin/admin can close | ✓ |
| Same role for both | Any authorized user can start AND complete | |

**User's choice:** Role-based

---

## Start flow

| Option | Description | Selected |
|--------|-------------|----------|
| Manual button on roster | Proctor/test_admin clicks "Start session", checks window | ✓ |
| Auto-start at scheduled time | Automatic based on current time matching start_time | |

**User's choice:** Manual button on roster

---

## Complete flow

| Option | Description | Selected |
|--------|-------------|----------|
| Manual close with checks | test_admin/admin clicks "Close session", warns about missing submissions | ✓ |
| Auto-complete on submissions | Auto-complete when all present applicants have submissions | |

**User's choice:** Manual close with checks

---

## Cancel transition

| Option | Description | Selected |
|--------|-------------|----------|
| Keep cancel | in_progress → cancelled stays, useful for mid-session cancellation | ✓ |
| Remove cancel | Sessions only go forward, use delete for drafts | |

**User's choice:** Keep cancel

---

## Control transfer after publish

**User's note:** "Admin/registrar should have no control for attendance — once published, all control goes to exam monitoring (My Sessions)"

---

## Notification delivery - Which transitions

| Option | Description | Selected |
|--------|-------------|----------|
| All transitions | publish, start, complete, cancel, reopen — all trigger notifications | ✓ |
| Publish and cancel only | Only transitions that affect applicants directly | |

**User's choice:** All transitions

---

## Notification recipients

| Option | Description | Selected |
|--------|-------------|----------|
| Role-filtered | Applicants: publish/cancel only. Proctors/test_admins: all changes. Admins: none | ✓ |
| All participants | Everyone involved gets all notifications | |

**User's choice:** Role-filtered

---

## Sound design

| Option | Description | Selected |
|--------|-------------|----------|
| Context-aware sound | Two-tier: softer for background polls, louder for direct actions | ✓ |
| Single lighter sound | One light chime for all notifications | |

**User's choice:** Context-aware sound

---

## Mobile notifications

| Option | Description | Selected |
|--------|-------------|----------|
| Responsive + bottom sheet | Responsive sizing, bottom-sheet style on small screens | |
| Responsive sizing only | Keep dropdown pattern, just make it larger on mobile | |

**User's choice:** Make notification area larger on mobile, otherwise keep as-is
**Notes:** User said "right now the notif area is very small on mobile I want it larger"

---

## Reminder configuration

| Option | Description | Selected |
|--------|-------------|----------|
| Keep + add 7-day, env-configurable | Add 7-day reminder, make days configurable via env vars | ✓ |
| Keep as-is (1-day, 3-day) | No changes to reminders | |

**User's choice:** Keep + add 7-day, env-configurable
**Notes:** User wants env variables for reminder windows (for demo environments). Default to 1,3,7 if no env set.

---

## Email scope

| Option | Description | Selected |
|--------|-------------|----------|
| Accept/reject only | Email only for ApplicationStatusChanged | |
| Accept/reject + exam publish/cancel | Email for ApplicationStatusChanged AND ExamSessionPublished/Cancelled | ✓ |

**User's choice:** Accept/reject + exam publish/cancel

---

## Exam reminders email

| Option | Description | Selected |
|--------|-------------|----------|
| Keep email for reminders | Exam reminders still send email + in-app | |
| In-app only | Exam reminders are database channel only, no email | ✓ |

**User's choice:** In-app only

---

## My Sessions structure

| Option | Description | Selected |
|--------|-------------|----------|
| Distinct proctor + test_admin pages | Separate routes, proctor sees assigned only, test_admin sees assigned + all if admin | ✓ |
| Single shared page | Same URL, role-based filtering | |

**User's choice:** Distinct proctor + test_admin pages

---

## My Sessions features

| Option | Description | Selected |
|--------|-------------|----------|
| Status badges | Color-coded status indicators | ✓ |
| Date grouping | Today, Upcoming, Past sections | ✓ |
| Quick actions | Start, Close, View Roster buttons per row | ✓ |
| Active highlight | In-progress sessions stand out visually | ✓ |

**User's choice:** All four features selected

---

## Authorization fix

| Option | Description | Selected |
|--------|-------------|----------|
| Policy-based auth | Use ExamSessionPolicy.viewRoster/manageRoster for proper session-level checks | ✓ |
| Role-only auth | Simple role check, no assignment verification | |

**User's choice:** Policy-based auth

---

## Claude's Discretion

- Sound frequency/duration values
- Date grouping thresholds
- Active session highlight styling
- Pagination vs infinite scroll
- Env variable naming

## Deferred Ideas

None — discussion stayed within phase scope