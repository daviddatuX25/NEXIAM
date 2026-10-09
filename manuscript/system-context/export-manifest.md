# Export Manifest: NEXIAM Manuscript Technical Context

## 1. Package Metadata

- **Package Title:** NEXIAM Portable System Context & Research Reference
- **Version:** 1.0.0-rc1
- **Baseline Timestamp:** 2026-10-09
- **Monorepo Git Commit Baseline:** `HEAD` (master branch, ahead 353 commits relative to origin/master)
- **Primary Authors/Researchers:** David Datu N. Sarmiento, Jaypee G. Pagaduan, Christine M. Lopez
- **Research Adviser:** Dr. Zeus Vincent Mendoza
- **Target Institution:** Ilocos Sur Polytechnic State College (ISPSC), Tagudin Campus

---

## 2. Inclusions (Exportable Scope)

The exported `manuscript/` bundle contains:

1. **Academic Manuscript Drafts & Chapters:**
   - Canonical markdown sections in `manuscript/sections/` (Chapters 1, 2, 3, Front Matter, Appendices).
   - Review copies (`NEXIAM_Chapter2_Methodology_REVIEW.md`, `NEXIAM_Chapter3_Results_Discussion_REVIEW.md`).
   - Reference indices (`MANUSCRIPT_REFERENCE_INDEX.md`, `MANUSCRIPT_IMPROVEMENT_LEDGER.md`).
2. **System Context & Technical Logic Reference (`manuscript/system-context/`):**
   - Grounded domain architecture and data contracts (`domains/01-admissions-and-intake.md` through `domains/06-shared-services-and-rbac.md`).
   - Reconciled terminology register (`03-terminology-reconciliation.md`).
   - Evidence registry, architectural conflicts, and open questions (`02-evidence-and-conflicts.md`).
   - Research objectives and claim crosswalks (`research-method-map.md`, `manuscript-claim-crosswalk.md`).
3. **Approved Research Instruments & Facsimiles:**
   - Evaluated 38-item TAM questionnaire packets (`exports/research-instruments/`).
   - Signed institutional communication letters (`V2_RESOURCES/latest_diagrams/Deployment Letter Final.pdf`, `English Critique Letter.pdf`).
4. **Visual Diagrams & Layout Models:**
   - System visual assets, DFDs, ERDs, and D2 sources (`V2_RESOURCES/figures/`, `V2_RESOURCES/latest_diagrams/`).

---

## 3. Explicit Exclusions (Sanitized Before Public Export/Push)

To preserve participant privacy and system security, the following artifacts must **NOT** be included in any public GitHub push or external distribution:

| File / Pattern | Reason for Exclusion | Safe Alternative Provided |
|---|---|---|
| `manuscript/Phase(RAD-based) Work/.../ISPSC Tagudin Campus — Admission and Testing Journey Survey (Responses) - Batch 2026-09-29.csv` | Contains real student names, timestamps, and identifiable comments | Anonymized aggregate summary in `manuscript/sections/03_chapter_3_results_and_discussion/03_1_so1_process_analysis.md` |
| `system/database/seed-assets/...` | Binary institutional test document templates | Described structurally in system-context specifications |
| Session transcript files (`~/.omp/agent/sessions/`) | Raw developer-agent chat logs and prompts | Extracted and synthesized in `01-source-register.md` and `03-terminology-reconciliation.md` |
| Local environment credentials (`.env`, `storage/*.key`) | Production/local security secrets | Synthetic test fixtures documented in seed configurations |

---

## 4. Portability Verification Checklist

- [x] All intra-package links resolve within `manuscript/` relative paths.
- [x] Critical external references (OpenSpecs, GF contracts, database schemas) are summarized with inline excerpts.
- [x] Technical claims distinguish design specifications from passing runtime proofs.
- [x] Diagram citations include local path locations and explicit integrity hashes.
- [x] Research instruments reflect confirmed researcher names: David Datu N. Sarmiento, Jaypee G. Pagaduan, Christine M. Lopez.
