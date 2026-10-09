# [DRAFT - UNDER REVIEW] NEXIAM Admission Support & Course Triage Manuscript Sections
**Date:** 2026-09-30  
**Status:** DRAFT WORKING COPY — Prepared for manuscript integration (Chapters 1 to 3 only)  
**Style Standard:** Anti-AI writing profile (No em dashes, no AI buzzwords, no textbook definition dumps, no code-level CQRS jargon, varied rhythm)

---

## 1. Chapter 1: Background of the Study (Insertion / Revision)
*(Position: Under local institutional precedents, contextualizing Yukee et al. and Ballesteros et al. with modern foundation model decision support)*

Institutional research at ISPSC Tagudin Campus has already established that student profiles can be clustered systematically. Yukee et al. (2025) confirmed that K-Means clustering could classify applicants using local entrance examination scores, while Ballesteros et al. (2025) grouped first-year students using Grade 12 General Weighted Average and socioeconomic background. Both studies, however, treated applicant profiling as a retrospective research exercise rather than an active tool for daily counseling. When guidance staff interview applicants, they evaluate incoming credentials manually, cross-referencing exam percentiles with high school records from memory.

Automating this review requires balancing accuracy with practical data constraints. Traditional machine learning models demand thousands of historical training records and fixed data tables, an approach that risks compounding past admission biases while failing whenever secondary records, such as specific high school grades, are missing (Van Busum & Fang, 2025). Rather than fitting an unvalidated model on small local cohorts, educational data systems can apply pre-trained tabular foundation models through in-context evaluation (Hollmann et al., 2025). This method evaluates an applicant's academic standing and exam percentiles against approved institutional benchmarks in a single forward calculation. The system provides immediate decision-support triage during counseling interviews, while leaving final placement decisions entirely in the hands of the counselor.

---

## 2. Chapter 1: Scope and Limitations of the Study (Block SO2-D Revision)
*(Position: Scope & Limitations, defining the course triage and decision-support boundaries)*

The course triage and decision-support module is restricted to generating advisory guidance indicators during post-examination counseling. It holds no autonomous authority to admit applicants, reject candidates, modify department quotas, or confirm enrollments. All final placement decisions remain the sole responsibility of the Guidance Counselor.

The module utilizes a tiered data intake structure designed to accommodate varying levels of applicant documentation. Core mandatory features comprise the Senior High School strand, Grade 12 General Weighted Average (GWA), and sub-test percentile scores from the institutional admission exam. Granular subject marks, including specific high school mathematics and science grades, are treated as optional secondary inputs. The underlying tabular foundation model processes missing optional fields directly through built-in missingness channels, preventing synthetic score penalties when detailed transcripts are unavailable at intake. Standardized vocational interest inventories are excluded from the current operational scope because the campus currently lacks an official psychometric interest instrument; applicant preferences are captured through stated program choices, leaving formal interest testing as a planned future capability.

---

## 3. Chapter 2: Review of Related Literature (Course Routing & Recommender Systems)
*(Position: Literature review subsection covering student course recommendation models)*

Course routing systems have historically relied on unsupervised clustering or classical supervised classification. In student preference modeling, Tan (2025) showed that K-Means optimization could group course preferences effectively, mirroring the local findings of Yukee et al. (2025) at ISPSC Tagudin Campus. While clustering identifies natural aptitude patterns, it remains descriptive and cannot directly recommend degree programs without secondary lookup rules. Supervised classifiers attempt to bridge this gap, yet they require large, balanced datasets from past cohorts (Mittal & Malhotra, 2026). In state college campuses where annual cohorts rarely exceed one thousand applicants, training supervised algorithms locally introduces severe risks of overfitting and reproduces historical placement disparities (Lira et al., 2023; Van Busum & Fang, 2025).

Recent developments in tabular foundation models resolve this operational tension. Tabular Prior-data Fitted Networks, or TabPFN, apply transformer-based in-context learning to structured tables without requiring gradient-based parameter updates on local data (Hollmann et al., 2025). Because the model is pre-trained across synthetic data distributions, it evaluates new candidate profiles against a curated set of approved institutional exemplars in milliseconds. This structure handles tabular records with missing cells naturally, allowing admissions systems to process complete entrance exam records alongside optional secondary grades. The model produces calibrated fit indicators while functioning as an advisory aid rather than an automated decision-maker.

---

## 4. Chapter 3: System Architecture & Methodology (Course Triage Module Specification)
*(Position: Specific Objective 2 / System Design section describing the admission decision-support module)*

The Course Triage and Decision Support Module operates as an advisory component within the Guidance Office counseling interface. It translates raw examination performance and prior academic achievement into structured placement indicators, assisting counselors during post-exam applicant interviews.

To support long-term institutional scalability, the module processes applicant records across three distinct data tiers:

1. *Mandatory Baseline Indicators:* The applicant's Senior High School academic strand, Grade 12 General Weighted Average (GWA), and six sub-test percentile ranks from the institutional entrance examination battery (covering General Ability, Verbal Aptitude, Numerical Aptitude, Spatial Aptitude, Perceptual Aptitude, and Manual Dexterity).
2. *Optional Secondary Records:* Subject-specific high school marks (such as Grade 11 or 12 Mathematics and Science ratings) entered at the counselor's discretion during advising sessions.
3. *Future Testing Modalities:* Reserved fields designed to ingest standardized vocational interest inventories (such as Holland RIASEC profiles) once an official instrument is adopted by the college.

Analytical evaluation is handled through an integrated tabular foundation model adapter executing in-context inference (TabPFN). The model receives the applicant's available indicator vector alongside verified curriculum benchmark profiles approved by the college. Rather than relying on traditional retraining pipelines, the in-context architecture performs Bayesian inference in a single forward pass, natively accommodating missing secondary grades through explicit missingness indicators without distorting overall aptitude assessments. 

The interface presents counselors with ranked program compatibility percentages, primary academic drivers (such as high quantitative aptitude or strand alignment), and an explicit data audit indicating which indicators were evaluated. In accordance with institutional policy, all module outputs remain advisory; the system records an attributable audit log whenever a counselor accepts, modifies, or overrides a placement recommendation.
