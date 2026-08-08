# NEXIAM — Handoff Note para sa Teammate

**Para kanino:** sa teammate na magpapatuloy ng development habang wala si David
**Date:** 2026-08-08
**Wika:** Taglish (para mabilis maintindihan)
**Pinakaimportante:** Ang repository na 'to mismo ang nagtuturo kung paano magtrabaho. Sundan ang mga links sa ibaba — hindi ito basta notes, ito ang authority.

---

## 1. UNA MONG BASAHIN (sa ganitong order)

1. `docs/GREENFIELD-MASTER-REFERENCE.md` — **ang master navigator.** Single entry point. Dito ka magsisimula at babalik lagi.
2. `docs/project-context.md` — ang **permanent work process** (paano tayo gumagawa, step by step).
3. `docs/MASTER-DELIVERY-ROADMAP.md` — ang phase roadmap (R0-R5 → E0-E6).
4. `docs/greenfield-foundation/README.md` — ang buong suite map + authority model.
5. `.cursor/rules/nexiam-greenfield.mdc` — ang always-on rules para sa agents.
6. `docs/legacy-reference/README.md` — paano ginagamit ang legacy bilang EVIDENCE.

> **CRITICAL RULE:** Ang `legacy_codebase/application/` ay **EVIDENCE LANG** — dun natin tinitignan kung "ano ang nangyari dati". Hindi 'to ang gagayahin na architecture. Ang target natin ay nasa `system/` (greenfield). Kapag nagtalo ang legacy at ang greenfield contracts, **panalo ang greenfield** (authority order sa master reference §3).

---

## 2. SAAN TAYO NGAYON (August 2026)

| Bahagi | Status |
|---|---|
| Planning (R0-R5) | PASS — may PRD, decisions D-001..D-088, HLD/LLD, schema, authz design |
| E0 scaffold | PASS — quality baseline green |
| E1 identity/reference | PASS — staff auth, personnel lifecycle, capability catalog |
| E1b UI kit | LOCKED |
| E2 connected slice | **IN PROGRESS** — reservation/scheduling cut DONE; proctor, grading, consultation, release = partially scaffolded pa |
| Exam session scheduling | **DONE** (booking_mode Option A, commit 3d6f69c) — staff sessions + applicant calendar |
| Pilot/production | **NOT GRANTED** — huwag mag-deploy |

Test baseline: **110 tests green** (in-memory sqlite). Kapag may bago kang ginawa, dapat manatiling green ang buong suite.

---

## 3. PAANO TAYO MAGTRABAHO — ANG PIPELINE (ito ang pinakamahalaga)

Ang paraan natin: **isa-isa ang pipeline** — hindi natin binubuhay ang buong legacy nang sabay-sabay. Bawat feature ay dumadaan sa buong pipeline na 'to:

```
legacy behavior (trace) → disposition (PRESERVE/REWRITE/SPIKE/DEFER/REJECT)
  → decision (D-xxx, kung kailangan ng founder)
  → isang ACTIVE OpenSpec change (openspec/changes/<pangalan>/)
  → tasks (tasks.md) → code sa system/ → tests → evidence note → commit
```

**Ang gagawin mo sa BAWAT feature:**
1. **Hanapin ang legacy source** — `legacy_codebase/application/app/Http/Controllers/...` at `resources/js/Pages/...` + `docs/legacy-reference/` (may traces na: `11-exam-scheduling-trace.md`, `08-e2-connected-slice-trace.md`, atbp).
2. **Itala ang behavior** — actor, data scope, states, success/error/denied behavior.
3. **Bigyan ng disposition** — PRESERVE / REWRITE / SPIKE / DEFER / REJECT (klase-klaseng nasa `docs/legacy-reference/README.md`).
4. **Kung may bagong business rule o policy** — gumawa ng decision doc: `docs/greenfield-foundation/decisions/D-0XX-<slug>.md` (gayahin ang format ng D-088). **Huwag i-invent — i-record as DECISION REQUIRED kung hindi mo kaya i-decide.**
5. **Isang ACTIVE OpenSpec lang** — kung may luma pang active, i-archive muna (gayahin ang `openspec/changes/archive/` pattern), tapos gumawa ng bago.
6. **I-implement sa `system/`** — sundin ang EXISTING patterns (module folders, Actions na may `handle()`, CapabilityAuthorizer, atbp — tingnan ang mga kapatid na file bago gumawa ng bago).
7. **Tests** — Pest feature tests bilang default. May test, may merge. Wala, wala.
8. **Evidence note** — `docs/greenfield-foundation/evidence/<feature>-evidence.md` (gayahin ang format ng `exam-session-scheduling-reservation-cut-evidence.md`).
9. **Commit** — isang logical unit bawat commit.

**Mga non-negotiable rules (galing sa master reference §7):**
- Ang **PostgreSQL constraints/locks** ang authority sa concurrency — hindi app-level check lang.
- **Isang command ang nagmamay-ari ng bawat state transition.** Stage = projection.
- State + invariant + audit ay **sabay nagko-commit** (transaction).
- Notifications = **after-commit at idempotent**.
- Authz: **CapabilityAuthorizer** (GF-21) ang authority — Spatie roles ay HELPER lang. Default-deny.
- Ang UI ay **hindi authorization** — server-authored navigation.
- Manual scheduling at manual scoring ay **complete fallbacks** — dapat laging gumagana kahit walang provider.
- "Models/workers propose; they never write authoritative state."

---

## 4. ANG GOAL MO: I-RESTORE ANG MGA FEATURE NA WALA PA (galing sa legacy)

Ang trabaho mo: kunin ang mga legacy surface na **hindi pa narerebuild** sa greenfield, at i-port sila via pipeline. Ito ang kilala nating kulang (galing sa master reference §6 + GF-24):

**Checklist (mag-work ka ng isa-isa, sa order na may sense):**

- [ ] **Applicant/score imports** (analyze → preview → confirm pattern) — `DECISION REQUIRED` sa design (separate imports vs isang framework; page vs dialog)
- [ ] **Missed-exam / no-show / retry / reassignment** — `DECISION REQUIRED` (E2 blocker ang item na 'to; may mga tanong na nakalista sa master reference §6.6)
- [ ] **Notifications/toasts** — redirect flash, polled DB notifications, mark-read, sounds, cooldowns
- [ ] **Breadcrumb catalog** — route-by-route (master reference §6.2)
- [ ] **Dialogs / destructive confirmation inventory** — command-level modal catalog (master reference §6.4)
- [ ] **Applicant status timeline + current-step animation**
- [ ] **Staff application pipeline strip**
- [ ] **Applicant password-strength meter**
- [ ] **QR/camera attendance vs submission modes**
- [ ] **Bulk accept / dismiss / revert actions**
- [ ] **Revert accepted/dismissed → pending**
- [ ] **Seasonal expiration ng pending applications**
- [ ] **Result-release mode + face-to-face consultation blocks**
- [ ] **Schedule assistant dialog** — manual scheduling lang muna, walang AI
- [ ] **Reports / result sheets + Program Head projections** (D-087 quota done; ang metrics hindi pa)
- [ ] **E2 exit evidence G2–G4/H4** — denial, concurrency, degraded, accessibility, breadcrumb, toast, document, audit evidence (naka-park 'to ngayon)
- [ ] **AI-seam reconciliation** — align ang E1 OpenSpec wording sa roadmap AI-port requirement (maliit lang, tingnan ang master reference §8.1)

> **Kung may item na nangangailangan ng desisyon ni David (founder):** huwag i-invent. Isulat as `DECISION REQUIRED` sa evidence/decision doc, tapos magpatuloy sa susunod na item. May pattern na 'to sa buong repo — sundin mo.

---

## 5. HUWAG MUNA GAWIN (mga gate na hindi pa pasado)

- **OMR — SKIP MUNA.** Hindi pa pipeline-ready: kulang ang approved sheet/input/ground truth/confidence/review contract at ang spike artifacts. Ang E3 gate ay nasa GF-24 §8. **Sundan ang pipeline — kapag wala pang gate, hindi pa pwede.**
- **AI scheduling / RAG / Nexi provider** — E3. May seam na tayo (`config/nexi.php`, mode=`degraded`) — **huwag mag-install ng provider o AI SDK.** Kailangan muna: AI boundary ADR, typed action catalog, Nexi persona spec.
- **YuKee / BGE / models worker** — E3/E4; may contradiction pa sa evidence.
- **Offline proctoring** — E3/E4.
- **Huwag i-edit ang `legacy_codebase/`** — evidence lang 'yan.
- **Huwag i-deploy / mag-push sa production** — pilot clearance NOT GRANTED. Ang deploy flow ay manual (Dokploy), at si David lang ang nagde-deploy.
- **Huwag i-commit ang `.env`** — naka-ignore na, ingatan lang.

---

## 6. DEV ENVIRONMENT — QUICK START

Ang app: `system/` — Laravel 13 + Inertia 3 + Svelte 5 + PostgreSQL 16, lahat via **Compose** (hindi Sail).

```bash
# 1. Simulan ang stack (app, queue, postgres, mailpit)
cd system
docker compose up -d

# 2. Buksan
#    App:     http://127.0.0.1:18000
#    Mailpit: http://127.0.0.1:18001  (test email inbox)

# 3. PHP commands (WALANG php sa host PATH — gamitin ang container)
docker compose exec -T app php artisan route:list
docker compose exec -T app php artisan config:show app.name

# 4. Pagkatapos magpalit ng PHP/JS code → rebuild (kailangan talaga, nasa image ang code)
docker compose build app && docker compose up -d --force-recreate app queue

# 5. Tests — buong suite (mabilis, in-memory sqlite, HOST code ang ginagamit)
docker run --rm --entrypoint php -v "$PWD:/work" -w /work nexiam-system-app artisan test --compact

# 6. Tests — isang file lang
docker run --rm --entrypoint php -v "$PWD:/work" -w /work nexiam-system-app artisan test --compact tests/Feature/Examination/

# 7. Quality gates (bago mag-commit ng PHP)
docker compose exec -T app vendor/bin/pint --dirty
```

**Seed login (lahat password: `password`):**
- `registrar.admin@nexiam.test` — Registrar Admin
- `registrar.clerk@nexiam.test` — Registrar Clerk
- `guidance.counselor@nexiam.test` — Guidance Counselor (Test Administrator)
- `guidance.clerk@nexiam.test` — Guidance Clerk (shared setup per D-087)
- `program.head.bsn@nexiam.test` — Program Head BSN

**Laravel Boost (agent tooling):**
- Boost MCP: `.cursor/mcp.json` → `system/bin/boost-mcp.sh` (docker wrapper — gagana kahit walang host PHP)
- Bersyon: **2.5.3** — may **infer-conventions skill** (`php artisan boost:list-skills`). Pwede mong patakbuhin para i-record ang coding conventions ng repo sa `.ai/rules/` — mabuti ito kung maraming agents ang gagana sa repo.

---

## 7. MGA PENDING / NAG-IIINTAY KAY DAVID

- **guidance.clerk seeding** (commit `7e7cf29`): sinabihan ni David na i-reverse, pero may "don't do it yet". **Naka-park — huwag galawin** hangga't walang bagong instruction.
- **GF-24 §14 gate table** at root `README.md` status line: stale pa (sinasabing "E2 NOT STARTED") — i-align sa `GREENFIELD-MASTER-REFERENCE.md` kapag may time.
- **Exhaustive parity ledger** — ang "trace every legacy surface" na requirement (BLOCK-04).

---

## 8. KAPAG NA-STUCK KA / MAY TANONG

1. Bumalik sa `docs/GREENFIELD-MASTER-REFERENCE.md` — may **stop rules** sa §12. Basahin bago ka magpatuloy sa kahit anong kahina-hinala.
2. Kung may behavior na walang disposition, o may business rule na galing lang sa code (hindi sa approved decision) → **STOP, i-record as DECISION REQUIRED. Huwag i-guess.**
3. Kung may page na walang route/state/breadcrumb/authorization contract → hindi pa pwede i-implement.
4. Kung may provider na gusto mong idagdag → hindi pwede nang walang gate/ADR.
5. Ang **tests ang truth** — kung red ang suite, huminto at ayusin muna.

> **Single-sentence continuation rule (GF-00 §9):** "Continue from the latest approved decision and verified checkpoint; never continue from memory, an unreviewed draft, or a legacy implementation assumption."

---

*Good luck! Sundan ang pipeline, i-restore ang mga feature nang paisa-isa, i-skip ang OMR, at kapag may desisyon na kailangan — i-record, huwag i-invent. Maghahanap tayo ni David pagbalik niya. 🚀*
