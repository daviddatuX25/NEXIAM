# NEXIAM ML Adapter Layer — Architecture & Terminology [SUPERSEDED]

**Date:** 2026-07-03
**Status:** SUPERSEDED by NEXIAM_Model_Engine_Architecture_Plan.md (2026-07-14)
**See:** `/home/user/Documents/NEXIAM - Capstone/NEXIAM_Model_Engine_Architecture_Plan.md`

Key changes in new plan:
- ClusterEngine → AdmissionSupportEngine (model-agnostic)
- Add BGE Interest Engine as Engine 2 (sentence embedding)
- Both engines visualized separately on Triage Dashboard
- Never combined into single AI recommendation

---

## Core Concept: Hot-Swappable Cluster Engines

Instead of hardcoding K-Means logic or running live sklearn training, NEXIAM defines a **ClusterEngine interface** that abstracts the clustering logic behind a single `classify()` method. The system ships with one engine and is designed to accept others.

### Interface (PHP)

```php
interface ClusterEngine
{
    public function classify(array $features): ClusterResult;
    public function metadata(): EngineMetadata;  // what features needed?
    public function label(): string;              // display name for dashboard
}
```

### Engines

| Engine | When | Source | Status |
|--------|------|--------|--------|
| **Yukee Baseline Engine** | Default at launch | Static rule-matching matrix using Yukee et al. (2025) validated cluster definitions (K=4, Language/Math/Spatial/Mixed profiles on ICAT percentile ranks) | Build now |
| **Yukee Trained Engine** | Future — after source code obtained | Loads actual trained K-Means centroids/weights from Yukee's implementation | Architectural hook ready |
| **Custom Engine** | Future research | Plug in any model — enriched features (GWA, dropout, course pref), different K, different algorithm | Hook ready |

### Configuration (`config/clustering.php`)

```php
'default_engine' => 'yukee_baseline',
'engines' => [
    'yukee_baseline' => [...],
    'yukee_trained' => [...],
],
```

Flip the `default_engine` value. No other code changes needed.

---

## Pattern Precedents (for defense framing)

This architecture is not novel — it follows established patterns:

1. **Laravel Mail system** — `Mail::send()` works the same regardless of `driver => smtp|log|mailgun`. Same business code, different engine.
2. **MLflow Model Registry** — Register a model, tag a version, serve via API. Client calls `predict()` and never knows which algorithm is behind it.
3. **AI Gateway pattern** — One API call, routes to OpenAI/Claude/local. Model is hot-swappable by config.

Defense line: *"It works like Laravel's mail system. The triage dashboard calls classify() the same way whether the engine is a rule matrix, a trained K-Means model, or a future neural network."*

---

## Terminology Standardization

| Old / Current Term | New Term | Rationale |
|---|---|---|
| Course Recommender / Recommender Module | **Triage Dashboard** | "Triage" is more accurate — the system sorts and flags, it doesn't just recommend courses |
| K-Means module | **Cluster Engine** | "Engine" implies swappable; "module" implies fixed. The architecture is the point |
| Static rule matrix / hardcoded rules | **Yukee Baseline Engine** | Gives it identity. Sounds intentional and research-grounded, not "we hardcoded it" |
| ML-Assisted Course Triage and Recommender Module | **ML Adapter Layer** | Describes the architecture abstraction, not a single feature |
| "Limitation: no live training" | **"Architecture: engine-swappable, model-consumption ready"** | Reframes the boundary as a deliberate design decision |
| K-Means Aptitude Insights Dashboard | **Triage Dashboard (Engine-Aware)** | Dashboard shows which engine produced the recommendation |

---

## Dashboard Design (Engine-Aware)

The triage dashboard is the counselor's decision-support view. It is engine-aware — the counselor always knows where the recommendation comes from.

```
┌─────────────────────────────────────────────────────────────┐
│ TRIAGE PROFILE          Engine: Yukee Baseline (v2025)     │
├──────────────────────────────────┬──────────────────────────┤
│ Aptitude Cluster:                │ Quota Status             │
│  ■ Math-Dominant                 │                          │
│  (highest: Math percentile)      │ BSIT   — 🟢 42/60 seats │
│                                  │ BSCE   — 🟡 55/60 seats │
│ Support Flags:                   │ BSENT  — 🟢 30/40 seats │
│  ⚠ Financial aid candidate       │                          │
│                                  │ RECOMMENDATION           │
│ Previous Cluster (if override):  │                          │
│  (none)                          │ Primary: BSIT            │
│                                  │ Alt: BSENT               │
│ [Override ▼]                     │                          │
│                                  │ [Approve] [Modify] [Defer]│
├──────────────────────────────────┴──────────────────────────┤
│ AUDIT LOG: Decision recorded — Counselor: [name] —          │
│ Engine: Yukee Baseline — Timestamp: 2026-07-03 14:30       │
└─────────────────────────────────────────────────────────────┘
```

Key behaviors:
- **Override**: Counselor can manually override cluster assignment. Logged with reason.
- **Approve**: Locks recommendation into consultation summary. Generates audit log entry.
- **Modify**: Counselor reorders course suggestions before approving.
- **Defer**: Marks for follow-up consultation — no recommendation locked.
- **Engine label**: Tells counselor which model produced the recommendation. If engine switches, label changes.

---

## What This Enables for the Defense

### Q: "Did you just hardcode Yukee's numbers?"

> "No. We built an ML Adapter Layer — a model-agnostic clustering interface. The default engine uses Yukee's campus-validated cluster definitions because those are the institution's own published parameters, peer-reviewed, and specific to ISPSC's ICAT instruments. The architecture supports plugging in their actual trained model, or any future model trained on enriched features, without touching the OMR pipeline, the dashboard, or the audit trail. The contribution is the operational pipeline AND the architectural readiness for model evolution."

### Q: "Why not run live K-Means training?"

> "The architecture supports it — a PreTrainedEngine can load centroid weights from the database. But live training on a production web server introduces validation, reproducibility, and statistical review requirements that are separate research questions. We scoped the system for model consumption, not model training. The adapter is designed so future researchers can add training capabilities with zero refactoring of the existing pipeline."

### Q: "What's different from Yukee's system?"

> "Yukee built a standalone clustering tool that runs on archived data. NEXIAM places the same validated clustering into a live operational pipeline — connected to OMR scoring, RBAC governance, quota visibility, audit trails, and a decision-support dashboard used at the point of consultation. We don't claim a better algorithm. We claim a complete operational integration that Yukee's system, by design, did not provide."

---

## Implementation Plan

1. **Define interface** — `ClusterEngine` with `classify()`, `metadata()`, `label()` (1 file, ~30 lines)
2. **Build Yukee Baseline Engine** — Rule-matrix using ICAT component percentile ranking (1 file, ~80 lines)
3. **Wire into config** — `config/clustering.php` with engine registry
4. **Build Triage Dashboard** — Svelte component, engine-aware display
5. **Wire audit trail** — Every approve/override/defer logged with engine name
6. **Document in Chapter 3** — Architecture description + future work section

---

## Relationship to Yukee et al. (2025)

- **Full manuscript access**: Yes
- **Researcher contact**: Yes — can formalize request for source code
- **System status**: Handed to Guidance Office, not in active use (doesn't match admission workflow template, not because it's non-functional)
- **Their system has**: ICAT score recording, result printing, auxiliary functions
- **Why unused**: Admission process is technical + workflow-specific. Their feature set doesn't match the institutional operational template
- **Our approach**: Use their validated parameters as the baseline engine. The architecture is ready to load their actual model when obtained
