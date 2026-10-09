# Front Matter: Title Page, Approval Sheet, and Preliminaries

## Title Page

```
                    ILOCOS SUR POLYTECHNIC STATE COLLEGE
                               Tagudin Campus
                            Tagudin, Ilocos Sur

                       COLLEGE OF ARTS AND SCIENCES
                     INFORMATION TECHNOLOGY DEPARTMENT




               NEXIAM: A ROLE-BASED COLLEGE ADMISSION TESTING
                SYSTEM FOR THE GUIDANCE AND REGISTRAR OFFICES
                           AT ISPSC TAGUDIN CAMPUS




                    A Capstone Project Manuscript Presented to
                             the Faculty and Staff of
                       the Information Technology Department
                    Ilocos Sur Polytechnic State College
                               Tagudin Campus




                      In Partial Fulfillment of the Requirements
                           for the Degree of Bachelor of
                          Science in Information Technology




                               SARMIENTO, DAVID D.
                               PAGADUAN, JAYPEE G.
                                  LOPEZ, C. M.

                                  October 2026
```

---

## Approval Sheet

```
                     ILOCOS SUR POLYTECHNIC STATE COLLEGE
                               Tagudin Campus
                            Tagudin, Ilocos Sur

                       COLLEGE OF ARTS AND SCIENCES
                     INFORMATION TECHNOLOGY DEPARTMENT


                                APPROVAL SHEET

This capstone project manuscript entitled "NEXIAM: A ROLE-BASED COLLEGE ADMISSION
TESTING SYSTEM FOR THE GUIDANCE AND REGISTRAR OFFICES AT ISPSC TAGUDIN CAMPUS,"
prepared and submitted by DAVID D. SARMIENTO, JAYPEE G. PAGADUAN, and C. M. LOPEZ
in partial fulfillment of the requirements for the degree of Bachelor of Science
in Information Technology, has been examined and is recommended for acceptance
and approval for ORAL EXAMINATION.


                              PANEL OF EXAMINERS

                   Approved by the Oral Examination Committee:


      [PANEL MEMBER 1]                              [PANEL MEMBER 2]
           Member                                        Member


   [CAPSTONE INSTRUCTOR]                     ZEUS VINCENT MENDOZA, DIT
    Capstone Instructor                                Adviser


Accepted and approved in partial fulfillment of the requirements for the degree of
Bachelor of Science in Information Technology.


                                                  [DEAN / CAMPUS DIRECTOR]
                                                   Campus Executive Director
```

---

## Abstract

```
SARMIENTO, DAVID D., PAGADUAN, JAYPEE G., LOPEZ, C. M. Ilocos Sur Polytechnic State
College, Tagudin Campus, Tagudin, Ilocos Sur, October 2026. "NEXIAM: A Role-Based
College Admission Testing System for the Guidance and Registrar Offices at ISPSC
Tagudin Campus."
Adviser: DR. ZEUS VINCENT MENDOZA

This study designed, developed, and evaluated NEXIAM, a web-based admission testing
and decision-support system engineered for the Guidance and Registrar Offices of
ISPSC Tagudin Campus. Operating across a paper-based infrastructure, the institution
historically experienced multi-day scoring delays, fragmented result dissemination,
informal inter-office coordination, undocumented counseling sessions, and an absence
of persistent admission configuration records. 

Structured under a descriptive-developmental research design, the study utilized the
Rapid Application Development (RAD) model across Requirements Planning, User Design,
Rapid Construction, and Cutover phases. Requirements Planning confirmed five critical
operational failure modes through questionnaires, interviews, observations, and applicant
surveys (n = 22), revealing that 60% of applicants face outer-municipality travel burdens
and 70% experience document verification uncertainty. User Design formulated a seven-role
Role-Based Access Control (RBAC) architecture with a contextual Proctor duty overlay,
structured across a seven-stage operational lifecycle: (1) Configuration and Setup,
(2) Admission and Application Intake, (3) Examination Scheduling, (4) Examination and
Testing Day, (5) Result Preparation, (6) Consultation and Course Triage, and
(7) Post-Admission Handoff and Reporting.

Rapid Construction implemented a modular monolith on PHP 8.4, Laravel, PostgreSQL, and
Inertia with Svelte, integrating computer-vision Optical Mark Recognition (OMR) on commodity
scanners, in-context tabular foundation model triage (TabPFN) handling structural feature
missingness across a 3-tier data model, active HTML/ODT result-sheet template rendering, and
an HMAC-SHA256 cryptographically chained audit log. System acceptance is evaluated during
the Cutover phase using the Technology Acceptance Model (TAM) across three primary stakeholder
groups (Registrar Personnel, Guidance Personnel, and First-Year Students; n = 260) measuring
Perceived Usefulness and Perceived Ease of Use.

Keywords: College Admission System, Optical Mark Recognition, TabPFN, In-Context Learning,
Role-Based Access Control, Rapid Application Development, Technology Acceptance Model,
Republic Act No. 10173, Republic Act No. 10931.
```

---

## Acknowledgment

The researchers express sincere gratitude to Almighty God for the wisdom, resilience, and clarity granted throughout the conception and implementation of this capstone research.

Heartfelt gratitude is extended to Dr. Zeus Vincent Mendoza, project adviser, for his continuous guidance, rigorous critique, and valuable technical insights that shaped the architectural and scholarly trajectory of this study.

The researchers convey profound appreciation to the administrative personnel and academic leadership of ISPSC Tagudin Campus:
* To the Guidance Office personnel and Test Administrators, for their generosity in providing empirical workflow access, participating in diagnostic inquiries, and evaluating operational prototypes.
* To the Registrar Office personnel, for their cooperation during requirements gathering and intake verification mapping.
* To the Academic Program Heads, for their contributions regarding departmental quota management and pre-enrollment planning.
* To the first-year student examinees, whose candid survey responses established the empirical foundation of the applicant journey.

Lastly, the researchers extend boundless gratitude to their parents, families, and friends, whose enduring encouragement, patience, and sacrifices provided the steady foundation required to bring this platform to fruition.

D.D.S.  
J.G.P.  
C.M.L.
