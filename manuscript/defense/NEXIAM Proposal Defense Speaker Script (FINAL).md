# **NEXIAM Proposal Defense Speaker Script (v4)**

**Target Time:** ~9:10 (leaves ~50-second buffer under the 10-minute max)
**Speakers:** David (Slides 1–5 + 7 + 13), Jaypee (Slides 6 + 8–9), Christine (Slides 10–12)
**Key change from v3:** Conceptual Framework and IPO merged into one slide (Slide 5). Layer walkthrough removed. Background slides expanded. All David lines updated.

---

### **SLIDE 1: Title Slide (No Clicks)**

**[Presenter: David]**
*(Leave this on screen while the panel gets settled. After introductions, immediately take the floor.)*

**David:** "Good morning, respected members of the panel. We are presenting NEXIAM: A Role-Based College Admission Testing System. Let's start immediately with why this system is necessary."

*(Transition to Slide 2)*

---

### **SLIDE 2: The Problem — Global & National (EXPANDED)**

**[Presenter: David]**

**David:** "Manual, paper-based admission testing is not just slow — it creates real integrity risks and equity gaps for applicants who cannot afford repeat trips to campus."

*(Click 1)* "Globally, higher education is shifting to AI-assisted, cloud-based admission platforms. Lira et al., publishing in *Science Advances*, demonstrated that machine learning models can assess applicant qualities with outcomes tied to long-term success. The AI-admissions space is validated at the highest level."

*(Click 2)* "Here in the Philippines, RA 10931 — the Free Higher Education Act — pushed public college applications up nationwide. Adwan et al. proved that unified admission platforms work across multiple institutions with 97% usability. The integration model itself is proven."

*(Click 3)* "RA 10173 — the Data Privacy Act — makes protecting applicant data a legal duty. That regulatory floor governs everything we build."

**DEFENSE ANCHOR:** You are establishing a three-part foundation: (1) AI in admissions is validated science, (2) unified platforms are proven to work, (3) Philippine law mandates the digitization step. The panel cannot say this is speculative.

---

### **SLIDE 3: The Problem — At ISPSC Tagudin (EXPANDED)**

**[Presenter: David]**

**David:** "At ISPSC Tagudin specifically, we process 700 to 1,000 applicants every year — entirely on paper. Here is the operational cost of that:"

*(Click 1)* "Hand-scoring takes five days, sometimes up to two full weeks per cycle."

*(Click 2)* "Results are posted on a Facebook page, forcing applicants to monitor or inquire during office hours only."

*(Click 3)* "Inter-office coordination runs on unstructured text messages. There is no shared system or visibility between Guidance and Registrar."

*(Click 4)* "And here is the gap. Each component NEXIAM needs is already proven independently. Dayata and Yap deployed a RAG chatbot at the University of San Carlos, published at IEEE COMNETSAT. Cuerdo proved low-cost OMR works in Philippine public schools. Tan validated K-Means for student course selection at an ACM conference. These pieces exist — they just do not run together as one governed pipeline. That is the gap NEXIAM fills."

**DEFENSE ANCHOR:** The "gap" is now presented as an INTEGRATION gap, not an invention gap. You are naming specific published precedents (IEEE, ACM) so the panel sees you did not just pick features arbitrarily — each is borrowed from a proven independent study.

---

### **SLIDE 4: Objectives of the Study (TRIMMED)**

**[Presenter: David]**

**David:** "Our general objective is to develop NEXIAM — a web-based, role-governed platform. Four specific objectives sit underneath this as a dependency chain."

*(Click 1)* "SO1: Analyze the existing process and pinpoint its gaps."

*(Click 2)* "SO2: Determine the exact system requirements those gaps demand."

*(Click 3)* "SO3: Evaluate user acceptance using the Technology Acceptance Model."

*(Click 4)* "SO4: Test statistically whether NEXIAM performs measurably better than the manual process."

---

### **SLIDE 5: Conceptual Framework — IPO Model (NEW, REPLACES OLD SLIDES 5+6)**

**[Presenter: David]**

**David:** "Here is how those objectives map into a single Input-Process-Output framework."

*(Click 1 — reveals full IPO diagram)* "Input covers campus data — applicant profiles, exam scores, and manual workflow data."

*(Click 2 — Process highlights)* "Process follows the Rapid Application Development model — from requirements planning through rapid construction and cutover. Integrated within this process is the TAM evaluation: a paired pre-test and post-test that turns system use into measurable evidence."

*(Click 3 — Output highlights)* "Output has two connected parts. First is the NEXIAM platform itself — the functional system. Second is the research results from the paired TAM measures. Together, they prove whether NEXIAM outperforms the manual method with statistical backing."

**DEFENSE ANCHOR:** This is now simpler and harder to attack. There are no layers to defend. The IPO is a standard descriptive-developmental framework. The panel cannot ask "why these six layers" because you are not presenting layers — you are presenting an input-process-output flow that matches established methodology.

*(David passes the floor to Jaypee)*

---

### **SLIDE 6: Scope and Limitation**

**[Presenter: Jaypee]**

**Jaypee:** "Thank you, David. Let us be very precise about our project boundaries."

*(Click 1)* "In scope: We cover the admission testing workflow only, operating across seven user roles. We target 95% or better OMR scoring accuracy, with our AI scheduling, Chatbot, and K-Means dashboard fully active."

*(Click 2)* "Our limitations: We do not touch enrollment or tuition records. The system is cloud-hosted overall, but the proctoring interface alone keeps offline resilience via service-worker caching, because exam venues lose connectivity. Finally, we claim application-layer data protection aligned with RA 10173, but we do not claim formal NPC certification."

**DEFENSE ANCHOR:** You just confidently addressed the offline/cloud nuance and the privacy claims before the panel could attack them.

---

### **SLIDE 7: Beneficiaries**

**[Presenter: David]**

**David:** "This scoped architecture directly benefits three groups."

*(Click 1)* "The Institution gains a faster, legally compliant admission process."

*(Click 2)* "Applicants gain a 24/7 portal, closing the equity gap of repeat physical trips to campus."

*(Click 3)* "And the Research Community gains a deployable blueprint for regional state colleges."

*(David passes the floor to Jaypee)*

---

### **SLIDE 8: Research Design & Software Model**

**[Presenter: Jaypee]**

**Jaypee:** "For our software model, we utilized AI-Augmented Rapid Application Development."

*(Gesture to the stats under the header)* "If anyone wonders whether 'AI-augmented' is a novelty, Osmani's 2026 Google whitepaper confirms that 85% of professional developers now use AI coding agents regularly, and 41% of new code industry-wide is AI-generated. We are simply building at the modern industry standard."

*(Click through the 4 phases)* "We map requirements, design user interfaces, rapidly construct the codebase, and cutover for evaluation."

*(Click to show loop)* "Crucially, AI generates code, but it is routed through a human 'Review and Integrate' step. If it fails, it loops back. AI writes; a human decides what ships."

---

### **SLIDE 9: Project Plan**

**[Presenter: Jaypee]**

**Jaypee:** *(No clicks needed, chart builds itself)* "This is our four-phase timeline running from June through September 2026. We are currently on track with Requirements Planning and moving into User Design."

*(Jaypee passes the floor to Christine)*

---

### **SLIDE 10: Population and Locale**

**[Presenter: Christine]**

**Christine:** "Thank you, Jaypee. To evaluate the system in September, we will use 47 evaluators."

*(Click 1)* "This breaks down into students, program heads, registrar staff, guidance, and IT experts."

*(Click 2)* "Because our evaluation happens in September — which has no active admission cycle — these are proxy evaluators simulating real-world scenarios."

*(Click 3)* "Notice we split the 47 into two distinct panels: An Operational Panel of 46 simulates the admission use cases, while a Technical Panel of 1 IT expert specifically evaluates backend architecture and security. Different questions require different evaluators."

---

### **SLIDE 11: Research Instrument (TAM)**

**[Presenter: Christine]**

**Christine:** "We measure their acceptance using Davis's Technology Acceptance Model across four constructs: Usefulness, Ease of Use, Attitude, and Intention."

*(Click 1)* "Our 16-item instrument features role-adapted phrasing, meaning an applicant and a Registrar answer questions tailored to their specific interface."

*(Click 2)* "Crucially, we administer this on a 7-point Likert scale as a Pre-Test *and* a Post-Test. We are measuring the actual *shift* in perception after using NEXIAM, not just a single snapshot."

---

### **SLIDE 12: Data Analysis Techniques**

**[Presenter: Christine]**

**Christine:** "Finally, our data analysis maps directly back to our specific objectives."

*(Click 1)* "SO1 uses thematic analysis to extract pain points from interviews."

*(Click 2)* "SO2 uses iterative validation with staff to confirm system requirements."

*(Click 3)* "SO3 uses pooled TAM means to check user acceptance."

*(Click 4)* "SO4 starts with a Shapiro-Wilk normality check. If the data is normal, we use a paired t-test; if not, a Wilcoxon signed-rank test. The data decides the statistical path, not us."

---

### **SLIDE 13: Closing**

**[Presenter: David]**

**David:** "That concludes our proposal for NEXIAM. Thank you for your time and expertise. We are now ready for your questions."

---

### **Time Budget**

| Slide | Presenter | Time |
|-------|-----------|------|
| Slide 1: Title | David | — |
| Slide 2: Problem — Global | **David** | **~1:10** |
| Slide 3: Problem — Local | **David** | **~1:10** |
| Slide 4: Objectives | **David** | **~0:30** |
| Slide 5: IPO Framework | **David** | **~0:35** |
| Slide 6: Scope/Limitation | Jaypee | ~1:15 |
| Slide 7: Beneficiaries | **David** | **~0:40** |
| Slide 8: Research Design | Jaypee | ~1:15 |
| Slide 9: Project Plan | Jaypee | ~0:30 |
| Slide 10: Population | Christine | ~1:00 |
| Slide 11: Instrument | Christine | ~0:45 |
| Slide 12: Data Analysis | Christine | ~1:00 |
| Slide 13: Closing | David | ~0:10 |
| **Total** | | **~9:10** ✅ |
| Buffer vs 10-min limit | | **~0:50** |

**David's total: ~4:15** (was ~5:05 in v3 — saving ~50 seconds)
**Total clicks: ~32** (was ~40 in v3 — simpler flow)

---

### **Next Steps**

1. Practice with a stopwatch — the expanded background slides need slightly faster delivery than the old version.
2. Once comfortable, drill the Q&A Defense Bank (v4) for every possible panel question.
3. Edit NEXIAM.odp: merge old slides 5+6 into one IPO slide, update slides 2-3 text.
