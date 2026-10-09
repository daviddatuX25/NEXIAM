# Reference Paper: Van Deventer et al. (2024)

## Full Citation (APA 7th)

Van Deventer, H., Mills, M., & Evrard, A. (2024). From interests to insights: An LLM approach to course recommendations using natural language queries. *arXiv preprint*, arXiv:2412.19312. https://arxiv.org/abs/2412.19312

## Metadata

| Field | Value |
|---|---|
| **Title** | From Interests to Insights: An LLM Approach to Course Recommendations Using Natural Language Queries |
| **Authors** | Hugh Van Deventer, Mark Mills, August Evrard |
| **Year** | 2024 (submitted Dec 26, 2024; revised Dec 30, 2024) |
| **arXiv ID** | arXiv:2412.19312 (corrected from 2410.18330) |
| **Submitted** | 2024-12-26 |

## Abstract

> Most universities in the United States encourage their students to explore academic areas before declaring a major and to acquire academic breadth by satisfying a variety of requirements. Each term, students must choose among many thousands of offerings, spanning dozens of subject areas, a handful of courses to take. The curricular environment is also dynamic, and poor communication and search functions on campus can limit a student's ability to discover new courses of interest. To support both students and their advisers in such a setting, we explore a novel Large Language Model (LLM) course recommendation system that applies a Retrieval Augmented Generation (RAG) method to the corpus of course descriptions. The system first generates an 'ideal' course description based on the user's query. This description is converted into a search vector using embeddings, which is then used to find actual courses with similar content by comparing embedding similarities. We describe the method and assess the quality and fairness of some example prompts. Steps to deploy a pilot system on campus are discussed.

## Relevance to NEXIAM

- **Relevant**: LLM-based course recommendations using natural language queries — maps to both NEXIAM's RAG chatbot (NL queries about courses) and the aptitude clustering (course routing)
- **Feature overlap**: uses RAG method (same technique as NEXIAM's chatbot) but for course recommendations rather than policy Q&A
- **Methodology highlight**: generates an "ideal" course description from user query → embedding search → course matches — a two-stage RAG approach
- **NEXIAM distinction**: NEXIAM's approach is more conservative — K-Means clustering for aptitude grouping (traditional ML, explainable) + RAG chatbot for policy Q&A (not course recommendations); Van Deventer et al. use LLMs directly for course recommendations
- **Fairness assessment**: their inclusion of "quality and fairness" evaluation aligns with NEXIAM's human-in-the-loop approach

## Source Verification

- [x] Full abstract extracted from arXiv (meta tags)
- [x] arXiv ID corrected to 2412.19312
- [x] Preprint — not yet peer-reviewed
- [x] Authors verified: Hugh Van Deventer, Mark Mills, August Evrard
