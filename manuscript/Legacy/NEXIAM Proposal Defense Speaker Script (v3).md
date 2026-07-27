# **NEXIAM Proposal Defense Speaker Script (v3)**

**Target Time:** \~9 minutes (leaves a 1-minute buffer for transitions) **Speakers:** David (Slides 1–6), Jaypee (Slides 7–10), Christine (Slides 11–13), David (Slide 14\)

### **SLIDE 1: Title Slide (No Clicks)**

**\[Presenter: David\]** *(Leave this on screen while the panel gets settled. After introductions, immediately take the floor.)* **David:** "Good morning, respected members of the panel. We are presenting NEXIAM: A Role-Based College Admission Testing System. Let’s start immediately with why this system is necessary." *(Transition to Slide 2\)*

### **SLIDE 2: The Problem — Global & National**

**\[Presenter: David\] David: "**Manual, paper-based admission testing isn't just slow. French and colleagues' 2024 review found high-stakes exams carry real benefits, but those are routinely outweighed by anxiety and equity concerns — and that's before operational strain even enters the picture." (Click 1\) "Globally, higher education is already shifting to AI-assisted, cloud-based admission platforms. Patil's team found large-scale exam management strains record integrity and verification at the source, and Sánchez-Mendiola's group tracked that strain at over six hundred thousand examinees in one cycle — this resembles a scaling problem." (Click 2\) "Nationally, RA 10931—the Free Higher Education Act—pushed public college applications up. Liu's team and Ruffalo Noel Levitz both point to the same consequence: slower processing distorts enrollment yield, and applicants likely commit to the better option. The Esquivels already saw this forcing Philippine institutions toward predictive modeling just to estimate class sizes." (Click 3\) "And RA 10173—the Data Privacy Act—makes protecting applicant data a legal duty, and the regulatory floor everything we’ll build has to stand for.

**DEFENSE ANCHOR:** You are establishing that this project isn't just for convenience; it is a response to legal mandates and national volume shifts.

### **SLIDE 3: The Problem — At ISPSC Tagudin**

**\[Presenter: David\]** **David:** "At ISPSC Tagudin specifically, we process seven hundred to a thousand applicants every year—entirely on paper. Here is the operational cost of that:" *(Click 1\)* "Hand-scoring takes five days, sometimes up to two full weeks." *(Click 2\)* "Result announcements are posted on a Facebook page, forcing applicants to monitor or inquire information during office hours only." *(Click 3\)* "Inter-office coordination runs on unstructured text messages. There is no shared system or visibility between the Guidance and Registrar offices, and also in communication with the different program heads" *(Click 4\)* "(Click 4\) "And this isn't even a research gap anymore — the pieces already exist here. Yukee's and Ballesteros's teams both validated K-Means profiling on this campus's own data, however these stayed retroactive and disconnected from the admission pipeline. Angala's and Cenezan's systems digitized records and evaluation, each in isolation. And another important piece is Eustaquio's 2023 registration system, which digitized operations strictly for already-enrolled students. Therefore our campus has the precedent, data, and a track record — what it doesn't have is one role-governed system tying it together. That's the gap NEXIAM sits in.”

**SLIDE 4: Objectives of the Study**

**\[Presenter: David\]** **David:** "Our general objective is to develop NEXIAM—a web-based, role-governed platform. Four specific objectives sit underneath this as a dependency chain." *(Click 1\)* "SO1: Analyze the existing process and pinpoint gaps." *(Click 2\)* "SO2: Determine the exact system requirements those gaps demand." *(Click 3\)* "SO3: Evaluate user acceptance using the Technology Acceptance Model." *(Click 4\)* "SO4: Test statistically whether NEXIAM performs measurably better than the manual process."

### **SLIDE 5: Conceptual Framework**

**\[Presenter: David\]** **David:** "Here is the architecture we built to satisfy those objectives." *(Click 1\)* "Everything sits on a Foundational Layer: digitizing configurations, applicant tracking, and proctor-logged marking into persistent records." *(Click 2\)* "Layer 1 is Role-Based Access Control (RBAC). It decides what Guidance, Registrar, or Applicants can see and do." *(Click 3\)* "Layer 2: Computer-vision OMR automates the checking of bubble sheets." *(Click 4\)* "Layer 3: K-Means Clustering surfaces descriptive aptitude patterns. To be clear, this does *not* make decisions for the counselor; it only visualizes the pattern." *(Click 5\)* "Layer 4: A RAG Chatbot answers applicant FAQs using only our institution’s data." *(Click 6\)* "Layer 5: AI-assisted scheduling proposes exam slots, but it is strictly *human-gated*—a person must approve it." *(Click 7\)* "Layer 6: HMAC-SHA256 logging keeps a tamper-evident audit trail."

### **SLIDE 6: IPO Diagram Mapped**

**\[Presenter: David\]** **David:** "This table maps those layers into an Input-Process-Output flow. Notice the 'Layer Category' column on the right. Seven of these eight output rows carry a 'Layer 1' tag. Access control isn't a feature we bolted on at the end; it is enforced end-to-end across nearly every output the system produces." *(David passes the floor to Jaypee)*

### **SLIDE 7: Scope and Limitation (CRITICAL FIX)**

**\[Presenter: Jaypee\]** **Jaypee:** "Thank you, David. Let’s be very precise about our project boundaries." *(Click 1\)* "In scope: We cover the admission testing workflow only, operating across seven user roles. We target 95% or better OMR scoring accuracy, with our AI scheduling, Chatbot, and K-Means dashboard fully active." *(Click 2\)* "Our limitations: We do not touch enrollment or tuition records. The system is cloud-hosted overall, *but* the proctoring interface alone keeps offline resilience via service-worker caching, because we know exam venues lose connectivity. Finally, we claim application-layer data protection aligned with RA 10173, but we do not claim formal NPC certification."

**DEFENSE ANCHOR:** You just confidently addressed the offline/cloud nuance and the privacy claims before the panel could attack them.

### **SLIDE 8: Beneficiaries**

**\[Presenter: Jaypee\]** **Jaypee:** "This scoped architecture directly benefits three groups." *(Click 1\)* "The Institution gains a faster, legally compliant admission process." *(Click 2\)* "Applicants gain a 24/7 portal, closing the equity gap of repeat physical trips to campus." *(Click 3\)* "And the Research Community gains a deployable blueprint for regional state colleges."

### **SLIDE 9: Research Design & Software Model (NEW AI STATS)**

**\[Presenter: Jaypee\]** **Jaypee:** "For our software model, we utilized AI-Augmented Rapid Application Development (RAD)." *(Gesture to the stats under the header)* "If anyone wonders whether 'AI-augmented' is a novelty, Osmani's 2026 Google whitepaper confirms that 85% of professional developers now use AI coding agents regularly, and 41% of new code industry-wide is AI-generated. We are simply building at the modern industry standard." *(Click through the 4 phases)* "We map requirements, design user interfaces, rapidly construct the codebase, and cutover for evaluation." *(Click to show loop)* "Crucially, AI generates code, but it is routed through a human 'Review & Integrate' step. If it fails, it loops back. AI writes; a human decides what ships."

### **SLIDE 10: Project Plan**

**\[Presenter: Jaypee\]** **Jaypee:** *(No clicks needed, chart builds itself)* "This is our four-phase timeline running from June through September 2026\. We are currently on track with Requirements Planning and moving into User Design." *(Jaypee passes the floor to Christine)*

### **SLIDE 11: Population and Locale (NEW PANEL SPLIT)**

**\[Presenter: Christine\]** **Christine:** "Thank you, Jaypee. To evaluate the system in September, we will use 49 purposive evaluators." *(Click 1\)* "This breaks down into students, program heads, registrar staff, guidance, and IT experts." *(Click 2\)* "Because our evaluation happens in September—which has no active admission cycle—these are proxy evaluators simulating real-world scenarios." *(Click 3\)* "Notice we split the 49 into two distinct panels: An Operational Panel of 46 simulates the day-to-day workflow, while a Technical Panel of 3 IT experts specifically evaluates backend architecture and security. Different questions require different evaluators."

### **SLIDE 12: Research Instrument (TAM)**

**\[Presenter: Christine\]** **Christine:** "We measure their acceptance using Davis’s Technology Acceptance Model across four constructs: Usefulness, Ease of Use, Attitude, and Intention." *(Click 1\)* "Our 16-item instrument features role-adapted phrasing, meaning an applicant and a Registrar answer questions tailored to their specific interface." *(Click 2\)* "Crucially, we administer this on a 7-point Likert scale as a Pre-Test *and* a Post-Test. We are measuring the actual *shift* in perception after using NEXIAM, not just a single snapshot."

### **SLIDE 13: Data Analysis Techniques**

**\[Presenter: Christine\]** **Christine:** "Finally, our data analysis maps directly back to our specific objectives." *(Click 1\)* "SO1 uses thematic analysis to extract pain points from interviews." *(Click 2\)* "SO2 uses iterative validation with staff to confirm system requirements." *(Click 3\)* "SO3 uses pooled TAM means to check user acceptance." *(Click 4\)* "SO4 starts with a Shapiro-Wilk normality check. If the data is normal, we use a paired t-test; if not, a Wilcoxon signed-rank test. The data decides the statistical path, not us."

### **SLIDE 14: Closing**

**\[Presenter: David\]** **David:** "That concludes our proposal for NEXIAM. Thank you for your time and expertise. We are now ready for your questions."

### **Next Steps:**

Practice reading this aloud with a stopwatch. If you are naturally speaking at a steady pace, this will clock in right around 8.5 to 9 minutes, giving you a perfect buffer.

Once you are comfortable with the flow of this script, tell me, and **I will generate the Full Defense Q\&A Bank** based on the 23 Critical/Moderate vulnerabilities we audited earlier, complete with role-played, bulletproof responses for David, Jaypee, and Christine\!

