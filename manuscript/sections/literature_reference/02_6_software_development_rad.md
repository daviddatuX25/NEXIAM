# Chapter 2: Review of Related Literature and Studies

## 2.6 Software Development Methodology: The Rapid Application Development Model

In software engineering for higher education institutions, selecting an appropriate development lifecycle is critical for ensuring both technical security and functional alignment with complex administrative workflows. When developing institutional web systems that span multiple administrative offices, the Rapid Application Development (RAD) model has emerged as an exceptionally effective methodology (Martin, 1991; Pressman & Maxim, 2020).

Originally formulated by James Martin, the RAD model compresses the extended development timelines characteristic of traditional waterfall methodologies by prioritizing active user involvement, rapid prototyping, and iterative refinement. In institutional environments where administrative requirements are shaped by evolving government mandates and multi-office handoffs, sequential linear lifecycles often fail because operational misalignments are discovered only during late-stage deployment. RAD resolves this vulnerability by maintaining continuous feedback loops between system developers and institutional stakeholders throughout construction.

The operational suitability of RAD within the Philippine state university system has been extensively validated in recent educational technology literature:
* Olipas (2020) demonstrated that developing administrative web platforms in a Philippine state university using RAD produced high levels of software quality, usability, and stakeholder acceptance, confirming that iterative prototyping allows non-technical university staff to validate user interfaces before backend architectures are finalized.
* Melendres and Aranda (2024) utilized the RAD framework to engineer an institutional student record and admissions tracking system in a regional state college, demonstrating that rapid feedback loops successfully resolved complex inter-office coordination bottlenecks between academic departments and the registrar.

The RAD methodology organizes engineering work across four structured phases:
1. *Requirements Planning:* Merges process analysis with system specification. Developers conduct descriptive inquiries, interviews, and field observations with operational personnel to identify institutional pain points and formalize functional requirements.
2. *User Design:* Translates verified requirements into deterministic architectural blueprints. Developers work alongside end-users to formulate relational database schemas, map role authorization matrices, produce data flow diagrams, and construct interactive interface mockups.
3. *Rapid Construction:* Represents the technical implementation core. Developers systematically integrate software modules, database engines, and processing pipelines through iterative cycles of design refinement and automated testing.
4. *Cutover:* Manages the transition of the completed platform into empirical evaluation and operational deployment, encompassing cloud staging, role-based simulation scenarios, and user acceptance testing.

Within the Rapid Construction phase of NEXIAM, modern software engineering practices were incorporated to ensure architectural maintainability. Rather than fragmenting the platform into microservices that introduce distributed transaction overhead, the system was engineered as a modular monolith using the Laravel framework paired with PostgreSQL as the relational state authority and Inertia with Svelte for responsive client interfaces. 

Furthermore, development incorporated human-governed AI-assisted implementation to accelerate routine module generation under strict peer review (Peng et al., 2023; Osmani et al., 2026). In accordance with established academic research standards, the study's scientific contribution is the integrated, role-governed admission testing platform that results, rather than the developmental tools utilized during its construction.
