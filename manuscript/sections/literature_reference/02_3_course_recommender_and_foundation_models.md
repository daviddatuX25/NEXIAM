# Chapter 2: Review of Related Literature and Studies

## 2.3 Course Recommendation Systems and Tabular Foundation Models

The application of machine learning to student academic advising and course routing has evolved across three distinct algorithmic paradigms: unsupervised clustering, classical supervised classification, and pre-trained tabular foundation modeling. Understanding this transition is essential for establishing the technical validity of the course triage architecture deployed in NEXIAM.

### Unsupervised Clustering in Educational Profiling
Unsupervised clustering algorithms, particularly K-Means, represent the historical baseline for educational data mining. In student preference modeling, Tan (2025) demonstrated that K-Means optimization could effectively segment students based on cognitive test profiles, grouping applicants into distinct aptitude clusters. This approach was mirrored directly in local institutional research at ISPSC Tagudin Campus. Yukee et al. (2025) applied K-Means clustering to historical entrance examination (ICAT) scores across six cognitive sub-tests (General Ability, Verbal Aptitude, Numerical Aptitude, Spatial Aptitude, Perceptual Aptitude, and Manual Dexterity), successfully identifying latent competency patterns within the student body. Similarly, Ballesteros et al. (2025) grouped incoming freshmen at the same campus using Grade 12 General Weighted Average and socioeconomic background indicators.

While unsupervised clustering confirms that student performance profiles naturally group into identifiable patterns, it suffers from two major operational limitations when applied to live college admissions:
1. *Absence of Program Recommendation Semantics:* Clustering algorithms are inherently descriptive rather than prescriptive. K-Means identifies that a student belongs to a specific cluster (for example, a group characterized by high spatial and numerical marks), but it cannot directly compute a compatibility index for a specific degree program (such as Bachelor of Science in Information Technology) without arbitrary, secondary heuristic lookup tables.
2. *Sensitivity to Data Incompleteness:* K-Means operates on fixed Euclidean distance matrices. When an applicant lacks specific secondary records, the algorithm fails or requires synthetic mean imputation, which distorts cluster assignments and introduces artificial bias.

### Supervised Classification and the Small-Sample Dilemma
To achieve direct program recommendations, educational technologists frequently turn to supervised machine learning models, including random forests, gradient boosted trees, and support vector machines (Mittal & Malhotra, 2026). Supervised classifiers learn mapping functions from historical student profiles to degree completion outcomes. 

However, deploying supervised classifiers in specialized or regional college campuses encounters what educational data scientists describe as the small-sample dilemma:
* Regional campuses like ISPSC Tagudin process approximately 700 applicants annually across eleven distinct degree programs. Partitioned across programs, historical training subsets often contain fewer than fifty records per category.
* Training complex supervised models on such limited datasets leads to severe statistical overfitting, extreme sensitivity to historical noise, and the amplification of past demographic or regional admission biases (Van Busum & Fang, 2025). 
* Furthermore, secondary school curricula exhibit wide variability under the Philippine educational system: applicants from Academic STEM tracks submit specialized physics and calculus grades, whereas applicants from TVL or HUMSS tracks do not possess these specialized marks. Conventional supervised models cannot process rows with structural missingness without extensive and potentially distortive feature engineering.

### In-Context Learning via Tabular Prior-Data Fitted Networks (TabPFN)
To resolve the tension between descriptive clustering and overfitted supervised learning, recent developments in artificial intelligence have introduced tabular foundation models. Pioneered by Hollmann et al. (2025), Tabular Prior-data Fitted Networks (TabPFN) apply transformer-based in-context learning to structured tabular data.

Unlike traditional machine learning algorithms that require iterative gradient descent and parameter updates on local training sets, TabPFN is pre-trained across millions of synthetic dataset distributions generated through structural causal models. Consequently, the model operates purely in-context:
1. *Zero Local Training:* The network receives a support set of approved institutional curriculum benchmark exemplars alongside a query vector representing an incoming applicant's available scores.
2. *Bayesian Forward Inference:* In a single forward calculation lasting milliseconds, TabPFN computes the posterior predictive distribution, outputting calibrated compatibility probabilities across degree programs without altering any model weights locally.
3. *Native Missingness Handling:* TabPFN processes missing tabular cells directly through native attention mechanisms and missingness indicators. When an applicant lacks granular Grade 11 or 12 mathematics grades, the model evaluates the available sub-test percentiles and general GWA without assigning artificial penalties or requiring speculative data imputation.

### Human-in-the-Loop Triage Governance
In high-stakes educational decision-making, algorithmic predictions must never displace human professional judgment. As Lira et al. (2023) argued in Science Advances, algorithmic assessments in college admissions must be bounded by ethical governance and institutional oversight to prevent disparate impact.

In NEXIAM, TabPFN is integrated as an advisory triage instrument within the Guidance consultation workbench. The model outputs ranked course match percentages and surfaces the primary academic drivers (such as strong quantitative aptitude or strand alignment) that influenced the score. The system enforces an immutable audit trail: whenever a Guidance Counselor accepts, modifies, or overrides an advisory recommendation, the system records the counselor's attributable rationale. This design ensures that the foundation model provides cognitive decision support while preserving the legal and moral authority of the human counselor.
