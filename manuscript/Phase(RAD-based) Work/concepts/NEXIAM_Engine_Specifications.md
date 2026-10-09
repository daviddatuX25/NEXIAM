# NEXIAM Engine Specifications — Two Initial Models

**Date:** 2026-07-14
**Purpose:** Precise specification for the two admission support engines deployed with NEXIAM at launch. These are reference documents — what each engine does, what it needs, what it outputs, and how the dashboard renders it.

---

## Engine 1: Yukee Cluster Engine — Aptitude Profiling

### Summary

Adopts K-Means cluster definitions from Yukee et al. (2025), validated on ISPSC's own ICAT admission data. Groups applicants into aptitude clusters (K=4). The cluster assignment is presented alongside a deterministic program mapping table validated by Program Heads.

### Manifest

```json
{
  "id": "yukee_cluster_v1",
  "name": "Yukee Cluster Engine v1",
  "version": "1.0.0",
  "author": "Yukee et al. (2025) — ISPSC Capstone Research; integrated by NEXIAM Team",
  "description": "K-Means aptitude clustering on ICAT component percentile ranks. Groups applicants into four validated cluster profiles for counselor-aided course placement.",
  "type": "clustering",
  "signal": "aptitude",
  "status": "active",

  "inputs": {
    "math_percentile": {
      "type": "float",
      "label": "Math sub-test percentile rank",
      "required": true,
      "range": [0, 100],
      "source": "exam_scores.icat.math"
    },
    "logic_percentile": {
      "type": "float",
      "label": "Logic sub-test percentile rank",
      "required": true,
      "range": [0, 100],
      "source": "exam_scores.icat.logic"
    },
    "reading_percentile": {
      "type": "float",
      "label": "Reading Comprehension percentile rank",
      "required": true,
      "range": [0, 100],
      "source": "exam_scores.icat.reading"
    },
    "subject_percentile": {
      "type": "float",
      "label": "Subject-specific sub-test percentile rank",
      "required": true,
      "range": [0, 100],
      "source": "exam_scores.icat.subject"
    }
  },

  "outputs": {
    "type": "clustering",
    "visualization": "scatter-plot",
    "label": "Aptitude Cluster Assignment",
    "fields": ["cluster_label", "dominant_aptitude", "confidence", "recommended_programs"],
    "clusters": [
      {
        "label": "Math-Dominant",
        "description": "High math percentile relative to other sub-tests",
        "programs": ["BS Information Technology", "BS Computer Engineering", "BS Mathematics"]
      },
      {
        "label": "Language-Dominant",
        "description": "High reading comprehension percentile",
        "programs": ["BSEd English", "AB Political Science", "AB Communication"]
      },
      {
        "label": "Spatial-Dominant",
        "description": "High logic and abstract reasoning",
        "programs": ["BS Civil Engineering", "BS Architecture"]
      },
      {
        "label": "Mixed / High-Across",
        "description": "No single dominant dimension; strong across multiple areas",
        "programs": ["BS Information Technology", "BS Computer Engineering", "BS Entrepreneurship"]
      }
    ]
  },

  "inference": {
    "protocol": "local",
    "handler": "YukeeClusterEngine",
    "description": "Stateless rule-matrix matching. No external API call. Runs inside Laravel process."
  },

  "ui": {
    "panel_title": "Aptitude Profile",
    "panel_icon": "brain-outline",
    "color": "#E67E22",
    "show_confidence": true,
    "show_recommended_programs": true,
    "show_cluster_description": true
  }
}
```

### How It Works

1. OMR engine scores the ICAT answer sheet → produces 4 sub-test percentile ranks
2. Percentile ranks are passed to Yukee Cluster Engine
3. Engine matches the rank pattern against validated cluster definitions (static rule matrix)
4. Returns: cluster label, dominant aptitude, confidence score, recommended programs (from mapping table)
5. Dashboard renders the output as a scatter plot + accompanying program list

### Cluster-to-Program Mapping Table

This mapping is **deterministic** — defined in a reference table, not computed by the model. It must be validated by Program Heads during User Design (see For Data Gathering.md).

| Cluster | Programs (ISPSC offerings) |
|---|---|
| Math-Dominant | BSIT, BSCE, BS Math |
| Language-Dominant | BSEd English, AB PolSci, AB Communication |
| Spatial-Dominant | BSCE, BS Architecture |
| Mixed / High-Across | BSIT, BSCE, BS Entrepreneurship |

### Data Gathering Dependencies

- ICAT sub-test structure confirmation (Guidance Office)
- Cluster-to-program mapping validation (Program Heads, n=11)
- Yukee centroid coordinates (optional; researcher contact pending)

### Source & License

- **Source:** Yukee et al. (2025) — ISPSC-published capstone research
- **License:** Published academic research, used with attribution
- **System status:** Handed to ISPSC Guidance Office; not in active production use

---

## Engine 2: BGE Interest Engine — Preference Matching

### Summary

Adopts the pre-trained `BAAI/bge-base-en-v1.5` sentence embedding model from Hugging Face (MIT license, peer-reviewed). Converts ISPSC program descriptions into 768-dimensional embedding vectors. At inference, encodes the applicant's stated course preferences and computes cosine similarity against all program embeddings. Returns a ranked list with similarity scores.

### Manifest

```json
{
  "id": "bge_interest_v1",
  "name": "BGE Interest Engine v1",
  "version": "1.0.0",
  "author": "BAAI (Beijing Academy of AI) — bge-base-en-v1.5; integrated by NEXIAM Team",
  "description": "Pre-trained sentence embedding model that matches applicant stated preferences to ISPSC program descriptions via cosine similarity ranking.",
  "type": "ranking",
  "signal": "interest",
  "status": "active",

  "inputs": {
    "applicant_preferences": {
      "type": "text",
      "label": "Applicant stated course or program preferences",
      "required": true,
      "source": "applicant.profile.preferences",
      "note": "Free-text from applicant portal during registration"
    }
  },

  "outputs": {
    "type": "ranking",
    "visualization": "bar-chart-ranked",
    "label": "Program Interest Match",
    "fields": ["program_name", "similarity_score", "rank"],
    "top_n": 5
  },

  "inference": {
    "protocol": "http",
    "method": "POST",
    "endpoint": "http://models.dxtechph.online/bge-v1/similarity",
    "timeout_ms": 5000,
    "retry": 1,
    "description": "Calls a lightweight Python/FastAPI microservice running sentence-transformers with the BGE model loaded. Returns ranked program similarity scores."
  },

  "precomputed": {
    "description": "Program description embeddings are computed once at deployment and stored in the database. Inference only encodes the applicant query and computes similarity — no re-embedding of program corpus.",
    "corpus_table": "program_embeddings",
    "embedding_column": "vector",
    "dimensions": 768,
    "model": "BAAI/bge-base-en-v1.5",
    "last_recomputed": null
  },

  "ui": {
    "panel_title": "Interest Match",
    "panel_icon": "heart-outline",
    "color": "#3498DB",
    "sort_by": "similarity_score",
    "sort_direction": "descending",
    "show_confidence": true
  }
}
```

### How It Works

1. **Pre-computation (once at deploy/setup):** ISPSC program descriptions are embedded via BGE model → stored in `program_embeddings` table
2. **At inference:** Applicant's free-text preferences ("gusto ko mag IT or Engineering") are encoded via the same BGE model
3. Cosine similarity is computed between the applicant vector and all program vectors
4. Top-N results returned, sorted by similarity score
5. Dashboard renders as a horizontal bar chart

### Why BGE Specifically

| Property | Value |
|---|---|
| Model | `BAAI/bge-base-en-v1.5` |
| Dimensions | 768 |
| License | MIT (free use, no restrictions) |
| Research | Xiao et al. (2023) — published, 5 peer-reviewed papers |
| Hugging Face | 450+ likes on HF Hub |
| Task | Sentence similarity / semantic search |
| Language | English (ISPSC programs are in English) |
| Why not MiniLM? | BGE is specifically optimized for retrieval/similarity tasks; MiniLM is general-purpose |

### What the Similarity Score Means

- 0.0–0.3: Weak or no match to stated preference
- 0.3–0.6: Moderate match — some overlap in description
- 0.6–0.8: Strong match — applicant's wording aligns closely with program description
- 0.8–1.0: Very strong match — near-exact semantic alignment

These are **similarity scores**, not probabilities. They measure how closely the applicant's stated interest aligns with the program's official description. The counselor interprets them alongside the aptitude cluster.

### Data Gathering Dependencies

- ISPSC program descriptions (official text from prospectus/website/CHED docs)
- Program descriptions must include: program name, brief description, career outcomes, core competencies

### Source & License

- **Source:** `BAAI/bge-base-en-v1.5` from Hugging Face
- **License:** MIT License — free for any use including academic
- **Citation:** Xiao, S., Liu, Z., Zhang, P., & Muennighoff, N. (2023). C-Pack: Packaged Resources To Advance General Chinese Embedding. arXiv:2309.07597.

---

## Dashboard Layout (Both Engines Active)

```
┌──────────────────────────────────────────────────────────────┐
│ TRIAGE PROFILE — Applicant: Juan Dela Cruz                   │
│                                                              │
│ ┌───────────────────────┐  ┌──────────────────────────────┐ │
│ │ APTITUDE PROFILE      │  │ INTEREST MATCH               │ │
│ │ Engine: Yukee v1      │  │ Engine: BGE v1               │ │
│ │ Type: Clustering      │  │ Type: Ranking                │ │
│ │                       │  │                              │ │
│ │ [SCATTER PLOT]        │  │ BSIT        ██████████ 0.92 │ │
│ │   •  •    •           │  │ BSCE        ████████░░ 0.87 │ │
│ │  •  ★  •   •          │  │ BSENT       ██████░░░░ 0.65 │ │
│ │   •    •  •           │  │ BSEd Eng    ██░░░░░░░░ 0.22 │ │
│ │                       │  │ AB PolSci   █░░░░░░░░░ 0.15 │ │
│ │ Cluster: Math-Dominant│  │                              │ │
│ │ Confidence: 0.89      │  │ Top match: BSIT (0.92)      │ │
│ │                       │  │                              │ │
│ │ Recommended Programs: │  │                              │ │
│ │  • BSIT               │  │                              │ │
│ │  • BSCE               │  │                              │ │
│ │  • BS Mathematics     │  │                              │ │
│ └───────────────────────┘  └──────────────────────────────┘ │
│                                                              │
│ COUNSELOR ACTION:                                            │
│  [Approve Placement: BSIT]  [Modify]  [Override]  [Defer]    │
│                                                              │
│ AUDIT: Decision logged — Counselor: C. Lopez —               │
│ Engines: Yukee v1 + BGE v1 — Timestamp: 2026-09-15 10:32   │
└──────────────────────────────────────────────────────────────┘
```

**Key rule:** The two panels are always separate. The counselor sees both signals and makes the call. The system never merges them into a single "AI says BSIT" recommendation.

---

## Comparison Table

| | Yukee Cluster Engine | BGE Interest Engine |
|---|---|---|
| **Signal** | Aptitude ("can they handle it?") | Interest ("do they want it?") |
| **Model type** | Clustering (K-Means, K=4) | Ranking (sentence embedding + cosine similarity) |
| **Source** | Yukee et al. (2025) — ISPSC research | BAAI — Hugging Face, MIT license |
| **Input** | 4 ICAT sub-test percentile ranks | Applicant free-text preferences |
| **Output** | Cluster label + recommended programs | Top-N ranked program list with similarity scores |
| **Inference** | Local (stateless rule matrix) | HTTP microservice (Python + sentence-transformers) |
| **Training required** | None (adopted as-is) | None (pre-trained, zero-shot) |
| **Dependency** | Yukee cluster definitions | BGE model + FastAPI service |
| **Visualization** | Scatter plot | Horizontal bar chart |
| **Dashboard panel** | Left — "Aptitude Profile" | Right — "Interest Match" |
| **License** | Published research (citation) | MIT |

---

*These two engine specifications define what ships at NEXIAM launch. Both are adopted, zero-training models. The Model Registry (see NEXIAM_Registry_Architecture.md) supports adding more engines without code changes.*
