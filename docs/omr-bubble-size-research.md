# OMR Bubble/Mark Sizing and Spacing — Research for the One-Page Six-Domain Layout

> **Purpose:** evidence for a founder clarification before any OpenSpec change. Research only — no code was changed.
> **Question it supports:** can NEXIAM's answer sheet carry **150 questions (6 domains × 25) on one long-bond page**, and at what bubble/mark size, spacing, and pitch?
> **Date:** 2026-08-16. Sources are primary where available; measurement of NEXIAM's own shipped PDF is included.

---

## 1. What NEXIAM currently ships (verified, not assumed)

Canonical geometry lives in `system/app/Assessment/Omr/Layout/OmrTemplateLayoutSolver.php` (constants) and was measured directly from `omr-test-kit/answer-sheet-longbond-v4.pdf` (rendered at 300 DPI via PyMuPDF):

| Property | Canonical (px @ 242 PPI) | Physical (mm) | Measured from v4 PDF @300 dpi |
|---|---|---|---|
| Paper — long bond | `PAPERS['long'] = [2060, 3144]` | 216 × 330 mm | 216.0 × 330.0 mm (page rect) |
| Bubble diameter | `BUBBLE = 56 × 56` | **5.88 mm** (56 / 242 × 25.4) | bubble outlines 6.6 mm incl. stroke; 400 bubbles on page 1 |
| Horizontal option pitch (A→B) | `MCQ4_OPTION_GAP = 66` (center-to-center) | **6.94 mm** | 6.90–6.94 mm |
| Horizontal edge gap between bubbles | 66 − 56 = 10 | **1.05 mm** | consistent with measurement |
| Vertical row pitch | `ROW_PITCH = 80` | **8.40 mm** | 8.38–8.42 mm |
| Vertical edge gap | 80 − 56 = 24 | **2.52 mm** | consistent |
| Margin (solver `MARGIN`) | 80 | 8.4 mm | — |
| Left column origin / band | `COLUMN_X[4] = [180, 620, 1060, 1500]`, 400 px bands, 40 px gaps | bands 42.0 mm, gaps 4.2 mm | column-to-column bubble pitch 25.4 mm |
| Flow zone | `FLOW_TOP = 900` … `height − FLOW_BOTTOM_RESERVE(230)` | top ≈ 95 mm, bottom reserve ≈ 24 mm | — |
| Rows per column | `(3144 − 230 − 900 − 56) / 80 + 1 = 25` | — | 25 rows confirmed |
| **Page capacity** | **4 columns × 25 rows = 100 questions/page** | — | v5 two-page pair = 200 questions |

Other locked facts:
- Legal paper `[2060, 3392]` (216 × 356 mm) is the only alternative; **A4 is refused** — `BundleRenderer` refuses any paper mismatch (typed `paper_mismatch`) and geometry is never scaled (`system/app/Printing/BundleRenderer.php:275`).
- Identity is **bubbled digits only** (applicant number 10 positions + optional birthdate 6 positions); the NAME/EXAM-DATE boxes are human-filing reference, **never OCR'd** (founder lock; `system/.ai/rules/assessment.md`; `omr-test-kit/README.md`).
- Renderer PPI is 242 (`BundleRenderer::PPI = 242`); PDF pages measured exactly 216×330 mm → 100 % print mapping is correct.
- The rule file notes `bubbleDimensions [120,120]` triggers overlap errors; `[56,56]` is the canonical pair (`system/.ai/rules/omr.md`).

## 2. What the detector (OMRChecker, pinned `de3f698`) actually requires

Primary sources: [README at pinned commit](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/README.md), [User Guide wiki](https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-User-Guide), [About Templates wiki](https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-About-Templates), [src/core.py](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/core.py).

- **Template pixels are arbitrary units, not physical sizes.** Every input image is resized to the template's `pageDimensions` before reading (`core.py` → `ImageUtils.resize_util(img, template.page_dimensions…)`). What must be true is **proportionality** between template geometry and the physical sheet, plus **enough source detail after resize**.
- **Bubble reading = mean intensity inside the bubble rect** (`cv2.mean` over `box_w × box_h` at each bubble), classified with a global threshold plus per-question "gap" thresholds (`get_global_threshold` / `get_local_threshold`). Consequences:
  - bubble-to-bubble separation matters (it feeds the gap thresholding); touching bubbles are the documented failure mode;
  - very small bubbles → fewer pixels per mark → more sensitive to partial/faint fills.
- **No minimum physical size is documented upstream.** The wiki workflow tunes origin/gaps/bubble size *visually* per sheet. Community sample templates use bubble sizes from 10 px (DomBrzezinski) to 42 px (Antibodyy) in arbitrary page units — no physical claim.
- README robustness claims (upstream claims, not NEXIAM acceptance evidence): min resolution 640×480, "nearly 100% accurate on good quality scans", "~90% on mobile images", "supports low resolution, xeroxed sheets"; PDF input renders at configurable `pdf_dpi` (72–600, default auto).

**Implication:** NEXIAM's 242 PPI render space is far above any detector floor; the binding constraints for a denser layout are (a) edge gaps for thresholding, (b) mark pixels per bubble after scan/photo resize, (c) human shading ergonomics, and (d) NEXIAM's own quality gates (≥99% item accuracy on clean images is a proposed fixture gate in `openspec/changes/archive/omr-scoring-capture/omrchecker-strategic-research.md`).

## 3. Real-world sizing references (industry/vendor/standards)

| Reference | Bubble diameter | Pitch / spacing | Notes |
|---|---|---|---|
| Traditional hardware OMR (Scantron-style) | ≈ 2.98 mm (0.117″), commonly cited 0.098″–0.125″ | proprietary; timing-track driven, often 4 rows/inch ≈ 6.35 mm | [datatec.co.in](https://datatec.co.in/an-in-depth-exploration-of-omr-sheet-design-and-security/), [omrhome.com](https://omrhome.com/blog/omr-sheet-size-and-dimension/), [gradientk12.com](https://gradientk12.com/scantron/) |
| Addmen Group (OMR vendor, ISO 9001) design guidance | **40–50 px** at design time ("standard limit"); bubbles below standard force high-sensitivity mode → more warnings/rejections; larger bubbles → filling fatigue | explicit rules against touching bubbles; thin outlines; small in-bubble labels | [addmengroup.com — Optimal Size of Bubbles](https://www.addmengroup.com/omr-design/omr-bubble-size.htm) |
| Addmen scanning spec | — | — | **100 DPI sufficient for standard OMR**; 99.8–99.9 % accuracy achievable with registration marks, full dark fills, no photocopies; grayscale/color for faint marks | [addmengroup.com — Scanning Specifications](https://www.addmengroup.com/omr-scanner/omr-scanning-specifications.htm) |
| Gradescope (modern software OMR) | official template (login-gated); printed at 100 % | **100 questions per US Letter page (2 pages = 200)** | free-write name/ID boxes (no bubbled identity); print-not-photocopy; color scans; phone submission possible but not recommended | [Pitt — Gradescope Bubble Sheets](https://teaching.pitt.edu/resources/gradescope-bubble-sheets/), [Gradescope help](https://guides.gradescope.com/hc/en-us/articles/22246010755853-Creating-a-Bubble-Sheet-Assignment) |
| SAT/College Board | **not publicly specified** (proprietary); practice-sheet measurement (cloudfront official practice answer sheet): inner letter glyphs ≈ 2.3 mm; classic sheets use ~4 mm bubbles with letters inside | not published | [official SAT practice answer sheet PDF](https://d15gkqt2d16c1n.cloudfront.net/testprep/prep_guide_resources/SAT%20Practice%20Test%20Answer%20Sheet%203.pdf) |
| OMRChecker sample/community templates | 10–42 px in arbitrary page units; e.g. UPSC-mock 30×25 px, 100 Q/sheet | no physical claims | [samples/](https://github.com/Udayraj123/OMRChecker/tree/master/samples) |

Synthesis: hardware-era sheets run **2.9–4 mm bubbles at ~6 mm pitch**; software-based systems (Gradescope) accept **100 questions per Letter page** (~279 mm tall) with comfortable bubbles. NEXIAM at 5.88 mm bubbles / 8.4 mm pitch is **generous by every reference** — deliberately so, for phone-camera capture. NEXIAM has headroom to shrink; the question is how much.

## 4. Unit conversions for NEXIAM's pixel space (242 PPI)

`px → mm: px × 25.4 / 242` · `mm → px: mm × 242 / 25.4`

| Bubble px @242 | mm | px @150 dpi scan | px @300 dpi scan | vs references |
|---|---|---|---|---|
| 56 (canonical) | 5.88 | 34.7 | 69.4 | largest; above all references |
| 48 | 5.04 | 29.8 | 59.5 | ≈ v3 A4 sheet (≥5 mm design) |
| 42 | 4.41 | 26.0 | 52.1 | ≈ SAT-class |
| **40** | **4.20** | 24.8 | 49.6 | **Addmen 40 px low bound; ≥2× hardware OMR** |
| 36 | 3.78 | 22.3 | 44.6 | |
| 28 | 2.94 | 17.3 | 34.7 | ≈ hardware-era minimum; not recommended for phone capture |

Related pitches at 242 PPI: 80 px = 8.40 mm (canonical row), 66 px = 6.94 mm (canonical option), 54 px = 5.67 mm, 40 px = 4.20 mm, 30 px = 3.15 mm.

## 5. Can 150 questions fit one long-bond page? (capacity math)

Flow zone (long bond): usable height = 3144 − 900 (header/identity) − 230 (footer/calibration) = **2014 px ≈ 212 mm**; usable width = 2060 − 2×80 = **1900 px ≈ 200 mm**. Current row width (4 options) = 4×56 + 3×66 = **422 px** — wider than the 400 px band, so bubbles already overhang the 40 px gaps (11 px/side) at 4 columns.

| Option | Columns × rows | Feasible at current sizes? | Required changes |
|---|---|---|---|
| **A — 6 × 25** | 6 × 25 = 150 | **No** (band = (1900 − 5×40)/6 = 283 px < 422 px row; collisions) | shrink bubble + option gap |
| **B — 5 × 30** | 5 × 30 = 150 | **No** (band = 348 px < 422 px; vertical pitch ≤ 67 px collides with 9 pt top labels) | shrink bubble + gap, and/or move labels |
| **C — 4 × 38** | 4 × 38 = 152 | **No** (pitch ≤ 53 px < 56 px bubble — impossible) | — |
| **D — 2 pages (75+75)** | trivially fits today | **Yes**, but violates the "one physical page contains all aptitude areas" founder experience ([omr-scoring-capture design.md](openspec/changes/archive/omr-scoring-capture/design.md)) | none |

**Viable one-page designs (geometry must change):**

1. **6 columns × 25 rows, bubble 40 px (4.20 mm), option gap 54 px (center-to-center; edge gap 14 px = 1.47 mm), pitch stays 80 px (8.4 mm).**
   - Row width = 4×40 + 3×54 = 322 px ≤ 283 px band + 40 px gap (19.5 px overhang/side < 20 px — just fits; prefer option gap 52 → row 316 px for margin).
   - Keeps every other invariant: flow zone, margins, 80 px pitch, top labels, identity grids, header — **only bubble diameter and option gap change**.
   - Clean domain mapping: one column per domain (6 domains × 25 questions).
   - 40 px is exactly the Addmen design low bound; 4.2 mm is ~2× hardware-era bubbles and comfortably above SAT-class.
2. **5 columns × 30 rows, bubble 44 px (4.62 mm), option gap 52 px, pitch 68 px (7.14 mm), labels moved/compressed.**
   - Row width = 4×44 + 3×52 = 332 px ≤ 348 px band ✓.
   - Row pitch 68 px gives only 11 px vertical edge gap and collides with the current 9 pt top labels (≈38 px) — requires label redesign (smaller font, or question numbers in the left gutter only) and a smaller flow top. More changes, more risk, no benefit over option 1.
3. **6 columns × 25 rows with bubble 36 px (3.78 mm), option gap 48 px (edge gap 12 px = 1.26 mm):** even more headroom in width (row = 288 px), but marks get small for phone capture; not recommended as the primary option.

Capacity levers that could relax either option further (each is a product decision, not a code decision):
- **Header/identity zone:** flow top is 900 px (≈95 mm) — the tallest single reserve. Identity grids + name box + header occupy it. Compressing or relocating identity (e.g., identity block beside/under the name box, or identity-only header with name box pushed into the flow margin) frees 100–300 px of vertical flow.
- **Legal paper:** 2060×3392 adds 248 px ≈ 26 mm of flow (+3 rows at 80 px pitch) but is a different paper stock decision.
- **Left gutter:** current 4-column layout uses 180 px left margin vs 80 px `MARGIN`; a 6-column layout can recenter bands to reclaim width.

## 6. Markable area, line weight, and handwriting treatment

- **Markable/response area** = the bubble rect (56×56 px) — OMRChecker averages the whole rect; the *filled* area is what counts, so partial fills in a large bubble still read. At 40 px the fill tolerance shrinks proportionally; keep the outline thin (Addmen guidance: outline must not be thick; in-bubble letters must stay small — NEXIAM letters are drawn outside/above bubbles, which is the safer pattern).
- **Line weight:** current bubble outline stroke is thin (measured outlines ≈ 0.7–0.8 mm incl. stroke at 5.88 mm diameter); keep line weight ≤ ~1 px at 242 PPI as bubbles shrink.
- **Handwriting (name/date boxes):** unchanged — free-write boxes are filing reference only; never OCR'd; identity remains bubbled digits. No research impact on sizing; only vertical space allocation matters.

## 7. Scan DPI guidance

- NEXIAM renders at 242 PPI and prints 100 % (measured 216×330 mm). Any scan/photo that covers the page with ≥ ~150 effective DPI (≥ 25 px per 4.2 mm bubble) is far above OMR floors: hardware vendors call **100 DPI sufficient** for standard OMR (Addmen scanning spec); OMRChecker claims support from 640×480 whole-page images; phone photos at ~230 effective DPI are typical.
- Real risk at 150 Q/page is not DPI — it is **alignment/perspective** (more rows = more cumulative warp for camera capture) and **fill quality** at smaller bubbles. Registration/corner markers and the existing CropPage preprocessor are the mitigation; NEXIAM's fixture gate (≥99 % clean-image accuracy, 0 % false-green) must be re-run on the new geometry before activation.

## 8. Recommended geometry (single recommendation, not a menu)

For the one-page six-domain layout, the minimum-change, evidence-compatible proposal:

> **6 columns × 25 rows on long bond; bubble 40×40 px (4.20 mm); option gap 52 px (5.46 mm pitch, 1.26 mm edge gap); row pitch 80 px (8.40 mm) unchanged; margins/flow zone unchanged; one column per domain; identity grids + name box unchanged above the flow zone.**

If the institution requires larger bubbles (e.g., ≥ 4.6 mm "very comfortable" from the physical test-kit feedback), then **2-page 150 (75+75)** or **legal paper + 5 columns** are the honest alternatives — 150 on one long-bond page cannot keep 4.6 mm+ bubbles with readable labels at 242 PPI without a header-zone compression decision.

## 9. Unresolved decisions for the founder

1. **Bubble diameter at 150/page:** accept 4.2 mm (option 1) vs keep ≥4.6 mm and accept 2 pages? (Physical test-kit feedback praised ≈4.6 mm as "very comfortable".)
2. **Column layout semantics:** is "six domains = six columns, 25 each" the intended mapping, or do domains split across columns / interleave?
3. **Header/identity zone:** may the top ~95 mm be compressed (smaller identity block, name box relocated, or identity on the side) to buy vertical space? This is the only lever that could allow larger bubbles in a 5-column/30-row variant.
4. **Paper:** confirm long bond (216×330 mm) is the print stock at the institution's printers; A4 is refused by design. Legal is the only larger alternative.
5. **Name/date boxes on a dense sheet:** keep both boxes (they consume ~45 mm of header), or keep only NAME?
6. **Option-count flexibility:** the solver currently supports only 4-option MCQ v1 (`unsupported_option_count`); a 5-option variant (some aptitude tests) would change row width and kill the 6-column fit — must be confirmed as out of scope for the one-page layout.
7. **Quality re-qualification:** a geometry change invalidates prior detection evidence; the fixture pack gates (≥99 % clean accuracy, 0 % false-green, identity ≥99.5 % precision) must be re-run on the new bundle before activation. Confirm this is scheduled as part of the change, not assumed.

## 10. Source list

- NEXIAM: `system/app/Assessment/Omr/Layout/OmrTemplateLayoutSolver.php`, `system/app/Printing/BundleRenderer.php`, `system/config/omr.php`, `system/.ai/rules/omr.md`, `omr-test-kit/README.md` + measured `answer-sheet-longbond-v4.pdf`; `openspec/changes/archive/omr-layout-solver/{design.md,specs/omr-template-layout/spec.md}`; `openspec/changes/archive/omr-scoring-capture/{design.md,omrchecker-strategic-research.md}`; `openspec/changes/archive/omr-printing-pipeline/tasks.md`.
- OMRChecker pinned `de3f698`: [README](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/README.md), [src/core.py](https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/core.py), [User Guide](https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-User-Guide), [About Templates](https://github.com/Udayraj123/OMRChecker/wiki/%5Bv1%5D-About-Templates), [samples](https://github.com/Udayraj123/OMRChecker/tree/master/samples).
- Industry: [Addmen — bubble size](https://www.addmengroup.com/omr-design/omr-bubble-size.htm), [Addmen — scanning spec](https://www.addmengroup.com/omr-scanner/omr-scanning-specifications.htm), [Addmen — Sheet Design & Calibration Guide PDF](https://www.addmengroup.com/Downloads/Addmen-OMR-Sheet-Design-Guide.pdf), [datatec.co.in OMR sheet design](https://datatec.co.in/an-in-depth-exploration-of-omr-sheet-design-and-security/), [omrhome.com sheet size](https://omrhome.com/blog/omr-sheet-size-and-dimension/), [gradientk12.com Scantron](https://gradientk12.com/scantron/), [Pitt — Gradescope](https://teaching.pitt.edu/resources/gradescope-bubble-sheets/), [Gradescope help](https://guides.gradescope.com/hc/en-us/articles/22246010755853-Creating-a-Bubble-Sheet-Assignment), [official SAT practice answer sheet PDF](https://d15gkqt2d16c1n.cloudfront.net/testprep/prep_guide_resources/SAT%20Practice%20Test%20Answer%20Sheet%203.pdf).

**Uncertainty note:** SAT/Scantron exact bubble diameters are proprietary/unpublished (reported ranges are industry-cited approximations); OMRChecker makes no physical-size guarantee; all vendor accuracy claims are vendor claims, not NEXIAM acceptance evidence.
