# QA Test-Strategy Research — Web Coverage, Accessibility, OMR Physical Acceptance

> **Status:** RESEARCH ONLY — no code changed, no tests run. Input for a durable QA operating procedure (UI/use-case/button coverage, Playwright where appropriate, OMR-first acceptance plan, recurring UI/UX audits).
> **Date:** 2026-08-17
> **Sources:** primary where available (Playwright docs, W3C WCAG 2.2/WAI/WCAG-EM, Martin Fowler, Google Testing Blog, Laravel docs, OMRChecker pinned repo+wiki, OMRChecker source). Every claim below carries its source and that source's limits.
> **Authority:** this document is research evidence only. Per `docs/project-context.md` §1 and `docs/GREENFIELD-MASTER-REFERENCE.md` §3, it ranks below founder decisions, GF contracts, and the active OpenSpec change; it does not authorize implementation.

---

## 0. OpenSpec active-change conflict (reported, not resolved)

Per the task brief, the conflict is reported rather than resolved. Verified at file level:

| Change | Evidence of "active" status |
|---|---|
| `openspec/changes/2026-08-15-applicant-portal-interface/` | `proposal.md` header: "**Status:** ACTIVE — sole implementation change since 2026-08-15"; `HANDOFF.md`: "ACTIVE — implementation complete; tests green; browser-verified. Sole active OpenSpec change."; documented as the active implementation change in `docs/project-context.md` (entry-point file). No `.openspec.yaml`. |
| `openspec/changes/omr-profile-test-readiness/` | `.openspec.yaml` (`schema: spec-driven`, `created: 2026-08-16` — created **after** the portal change); full proposal/tasks/design/spec; **not** in `openspec/changes/archive/`; **not** referenced in `docs/project-context.md`. `tasks.md` §7.7 requires a physical acceptance matrix "record[ed] in HANDOFF.md". |

`openspec/config.yaml` contains no global active-change marker (`schema: spec-driven` only). Both changes sit outside `archive/`; the portal one self-declares sole-active while the OMR one is newer and unreferenced by project context. **Two changes are simultaneously de-facto active; the authority model (§1 of project-context.md) assumes exactly one.** A founder/lead decision is required: archive the portal change (its own HANDOFF says archive is pending founder review) or formally demote/park `omr-profile-test-readiness` until the portal is archived. This report's OMR section is written so it applies to either outcome.

---

## 1. Scope and method

Researched four question families against authoritative primary sources:

1. **Playwright** — official best practices, trace viewer, locator guidance.
2. **WCAG 2.2 / W3C accessibility testing** — conformance requirements and W3C's own evaluation methodology expectations.
3. **Testing-layer strategy** (unit / integration / E2E) — high-trust industry sources plus the framework docs for this repo's stack.
4. **OMR physical acceptance / ground truth** — official OMRChecker documentation (pinned commit + wiki + test suite) and NEXIAM's own OMR gates.

Repo grounding used: `system/playwright.config.ts`, `system/package.json`, `system/composer.json`, `docs/e2-page-state-catalog.md`, `docs/omr-bubble-size-research.md`, `docs/omrchecker-integration-explainer.md`, `openspec/changes/archive/omr-scoring-capture/omrchecker-strategic-research.md`, `openspec/changes/omr-profile-test-readiness/{proposal,tasks}.md`. Test layout as of 2026-08-17: Pest `tests/Unit` + `tests/Feature` (+ `tests/Support/E2Helpers.php`), Vitest `tests/frontend/*.test.ts`, Playwright `tests/browser/*.spec.ts` (2 specs).

---

## 2. Playwright: official guidance and what it proves

### 2.1 Best practices (Playwright docs, "Best Practices")

Official, stable-version docs. Verified content:

- **Test user-visible behavior.** "Automated tests should verify that the application code works for the end users, and avoid relying on implementation details" (function names, CSS classes, array-ness).
- **Test isolation.** "Each test should be completely isolated... own local storage, session storage, data, cookies"; browser contexts give this; reuse signed-in state via a setup project (log in once, not per test).
- **Avoid testing third-party dependencies** — use `page.route()` to stub/fake external calls; only test what you control.
- **Control the database** — test against staging/controlled data; for visual regression keep OS + browser versions identical.
- **Use locators** — auto-waiting and retry-ability built in; prioritize user-facing attributes (`getByRole` first).
- **Use web-first assertions** (`await expect(...).toBeVisible()`), never manual `isVisible()` one-shot checks.
- **Debugging:** traces "run on CI on the first retry of a failed test"; `--trace on` locally; trace viewer is the CI debugging tool; "We don't recommend setting this to `on` so that traces are run on every test as it's very performance heavy."
- **Tooling:** VS Code extension, `codegen`, trace viewer, UI Mode, TypeScript out of the box.
- **Test across all browsers** via `projects` in config.

**NEXIAM current-state check** (`system/playwright.config.ts`): already follows the official recipe — `trace: 'on-first-retry'`, `retries: 2` on CI, `fullyParallel`, controlled Compose app at `baseURL` `http://127.0.0.1:18000` with an opt-in local `artisan serve` webServer. Gaps vs official guidance: chromium-only project (Playwright recommends multi-browser projects; at minimum add firefox for smoke specs), and `reporter: 'line'` hides the HTML report that links traces (`show-report` is the documented trace access path).

### 2.2 Locators (Playwright docs, "Locators")

- Priority: `getByRole` > `getByLabel`/`getByText`/`getByPlaceholder` > `getByTestId`; chain + filter to narrow; avoid XPath/CSS-class selectors.
- Role locators follow the W3C ARIA/accname specs — **and the docs state the limit explicitly**: "role locators **do not replace** accessibility audits and conformance tests, but rather give early feedback about the ARIA guidelines."
- `getByTestId` is "the most resilient" but "not user facing" — use deliberately, for stable automation-only hooks.

### 2.3 Trace viewer (Playwright docs, "Trace viewer")

- Records actions (locator used, timing, before/after DOM snapshots, source location), screencast film strip, console + network logs; timeline scrubbing.
- Viewing: `npx playwright show-trace <trace.zip>` or `trace.playwright.dev` (trace is processed entirely in-browser — safe for private captures, which matters for NEXIAM's applicant-data screens).
- Config: `'on-first-retry'` (CI), `'retain-on-failure'` (no retries), `'on'` (local dev), `'off'`; `--trace on` flag; UI Mode traces each test automatically.

**What these docs prove:** the official operating recipe for resilient E2E and for CI failure diagnosis. **What they do not prove:** how many E2E tests to write, how to map tests to product coverage, or accessibility conformance — Playwright itself defers those to other practices (see §3, §4).

---

## 3. WCAG 2.2 and W3C accessibility testing expectations

### 3.1 WCAG 2.2 (W3C Recommendation, 2024-12-12)

- Four principles (Perceivable, Operable, Understandable, Robust), **13 guidelines**, success criteria (87 numbered IDs; **4.1.1 Parsing is "Obsolete and removed"** in 2.2, leaving 86 applicable), three conformance levels: A, AA, AAA.
- **New in 2.2** (verified from the spec, each marked "New"):

| SC | Name | Level |
|---|---|---|
| 2.4.11 | Focus Not Obscured (Minimum) | AA |
| 2.4.12 | Focus Not Obscured (Enhanced) | AAA |
| 2.5.7 | Dragging Movements | AA |
| 2.5.8 | Target Size (Minimum) — targets ≥ 24×24 CSS px unless spaced | AA |
| 3.2.6 | Consistent Help | A |
| 3.3.7 | Redundant Entry | A |
| 3.3.8 | Accessible Authentication (Minimum) — no cognitive-function test required, or alternatives offered | AA |
| 3.3.9 | Accessible Authentication (Enhanced) | AAA |

- **Conformance requirements that shape a QA procedure** (§5.2–5.3): conformance is per **full pages** (§5.2.2); a page in a **complete process** requires all pages of that process to conform (§5.2.3); **conformance claims** (optional but, if made, must record) date, guidelines title/version/URI, conformance level, and the pages in scope (§5.3.1); statements of partial conformance exist only for third-party content or language (5.4/5.5).
- Per-SC testability lives in the companion "Understanding WCAG 2.2" pages (sufficient/advisory techniques, common failures) — the W3C's own testing material per criterion.

### 3.2 W3C WAI "Evaluating Web Accessibility" (test-evaluate hub) — the key expectation

> "There are evaluation tools that help with evaluation. **However, no tool alone can determine if a site meets accessibility standards. Knowledgeable human evaluation is required** to determine if a site is accessible."

The hub's toolkit: Easy Checks (first review), Evaluation Tools List (100+ tools), conformance evaluation via **WCAG-EM**, ACT Rules (test rules for tools), involving users with disabilities. This is the W3C's explicit position on what automated checks can and cannot claim.

### 3.3 WCAG-EM 2 (W3C Group Note, published 2026-07-23)

Five steps: **(1)** define scope (incl. conformance level A/AA/AAA), **(2)** explore the product, **(3)** select a representative sample, **(4)** evaluate the sample, **(5)** report. Verified details that matter for a recurring audit:

- **Sampling is not left to taste:** structured sample (key views, variety of sample types) **plus a randomly selected sample** "acts as an indicator to verify that the structured sample set... is sufficiently representative"; **complete processes** must be included (3.1–3.3).
- **Small products skip sampling:** "when it is... make[s] sense to skip the sampling procedure, and evaluate the entire digital product instead. Such cases include when the digital product: has a small number of views... cannot meaningfully be split into views." Sample size depends on product size, view complexity, etc.
- Evaluation checks all SCs on the initial sample, then complete processes, then compares structured vs random results (4.1–4.3). WCAG-EM 2 extends WCAG-EM 1 from websites to **apps and other digital products** — directly applicable to a Laravel+Inertia app.

### 3.4 What the W3C material proves vs not

**Proves:** the conformance target shape (levels, full pages, complete processes), that no automated tool is sufficient alone, and a concrete sampling methodology for periodic audits. **Does not prove:** which tools to buy, nor that any particular automated tool's pass rate equals conformance; ACT Rules are the standard for *tool* testability, not a substitute for expert review.

---

## 4. Testing-layer strategy (unit / integration / E2E)

### 4.1 The Practical Test Pyramid (Martin Fowler / Ham Vocke, 2018)

- Pyramid attribution: Mike Cohn, *Succeeding with Agile*. Two rules: write tests at **different granularity**; **the more high-level, the fewer tests** (avoid the "test ice-cream cone").
- **Unit tests:** narrowest scope, most numerous, fast; test the public interface; don't test trivial code; "Test for observable behaviour instead" of internal call structure; Arrange–Act–Assert / Given–When–Then.
- **Integration tests:** one integration point at a time (real DB, real filesystem, local fake of external service — e.g., Wiremock); serialization/deserialization boundaries; narrow integration beats broad; never hit production services from tests.
- **E2E:** "end-to-end tests require a lot of maintenance and run pretty slowly... you should aim to reduce the number of end-to-end tests to a bare minimum"; encode only the **high-value user journeys** (e-commerce example: search → basket → checkout); everything else is already covered lower down.
- **Subcutaneous tests** (REST API level, just below the UI): cheaper, less flaky, broad stack coverage without the browser.
- **Acceptance tests** are "completely orthogonal to your test pyramid" — they prove user-visible correctness and may live at *any* layer.
- **Exploratory testing:** still required; "Certain quality issues don't even become apparent within your automated tests (think about design or usability)"; regular destructive-mindset manual sessions, findings then turned into regression tests.
- **Avoid duplication:** "Push your tests as far down the test pyramid as you can"; if a high-level test fails with no lower-level test failing, add the lower-level test; delete redundant high-level tests.

### 4.2 Google Testing Blog — "Just Say No to More End-to-End Tests" (Mike Wacker, 2015)

Verified case study: a team's E2E-heavy strategy caught real bugs but shipped **a week late with overtime**; "finding the root cause for a failing end-to-end test is painful"; partner and lab failures ruined results on multiple days; "many smaller bugs were hidden behind bigger bugs." Google's structural recommendation: put most effort in unit + integration tests; E2E only where it earns its cost. This is the canonical high-trust counterweight to "cover every button with Playwright."

### 4.3 Laravel official docs — Testing: Getting Started (this stack's framework guidance)

- `tests/Unit`: "focus on a very small, isolated portion of your code... **do not boot your Laravel application**."
- `tests/Feature`: "may test... even a full HTTP request... **Generally, most of your tests should be feature tests.**"
- Parallel testing (`--parallel`, per-process test DBs), coverage (`--coverage --min`), profiling (`--profile`).

**NEXIAM mapping (verified):** the repo already matches the pyramid — many Pest Feature tests (HTTP/action level = the "feature" bulk), Pest Unit tests for OMR layout/native-bundle/manifest math, Vitest frontend tests (component/unit for Svelte logic), and a deliberately small Playwright suite (`tests/browser/` has 2 specs). `pestphp/pest-plugin-browser` v5.0.1 is installed (Playwright-powered browser testing from Pest) but unused so far — a viable alternative/addition to raw Playwright specs.

---

## 5. OMR physical acceptance and ground truth (official OMRChecker)

Pinned upstream: `Udayraj123/OMRChecker@de3f698` (verified commit date **2026-06-30**, "Feat: add PDF input support with PyMuPDF (#282)") — the same pin as `docs/omrchecker-integration-explainer.md`. Sources: pinned README, cloned official wiki (`[v1]-User-Guide`, `[v1]-About-Templates`, `[v2]-About-Evaluation`), and the repo's own test suite (`src/tests/`).

### 5.1 What the official documentation actually says

- **Ground-truth mechanism is file-defined:** a run is configured by `template.json` (page dimensions, bubble dimensions, `fieldBlocks`, `customLabels`, `outputColumns`, `emptyValue`, preprocessors `CropPage` / `CropOnMarkers` with marker image) **paired with `evaluation.json`** — the answer-key file ("Add evaluation.json at the same folder level as your template.json"). Evaluation supports single answers, multi-correct answers, weighted/partial marking, negative/fractional marking schemes per section, and rendered verdicts (`draw_question_verdicts`, `draw_score`, `should_explain_scoring`).
- **The project's own regression suite is snapshot-based ground truth:** `src/tests/test_all_samples.py` runs every `samples/` folder through the real entry point and asserts the produced CSV output matches committed snapshots (`__snapshots__/test_all_samples.ambr`); plus `test_edge_cases.py` and `test_template_validations.py`. I.e., upstream treats "known sheets with known expected output" as the acceptance mechanism — not accuracy percentages.
- **Accuracy claims exist but are claims:** README says "nearly 100% accurate on good quality document scans; and about 90% accurate on mobile images", min resolution 640×480, ~200 OMR/min, "supports low resolution, xeroxed sheets" — with **no methodology, dataset, or confidence intervals**. NEXIAM's own strategic research already flags these as "upstream claims, not NEXIAM acceptance evidence" (`omrchecker-strategic-research.md:58`) — this report concurs and extends: treat README numbers as marketing until reproduced on NEXIAM sheets.
- **Physical-capture guidance in the wiki is thin but specific:** for mobile capture, add `CropOnMarkers` + `omr_marker.jpg` ("If you can modify your OMR sheets with these markers, it will give you much higher accuracy when scanning using mobile camera"); template tuning is explicitly an **iterative visual process** (`--setLayout`, tweak origin/gaps/bubble size until the overlay fits); community samples exist for scan angles (e.g., `samples/community/UPSC-mock/scan-angles`). **No official minimum physical bubble size, DPI table, or fill-quality spec exists** — NEXIAM's own geometry research (`docs/omr-bubble-size-research.md`) fills that gap with industry references.
- **Template geometry is arbitrary pixels, not physical units** — images are resized to `pageDimensions` before reading; what must hold is proportionality and sufficient source detail.

### 5.2 NEXIAM's already-locked OMR acceptance gates (repo evidence)

From `omrchecker-strategic-research.md` (archive) and `omr-profile-test-readiness/{proposal,tasks}.md` (active-claimant):

- false-green rate: **0%** in the acceptance fixture pack;
- item-level definite-answer accuracy: **≥99%** on clean/controlled images;
- identity suggestion precision: **≥99.5%** with **≥95%** clean suggestion coverage, no automatic binding;
- review contract explicit before physical acceptance; **physical acceptance is a gate, not a test fixture** (design.md §7/§10);
- tasks.md §7.7: "Run the physical acceptance matrix: baseline, blank, double mark, erase, rotation, shadow, fold, wrong paper; record outcomes in HANDOFF.md."

### 5.3 Proposed OMR 10-sample shading matrix (10 physical sheets, pre-registered ground truth)

Design rules: (a) every sheet's expected result is fixed **before** filling (authored key + per-sheet deviation manifest — the `evaluation.json` discipline); (b) each row targets one failure mode; (c) pass = NEXIAM gates above, and any adverse sheet must degrade to `needs-review`/manual fallback — never a confident wrong answer; (d) every sheet carries bubbled applicant-number identity so identity precision is scored on all 10.

| # | Sheet scenario (expected outcome pre-registered) | Capture mode | Expected / minimum acceptable result |
|---|---|---|---|
| 1 | **Baseline** — 150/150 full dark ballpoint fills matching the authored key; correct identity bubbles | Flatbed scan 300 dpi | 100% item agreement; 0 false-green; identity exact |
| 2 | **Blank sheet** — no marks at all (identity included) | Flatbed scan 300 dpi | All items blank; sheet flagged `needs-review` for unbound identity; 0 false-green |
| 3 | **Pencil fills** — medium-pressure #2 pencil on all 150 (not ballpoint) | Flatbed scan 150 dpi | Items read or flagged, never wrong; 0 false-green; threshold behavior recorded |
| 4 | **Faint/partial fills** — 10 designated items deliberately light | Phone camera, normal light | The 10 flagged `needs-review`; remaining 140 exact; 0 false-green |
| 5 | **Double marks** — 5 items with two bubbles filled | Flatbed scan 300 dpi | All 5 detected as multi-mark and flagged; no single-mark item misread |
| 6 | **Erase residue + margin smudge** — 1 item erased to residue, 1 stray scribble in margin | Flatbed scan 300 dpi | Residue item blank-or-flagged; margin mark never shifts/contaminates a row; 0 false-green |
| 7 | **Rotation** — baseline sheet photographed at ~15–20° with corner markers (`CropOnMarkers` path) | Phone camera | Alignment succeeds; item agreement ≥99%; shifts within bounded tolerance |
| 8 | **Shadow/lighting** — hard shadow across one column | Phone camera | Shadowed region flagged `needs-review`; unshadowed region exact; 0 false-green |
| 9 | **Fold/wrinkle** — sheet folded along one row band, then flattened | Flatbed scan 300 dpi | Folded rows flagged; no cross-row contamination elsewhere |
| 10 | **Wrong paper / mixed marks** — A4 print of the long-bond template, plus ✗-marks and ✓-marks in 5 bubbles, 30 intentional blanks | Flatbed scan 300 dpi | Wrong-paper detected (typed mismatch, no scoring); ✗/✓ never mapped to an option (flagged); blanks read blank |

**Per-sheet record** (to be captured in the change's HANDOFF per tasks.md §7.7): expected vs observed item-by-item diff count, false-green count, `needs-review` flag count, identity suggestion vs expected, aligned-crop usable yes/no, capture mode/DPI/lighting, verdict (pass / pass-with-flags / fail). Aggregate verdict against the §5.2 gates; store the manifest + evidence crops beside the change's evidence, mirroring `docs/greenfield-foundation/evidence/` conventions.

**Limits of this matrix:** it validates the *physical + capture + detection* chain, not grading math (NEXIAM's `ScoreConverter` is covered by Pest tests); it is not a substitute for the digital fixture pack (clean synthetic images, which must be re-run whenever geometry changes — `omr-bubble-size-research.md` §9.7); OMRChecker's own accuracy claims cannot be imported as pass criteria (§5.1).

---

## 6. Recurring UI/UX audit cadence (synthesis)

Sources: WCAG-EM 2 sampling (§3.3), Fowler exploratory testing (§4.1), Playwright UI Mode/trace tooling (§2.3), and NEXIAM's own `docs/e2-page-state-catalog.md` (the repo's authoritative page/state ledger, with fixed states: `initial-loading`, `ready`, `empty/not-yet-available`, `denied`, `validation`, `server-error`, `stale/conflict`, `degraded/retry`, `success/pending`).

Proposed cadence, fully grounded in cited sources:

1. **Every feature cut** (per GF workflow): page-state catalog rows for the feature must each be touched by at least one Pest Feature test (state rendering + authz), Vitest component test (interaction logic), or Playwright spec (only high-value journeys) — coverage mapped in the catalog, mirroring Fowler's "push tests down" rule.
2. **Quarterly WCAG-EM-style audit**: product is small enough to evaluate **entire** (WCAG-EM: skip sampling for few views); per audit: automated scan pass (axe-class tool) → keyboard-only walk of every complete process (login → portal hub → each journey; §5.2.3 Complete processes) → screen-reader spot check on the status-spine hub and capture/review screens → record findings in a report matching WCAG-EM output; remember WAI's position: automated pass ≠ conformance.
3. **Scheduled exploratory sessions** (Fowler): destructive-mindset manual pass over staff + applicant surfaces; findings become regression tests.
4. **Every CI E2E failure**: diagnose via trace viewer (`on-first-retry` already configured; keep traces for failed applicant-data specs private or use `trace.playwright.dev`'s in-browser processing), and per Fowler rule 1, push any uncovered failure down into a lower-level test.

---

## 7. Concrete NEXIAM recommendations

| # | Recommendation | Grounding |
|---|---|---|
| R1 | Keep Playwright suite deliberately small — one spec per **high-value journey** (applicant portal spine, staff grading/capture, OMR review commit), not per button; cover per-button behavior with Pest Feature + Vitest instead. | Playwright best-practices; Fowler; Google E2E |
| R2 | Extend `playwright.config.ts` with a firefox project for the journey specs (chromium-only today); keep `trace: 'on-first-retry'` and CI `retries: 2`; switch `reporter` to `html` on CI so traces are reachable via `show-report`. | Playwright best-practices/trace-viewer docs; verified config |
| R3 | Adopt role-first locators (`getByRole`/`getByLabel`) for all new browser specs; treat `getByTestId` as the deliberate exception; record the convention in the active change's design. | Playwright locators doc |
| R4 | Use the e2 page-state catalog as the coverage ledger: each `P-*` row's states map to a test owner (Pest / Vitest / Playwright / exploratory), so "complete UI/use-case/button coverage" is checked against a repo contract, not ad hoc. | `docs/e2-page-state-catalog.md`; Fowler acceptance-orthogonality |
| R5 | Add an automated accessibility scan to the browser suite (axe-class tool on journey specs) but report it as *automated findings only*; never call a page "WCAG AA conformant" from tool output. WAI: "no tool alone can determine if a site meets accessibility standards." | W3C test-evaluate; Playwright role-locator limit note |
| R6 | When the portal change is resolved, run the OMR physical acceptance matrix (§5.3) under `omr-profile-test-readiness` §7.7 and record outcomes in that change's HANDOFF; pre-register ground truth (key + deviation manifest) before filling/scanning. | `tasks.md` §7.7; OMRChecker evaluation.json discipline |
| R7 | Schedule quarterly WCAG-EM-style full-product audits (entire product, no sampling) with automated + keyboard + screen-reader passes, reported per WCAG-EM structure; pair with Fowler-style exploratory sessions. | WCAG-EM 2; WAI; Fowler |
| R8 | On any E2E CI failure: open trace viewer first, then push the root cause into a lower-level test if no lower-level test fails (Fowler rule 1). | Playwright trace docs; Fowler |
| R9 | Do not import OMRChecker README accuracy claims into acceptance criteria; re-run the digital fixture pack (≥99% clean accuracy, 0% false-green, identity ≥99.5% precision) after any geometry/template change, in addition to the physical matrix. | OMRChecker README limits; NEXIAM strategic research; bubble research §9.7 |
| R10 | Decide the §0 OpenSpec conflict before scheduling any of the above as work under a change — no implementation may run under two active changes. | project-context §1 |

---

## 8. Limits of each source (what it proves / does not)

| Source | Proves | Does not prove |
|---|---|---|
| Playwright Best Practices / Locators / Trace viewer (playwright.dev, stable) | Official resilient-testing recipe (user-visible behavior, isolation, web-first assertions, role locators, trace config & viewing, CI debugging) | How many E2E tests to write; accessibility conformance (explicitly deferred by the docs); mapping tests to product coverage |
| WCAG 2.2 (W3C REC 2024-12-12) | The conformance target: 4 principles, 13 guidelines, 86 applicable SCs (4.1.1 removed), A/AA/AAA, full pages, complete processes, claim components; 9 new SCs with levels | Which tests to run; tool choices; whether any given page conforms |
| WAI Evaluating Web Accessibility (test-evaluate) | "No tool alone can determine conformance; knowledgeable human evaluation required"; the WAI evaluation toolset structure | Anything about NEXIAM's specific pages |
| WCAG-EM 2 (W3C Note 2026-07-23) | A standard 5-step evaluation procedure; structured + random sampling; complete processes; small products evaluated entirely; applies to apps, not just websites | Per-SC testing detail (delegated to WCAG 2 Understanding); tool-specific checks |
| Fowler, *The Practical Test Pyramid* (2018) | Layer definitions and proportions; duplication avoidance rules; acceptance tests orthogonal; exploratory testing required | Framework-specific tooling (Java examples); any guarantee that a given layer split is optimal for Laravel/Inertia |
| Google Testing Blog, "Just Say No to More End-to-End Tests" (2015) | Concrete cost/flakiness evidence for E2E-heavy strategies; guidance to prefer unit+integration | That E2E tests should be eliminated (they catch real bugs — per the same article) |
| Laravel docs — Testing (12.x, current published) | Unit vs Feature semantics for this framework; "most tests should be feature tests"; parallel/coverage tooling | E2E/browser strategy (deliberately out of scope of that chapter) |
| OMRChecker README @ pinned commit de3f698 (2026-06-30) | Feature claims: angle/color tolerance, min 640×480, PDF params (`pdf_dpi` 72–600), markers guidance, samples; MIT license; answer-key/evaluation support marked TODO-ish in README but real in samples | Accuracy percentages ("nearly 100%"/"~90%" are unmethodologized upstream claims, explicitly not acceptance evidence per NEXIAM's own research) |
| OMRChecker wiki ([v1]-User-Guide, [v1]-About-Templates, [v2]-About-Evaluation) | Official `template.json` semantics (pageDimensions/bubblesGap/fieldBlocks/CropOnMarkers+marker file); `evaluation.json` answer-key formats incl. weighted/negative schemes; mobile-marker accuracy guidance; template tuning is visual/iterative | Minimum physical bubble sizes, DPI tables, fill-quality specs (absent from official docs — NEXIAM's bubble research covers this externally) |
| OMRChecker `src/tests/` (pinned) | Upstream's own acceptance method: run samples through the real pipeline, snapshot-compare outputs (`.ambr`) — i.e., known sheets + known expected output is their ground truth | Any performance/accuracy numbers for NEXIAM's sheets or geometry |

**Uncertainty note:** OMRChecker README accuracy figures and "large scale Technothlon" claims are vendor-side statements with no published methodology; WCAG-EM 2 was published 2026-07-23 and this report uses its current public text; Playwright/Laravel doc links below point to latest stable, while repo pins are `@playwright/test ^1.62.1`, `laravel/framework ^13.23`, `pestphp/pest ^5.0` (verified in `system/package.json` / `system/composer.json`).

---

## 9. Source list

Primary / official:

- Playwright — Best Practices: https://playwright.dev/docs/best-practices
- Playwright — Trace viewer: https://playwright.dev/docs/trace-viewer
- Playwright — Locators: https://playwright.dev/docs/locators
- WCAG 2.2 (W3C Recommendation, 2024-12-12): https://www.w3.org/TR/WCAG22/
- WAI — Evaluating Web Accessibility Overview: https://www.w3.org/WAI/test-evaluate/
- WAI — WCAG-EM Overview (WCAG Evaluation Methodology): https://www.w3.org/WAI/test-evaluate/conformance/wcag-em/
- WCAG-EM 2 (W3C Group Note, 2026-07-23): https://www.w3.org/TR/WCAG-EM/
- Martin Fowler / Ham Vocke — The Practical Test Pyramid: https://martinfowler.com/articles/practical-test-pyramid.html
- Google Testing Blog — Just Say No to More End-to-End Tests (M. Wacker, 2015): https://testing.googleblog.com/2015/04/just-say-no-to-more-end-to-end-tests.html
- Laravel — Testing: Getting Started (current published docs): https://laravel.com/docs/12.x/testing
- OMRChecker — README at pinned commit `de3f698`: https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/README.md
- OMRChecker wiki (cloned 2026-08-17): [v1]-User-Guide https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-User-Guide · [v1]-About-Templates https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-About-Templates · [v2]-About-Evaluation https://github.com/Udayraj123/OMRChecker/wiki/%5Bv2%5D-About-Evaluation
- OMRChecker test suite at pinned commit: `src/tests/test_all_samples.py`, `__snapshots__/test_all_samples.ambr` (via repo tree API)
- Pest — pest-plugin-browser (Playwright-powered browser testing, v5.0.1 verified on Packagist): https://pestphp.com/docs/plugin-browser

NEXIAM repo evidence cited:

- `system/playwright.config.ts`, `system/package.json`, `system/composer.json`
- `docs/e2-page-state-catalog.md` · `docs/omr-bubble-size-research.md` · `docs/omrchecker-integration-explainer.md`
- `openspec/changes/archive/omr-scoring-capture/omrchecker-strategic-research.md`
- `openspec/changes/omr-profile-test-readiness/{proposal.md,tasks.md,design.md,.openspec.yaml}`
- `openspec/changes/2026-08-15-applicant-portal-interface/{proposal.md,HANDOFF.md}` · `openspec/config.yaml`
