# Scaling Readiness — Task Tracker

> Updated: 2026-06-12

| # | Plan File | Phase | Status |
|---|-----------|-------|--------|
| CLN-1 | [repo-cleanup-design](./2026-06-12-repo-cleanup-design.md) | Explore context & list files | ✅ DONE |
| CLN-2 | [repo-cleanup-design](./2026-06-12-repo-cleanup-design.md) | Ask clarifying questions and propose options | ✅ DONE |
| CLN-3 | [repo-cleanup-design](./2026-06-12-repo-cleanup-design.md) | Present design & write design doc | ✅ DONE |
| CLN-4 | [repo-cleanup-design](./2026-06-12-repo-cleanup-design.md) | Execute plan (pruning worktrees, log files, config files) | ✅ DONE |
| CLN-5 | [repo-cleanup-design](./2026-06-12-repo-cleanup-design.md) | Rebuild distilled ctxpkg and remove temporary scripts | ✅ DONE |

| # | Plan File | Phase | Status |
|---|-----------|-------|--------|
| P1-T1 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Remove `/proctor` dead stub route | ⬜ TODO |
| P1-T2 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Remove `exam-scheduling/schedule-assistant` GET redirect | ⬜ TODO |
| P1-T3 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Clarify grading import POST-guard redirect comments | ⬜ TODO |
| P1-T4 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Clarify knowledge-documents redirect comment | ⬜ TODO |
| P1-T5 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Delete orphaned `AdmissionSlipTemplateController` | ⬜ TODO |
| P1-T6 | [phase1-route-cleanup](./2026-05-27-scaling-readiness-phase1-route-cleanup.md) | Merge duplicate `setup+reports` route groups | ⬜ TODO |
| P2-T1 | [phase2-application-controller-split](./2026-05-27-scaling-readiness-phase2-application-controller-split.md) | Extract `PublicApplicationController` | ⬜ TODO |
| P2-T2 | [phase2-application-controller-split](./2026-05-27-scaling-readiness-phase2-application-controller-split.md) | Extract `PortalApplicationController` | ⬜ TODO |
| P2-T3 | [phase2-application-controller-split](./2026-05-27-scaling-readiness-phase2-application-controller-split.md) | Extract `BulkApplicationController` | ⬜ TODO |
| P2-T4 | [phase2-application-controller-split](./2026-05-27-scaling-readiness-phase2-application-controller-split.md) | Extract `AdmissionSlipController` | ⬜ TODO |
| P2-T5 | [phase2-application-controller-split](./2026-05-27-scaling-readiness-phase2-application-controller-split.md) | Extract `AdminApplicationController` + delete old controller | ⬜ TODO |
| P3-T1 | [phase3-exam-session-controller-split](./2026-05-27-scaling-readiness-phase3-exam-session-controller-split.md) | Extract `ExamSessionWorkflowController` (7 transition methods) | ⬜ TODO |
| P3-T2 | [phase3-exam-session-controller-split](./2026-05-27-scaling-readiness-phase3-exam-session-controller-split.md) | Extract `ExamSessionRosterController` (assign/remove applicants) | ⬜ TODO |
| P3-T3 | [phase3-exam-session-controller-split](./2026-05-27-scaling-readiness-phase3-exam-session-controller-split.md) | Extract `ExamSessionMonitoringController` (monitoring + test-admin) | ⬜ TODO |
| P4-A1 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Define `ResultSheetRenderer` interface + `RenderResult` VO | ⬜ TODO |
| P4-A2 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Extract `DocxRenderer` | ⬜ TODO |
| P4-A3 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Extract `OdtRenderer` | ⬜ TODO |
| P4-A4 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Extract `PdfRenderer` | ⬜ TODO |
| P4-A5 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Update `ResultSheetTemplateService` to delegate to renderers | ⬜ TODO |
| P4-B1 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Extract `ReportExportService` pipe classes (Pipeline pattern) | ⬜ TODO |
| P4-C1 | [phase4-service-decomposition](./2026-05-27-scaling-readiness-phase4-service-decomposition.md) | Extract `DashboardAnalyticsService` query objects | ⬜ TODO |
| CAP-1 | [capstone-dashboard-design](./2026-06-02-capstone-dashboard-design.md) | Update `capstone/README.md` to reflect new structure | ✅ DONE |
| CAP-2 | [capstone-dashboard-design](./2026-06-02-capstone-dashboard-design.md) | Create `capstone/index.html` with interactive portal and Gantt visualization | ✅ DONE |
| CAP-3 | [capstone-dashboard-design](./2026-06-02-capstone-dashboard-design.md) | Remove old `capstone/team_meta/PROJECT_GANTT_CHART.html` | ✅ DONE |
| CEP-1 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Create `FEATURE_VERIFICATION_PROTOCOL.md` (data gathering script) | ✅ DONE |
| CEP-2 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Fix direct contradictions in `RESEARCH_ARGUMENT_BANK.md` | ✅ DONE |
| CEP-3 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Update `C1-11_David_Scope_Delimitations.md` | ✅ DONE |
| CEP-4 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Update `C1-01_David_Background_P1.md` | ✅ DONE |
| CEP-5 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Expand `C1-09_Jaypee_Objectives.md` | ✅ DONE |
| CEP-6 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Update `C2-01_David_Research_Design.md` (two-phase intervention) | ✅ DONE |
| CEP-7 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Update `RESEARCH_ARGUMENT_BANK.md` supporting entries | ✅ DONE |
| CEP-8 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Update `SYSTEM_FEATURES.md` and feature descriptions | ✅ DONE |
| CEP-9 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Create `DEFENSE_EVIDENCE_PLAYBOOK.md` | ✅ DONE |
| CEP-10 | [capstone-elevation-plan](../../capstone/research/CONTEXT_UPDATE_June4.md) | Create `EVALUATION_STRATEGY.md` and `ROLE_MODEL_ANALYSIS.md` | ✅ DONE |
| J5-D1 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | David: C2-02 Software Model (AIDLC) — full draft | ✅ DONE |
| J5-D2 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | David: Add STEER markers to C1-01 | ✅ DONE |
| J5-D3 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | David: Start C1-02 research (pull 5 sources) | ✅ DONE |
| J5-J1 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | Jaypee: C1-12 Significance — full draft | ✅ DONE |
| J5-J2 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | Jaypee: C1-04 Background P4 Local — draft | ✅ DONE |
| J5-C1 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | Christine: C2-04 Project Assignment — full draft | ✅ DONE |
| J5-C2 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | Christine: C2-06 Research Instruments — draft | ✅ DONE |
| J5-C3 | [june5_10_execution_plan_v3](../../.gemini/antigravity-cli/brain/7c6dbee5-5894-4622-a4a1-649cc0d110a6/june5_10_execution_plan_v3.md) | Christine: C2-05 Population & Locale — skeleton | ✅ DONE |
| AD-1 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Remove multi-tenancy from current scope to recommendations (SecureCAT-v2-swl) | ✅ DONE |
| AD-2 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Add infrastructure and tenancy notes to research notes (SecureCAT-v2-m66) | ✅ DONE |
| AD-3 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Update IPO diagram: consistent naming and remove formulas (SecureCAT-v2-9i2) | ✅ DONE |
| AD-4 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Define and frame RAG/AI Companion use cases (SecureCAT-v2-i18) | ✅ DONE |
| AD-5 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Structure ML section with transitions and bridge to integration (SecureCAT-v2-awo) | ✅ DONE |
| AD-6 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Enhance security emphasis (SecureCAT-v2-j2x) | ✅ DONE |
| AD-7 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Rewrite Background of the Study for quality & organization (SecureCAT-v2-y21) | ✅ DONE |
| AD-8 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | David reviews simulated interview responses for accuracy (SecureCAT-v2-c67) | ✅ DONE |
| AD-9 | [IMPROVEMENT_TASKS.md](../../capstone/notes/IMPROVEMENT_TASKS.md) | Perform final unification pass (SecureCAT-v2-6rl) | 🔄 IN_PROGRESS |
| AD2-1 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C2-01 Research Design to future tense (SecureCAT-v2-ezd) | ✅ DONE |
| AD2-2 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C2-02 Software Model to future tense (SecureCAT-v2-aq2) | ✅ DONE |
| AD2-3 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C2-05 Population & Locale to future tense (SecureCAT-v2-akq) | ✅ DONE |
| AD2-4 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C2-06 Research Instruments to future tense (SecureCAT-v2-d65) | ✅ DONE |
| AD2-5 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C2-07 Data Analysis to future tense (SecureCAT-v2-j45) | ✅ DONE |
| AD2-6 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Align C1-12 Significance of the Study to future tense (SecureCAT-v2-5zc) | ✅ DONE |
| AD2-7 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Verify Thematic Consistency and Bead Trends (SecureCAT-v2-tz9) | ✅ DONE |
| AD2-8 | [IMPROVEMENT_TASKS_2.md](../../capstone/notes/IMPROVEMENT_TASKS_2.md) | Compile and Assemble Updated Manuscript (SecureCAT-v2-5ls) | ⬜ TODO |
| BMK-1 | [range-bookmarks-design](./2026-06-15-range-bookmarks-design.md) | Design range-based bookmarks | ✅ DONE |
| BMK-2 | [range-bookmarks-implementation](./2026-06-15-range-bookmarks-implementation.md) | Task 1: Add Unit Tests for Range Bookmarks | ✅ DONE |
| BMK-3 | [range-bookmarks-implementation](./2026-06-15-range-bookmarks-implementation.md) | Task 2: Implement Range-Based Bookmarks in md_to_docx.py | ✅ DONE |
| BMK-4 | [range-bookmarks-implementation](./2026-06-15-range-bookmarks-implementation.md) | Task 3: Update Bookmark Management Scripts | ✅ DONE |
| BMK-5 | [range-bookmarks-implementation](./2026-06-15-range-bookmarks-implementation.md) | Task 4: Run Verification and Audit Pipeline | ✅ DONE |
| REV-1 | [manuscript-critical-revisions](./2026-06-16-manuscript-critical-revisions.md) | Task 1: Apply Chapter 1 Critical Revisions (Changes 1 to 14) | ✅ DONE |
| REV-2 | [manuscript-critical-revisions](./2026-06-16-manuscript-critical-revisions.md) | Task 2: Apply Chapter 2 Critical Revisions (Changes 15 to 21) | 🔄 IN_PROGRESS |
| REV-3 | [manuscript-critical-revisions](./2026-06-16-manuscript-critical-revisions.md) | Task 3: Perform Reference List Audit & Dead Citations Check (Changes 22 to 25) | ⬜ TODO |
| REV-4 | [manuscript-critical-revisions](./2026-06-16-manuscript-critical-revisions.md) | Task 4: Run Unified Pipeline and Verify Roundtrip Integration | ⬜ TODO |
| REV-5 | [manuscript-critical-revisions](./2026-06-16-manuscript-critical-revisions.md) | Task 5: Push Updates to Drive & Create Backup | ⬜ TODO |
| LCX-1 | [remove-lean-ctx](./2026-06-26-remove-lean-ctx.md) | Clean local lean-ctx traces and install globally | ✅ DONE |


