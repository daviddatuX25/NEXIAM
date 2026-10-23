"""Internal guarded document rectification recovery coordination.

Coordinates the secondary recovery flow when raw CropOnMarkers fiducial
registration fails on marker-bearing OMR templates. Enforces strict recovery
invariants:
  - Raw marker registration (all 4 markers >= 0.30) is authoritative and
    bypasses this module entirely.
  - Proposes candidates up to configured limits on downsampled frames.
  - Endpoint-specific candidate evaluation runs via a callback.
  - Uniquely passing candidates are promoted with canonical metadata.
  - Multiple valid candidates fail closed with code 'ambiguous_rectification'
    and reason 'multiple_valid_candidates'.
  - Timeouts, internal exceptions, and zero valid candidates preserve the raw
    typed marker failure state and record the exact reason in metadata.
  - Shadow mode logs telemetry on failed raw capture without promoting.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Any, Callable

import numpy as np
import document_rectifier

RECOVERY_TRIGGER_CODES = frozenset({"marker_missing", "marker_mismatch", "sheet_partial"})

class RecoveryRejected(Exception):
    """Raised when guarded recovery is rejected (shadow mode, ambiguity, or failure to recover)."""

    def __init__(self, code: str, detail: str, rectification: dict | None = None):
        super().__init__(detail)
        self.code = code
        self.detail = detail
        self.rectification = rectification


@dataclass(frozen=True)
class EvaluatedCandidate:
    """A candidate quadrilateral proposal evaluated through endpoint gates."""

    candidate: document_rectifier.RectifiedCandidate
    marker_matches: dict[str, float]
    payload: Any = None


def base_rectification_metadata() -> dict[str, object]:
    """Base metadata for raw-pass success when rectification was not attempted."""
    return {
        "attempted": False,
        "applied": False,
    }


def marker_frame_from_manifest(
    manifest: dict,
    target_width: int,
    target_height: int,
) -> tuple[tuple[float, float], tuple[float, float], tuple[float, float], tuple[float, float]] | None:
    """Return normalized printed-paper marker centers from canonical metadata."""
    native_template = manifest.get("native_template")
    markers = native_template.get("markers") if isinstance(native_template, dict) else None
    if not isinstance(markers, dict) and isinstance(native_template, dict):
        pages = native_template.get("pages")
        first_page = pages[0] if isinstance(pages, list) and pages and isinstance(pages[0], dict) else None
        markers = first_page.get("markers") if isinstance(first_page, dict) else None
    insets = markers.get("insets_px") if isinstance(markers, dict) else None
    if not isinstance(insets, dict):
        metadata = manifest.get("native_bundle_metadata")
        insets = metadata.get("rectification_marker_insets_px") if isinstance(metadata, dict) else None
    if not isinstance(insets, dict):
        return None
    top = float(insets.get("top", 0))
    right = float(insets.get("right", 0))
    bottom = float(insets.get("bottom", 0))
    left = float(insets.get("left", 0))
    if min(top, right, bottom, left) <= 0:
        return None
    paper_width = float(target_width) + left + right
    paper_height = float(target_height) + top + bottom
    return (
        (left / paper_width, top / paper_height),
        ((left + target_width) / paper_width, top / paper_height),
        ((left + target_width) / paper_width, (top + target_height) / paper_height),
        (left / paper_width, (top + target_height) / paper_height),
    )


def evaluate_guarded_recovery(
    image: np.ndarray,
    manifest: dict,
    raw_matches: dict[str, float],
    raw_state: dict,
    target_width: int,
    target_height: int,
    evaluate_candidate: Callable[[document_rectifier.RectifiedCandidate], EvaluatedCandidate | None],
    limits: document_rectifier.RectificationLimits | None = None,
) -> tuple[EvaluatedCandidate, dict[str, object]]:
    """Execute guarded document rectification recovery and return the promoted candidate.

    Raises:
        RecoveryRejected: on shadow mode, multiple candidate ambiguity,
        zero valid candidates, rectifier timeout, or rectifier exception.
    """
    if raw_state.get("code") not in RECOVERY_TRIGGER_CODES:
        raise RecoveryRejected(
            raw_state.get("code", "preprocessing_failed"),
            raw_state.get("message", "Image preprocessing failed.") + f" (orientation_hint={raw_state.get('orientation_hint', 'upright')})",
            rectification=base_rectification_metadata(),
        )

    shadow_mode = bool(
        manifest.get("rectification_shadow_mode", False)
        or os.environ.get("OMR_RECTIFICATION_SHADOW_MODE", "false").lower() == "true"
    )

    rect_target = document_rectifier.RectificationTarget(
        target_width=target_width,
        target_height=target_height,
        marker_frame=marker_frame_from_manifest(manifest, target_width, target_height),
    )
    if limits is None:
        limits = document_rectifier.RectificationLimits(
            timeout_seconds=0.25,
            max_candidates=3,
            max_downsample_dim=1024,
        )

    try:
        rect_res = document_rectifier.propose_rectifications(image, rect_target, limits)
    except Exception as exc:
        rect_res = document_rectifier.RectificationResult(
            detector_version=document_rectifier.RECTIFIER_VERSION,
            status="rectifier_exception",
            input_shape=(image.shape[0], image.shape[1], 1 if image.ndim == 2 else image.shape[2]),
            target_dimensions=(target_width, target_height),
            candidates=[],
            diagnostics={"error": str(exc), "timings_ms": {"total": 0.0}},
        )

    duration_ms = float(rect_res.diagnostics.get("timings_ms", {}).get("total", 0.0))

    valid_candidates: list[EvaluatedCandidate] = []
    if rect_res.status == "success":
        for cand in rect_res.candidates:
            if cand.warped_image.shape[:2] != (target_height, target_width):
                continue
            try:
                evaluated = evaluate_candidate(cand)
                if evaluated is not None:
                    valid_candidates.append(evaluated)
            except Exception:
                continue

    if shadow_mode:
        meta = {
            "attempted": True,
            "applied": False,
            "detector_version": rect_res.detector_version,
            "status": rect_res.status,
            "candidate_count": len(rect_res.candidates),
            "valid_candidate_count": len(valid_candidates),
            "raw_marker_matches": raw_matches,
            "duration_ms": duration_ms,
            "shadow_mode": True,
            "reason": "shadow_mode",
        }
        raise RecoveryRejected(
            raw_state["code"],
            raw_state["message"] + f" (orientation_hint={raw_state['orientation_hint']})",
            rectification=meta,
        )

    if len(valid_candidates) == 1:
        promoted = valid_candidates[0]
        cand = promoted.candidate
        meta = {
            "attempted": True,
            "applied": True,
            "detector_version": rect_res.detector_version,
            "status": rect_res.status,
            "candidate_count": len(rect_res.candidates),
            "selected_candidate_index": int(cand.candidate_index),
            "selection_reason": "unique_four_marker_pass",
            "quad_normalized": cand.quad_normalized,
            "quad_area_ratio": float(cand.area_ratio),
            "quad_edge_score": float(cand.edge_score),
            "homography_hash": cand.homography_hash,
            "input_dimensions": [int(image.shape[1]), int(image.shape[0])],
            "output_dimensions": [int(target_width), int(target_height)],
            "raw_marker_matches": raw_matches,
            "rectified_marker_matches": promoted.marker_matches,
            "duration_ms": duration_ms,
        }
        return promoted, meta

    if len(valid_candidates) > 1:
        meta = {
            "attempted": True,
            "applied": False,
            "detector_version": rect_res.detector_version,
            "status": rect_res.status,
            "candidate_count": len(rect_res.candidates),
            "valid_candidate_count": len(valid_candidates),
            "raw_marker_matches": raw_matches,
            "duration_ms": duration_ms,
            "reason": "multiple_valid_candidates",
        }
        raise RecoveryRejected(
            "ambiguous_rectification",
            f"Multiple ({len(valid_candidates)}) candidate quadrilaterals independently passed four-marker layout gates.",
            rectification=meta,
        )

    if rect_res.status == "timeout":
        reason = "rectifier_timeout"
    elif rect_res.status == "rectifier_exception":
        reason = "rectifier_exception"
    else:
        reason = "no_valid_candidate"

    meta = {
        "attempted": True,
        "applied": False,
        "detector_version": rect_res.detector_version,
        "status": rect_res.status,
        "candidate_count": len(rect_res.candidates),
        "valid_candidate_count": 0,
        "raw_marker_matches": raw_matches,
        "duration_ms": duration_ms,
        "reason": reason,
    }
    raise RecoveryRejected(
        raw_state["code"],
        raw_state["message"] + f" (orientation_hint={raw_state['orientation_hint']})",
        rectification=meta,
    )
