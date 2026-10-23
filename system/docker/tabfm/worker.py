"""Standalone pairwise admission inference worker.

The worker is advisory only: it evaluates applicant/program compatibility and
never writes application or program-selection state.
"""

from __future__ import annotations

import hashlib
import importlib.metadata
import json
import math
import os
import re
import stat
import threading
import time
from collections import defaultdict
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

APTITUDE_COLUMNS = (
    "LOGIC", "PROFILE_VERBAL", "PROFILE_QUANT", "SPATIAL", "MEMORY", "LANGUAGE",
)
APPROVED_FEATURE_COLUMNS = frozenset(("gwa", *APTITUDE_COLUMNS))


STABLE_FEATURE_KEY = re.compile(r"^[A-Za-z][A-Za-z0-9_]{0,63}$")
CHECKPOINT_SHA256 = re.compile(r"^[0-9a-fA-F]{64}$")
EXPECTED_TABPFN_PACKAGE_VERSION = "9.1.0"
EXPECTED_CHECKPOINT_ID = (
    "Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt"
)
EXPECTED_CHECKPOINT_SHA256 = (
    "1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88"
)


def _http_service_approved() -> bool:
    """Require explicit approvals and the exact runtime pins for HTTP use."""
    if (
        os.environ.get("TABPFN_INSTITUTIONAL_USE_APPROVED", "").lower() != "true"
        or os.environ.get("TABPFN_HOSTED_USE_APPROVED", "").lower() != "true"
        or os.environ.get("TABPFN_CHECKPOINT_ID") != EXPECTED_CHECKPOINT_ID
        or os.environ.get("TABPFN_CHECKPOINT_SHA256", "").lower()
        != EXPECTED_CHECKPOINT_SHA256
    ):
        return False
    try:
        return (
            importlib.metadata.version("tabpfn")
            == EXPECTED_TABPFN_PACKAGE_VERSION
        )
    except Exception:
        return False


def _http_provenance_matches_pins(provenance: dict[str, str]) -> bool:
    return (
        provenance.get("package") == "tabpfn"
        and provenance.get("package_version") == EXPECTED_TABPFN_PACKAGE_VERSION
        and provenance.get("checkpoint_identity") == EXPECTED_CHECKPOINT_ID
        and provenance.get("checkpoint_sha256") == EXPECTED_CHECKPOINT_SHA256
    )


class PayloadValidationError(ValueError):
    """Raised when an inference request does not satisfy the input contract."""

INFERENCE_UNAVAILABLE_MESSAGE = (
    "TabFM neural foundation model is unavailable. Counselors proceed with standard evaluation."
)


class InferenceUnavailableError(RuntimeError):
    """Raised when neural inference cannot provide a valid result."""


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



def _validate_feature_definitions(value: Any) -> list[dict[str, Any]]:
    if not isinstance(value, list):
        raise PayloadValidationError("feature_definitions must be a list")
    if not value:
        raise PayloadValidationError("feature_definitions must not be empty")
    definitions: list[dict[str, Any]] = []
    seen: set[str] = set()
    for index, item in enumerate(value):
        prefix = f"feature_definitions[{index}]"
        if not isinstance(item, dict):
            raise PayloadValidationError(f"{prefix} must be an object")
        key = item.get("feature_key")
        if not isinstance(key, str) or not STABLE_FEATURE_KEY.fullmatch(key):
            raise PayloadValidationError(f"{prefix}.feature_key must be a stable key")
        if key == "candidate_program":
            raise PayloadValidationError("candidate_program is reserved for internal program context")
        if key in seen:
            raise PayloadValidationError(f"{prefix}.feature_key is duplicated")
        seen.add(key)
        label = item.get("label")
        if not isinstance(label, str) or not label.strip():
            raise PayloadValidationError(f"{prefix}.label must be a non-empty string")
        logical_type = item.get("logical_type")
        if logical_type not in ("numeric", "binary", "categorical"):
            raise PayloadValidationError(f"{prefix}.logical_type is unsupported")
        required = item.get("is_required")
        if not isinstance(required, bool):
            raise PayloadValidationError(f"{prefix}.is_required must be boolean")
        source = item.get("source_definition")
        scale = None
        if source is not None:
            if not isinstance(source, dict):
                raise PayloadValidationError(f"{prefix}.source_definition must be an object or null")
            for source_key in ("source_id", "source_code", "source_version"):
                source_value = source.get(source_key)
                if not isinstance(source_value, (str, int)) or isinstance(source_value, bool) or not str(source_value).strip():
                    raise PayloadValidationError(f"{prefix}.source_definition.{source_key} is required")
        if key not in APPROVED_FEATURE_COLUMNS:
            raise PayloadValidationError(
                f"{prefix}.feature_key is not an approved aptitude measure or GWA"
            )
        if logical_type != "numeric":
            raise PayloadValidationError(f"{prefix}.logical_type must be numeric")
        if key != "gwa" and not required:
            raise PayloadValidationError(f"{prefix}.is_required must be true")
        if key == "gwa" and source is not None:
            raise PayloadValidationError("gwa.source_definition must be null")
        if key != "gwa":
            if not isinstance(source, dict) or source.get("source_code") != key:
                raise PayloadValidationError(f"{prefix}.source_definition must identify its approved aptitude")
        definitions.append({
            "feature_key": key,
            "label": label.strip(),
            "logical_type": logical_type,
            "is_required": required,
            "source_definition": source,
            "scale": scale,
        })
    if {definition["feature_key"] for definition in definitions} != APPROVED_FEATURE_COLUMNS:
        raise PayloadValidationError(
            "feature_definitions must declare exactly the approved aptitude measures and GWA"
        )
    return definitions


def _validate_feature_map(
    values: Any,
    definitions: list[dict[str, Any]],
    prefix: str,
    *,
    enforce_required: bool = False,
) -> dict[str, Any]:
    if not isinstance(values, dict):
        raise PayloadValidationError(f"{prefix} must be an object")
    definitions_by_key = {item["feature_key"]: item for item in definitions}
    unknown = sorted(key for key in values if key not in definitions_by_key)
    if unknown:
        raise PayloadValidationError(f"{prefix} contains undeclared features: {', '.join(unknown)}")
    normalized: dict[str, Any] = {}
    for definition in definitions:
        key = definition["feature_key"]
        value = values.get(key)
        logical_type = definition["logical_type"]
        if value is None or (isinstance(value, float) and math.isnan(value)):
            if enforce_required and (definition["is_required"] or prefix.startswith("context_exemplars[")):
                raise PayloadValidationError(f"{prefix}.{key} is required by feature_definitions")
            normalized[key] = None
        elif logical_type == "numeric":
            normalized[key] = _number(value, f"{prefix}.{key}")
            source = definition["source_definition"]
            if source is not None and "min_value" in source and not source["min_value"] <= normalized[key] <= source["max_value"]:
                raise PayloadValidationError(f"{prefix}.{key} is outside its approved source bounds")
        elif logical_type == "binary":
            if isinstance(value, bool) or value not in (0, 1):
                raise PayloadValidationError(f"{prefix}.{key} must be binary (0 or 1)")
            normalized[key] = int(value)
        else:
            if not isinstance(value, str) or not value.strip():
                raise PayloadValidationError(f"{prefix}.{key} must be a non-empty category")
            normalized[key] = value.strip()
    return normalized


def _validate_applicant(applicant: Any, definitions: list[dict[str, Any]]) -> dict[str, Any]:
    if not isinstance(applicant, dict):
        raise PayloadValidationError("query_applicant must be an object")
    return _validate_feature_map(
        applicant,
        definitions,
        "query_applicant",
        enforce_required=True,
    )


def _validate_exemplars(exemplars: Any, definitions: list[dict[str, Any]]) -> list[dict[str, Any]]:
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
        direct_values = {key: item for key, item in exemplar.items()
                         if key not in {"values", "target", "target_class", "tier_level",
                                        "program_code", "program_name"}}
        if set(nested_values) & set(direct_values):
            raise PayloadValidationError(f"{prefix} repeats a feature in values and top-level fields")
        features = _validate_feature_map(
            {**nested_values, **direct_values}, definitions, prefix, enforce_required=True,
        )
        validated.append({
            "program_code": program_code.strip(),
            "program_name": str(exemplar.get("program_name") or program_code).strip(),
            "target": int(target),
            "features": features,
        })
    return validated


_CLASSIFIER_INSTANCE: Any = None
_CLASSIFIER_ERROR: str | None = None
_CLASSIFIER_INITIALIZATION_LOCK = threading.Lock()
_INFERENCE_LOCK = threading.Lock()
_MODEL_PROVENANCE: dict[str, str] | None = None
_WARMED_CLASSIFIER: Any = None


def _verified_checkpoint() -> tuple[str, dict[str, str]]:
    model_path = os.environ.get("TABPFN_CHECKPOINT_PATH")
    checkpoint_identity = os.environ.get("TABPFN_CHECKPOINT_ID")
    expected_sha256 = os.environ.get("TABPFN_CHECKPOINT_SHA256")
    if not model_path:
        raise ValueError("TABPFN_CHECKPOINT_PATH is required")
    if (
        not checkpoint_identity
        or not checkpoint_identity.strip()
        or len(checkpoint_identity) > 255
        or any(ord(character) < 32 for character in checkpoint_identity)
    ):
        raise ValueError("TABPFN_CHECKPOINT_ID is required")
    if not expected_sha256 or not CHECKPOINT_SHA256.fullmatch(expected_sha256):
        raise ValueError("TABPFN_CHECKPOINT_SHA256 must be a 64-character hexadecimal digest")

    checkpoint_stat = os.lstat(model_path)
    if not stat.S_ISREG(checkpoint_stat.st_mode) or checkpoint_stat.st_size <= 0:
        raise ValueError("configured TabPFN checkpoint must be a non-empty regular file")

    digest = hashlib.sha256()
    with open(model_path, "rb") as checkpoint:
        for chunk in iter(lambda: checkpoint.read(1024 * 1024), b""):
            digest.update(chunk)
    actual_sha256 = digest.hexdigest()
    if actual_sha256 != expected_sha256.lower():
        raise ValueError("configured TabPFN checkpoint SHA-256 does not match")

    return model_path, {
        "package": "tabpfn",
        "package_version": importlib.metadata.version("tabpfn"),
        "checkpoint_identity": checkpoint_identity.strip(),
        "checkpoint_sha256": actual_sha256,
    }


def _construct_classifier(model_path: str) -> Any:
    from tabpfn import TabPFNClassifier  # type: ignore[import-not-found]

    return TabPFNClassifier(
        device=os.environ.get("TABPFN_DEVICE", "cpu"),
        model_path=model_path,
    )


def _get_classifier() -> Any:
    global _CLASSIFIER_INSTANCE, _CLASSIFIER_ERROR, _MODEL_PROVENANCE, _WARMED_CLASSIFIER
    with _CLASSIFIER_INITIALIZATION_LOCK:
        if _CLASSIFIER_INSTANCE is not None:
            return _CLASSIFIER_INSTANCE
        if _CLASSIFIER_ERROR is not None:
            return None
        try:
            model_path, provenance = _verified_checkpoint()
            _CLASSIFIER_INSTANCE = _construct_classifier(model_path)
            _MODEL_PROVENANCE = provenance
            return _CLASSIFIER_INSTANCE
        except Exception as exc:
            _CLASSIFIER_ERROR = f"{exc.__class__.__name__}: {exc}"
            _MODEL_PROVENANCE = None
            _WARMED_CLASSIFIER = None
            return None


def _record_classifier_failure(error: Exception) -> None:
    global _CLASSIFIER_INSTANCE, _CLASSIFIER_ERROR, _MODEL_PROVENANCE, _WARMED_CLASSIFIER
    with _CLASSIFIER_INITIALIZATION_LOCK:
        _CLASSIFIER_ERROR = f"{error.__class__.__name__}: {error}"
        _CLASSIFIER_INSTANCE = None
        _MODEL_PROVENANCE = None
        _WARMED_CLASSIFIER = None


def _validated_binary_probabilities(
    classifier: Any,
    values: Any,
    expected_rows: int,
) -> tuple[Any, int]:
    import numpy as np

    classes = np.asarray(getattr(classifier, "classes_", None))
    if classes.shape != (2,) or set(classes.tolist()) != {0, 1}:
        raise RuntimeError("TabPFN must expose binary classes 0 and 1")
    probabilities = np.asarray(values, dtype=float)
    if (
        probabilities.shape != (expected_rows, 2)
        or not np.isfinite(probabilities).all()
        or ((probabilities < 0) | (probabilities > 1)).any()
        or not np.allclose(probabilities.sum(axis=1), 1.0, rtol=1e-5, atol=1e-8)
    ):
        raise RuntimeError("TabPFN returned invalid binary probabilities")
    positive_class_index = int(np.flatnonzero(classes == 1)[0])
    return probabilities, positive_class_index


def _warm_classifier(classifier: Any) -> None:
    global _WARMED_CLASSIFIER
    if _WARMED_CLASSIFIER is classifier:
        return

    import numpy as np

    classifier.fit(np.asarray([[0.0], [1.0]], dtype=float), np.asarray([0, 1]))
    _validated_binary_probabilities(
        classifier,
        classifier.predict_proba(np.asarray([[0.5]], dtype=float)),
        expected_rows=1,
    )
    _WARMED_CLASSIFIER = classifier


def _ready_classifier() -> tuple[Any, dict[str, str]]:
    classifier = _get_classifier()
    if classifier is None:
        raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE)
    try:
        if _MODEL_PROVENANCE is None:
            raise RuntimeError("TabPFN model provenance is unavailable")
        _warm_classifier(classifier)
        return classifier, dict(_MODEL_PROVENANCE)
    except Exception as exc:
        _record_classifier_failure(exc)
        raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE) from exc


def _tabpfn_scores(
    exemplars: list[dict[str, Any]],
    applicant: dict[str, Any],
    programs: list[str],
    classifier: Any,
    definitions: list[dict[str, Any]],
) -> dict[str, float]:
    import numpy as np

    categorical_keys = [item["feature_key"] for item in definitions if item["logical_type"] == "categorical"]
    definitions_by_key = {item["feature_key"]: item for item in definitions}
    categories = {
        key: sorted({
            str(row["features"][key])
            for row in exemplars
            if row["features"].get(key) is not None
        } | ({str(applicant[key])} if applicant.get(key) is not None else set())
          | set(
              definitions_by_key[key].get("source_definition", {}).get("options") or []
              if isinstance(definitions_by_key[key].get("source_definition"), dict)
              else []
          ))
        for key in categorical_keys
    }
    categories["candidate_program"] = sorted({row["program_code"] for row in exemplars} | set(programs))
    encoded_columns: list[tuple[str, str | None]] = [
        (item["feature_key"], None)
        for item in definitions
        if item["logical_type"] != "categorical"
    ]
    encoded_columns.extend(
        (key, category)
        for key in (*categorical_keys, "candidate_program")
        for category in categories[key]
    )

    def encode(features: dict[str, Any], program_code: str | None = None) -> list[float]:
        encoded: list[float] = []
        for key, category in encoded_columns:
            if category is None:
                value = features.get(key)
                encoded.append(float("nan") if value is None else float(value))
            else:
                value = program_code if key == "candidate_program" else features.get(key)
                encoded.append(float("nan") if value is None else float(str(value) == category))
        return encoded

    context_rows = [encode(row["features"], row["program_code"]) for row in exemplars]
    query_rows = [encode(applicant, program_code) for program_code in programs]
    matrix = np.asarray((*context_rows, *query_rows), dtype=float)
    classifier.fit(matrix[:len(context_rows)], np.asarray([row["target"] for row in exemplars]))
    probability_matrix, positive_class_index = _validated_binary_probabilities(
        classifier,
        classifier.predict_proba(matrix[len(context_rows):]),
        expected_rows=len(programs),
    )
    positive_probabilities = probability_matrix[:, positive_class_index]
    return {
        program_code: round(float(probability) * 100, 2)
        for program_code, probability in zip(programs, positive_probabilities, strict=True)
    }


def _program_drivers(
    applicant: dict[str, Any],
    exemplars: list[dict[str, Any]],
    definitions: list[dict[str, Any]],
) -> list[str]:
    drivers: list[str] = []
    for exemplar in exemplars:
        if exemplar["target"] != 1:
            continue
        for definition in definitions:
            key = definition["feature_key"]
            query_value = applicant.get(key)
            exemplar_value = exemplar["features"].get(key)
            if query_value is None or exemplar_value is None or query_value != exemplar_value:
                continue
            label = f"{definition['label']} aligns with positive benchmarks"
            if label not in drivers:
                drivers.append(label)
            if len(drivers) == 4:
                return drivers
    return drivers


def evaluate_pairwise_tabpfn(payload: dict) -> dict:
    """Evaluate query-applicant/program pairs from benchmark exemplars."""
    start_time = time.perf_counter()
    if not isinstance(payload, dict):
        raise PayloadValidationError("payload must be an object")
    definitions = _validate_feature_definitions(payload.get("feature_definitions"))
    exemplars = _validate_exemplars(payload.get("context_exemplars"), definitions)
    applicant = _validate_applicant(payload.get("query_applicant"), definitions)

    grouped: dict[str, list[dict[str, Any]]] = defaultdict(list)
    names: dict[str, str] = {}
    for exemplar in exemplars:
        grouped[exemplar["program_code"]].append(exemplar)
        names.setdefault(exemplar["program_code"], exemplar["program_name"])
    programs = list(grouped)

    if {row["target"] for row in exemplars} != {0, 1}:
        raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE)

    with _INFERENCE_LOCK:
        try:
            classifier, provenance = _ready_classifier()
            scores = _tabpfn_scores(exemplars, applicant, programs, classifier, definitions)
        except InferenceUnavailableError:
            raise
        except Exception as exc:
            _record_classifier_failure(exc)
            raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE) from exc
    drivers_by_program = {
        program_code: _program_drivers(applicant, grouped[program_code], definitions)
        for program_code in programs
    }
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
            "drivers": drivers_by_program[program_code],
        })
    recommendations.sort(key=lambda item: (-item["compatibility_index"], item["program_code"]))
    for rank, recommendation in enumerate(recommendations, 1):
        recommendation["rank"] = rank

    definitions_by_key = {item["feature_key"]: item for item in definitions}
    missing_optional = [
        item["feature_key"] for item in definitions
        if not item["is_required"] and applicant.get(item["feature_key"]) is None
    ]
    missing_features = [
        item["feature_key"] for item in definitions
        if applicant.get(item["feature_key"]) is None
    ]
    return {
        "status": "evaluated",
        "recommendations": recommendations,
        "engine_version": provenance["package_version"],
        "inference_backend": "tabpfn_pytorch",
        "fallback_reason": None,
        "provenance": provenance,
        "execution_time_ms": round((time.perf_counter() - start_time) * 1000, 2),
        "modality_coverage": {
            "available_features": sum(value is not None for value in applicant.values()),
            "total_features": len(definitions),
            "missing_optional_features": missing_optional,
            "missing_features": missing_features,
            "feature_coverage": {
                key: {"available": applicant.get(key) is not None, "required": definition["is_required"]}
                for key, definition in definitions_by_key.items()
            },
        },
        "feature_provenance": [
            {key: value for key, value in item.items() if key != "scale"}
            for item in definitions
        ],
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
            if not _http_service_approved():
                self._send_json(503, {
                    "status": "unavailable",
                    "ready": False,
                    "backend": "tabpfn_pytorch",
                    "message": INFERENCE_UNAVAILABLE_MESSAGE,
                })
                return
            with _INFERENCE_LOCK:
                try:
                    _classifier, provenance = _ready_classifier()
                    if not _http_provenance_matches_pins(provenance):
                        raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE)
                except InferenceUnavailableError:
                    self._send_json(503, {
                        "status": "unavailable",
                        "ready": False,
                        "backend": "tabpfn_pytorch",
                        "message": INFERENCE_UNAVAILABLE_MESSAGE,
                    })
                else:
                    self._send_json(200, {
                        "status": "ready",
                        "ready": True,
                        "backend": "tabpfn_pytorch",
                        "provenance": provenance,
                    })
            return
        self._send_json(404, {"error": "not_found"})

    def do_POST(self) -> None:
        if self.path != "/inference/tabpfn":
            self._send_json(404, {"error": "not_found"})
            return
        if not _http_service_approved():
            self._send_json(503, {
                "status": "unavailable",
                "message": INFERENCE_UNAVAILABLE_MESSAGE,
            })
            return
        try:
            content_length = int(self.headers.get("Content-Length", "0"))
            if content_length <= 0 or content_length > 2_000_000:
                raise PayloadValidationError("Content-Length must be between 1 and 2000000 bytes")
            payload = json.loads(self.rfile.read(content_length))
            result = evaluate_pairwise_tabpfn(payload)
            if not _http_provenance_matches_pins(result["provenance"]):
                raise InferenceUnavailableError(INFERENCE_UNAVAILABLE_MESSAGE)
        except InferenceUnavailableError as error:
            self._send_json(503, {"status": "unavailable", "message": str(error)})
            return
        except (PayloadValidationError, json.JSONDecodeError, UnicodeDecodeError) as error:
            self._send_json(400, {"error": "invalid_request", "message": str(error)})
            return
        except Exception:
            self._send_json(503, {
                "status": "unavailable",
                "message": INFERENCE_UNAVAILABLE_MESSAGE,
            })
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
