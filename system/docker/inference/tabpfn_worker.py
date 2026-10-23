"""Standalone pairwise admission inference worker.

The worker is advisory only: it evaluates applicant/program compatibility and
never writes application or program-selection state.
"""

from __future__ import annotations

import json
import math
import os
import threading
import time
from collections import defaultdict
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

FEATURE_COLUMNS = [
    "gwa",
    "gen_ability",
    "verbal_apt",
    "num_apt",
    "spatial_apt",
    "percept_apt",
    "manual_dext",
    "shs_strand",
    "shs_math",
    "shs_sci",
    "shs_eng",
    "riasec_r",
    "riasec_i",
    "riasec_a",
    "riasec_s",
    "riasec_e",
    "riasec_c",
    "candidate_program",
]

APTITUDE_COLUMNS = (
    "gen_ability",
    "verbal_apt",
    "num_apt",
    "spatial_apt",
    "percept_apt",
    "manual_dext",
)
SUBJECT_COLUMNS = ("shs_math", "shs_sci", "shs_eng")
RIASEC_COLUMNS = ("riasec_r", "riasec_i", "riasec_a", "riasec_s", "riasec_e", "riasec_c")
NUMERIC_COLUMNS = (
    "gwa",
    *APTITUDE_COLUMNS,
    *SUBJECT_COLUMNS,
)
REQUIRED_APPLICANT_COLUMNS = ("gwa", "shs_strand", *APTITUDE_COLUMNS)
ENGINE_VERSION = "bayesian-prior-kernel-v1"


class PayloadValidationError(ValueError):
    """Raised when an inference request does not satisfy the input contract."""


def _is_missing(value: Any) -> bool:
    if value is None:
        return True
    if isinstance(value, float):
        return math.isnan(value)
    return False


def _number(value: Any, name: str, *, optional: bool = False) -> float | None:
    if _is_missing(value):
        if optional:
            return None
        raise PayloadValidationError(f"{name} is required and must be numeric")
    if isinstance(value, bool) or not isinstance(value, (int, float)):
        raise PayloadValidationError(f"{name} must be numeric")
    result = float(value)
    if not math.isfinite(result):
        if optional and math.isnan(result):
            return None
        raise PayloadValidationError(f"{name} must be finite")
    return result



APTITUDE_FEATURE_ALIASES = {
    "logic": "gen_ability",
    "profile_verbal": "verbal_apt",
    "profile_quant": "num_apt",
    "spatial": "spatial_apt",
    "memory": "percept_apt",
    "language": "manual_dext",
}


def _canonical_features(values: dict[str, Any]) -> dict[str, Any]:
    canonical = dict(values)
    for alias, feature in APTITUDE_FEATURE_ALIASES.items():
        if feature not in canonical:
            for key, value in values.items():
                if isinstance(key, str) and key.casefold() == alias:
                    canonical[feature] = value
                    break
    return canonical

def _validate_applicant(applicant: Any) -> dict[str, Any]:
    if not isinstance(applicant, dict):
        raise PayloadValidationError("query_applicant must be an object")
    applicant = _canonical_features(applicant)
    missing = [column for column in REQUIRED_APPLICANT_COLUMNS if column not in applicant]
    if missing:
        raise PayloadValidationError(f"query_applicant is missing required features: {', '.join(missing)}")

    normalized: dict[str, Any] = {}
    for column in NUMERIC_COLUMNS:
        if column in applicant or column in REQUIRED_APPLICANT_COLUMNS:
            normalized[column] = _number(
                applicant.get(column),
                f"query_applicant.{column}",
                optional=column in SUBJECT_COLUMNS,
            )
    strand = applicant.get("shs_strand")
    if not isinstance(strand, str) or not strand.strip():
        raise PayloadValidationError("query_applicant.shs_strand must be a non-empty string")
    normalized["shs_strand"] = strand.strip()

    for column in RIASEC_COLUMNS:
        value = applicant.get(column)
        if value is None:
            normalized[column] = None
        elif isinstance(value, bool) or value not in (0, 1):
            raise PayloadValidationError(f"query_applicant.{column} must be binary (0 or 1)")
        else:
            normalized[column] = int(value)
    return normalized


def _validate_exemplars(exemplars: Any) -> list[dict[str, Any]]:
    if not isinstance(exemplars, list) or not exemplars:
        raise PayloadValidationError("context_exemplars must be a non-empty list")

    validated: list[dict[str, Any]] = []
    for index, exemplar in enumerate(exemplars):
        prefix = f"context_exemplars[{index}]"
        if not isinstance(exemplar, dict):
            raise PayloadValidationError(f"{prefix} must be an object")
        target = exemplar.get("target", exemplar.get("target_class"))
        if isinstance(target, bool) or target not in (0, 1):
            raise PayloadValidationError(f"{prefix}.target must be 0 or 1")
        program_code = exemplar.get("program_code")
        if not isinstance(program_code, str) or not program_code.strip():
            raise PayloadValidationError(f"{prefix}.program_code must be a non-empty string")
        nested_values = exemplar.get("values", {})
        if not isinstance(nested_values, dict):
            raise PayloadValidationError(f"{prefix}.values must be an object")
        raw_features = _canonical_features({**nested_values, **exemplar})

        features: dict[str, Any] = {}
        for column in NUMERIC_COLUMNS:
            value = raw_features.get(column)
            features[column] = _number(value, f"{prefix}.{column}", optional=True)
        strand = raw_features.get("shs_strand")
        features["shs_strand"] = strand.strip() if isinstance(strand, str) and strand.strip() else None
        for column in RIASEC_COLUMNS:
            value = raw_features.get(column)
            if value is None:
                features[column] = None
            elif isinstance(value, bool) or value not in (0, 1):
                raise PayloadValidationError(f"{prefix}.{column} must be binary (0 or 1)")
            else:
                features[column] = int(value)

        validated.append({
            "program_code": program_code.strip(),
            "program_name": str(exemplar.get("program_name") or program_code).strip(),
            "target": int(target),
            "features": features,
        })
    return validated


def _feature_distance(left: dict[str, Any], right: dict[str, Any]) -> tuple[float, int]:
    """Return weighted RMS feature distance and the number of compared modalities."""
    weighted_error = 0.0
    total_weight = 0.0
    dimensions = 0

    # Academic/aptitude measurements carry most of the decision signal.
    for column in NUMERIC_COLUMNS:
        first, second = left.get(column), right.get(column)
        if first is None or second is None:
            continue
        delta = min(abs(first - second) / 100.0, 1.0)
        weight = 1.8 if column in APTITUDE_COLUMNS else (1.4 if column == "gwa" else 1.0)
        weighted_error += weight * delta * delta
        total_weight += weight
        dimensions += 1

    first_strand, second_strand = left.get("shs_strand"), right.get("shs_strand")
    if first_strand and second_strand:
        weighted_error += 1.0 * (first_strand.casefold() != second_strand.casefold())
        total_weight += 1.0
        dimensions += 1

    # RIASEC is multi-hot evidence, not a target shortcut. In particular,
    # negative anchors retain these values but aptitude evidence still dominates.
    for column in RIASEC_COLUMNS:
        first, second = left.get(column), right.get(column)
        if first is None or second is None:
            continue
        weighted_error += 0.25 * (first != second)
        total_weight += 0.25
        dimensions += 1

    if not dimensions or not total_weight:
        return 1.0, 0
    return math.sqrt(weighted_error / total_weight), dimensions


def _score_program(
    applicant: dict[str, Any], exemplars: list[dict[str, Any]],
) -> tuple[int, list[str], int]:
    positives = [row for row in exemplars if row["target"] == 1]
    negatives = [row for row in exemplars if row["target"] == 0]
    if not positives:
        return 0, ["No positive benchmark exemplars are available for this program."], 0

    # Estimate local class likelihoods using a smooth Gaussian prior-data kernel.
    # A small global beta prior prevents tiny context sets from producing 0/100.
    distances: list[tuple[int, float, int, dict[str, Any]]] = []
    for row in exemplars:
        distance, dimensions = _feature_distance(applicant, row["features"])
        distances.append((row["target"], distance, dimensions, row["features"]))

    all_positive = sum(row["target"] for row in exemplars)
    global_prior = (all_positive + 1.0) / (len(exemplars) + 2.0)
    bandwidth = 0.12
    prior_strength = 1.0
    positive_weight = 0.0
    negative_weight = 0.0
    for target, distance, _dimensions, _features in distances:
        kernel_weight = math.exp(-0.5 * (distance / bandwidth) ** 2)
        if target == 1:
            positive_weight += kernel_weight
        else:
            negative_weight += kernel_weight

    posterior = (positive_weight + prior_strength * global_prior) / (
        positive_weight + negative_weight + prior_strength
    )
    score = max(0, min(100, int(round(posterior * 100))))

    # Drivers describe the query's closest positive evidence and avoid claiming
    # that missing features contributed to the inference.
    closest_positive = sorted(
        (item for item in distances if item[0] == 1),
        key=lambda item: item[1],
    )[:3]
    drivers: list[str] = []
    for _target, distance, _dimensions, features in closest_positive:
        for column in (*APTITUDE_COLUMNS, "gwa", *SUBJECT_COLUMNS, "shs_strand", *RIASEC_COLUMNS):
            query_value = applicant.get(column)
            exemplar_value = features.get(column)
            if query_value is None or exemplar_value is None:
                continue
            if column == "shs_strand":
                if query_value.casefold() == exemplar_value.casefold():
                    label = f"SHS strand aligns with {query_value} benchmarks"
                else:
                    continue
            elif column in RIASEC_COLUMNS:
                if query_value == exemplar_value == 1:
                    label = f"RIASEC {column.removeprefix('riasec_').upper()} interest aligns"
                else:
                    continue
            else:
                if abs(query_value - exemplar_value) > 8:
                    continue
                label = f"{column.replace('_', ' ').upper()} is close to positive benchmarks"
            if label not in drivers:
                drivers.append(label)
            if len(drivers) >= 4:
                break
        if len(drivers) >= 4:
            break

    if not drivers:
        drivers.append("Compatibility is estimated from the available pairwise benchmark profile.")
    compared_dimensions = max((item[2] for item in distances), default=0)
    return score, drivers, compared_dimensions


_CLASSIFIER_INSTANCE: Any = None
_CLASSIFIER_ERROR: str | None = None
_INFERENCE_SEMAPHORE = threading.BoundedSemaphore(
    int(os.environ.get("TABPFN_MAX_CONCURRENCY", "2"))
)


def _get_classifier() -> Any:
    global _CLASSIFIER_INSTANCE, _CLASSIFIER_ERROR
    if _CLASSIFIER_INSTANCE is not None:
        return _CLASSIFIER_INSTANCE
    if _CLASSIFIER_ERROR is not None:
        return None
    try:
        import torch  # type: ignore[import-not-found]  # noqa: F401
        from tabpfn import TabPFNClassifier  # type: ignore[import-not-found]

        device = os.environ.get("TABPFN_DEVICE", "cpu")
        model_path = os.environ.get("TABPFN_MODEL_PATH")
        kwargs: dict[str, Any] = {"device": device}
        if model_path:
            kwargs["model_path"] = model_path
        _CLASSIFIER_INSTANCE = TabPFNClassifier(**kwargs)
        return _CLASSIFIER_INSTANCE
    except Exception as exc:
        _CLASSIFIER_ERROR = f"{exc.__class__.__name__}: {exc}"
        return None


def _tabpfn_scores(
    exemplars: list[dict[str, Any]], applicant: dict[str, Any], programs: list[str], classifier: Any,
) -> dict[str, float]:
    import numpy as np

    feature_columns = [column for column in FEATURE_COLUMNS if column != "candidate_program"]
    numeric_columns = set(NUMERIC_COLUMNS)
    context_rows: list[list[Any]] = []
    labels: list[int] = []
    for row in exemplars:
        values = row["features"]
        context_rows.append([
            values.get(column) if column in numeric_columns else values.get(column)
            for column in feature_columns
        ] + [row["program_code"]])
        labels.append(row["target"])

    query_rows = []
    for program_code in programs:
        query_rows.append([
            applicant.get(column) for column in feature_columns
        ] + [program_code])

    # Establish stable categorical encodings derived strictly from context/schema.
    categories: dict[int, dict[str, int]] = defaultdict(dict)
    combined = context_rows + query_rows
    encoded: list[list[float]] = []
    for row in combined:
        encoded_row: list[float] = []
        for column_index, value in enumerate(row):
            column = (feature_columns + ["candidate_program"])[column_index]
            if value is None:
                encoded_row.append(float("nan"))
            elif column == "shs_strand" or column == "candidate_program":
                category = str(value)
                mapping = categories[column_index]
                mapping.setdefault(category, len(mapping))
                encoded_row.append(float(mapping[category]))
            else:
                encoded_row.append(float(value))
        encoded.append(encoded_row)
    matrix = np.asarray(encoded, dtype=float)

    # TabPFN natively supports NaN values through missingness attention masks;
    # do NOT overwrite nonfinite values with ad-hoc column means.
    classifier.fit(matrix[:len(context_rows)], np.asarray(labels))
    probabilities = classifier.predict_proba(matrix[len(context_rows):])[:, 1]
    return {
        program_code: round(float(probability) * 100, 2)
        for program_code, probability in zip(programs, probabilities, strict=True)
    }


def evaluate_pairwise_tabpfn(payload: dict) -> dict:
    """Evaluate query-applicant/program pairs from benchmark exemplars."""
    start_time = time.perf_counter()
    if not isinstance(payload, dict):
        raise PayloadValidationError("payload must be an object")
    exemplars = _validate_exemplars(payload.get("context_exemplars"))
    applicant = _validate_applicant(payload.get("query_applicant"))

    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    names: dict[str, str] = {}
    for exemplar in exemplars:
        grouped[exemplar["program_code"]].append(exemplar)
        names.setdefault(exemplar["program_code"], exemplar["program_name"])
    programs = list(grouped)

    backend = "bayesian_prior_kernel_v1"
    fallback_reason: str | None = None
    scores: dict[str, float] = {}

    target_classes = {row["target"] for row in exemplars}
    if len(target_classes) <= 1:
        fallback_reason = "insufficient_target_classes"
    else:
        classifier = _get_classifier()
        if classifier is not None:
            acquired = _INFERENCE_SEMAPHORE.acquire(timeout=2.0)
            if acquired:
                try:
                    scores = _tabpfn_scores(exemplars, applicant, programs, classifier)
                    backend = "tabpfn_pytorch"
                except Exception as exc:
                    scores.clear()
                    fallback_reason = f"inference_exception: {exc.__class__.__name__}"
                finally:
                    _INFERENCE_SEMAPHORE.release()
            else:
                fallback_reason = "concurrency_limit_exceeded"
        else:
            fallback_reason = _CLASSIFIER_ERROR or "tabpfn_unavailable"

    comparison_counts: dict[str, int] = {}
    drivers_by_program: dict[str, list[str]] = {}
    if not scores:
        for program_code in programs:
            score, drivers, dimensions = _score_program(applicant, grouped[program_code])
            scores[program_code] = score
            drivers_by_program[program_code] = drivers
            comparison_counts[program_code] = dimensions
    else:
        backend = "tabpfn_pytorch"
        for program_code in programs:
            _score, drivers, dimensions = _score_program(applicant, grouped[program_code])
            drivers_by_program[program_code] = drivers
            comparison_counts[program_code] = dimensions
    recommendations = []
    for program_code in programs:
        compatibility_index = scores[program_code]
        band = (
            "high_match" if compatibility_index >= 80
            else "moderate_match" if compatibility_index >= 68
            else "below_threshold"
        )
        recommendations.append({
            "program_code": program_code,
            "program_name": names[program_code],
            "compatibility_index": compatibility_index,
            "band": band,
            "passed_cutoff": compatibility_index >= 68,
            "drivers": drivers_by_program[program_code],
        })
    recommendations.sort(key=lambda item: (-item["compatibility_index"], item["program_code"]))
    for rank, recommendation in enumerate(recommendations, 1):
        recommendation["rank"] = rank

    available_count = sum(value is not None for value in applicant.values())
    modality_coverage = {
        "available_features": available_count,
        "total_features": len(FEATURE_COLUMNS) - 1,
        "compared_dimensions_by_program": comparison_counts,
        "missing_optional_features": [
            column for column in (*SUBJECT_COLUMNS, *RIASEC_COLUMNS)
            if applicant.get(column) is None
        ],
    }
    return {
        "recommendations": recommendations,
        "engine_version": "tabpfn-v2" if backend == "tabpfn_pytorch" else "bayesian-prior-kernel-v1",
        "inference_backend": backend,
        "fallback_reason": fallback_reason,
        "execution_time_ms": round((time.perf_counter() - start_time) * 1000, 2),
        "modality_coverage": modality_coverage,
    }


class TabPFNRequestHandler(BaseHTTPRequestHandler):
    """Small JSON HTTP adapter for the standalone inference worker."""

    server_version = "NEXIAM-TabPFN/1.0"

    def _send_json(self, status: int, body: dict[str, Any]) -> None:
        encoded = json.dumps(body, allow_nan=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def do_GET(self) -> None:
        if self.path == "/health":
            classifier = _get_classifier()
            self._send_json(200, {
                "status": "ok",
                "ready": classifier is not None,
                "engine": "tabfm_pairwise",
                "backend": "tabpfn_pytorch" if classifier is not None else "bayesian_prior_kernel_v1",
                "fallback_reason": _CLASSIFIER_ERROR if classifier is None else None,
            })
            return
        self._send_json(404, {"error": "not_found"})

    def do_POST(self) -> None:
        if self.path != "/inference/tabpfn":
            self._send_json(404, {"error": "not_found"})
            return
        try:
            content_length = int(self.headers.get("Content-Length", "0"))
            if content_length <= 0 or content_length > 2_000_000:
                raise PayloadValidationError("Content-Length must be between 1 and 2000000 bytes")
            payload = json.loads(self.rfile.read(content_length))
            result = evaluate_pairwise_tabpfn(payload)
        except (PayloadValidationError, json.JSONDecodeError, UnicodeDecodeError) as error:
            self._send_json(400, {"error": "invalid_request", "message": str(error)})
            return
        except Exception:
            self._send_json(500, {"error": "inference_failed"})
            return
        self._send_json(200, result)

    def log_message(self, _format: str, *_args: Any) -> None:
        return


def main() -> None:
    server = ThreadingHTTPServer(("0.0.0.0", 8101), TabPFNRequestHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
