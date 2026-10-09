# SO1 Figure Sources and Preparation

## Current figures

**Figure 3.1** shows the source-review and process-mapping sequence. **Figure 3.2** summarizes the reported institutional admission-testing process. They are author-created charts of the study records, not screenshots of implemented software or measured before/after results.

Each figure has editable `.d2` source, vector `.svg`, and `.png` output in this directory. The PNGs are inserted in the Chapter 3 discussion, followed by the caption and a short explanation. SVGs remain available for high-quality document preparation.

## Reproduce

From `system/`:

```bash
node bin/render-manuscript-so1.mjs
node bin/publish-manuscript.mjs
```

The renderer uses the installed D2 compiler (`~/.local/bin/d2`; optional `D2_BIN` override) and exports only the two named figure sources. The browser publisher copies only the two approved PNGs. Clicking a figure opens its full-size PNG.

## Evidence map

| Chart content | Source | Limit |
|---|---|---|
| Evidence review by source/cohort | Chapter 3, Table 3.1; Appendices G to I | The two applicant-survey cohorts are not one pooled population. |
| Staff-managed examination batches | Guidance Round 2 CSV, 25 June 2026 | Available rooms and pooled applicants are recorded constraints; no applicant self-booking is inferred. |
| Paper ICAT and manual stencil scoring | Guidance Round 1/2 records | No numerical accuracy or speed improvement is inferred from this chart. |
| Result explanation/counseling | Guidance questionnaire and applicant accounts | This is the reported face-to-face result workflow, not online consultation. |
| Quota/slot information input | Program Head Round 1 CSV | The recorded channel is face-to-face meetings. The arrow is an information input, not an applicant processing stage. |
| Paper submission and staff review | Applicant process accounts and defended manuscript context | Generic admission staff is used; the chart does not invent Registrar-specific approval branches. |

No unsupported platform brands, imposed repeat-visit counts, causal red failure labels, or generalized cohort percentages appear in the charts. Enrollment processing is outside the illustrated admission-testing path.

## Existing technical diagrams

The researcher already has DFD Level 0/1, a basic ERD, and activity diagrams. Do not redraw them in this cut. Appendices C to F reserve the full use-case/DFD/ERD material. Select an existing macro/activity view for a later NEXIAM-supported-flow comparison and identify its version. A design/process comparison is not proof of measured improvement.

For final DOCX layout, size the complete figure to fit the page without splitting it; keep its caption below and avoid shrinking node labels below a readable size. The current source figures are portrait layouts. Institutional pagination and final image placement are still a layout check, not established by the browser preview.
