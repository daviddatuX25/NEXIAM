# NEXIAM Admission Support Engine Architecture — Design Plan

**Date:** 2026-07-14
**Status:** Approved architecture plan — supersedes earlier ML Adapter Architecture draft
**Philosophy:** Adopt models, don't make them. The engine is the contribution, not the algorithms.

---

## 1. Core Principle: Model-Agnostic Engine Interface

NEXIAM defines a single interface that any admission support model — clustering, embedding, future neural network — can plug into. The system never calls a specific algorithm; it calls the interface.

```php
interface AdmissionSupportEngine
{
    public function analyze(array $features): AnalysisResult;
    public function metadata(): EngineMetadata;  // what features does this engine need?
    public function label(): string;              // display name for triage dashboard
}
```

`AnalysisResult` contains:
- **findings**: structured output (cluster label, similarity scores, ranked programs)
- **confidence**: per-finding confidence/strength metric
- **engine_version**: which engine produced this
- **features_used**: which input features were consumed (for transparency)

### Why this pattern

Same rationale as before (Laravel Mail, MLflow, AI Gateway) — business logic calls the interface, not the concrete engine. The counselor dashboard never knows whether the result came from a rule matrix, a trained centroid model, or a sentence embedding.

---

## 2. Initial Engine Lineup

### Engine 1: Yukee Cluster Engine (Baseline) — *Aptitude Profiling*

| Property | Value |
|---|---|
| Source | Adopted — Yukee et al. (2025) cluster definitions on ISPSC ICAT data |
| Method | Static rule-matrix matching K-Means validated centroids (K=4) |
| Features | ICAT component percentile ranks: Math, Logic, Reading Comprehension, Subject-specific |
| Output | Cluster label: Math-Dominant, Language-Dominant, Spatial-Dominant, Mixed/High |
| Status | Build now |
| License | N/A (published academic research, used with attribution) |

**Input → Cluster → Program Mapping:**
- Math-Dominant cluster → BSIT, BSCE, BS Mathematics, BSCS-aligned programs
- Language-Dominant cluster → BSEd English, AB Political Science, Communication programs
- Spatial-Dominant cluster → BSCE, BS Architecture, Engineering programs
- Mixed/High cluster → Broad match — applicant excels across domains

### Engine 2: BGE Interest Engine — *Preference Matching*

| Property | Value |
|---|---|
| Source | Adopted — BAAI/bge-base-en-v1.5 from Hugging Face |
| Method | Sentence embedding → cosine similarity against course descriptions |
| Features | Applicant's stated program preferences (text), course/program descriptions |
| Output | Ranked list of ISPSC programs with similarity scores (0.0–1.0) |
| Status | Plug-in (zero training, download and run inference) |
| License | MIT License — free use, no restrictions |

**How it works:**
1. Pre-compute 768-dim embeddings for each ISPSC program description (stored in DB, computed once at deploy/setup)
2. At inference: encode applicant's stated preferences or derived profile text
3. Compute cosine similarity against all program embeddings
4. Return top-N programs with similarity scores

### Engine 3: Custom Engine (Future Hook)

| Property | Value |
|---|---|
| Source | Future research or enriched data |
| Method | Any — pluggable via same interface |
| Examples | Trained K-Means from Yukee source code, neural network with GWA/dropout data, zero-shot classifier |
| Status | Architectural hook ready — no code changes needed to pipeline |

---

## 3. Architecture Diagram

```
┌──────────────────────────────────────────────────────────────┐
│                    Triage Dashboard (Svelte)                  │
│                                                              │
│  ┌─────────────────────┐  ┌──────────────────────────────┐  │
│  │ APTITUDE SIGNALS     │  │ INTEREST SIGNALS             │  │
│  │                      │  │                              │  │
│  │ Cluster: Math-Dom    │  │ BSIT   ████████████ 0.92    │  │
│  │ Engine: Yukee Baseln │  │ BSCE   ██████████░ 0.87    │  │
│  │                      │  │ Engine: BGE v1.5           │  │
│  │ → BSIT, BSCE, Math   │  │                              │  │
│  └─────────────────────┘  └──────────────────────────────┘  │
│                                                              │
│  [Approve Recommendation] [Modify] [Override] [Defer]       │
│  Counselor: [name] — logged to HMAC-SHA256 audit trail      │
└──────────────────────────────────────────────────────────────┘
         ▲                                   ▲
         │                                   │
         └─────────── Engine Registry ───────┘
                         │
         ┌───────────────┼───────────────┐
         ▼               ▼               ▼
  Yukee Cluster    BGE Interest     Custom Engine
  Engine           Engine           (future hook)

         │               │
         ▼               ▼
  ┌─────────────┐  ┌──────────────────┐
  │ ICAT Score  │  │ Course Desc.     │
  │ Percentiles │  │ Embeddings (pre- │
  │ (DB table)  │  │ computed, stored)│
  └─────────────┘  └──────────────────┘
```

**Key rule:** Engines NEVER combine their output into a single AI-generated recommendation. Each signal is presented separately. The counselor integrates them mentally.

---

## 4. Configuration & Extensibility

```php
// config/admission.php
'engines' => [
    'default' => ['yukee_cluster', 'bge_interest'],
    
    'yukee_cluster' => [
        'class' => YukeeClusterEngine::class,
        'enabled' => true,
        'features' => ['math_percentile', 'logic_percentile', 
                       'reading_percentile', 'subject_percentile'],
        'mapping' => [
            'math_dominant' => ['BSIT', 'BSCE', 'BS_MATH'],
            'language_dominant' => ['BSED_ENGLISH', 'AB_POLSCI'],
            'spatial_dominant' => ['BSCE', 'BS_ARCHI'],
            'mixed' => ['BSIT', 'BSCE', 'BSENT'],
        ],
    ],
    
    'bge_interest' => [
        'class' => BGEInterestEngine::class,
        'enabled' => true,
        'model' => 'BAAI/bge-base-en-v1.5',  // HF model ID
        'dimension' => 768,
    ],
    
    'custom_engine' => [
        'class' => CustomEngine::class,
        'enabled' => false,  // future
    ],
],
```

To add an engine: create a class implementing `AdmissionSupportEngine`, register it in config, flip `enabled => true`. No pipeline changes.

---

## 5. Future Extension to Chatbot (Optional)

The same engine registry can be exposed to the RAG chatbot — if future policy allows applicants to see program suggestions:

```php
// Chatbot tool call
$engines = config('admission.engines.default');
$results = [];
foreach ($engines as $name) {
    $engine = AdmissionEngineRegistry::get($name);
    $results[$name] = $engine->analyze($applicantFeatures);
}
// Format for chatbot: "Based on your exam profile, here are programs 
// that match your aptitude and stated interests. For final guidance, 
// please consult the Guidance Office."
```

**Safeguard:** Chatbot always appends disclaimer. Never presents as authoritative placement. This matches your existing Trap 24 defense.

---

## 6. What We Adopt vs. What We Build

| Component | Action | Source |
|---|---|---|
| K-Means cluster definitions (K=4, labels) | Adopt | Yukee et al. (2025) — ISPSC-published research |
| Cluster-to-program mapping table | Build | Deterministic — derived from ISPSC program prerequisites |
| BGE sentence embedding | Adopt | BAAI/bge-base-en-v1.5 — HF, MIT license, peer-reviewed |
| Course description embeddings | Compute once | Using adopted BGE model + ISPSC program documents |
| AdmissionSupportEngine interface | Build | Architecture contribution (the pipeline) |
| Engine Registry + config | Build | Architecture contribution (the pipeline) |
| Triage Dashboard (Svelte) | Build | Architecture contribution (the interface) |
| HMAC-SHA256 audit trail | Build | System feature (already planned) |
| Model combining/ensembling | **NOT built** | Out of scope — counselor does the integration |

---

## 7. Defense Positioning

| Panelist Attack | Defense |
|---|---|
| "You didn't train either model — is this a real contribution?" | The contribution is the governed pipeline that makes both models usable in one clinical dashboard. Adopting pre-existing, peer-reviewed components follows the panel's own guidance. |
| "BGE isn't validated for Philippine HEI admission" | BGE is a general-purpose sentence embedding model validated on MTEB benchmarks (Xiao et al., 2023). Semantic similarity between course descriptions and applicant preferences is a standard IR task, not domain-specific modeling. |
| "Why not combine the signals into one score?" | The Guidance Counselor is the expert integrator. Combining algorithmically would mask the separate evidential bases — aptitude vs. preference. Cherry et al. (2018) warn that opaque AI recommendations reduce counselor engagement. |
| "The program mapping table is arbitrary" | The mapping follows ISPSC's published program prerequisites and admission criteria. Each program's required competencies are documented in the CHED-approved curriculum. |

---

## 8. Implementation Order

1. **Rename/refactor interface** — `ClusterEngine` → `AdmissionSupportEngine`, `classify()` → `analyze()`
2. **Build Yukee Cluster Engine** — rule-matrix matching ICAT percentile ranks to cluster labels
3. **Define program mapping table** — cluster → ISPSC programs
4. **Integrate BGE library** — `pip install sentence-transformers` or via HF Inference API
5. **Compute course embeddings** — one-time batch encode of ISPSC program descriptions
6. **Build BGE Interest Engine** — implement `AdmissionSupportEngine` interface
7. **Update Triage Dashboard** — add Interest Signals panel alongside existing Aptitude panel
8. **Wire audit trail** — log engine name, input features, output for every analysis
9. **Update manuscript** — Scope, IPO, System Architecture sections

---

*This document supersedes `NEXIAM_ML_Adapter_Architecture.md` (July 3, 2026). The new architecture renames ClusterEngine → AdmissionSupportEngine, adds BGE Interest Engine as Engine 2, and formalizes the model-agnostic plugin pattern.*
