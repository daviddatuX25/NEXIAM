# NEXIAM Model Registry — Architecture Specification

**Date:** 2026-07-14
**Purpose:** How models are registered, discovered, configured, and rendered. Not code — design contract.

---

## 1. Philosophy

NEXIAM does not own the models. It owns the **registry** — the governed space where models are declared, validated, and surfaced.

- Models are **adopted**, not trained
- Models declare themselves via a manifest (JSON metadata)
- The dashboard renders models via their declared visualization type
- No code deploy is required to add, swap, or disable a model
- Models can live locally, remotely, or as external API services

---

## 2. The Manifest

Every model registers with a self-describing JSON document. This is the single contract between a model and NEXIAM.

### Manifest Schema

```json
{
  "id": "bge_interest_v2",
  "name": "BGE Interest Matcher v2",
  "version": "2.0.0",
  "author": "ML Capstone Batch 2027 — Group 3",
  "description": "Matches applicant preferences to ISPSC programs via fine-tuned BGE embeddings",
  "type": "ranking",
  "signal": "interest",
  "status": "sandbox",

  "inputs": {
    "applicant_preferences": {
      "type": "text",
      "label": "Applicant stated course preferences",
      "required": true,
      "source": "applicant.profile.preferences"
    }
  },

  "outputs": {
    "type": "ranking",
    "visualization": "bar-chart-ranked",
    "label": "Program Interest Match",
    "fields": ["program_name", "score", "confidence"]
  },

  "inference": {
    "protocol": "http",
    "method": "POST",
    "endpoint": "http://models.dxtechph.online/bge-v2/predict",
    "timeout_ms": 5000,
    "retry": 1
  },

  "ui": {
    "panel_title": "Interest Match",
    "panel_icon": "heart-outline",
    "color": "#4A90D9",
    "sort_by": "score",
    "sort_direction": "descending"
  }
}
```

### Field Reference

| Field | Purpose |
|---|---|
| `id` | Unique slug within the registry |
| `type` | `clustering`, `ranking`, `classification`, `hybrid`, `custom` |
| `signal` | `aptitude`, `interest`, or `custom:<name>` |
| `status` | `active`, `sandbox`, `disabled` |
| `inputs` | What features the engine consumes (field name, type, source path) |
| `outputs.type` | Output schema type |
| `outputs.visualization` | Which UI component to render (`bar-chart-ranked`, `scatter-plot`, `table-probability`, etc.) |
| `inference` | Where and how to call the model (HTTP, local handler, etc.) |
| `ui` | Dashboard rendering hints (title, icon, color, sort) |

### Manifest Validation

On registration, the manifest is validated against a JSON Schema. Invalid manifests are rejected with error messages. Uses `opis/json-schema` (PHP, MIT license, lightweight).

---

## 3. Model Registry (Database)

A single table holds all registered models. No filesystem scanning, no config file edits.

### Table: `model_registry`

| Column | Type | Purpose |
|---|---|---|
| `id` | string (PK) | Unique slug from manifest |
| `name` | string | Human-readable |
| `version` | string | Semver |
| `type` | string | clustering, ranking, classification, custom |
| `signal` | string | aptitude, interest, custom |
| `manifest` | json | Full manifest document |
| `status` | string | active, sandbox, disabled |
| `created_by` | string (FK → users) | Who registered it |
| `created_at` | timestamp | Registration timestamp |
| `updated_at` | timestamp | Last manifest update |

### Inference Results: `model_inference_results`

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `model_id` | string (FK → model_registry) | Which model produced this |
| `applicant_id` | bigint (FK → applicants) | Which applicant |
| `result` | json | Model output (shape varies per model type) |
| `metadata` | json | Engine version, features consumed, confidence |
| `computed_at` | timestamp | When inference ran |

**Why JSON columns:** Each model returns differently shaped output. JSON columns store any shape without migration. MySQL 8.0+ supports indexing into JSON paths via virtual columns for queries.

---

## 4. Status Lifecycle

```
sandbox → validated → active
                         ↓
                      disabled
```

| Status | What it means |
|---|---|
| `sandbox` | Experimental. Results visible only on Research tab. No counselor decisions can use it. Intended for ML students and in-progress models. |
| `validated` | Internal review complete. Awaiting activation. |
| `active` | Live on Triage Dashboard. Counselor uses it. Every action logged to audit trail. |
| `disabled` | Decommissioned. No inference calls. Historical results preserved for audit. |

**Model Promotion:** IT Administrator or authorized staff promotes a model from sandbox → active via the admin panel. The promotion itself is logged to the HMAC-SHA256 audit trail.

---

## 5. Dashboard Rendering

The Triage Dashboard queries all `status=active` models, groups them by `signal`, and renders each model's output in its own panel.

### How Visualization Components Are Selected

- Model manifest declares `outputs.visualization` (e.g., `"bar-chart-ranked"`)
- NEXIAM maintains a **library of standard visualization components** (Svelte)
- On dashboard load: for each active model → look up component by visualization type → render
- If visualization type is `custom:<path>`, the model can ship its own component under a restricted sandbox path

### Standard Component Library

| Visualization Key | Rendering | Models That Use It |
|---|---|---|
| `bar-chart-ranked` | Horizontal bars, sorted by score | Ranking models (BGE Interest) |
| `scatter-plot` | 2D point plot, color-coded by cluster | Clustering models (Yukee) |
| `table-probability` | Sortable table with confidence columns | Classification models |
| `radar-chart` | Multi-axis overlay for multi-signal | Hybrid models |
| `signal:simple-list` | Text list with confidence badges | Fallback / generic |

**New visualization types** can be added by developers as Svelte components under a designated directory. Models reference them via manifest key.

---

## 6. Admin Panel (System IT Administrator Role)

### Engine Manager View

```
┌─────────────────────────────────────────────────────────────┐
│ Engine Manager                                   [+ Register] │
├──────────┬──────────┬────────┬──────────┬───────────────────┤
│ Name     │ Type     │ Signal │ Status   │ Actions           │
├──────────┼──────────┼────────┼──────────┼───────────────────┤
│ Yukee    │ cluster  │ aptit. │ active   │ [Disable] [Edit]  │
│ BGE      │ ranking  │ inter. │ active   │ [Disable] [Edit]  │
│ NN Class │ classif. │ aptit. │ sandbox  │ [Promote] [Edit]  │
└──────────┴──────────┴────────┴──────────┴───────────────────┘
```

### Register New Model Form

- Upload manifest JSON (or build via wizard)
- Validated against JSON Schema on submit
- Status auto-assigned as `sandbox`
- Health endpoint pinged to verify model is reachable

---

## 7. Model Lifecycle (External View)

For a future ML student:

1. Train model in Python, R, or any language
2. Expose model via HTTP endpoint (FastAPI, Flask, etc.)
3. Create a manifest JSON describing the model
4. Register via admin panel (or contact IT Admin)
5. Model appears as `sandbox` — Research tab only
6. Adviser reviews; if acceptable → promoted to `active`
7. Model goes live on Guidance Counselor's Triage Dashboard
8. Every inference call logged to HMAC-SHA256 audit trail

**The student never touches NEXIAM code.** Their model is a standalone service with one REST endpoint.

---

## 8. Storage Decision

**MySQL JSON columns** — not a separate NoSQL database.

- `model_registry.manifest` = JSON
- `model_inference_results.result` = JSON
- MySQL 8 JSON functions: `JSON_EXTRACT()`, `JSON_CONTAINS()`, virtual column indexing

Rationale: No new infrastructure. The project already requires MySQL for RBAC, applicants, and audit trails. JSON columns provide document-store flexibility inside the existing database.

---

## 9. What NEXIAM Owns vs. What Models Own

| Component | Owned By |
|---|---|
| Engine registry table + CRUD | NEXIAM |
| Manifest JSON Schema validator | NEXIAM |
| Standard visualization components | NEXIAM |
| Triage Dashboard layout | NEXIAM |
| Inference result storage | NEXIAM |
| Audit trail logging | NEXIAM |
| Model logic (algorithm, weights, endpoint) | **Model author** |
| Custom UI component (if `custom:<path>`) | **Model author** |
| Model training data and methodology | **Model author** |

---

*This document supersedes `NEXIAM_ML_Adapter_Architecture.md` (July 3) and `NEXIAM_Model_Engine_Architecture_Plan.md` (July 14).*
