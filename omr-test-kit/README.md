# OMR Physical Test Kit — print, shade, scan

**Goal:** prove the OMR capture feature works with a real printed sheet, real pen marks, and a phone camera.

**Printable files** (same directory, served at `http://127.0.0.1:8735/omr-test-kit/`):

| File | Use |
|---|---|
| `answer-sheet-longbond-v4.pdf` | **THE current test sheet — TWO PAGES on long bond 8.5×13in** — print BOTH pages at 100%, no fit-to-page |
| `answer-sheet-page-2.pdf` | **TWO-PAGE sheet, page 2 (QUANT)** — A4 portrait, 100% |
| `answer-sheet-fit-a4.pdf` | Single-page A4 sheet (legacy v2, dense) |
| `answer-sheet-a3.pdf` | Single-page A3 (legacy v2) |

> The sheet is a proper answer sheet, one page:
> - **NAME (PRINT):** and **EXAM DATE:** boxes at the top — human filing reference. The machine never reads handwriting (founder lock: identity is bubbled digits only; the review screen's examinee name comes from staff binding to the roster, not OCR).
> - **APPLICANT NUMBER grid:** 10 columns (positions 1–10, labeled), digits 0–9 (labeled) down each column. Shade one digit per column.
> - **200 questions** in two labeled sections: **SECTION 1 — VERBAL** (top band) and **SECTION 2 — QUANT** (bottom band), five numbered columns each (1–20, 21–40, …), four bubbles per row labeled **A B C D**.

**Pen:** fine-tip ballpoint or marker (**0.3–0.5mm tip**). On the two-page sheet bubbles print ≈4.6mm — very comfortable. Fill the whole circle. No pencil for the baseline tests. **Shade the applicant number on BOTH pages** (the system reconciles them).

## Before you start

App must be running with the demo session (and no open OMR batch yet):

```bash
cd system
docker compose up -d --force-recreate app queue inference
docker compose exec app php artisan foundation:reset --force
docker compose exec app php artisan db:seed --class=GradingDemoSeeder --force
```

- Open `http://127.0.0.1:18000/grading/sessions/1/omr`
- Login: `guidance.counselor@nexiam.test` / password `password`
- Create the batch: pick the pair labeled **"…sheet v3 · …booklet A v3"** (the TWO-PAGE A4 sheet)
- Choose **Auto** mode → upload **page 1** first (or page 2 — any order works), then the other page **with the same "sheet set"** (the capture screen asks for page 1 of 2, then page 2 of 2) → analysis starts only after both pages land
- The sheet appears in the gallery as **Needs review** with all 200 merged items

## The answer key (for verification)

The demo exam's correct answers repeat **A, B, C, D, A, B, C, D…** for every area:

- Question 1 = A, Question 2 = B, Question 3 = C, Question 4 = D, Question 5 = A, … Question 100 = D
- Same pattern in **both** VERBAL and QUANT sections

## Test 1 — the perfect sheet (baseline)

1. Print and shade:
   - **Applicant number: `1234567890`** — column 1 shade **1**, column 2 shade **2**, column 3 shade **3**, … column 9 shade **9**, column 10 shade **0**
   - **Every question: shade the correct answer** (A B C D repeating: Q1=A, Q2=B, Q3=C, Q4=D, Q5=A…)
2. Upload. **Expected:**
   - Gallery shows `Needs review`
   - Review shows detected applicant number **1234567890** (no "Identity not detected")
   - Per-area proposal: **QUANT 100/100, VERBAL 100/100**
   - No issues listed

## Test 2 — all answers wrong the same way

1. Same applicant number (`1234567890`).
2. Shade **A for every question** (all 200).
3. **Expected:** QUANT **25/100**, VERBAL **25/100** (A is correct for Q1, Q5, Q9… = every 4th question = 25 per area)

## Test 3 — blank sheet

1. Upload a sheet with **no marks at all**.
2. **Expected:** `Needs review` with **Identity not detected** + a long list of `blank_mark` issues; every item blank; you can still bind + save (mandatory review path works).

## Test 4 — messy marks (edge cases)

1. Applicant number `1234567890` but shade **both 3 and 5 in column 3** (conflict).
2. QUANT Q1: shade **both A and B** (double mark).
3. QUANT Q2: shade B, then **erase poorly** so a ghost remains.
4. VERBAL Q3: shade **half of C** (light/partial mark).
5. **Expected:** review shows issues (ambiguous/identity conflict), detected number may be flagged — bind manually and save. Record exactly what the UI reports.

## Test 5 — physical stress

1. Photograph the Test-1 sheet **rotated ~5–10°** on the desk.
2. Photograph it again with **uneven light / shadow** across a column.
3. Photograph it **slightly folded or crumpled** at one corner.
4. **Expected:** either a correct `Needs review` or a typed failure state — record which. This data calibrates our tolerance work.

## Test 6 — wrong input

1. Upload a **blank piece of paper** (not the sheet).
2. **Expected:** the sheet lands in a failed state with a typed diagnostic (e.g. preprocessing failed) — it must NOT silently produce a proposal.

## Reading the review panel

- **Detected** column = what the engine saw; **Score** column = 1/0 vs the answer key (only staff sees the key)
- Identity is always **staff-bound** to the roster dropdown — the detected number is advisory, never automatic
- Issues listed per item tell you exactly which question/type failed

## Report your results

For each test note: photo quality (light/angle), what the gallery said, what the review showed, and any mismatch vs "Expected". That record becomes our acceptance evidence.
