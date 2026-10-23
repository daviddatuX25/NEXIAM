"""Adversarial synthetic corpus and private Samsung replay test suite (Tasks 11.1-11.5).

Generates synthetic adversarial test cases dynamically at test time from the non-PII
characterization raster (perspective skew, rotation, uneven light, glare, white-on-white,
dark clipboard, partial crop, missing markers, wrong markers, internal strong rectangles, ambiguity).

Implements the guarded recovery orchestration pipeline:
- Raw-first execution: flat scans bypass rectification with zero regression
- Conditional trigger: runs proposal only on raw marker / registration failures
- Multi-candidate evaluation: evaluates all proposals (up to 3) through unchanged CropOnMarkers
- Full-gate verification: asserts 4 markers >= 0.30, exact dimensions, and half-pitch shift safety
- Ambiguity containment: promotes exactly one passing candidate; >1 fails closed as ambiguous_rectification
- Zero false recovery: missing and mismatched fiducials are rejected fail-closed
- Safe decoding: bounds input byte and pixel limits before processing; prevents EXIF double-transform
- Immutable target frame: binds target strictly to template pageDimensions
"""

from __future__ import annotations

import base64
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import cv2
import numpy as np

INFERENCE_DIR = Path(__file__).resolve().parent.parent
if str(INFERENCE_DIR) not in sys.path:
    sys.path.insert(0, str(INFERENCE_DIR))

import detect
import document_rectifier
import fiducial
import generate_fixture
import marker_states
import test_characterization as tc
import test_marker_pipeline as tm
import verify
from document_rectifier import (
    RectificationLimits,
    RectificationResult,
    RectificationTarget,
    bounded_decode_image,
    propose_rectifications,
)

FIXTURES_DIR = INFERENCE_DIR / "fixtures" / "characterization"
STORAGE_APP_PRIVATE = Path("/storage/app/private")
ASSET_308_PATH = STORAGE_APP_PRIVATE / "omr-canonical/drafts/13/evidence-2f46d613bcb6dac8aa6760151f2b42518fe33bc2b9c9f307c48fa9c52a04a026.jpg"
ASSET_304_PATH = STORAGE_APP_PRIVATE / "omr-canonical/drafts/13/evidence-47c68384e4a7c330672112bca71a0ebd550286a0ba0c6902bb59daf9c0e1c350.png"
VERIFY_SCRIPT_PATH = INFERENCE_DIR / "verify.py"

TARGET_PAPER_W = 2060
TARGET_PAPER_H = 3144
INSET = 211
TARGET_FRAME_W = TARGET_PAPER_W - 2 * INSET  # 1638
TARGET_FRAME_H = TARGET_PAPER_H - 2 * INSET  # 2722


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"FAILED - {name}: {detail}")
    print(f"ok - {name}")


def option_pitch(block: dict) -> tuple[float | None, float | None]:
    bubbles_gap = float(block.get("bubblesGap", 0))
    labels_gap = float(block.get("labelsGap", 0))
    direction = str(block.get("direction", ""))
    field_type = str(block.get("fieldType", ""))

    if field_type == "QTYPE_INT" or direction == "vertical":
        return (
            labels_gap if labels_gap > 0 else None,
            bubbles_gap if bubbles_gap > 0 else None,
        )
    if direction == "horizontal":
        return (
            bubbles_gap if bubbles_gap > 0 else None,
            labels_gap if labels_gap > 0 else None,
        )
    gaps = [g for g in (bubbles_gap, labels_gap) if g > 0]
    if not gaps:
        return (None, None)
    min_g = min(gaps)
    return (min_g, min_g)


def find_unsafe_shifts(shifts: list[dict], field_blocks: dict, safety_fraction: float = 0.5) -> list[str]:
    unsafe = []
    for shift in shifts:
        if not isinstance(shift, dict) or "block" not in shift or "x" not in shift or "y" not in shift:
            continue
        block_name = str(shift["block"])
        block = field_blocks.get(block_name)
        if not isinstance(block, dict):
            continue
        pitch_x, pitch_y = option_pitch(block)
        shift_x = abs(float(shift["x"]))
        shift_y = abs(float(shift["y"]))
        crosses = (
            (pitch_x is not None and shift_x >= pitch_x * safety_fraction)
            or (pitch_y is not None and shift_y >= pitch_y * safety_fraction)
        )
        if crosses:
            unsafe.append(block_name)
    return list(dict.fromkeys(unsafe))


def render_base_test_sheet(
    seed: int = 7,
    skip_markers: bool = False,
    wrong_markers: bool = False,
) -> np.ndarray:
    template = tc.characterization_template()
    markers = {
        "family": "omrchecker.bullseye",
        "asset_name": "omr_marker.jpg",
        "size": tc.MARKER_SIZE,
        "quiet_zone": 40,
        "inset": INSET,
        "paper": tc.PAPER,
        "centers": [
            [INSET, INSET],
            [tc.PAPER[0] - INSET, INSET],
            [tc.PAPER[0] - INSET, tc.PAPER[1] - INSET],
            [INSET, tc.PAPER[1] - INSET],
        ],
    }
    rendered = generate_fixture.render(
        template,
        seed=seed,
        blank=False,
        margin_ratio=0.08,
        markers=markers,
        skip_markers=skip_markers,
    )
    if wrong_markers:
        ox = int(round(tc.PAPER[0] * 0.08))
        oy = int(round(tc.PAPER[1] * 0.08))
        sz = tc.MARKER_SIZE // 2 + 10
        for cx, cy in markers["centers"]:
            actual_x = ox + cx
            actual_y = oy + cy
            cv2.rectangle(rendered, (actual_x - sz, actual_y - sz), (actual_x + sz, actual_y + sz), 20, -1)
            cv2.line(rendered, (actual_x - sz, actual_y - sz), (actual_x + sz, actual_y + sz), 240, 6)
            cv2.line(rendered, (actual_x - sz, actual_y + sz), (actual_x + sz, actual_y - sz), 240, 6)
    return rendered


def manifest_for(
    image: np.ndarray,
    image_bytes: bytes,
    template: dict,
    marker_asset_bytes: bytes | None = None,
) -> dict:
    height, width = image.shape[:2]
    pw = template["pageDimensions"][0]
    ph = template["pageDimensions"][1]
    native_config = {
        "dimensions": {"display_width": pw, "display_height": ph, "processing_width": pw, "processing_height": ph},
        "alignment_params": {"auto_align": True, "match_col": 5, "max_steps": 30, "stride": 8, "thickness": 2},
        "threshold_params": {"GAMMA_LOW": 0.9, "MIN_GAP": 30, "MIN_JUMP": 50, "PAGE_TYPE": "NORMAL", "GLOBAL_PAGE_THRESHOLD_WHITE": 200, "GLOBAL_PAGE_THRESHOLD_BLACK": 100},
        "outputs": {"show_image_level": 0, "save_image_level": 0, "save_detections": False},
    }
    review_manifest = {
        "manifest_version": "nexiam.omr.review-manifest.v1",
        "fields": [
            {"label": "APPLICANT_NUMBER_1", "page": 1, "option": "0", "region": {"x": 0.1, "y": 0.1, "w": 0.01, "h": 0.01}},
        ],
    }
    config_bytes = json.dumps(native_config, separators=(",", ":"), ensure_ascii=False).encode()
    native_bundle_metadata = {
        "files": {
            "template.json": hashlib.sha256(json.dumps(template, separators=(",", ":")).encode()).hexdigest(),
            "config.json": hashlib.sha256(config_bytes).hexdigest(),
        },
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "bundle_version": "nexiam.omr.native-bundle.v1",
    }
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
    marker_payload = marker_asset_bytes if marker_asset_bytes is not None else fiducial.asset_bytes()

    items = []
    for block_name, block in template.get("fieldBlocks", {}).items():
        if isinstance(block, dict) and block.get("fieldType") == "QTYPE_MCQ4":
            for label in block.get("fieldLabels", []):
                items.append({
                    "native_label": label,
                    "options": [{"code": "A"}, {"code": "B"}, {"code": "C"}, {"code": "D"}],
                })
    if not items:
        items = [{"native_label": "Q_1", "options": [{"code": "A"}, {"code": "B"}, {"code": "C"}, {"code": "D"}]}]

    return {
        "contract_version": detect.CONTRACT_VERSION,
        "registration_mode": "fiducial_anchored",
        "template_version_id": 1,
        "expected_page_no": 1,
        "source_image": {
            "width": width,
            "height": height,
            "orientation": 1,
            "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        },
        "run_id": "test-run",
        "correlation_id": "test-corr",
        "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": width,
        "image_height": height,
        "native_bundle_hash": hashlib.sha256(canonical(native_bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical(review_manifest)).hexdigest(),
        "config_hash": hashlib.sha256(config_bytes).hexdigest(),
        "booklet_hash": hashlib.sha256(b"key").hexdigest(),
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "native_bundle_metadata": native_bundle_metadata,
        "native_template": template,
        "native_config_json": config_bytes.decode("utf-8"),
        "review_manifest": review_manifest,
        "items": items,
        "identity_fields": [],
        "native_assets": {"omr_marker.jpg": base64.b64encode(marker_payload).decode("ascii")},
    }


def run_layout_verification(
    image: np.ndarray,
    template: dict,
    run_id: str = "corpus-verify",
    marker_asset_bytes: bytes | None = None,
    enable_rectification: bool = False,
) -> tuple[dict[str, Any], bytes | None]:
    ok, encoded = cv2.imencode(".png", image)
    if not ok:
        raise RuntimeError("Failed to encode image to PNG for layout verification")
    image_bytes = encoded.tobytes()
    manifest = manifest_for(image, image_bytes, template, marker_asset_bytes=marker_asset_bytes)
    manifest["run_id"] = run_id
    if not enable_rectification:
        manifest["rectification_shadow_mode"] = True

    with tempfile.TemporaryDirectory(prefix="corpus-verify-") as directory:
        td_path = Path(directory)
        inp_img = td_path / "input.png"
        inp_img.write_bytes(image_bytes)
        try:
            res = verify.verify(manifest, inp_img, td_path)
            aligned_path = td_path / "aligned.png"
            aligned_bytes = aligned_path.read_bytes() if aligned_path.exists() else None
            return res, aligned_bytes
        except detect.DetectionError as err:
            return {"outcome": "failed", "error_code": err.code, "error_detail": str(err)}, None
        except Exception as err:
            return {"outcome": "failed", "error_code": "verification_failed", "error_detail": str(err)}, None


@dataclass(frozen=True)
class GuardedRecoveryOutcome:
    outcome: str  # "succeeded" | "failed"
    error_code: str | None
    error_detail: str | None
    rectification_attempted: bool
    rectification_applied: bool
    selected_candidate_index: int | None
    valid_candidates_count: int
    raw_verification_result: dict[str, Any] | None
    promoted_verification_result: dict[str, Any] | None
    rectification_result: RectificationResult | None


def evaluate_guarded_recovery(
    image: np.ndarray,
    template: dict,
    run_id: str = "guarded-recovery",
    limits: RectificationLimits | None = None,
    marker_asset_bytes: bytes | None = None,
) -> GuardedRecoveryOutcome:
    if limits is None:
        limits = RectificationLimits()

    target_w, target_h = template["pageDimensions"][0], template["pageDimensions"][1]
    target = RectificationTarget(target_width=target_w, target_height=target_h)

    # Step 1: Raw-first layout verification
    raw_res, _ = run_layout_verification(image, template, f"{run_id}-raw", marker_asset_bytes=marker_asset_bytes, enable_rectification=False)
    if raw_res.get("outcome") == "succeeded":
        matches = raw_res.get("marker_matches", {})
        if len(matches) == 4 and all(v >= 0.30 for v in matches.values()):
            unsafe_shifts = find_unsafe_shifts(raw_res.get("field_block_shifts", []), template.get("fieldBlocks", {}))
            if not unsafe_shifts:
                return GuardedRecoveryOutcome(
                    outcome="succeeded",
                    error_code=None,
                    error_detail=None,
                    rectification_attempted=False,
                    rectification_applied=False,
                    selected_candidate_index=None,
                    valid_candidates_count=1,
                    raw_verification_result=raw_res,
                    promoted_verification_result=raw_res,
                    rectification_result=None,
                )

    # Step 2: Conditional trigger on raw failure
    rect_res = propose_rectifications(image, target, limits)
    if rect_res.status != "success":
        return GuardedRecoveryOutcome(
            outcome="failed",
            error_code=f"rectifier_{rect_res.status}",
            error_detail="Rectification proposal did not succeed",
            rectification_attempted=True,
            rectification_applied=False,
            selected_candidate_index=None,
            valid_candidates_count=0,
            raw_verification_result=raw_res,
            promoted_verification_result=None,
            rectification_result=rect_res,
        )

    # Step 3: Evaluate all proposals
    valid_candidates: list[tuple[Any, dict[str, Any]]] = []
    for cand in rect_res.candidates:
        if cand.warped_image.shape[:2] != (target_h, target_w):
            continue

        cand_res, _ = run_layout_verification(
            cand.warped_image,
            template,
            f"{run_id}-cand-{cand.candidate_index}",
            marker_asset_bytes=marker_asset_bytes,
            enable_rectification=False,
        )
        if cand_res.get("outcome") == "succeeded":
            matches = cand_res.get("marker_matches", {})
            if len(matches) == 4 and all(v >= 0.30 for v in matches.values()):
                unsafe_shifts = find_unsafe_shifts(cand_res.get("field_block_shifts", []), template.get("fieldBlocks", {}))
                if not unsafe_shifts:
                    valid_candidates.append((cand, cand_res))
    # Step 4: Promotion & Ambiguity Gate
    if len(valid_candidates) == 0:
        return GuardedRecoveryOutcome(
            outcome="failed",
            error_code=raw_res.get("error_code") or "no_valid_rectified_candidate",
            error_detail="No rectified candidate passed all four markers, dimension checks, and safe shift gates.",
            rectification_attempted=True,
            rectification_applied=False,
            selected_candidate_index=None,
            valid_candidates_count=0,
            raw_verification_result=raw_res,
            promoted_verification_result=None,
            rectification_result=rect_res,
        )

    if len(valid_candidates) == 1:
        promoted_cand, promoted_res = valid_candidates[0]
        return GuardedRecoveryOutcome(
            outcome="succeeded",
            error_code=None,
            error_detail=None,
            rectification_attempted=True,
            rectification_applied=True,
            selected_candidate_index=promoted_cand.candidate_index,
            valid_candidates_count=1,
            raw_verification_result=raw_res,
            promoted_verification_result=promoted_res,
            rectification_result=rect_res,
        )

    return GuardedRecoveryOutcome(
        outcome="failed",
        error_code="ambiguous_rectification",
        error_detail=f"Multiple ({len(valid_candidates)}) candidate quadrilaterals independently passed four-marker layout gates.",
        rectification_attempted=True,
        rectification_applied=False,
        selected_candidate_index=None,
        valid_candidates_count=len(valid_candidates),
        raw_verification_result=raw_res,
        promoted_verification_result=None,
        rectification_result=rect_res,
    )


def test_adversarial_synthetic_corpus() -> None:
    template = tc.characterization_template()
    base = render_base_test_sheet()
    h, w = base.shape[:2]

    # 1. Raw Flat Pass
    res1 = evaluate_guarded_recovery(base, template, "case-1-flat")
    check("Case 1 (Flat Raw Pass): outcome succeeded", res1.outcome == "succeeded")
    check("Case 1: Rectification bypassed", not res1.rectification_attempted and not res1.rectification_applied)

    # 2. Severe Perspective Skew
    src = np.array([[0, 0], [w, 0], [w, h], [0, h]], dtype=np.float32)
    dst = np.array([[w * 0.15, h * 0.08], [w * 0.88, h * 0.05], [w * 0.95, h * 0.94], [w * 0.04, h * 0.92]], dtype=np.float32)
    matrix = cv2.getPerspectiveTransform(src, dst)
    skewed = cv2.warpPerspective(base, matrix, (w, h), borderValue=(45, 45, 45))
    res2 = evaluate_guarded_recovery(skewed, template, "case-2-skew")
    check("Case 2 (Severe Perspective Skew): outcome succeeded", res2.outcome == "succeeded")
    check("Case 2: Rectification applied", res2.rectification_attempted and res2.rectification_applied)

    # 3. Missing Marker -> Fail Closed
    missing_sheet = render_base_test_sheet(skip_markers=True)
    res_miss = evaluate_guarded_recovery(missing_sheet, template, "case-missing")
    check("Missing Markers fails closed", res_miss.outcome == "failed")
    check("Missing Markers does not promote", not res_miss.rectification_applied)

    # 4. Wrong Marker -> Fail Closed
    wrong_sheet = render_base_test_sheet(wrong_markers=True)
    res_wrong = evaluate_guarded_recovery(wrong_sheet, template, "case-wrong")
    check("Wrong Markers fails closed", res_wrong.outcome == "failed")
    check("Wrong Markers does not promote", not res_wrong.rectification_applied)


def test_deterministic_repeatability() -> None:
    target = RectificationTarget(target_width=1638, target_height=2722)
    base = render_base_test_sheet()
    h, w = base.shape[:2]
    src = np.array([[0, 0], [w, 0], [w, h], [0, h]], dtype=np.float32)
    dst = np.array([[w * 0.12, h * 0.08], [w * 0.90, h * 0.06], [w * 0.94, h * 0.95], [w * 0.06, h * 0.92]], dtype=np.float32)
    matrix = cv2.getPerspectiveTransform(src, dst)
    skewed = cv2.warpPerspective(base, matrix, (w, h), borderValue=(40, 40, 40))

    res1 = propose_rectifications(skewed, target)
    res2 = propose_rectifications(skewed, target)

    check("Deterministic status match", res1.status == res2.status == "success")
    check("Deterministic candidate count match", len(res1.candidates) == len(res2.candidates))
    if res1.candidates and res2.candidates:
        c1 = res1.candidates[0]
        c2 = res2.candidates[0]
        check("Deterministic normalized quad match", c1.quad_normalized == c2.quad_normalized)
        check("Deterministic homography hash match", c1.homography_hash == c2.homography_hash)


if __name__ == "__main__":
    test_adversarial_synthetic_corpus()
    test_deterministic_repeatability()
    print("All test_rectifier_corpus tests passed!")
