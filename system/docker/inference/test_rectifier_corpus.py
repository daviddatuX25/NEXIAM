"""Adversarial synthetic corpus and private Samsung replay test suite (Tasks 11.1-11.3).

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

Usage:
    python3 test_rectifier_corpus.py
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
from unittest.mock import patch
import cv2
import numpy as np

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

FIXTURES_DIR = Path(__file__).resolve().parent / "fixtures" / "characterization"
STORAGE_APP_PRIVATE = Path("/storage/app/private")
ASSET_308_PATH = STORAGE_APP_PRIVATE / "omr-canonical/drafts/13/evidence-2f46d613bcb6dac8aa6760151f2b42518fe33bc2b9c9f307c48fa9c52a04a026.jpg"
ASSET_304_PATH = STORAGE_APP_PRIVATE / "omr-canonical/drafts/13/evidence-47c68384e4a7c330672112bca71a0ebd550286a0ba0c6902bb59daf9c0e1c350.png"
VERIFY_SCRIPT_PATH = Path(__file__).resolve().with_name("verify.py")

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
    """Native option pitch in pixels for one field block.

    Port of OmrDetectionResultValidator::optionPitch:
    - QTYPE_INT or vertical: [labelsGap, bubblesGap]
    - horizontal: [bubblesGap, labelsGap]
    - unknown: [min(gaps), min(gaps)]
    """
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
    """Port of OmrDetectionResultValidator::unsafeFieldBlockShifts.

    A shift reaching half the option pitch is unsafe and emits sheet_misaligned.
    """
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
    """Render a deterministic non-PII characterization sheet (paper + margin)."""
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
            # Draw solid dark square with an inner X that fails concentric bullseye template match
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
    """Construct a complete typed detection manifest for verify.py."""
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
    """Execute read-only CropOnMarkers layout verification on an image."""
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
    """Outcome of guarded document recovery pipeline."""

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
    """Execute the guarded document recovery pipeline:

    1. Raw-first check: run CropOnMarkers layout verification directly on raw image.
       If raw succeeds with all 4 markers >= 0.30 and all shifts safe:
       return outcome='succeeded', rectification={attempted: False, applied: False}.
    2. Conditional trigger: on raw failure (or low markers / unsafe shifts), invoke propose_rectifications.
    3. Multi-candidate evaluation: evaluate all proposals (up to max 3) through CropOnMarkers.
       - verify marker count == 4 and all quadrant scores >= 0.30
       - verify aligned dimensions match target dimensions exactly
       - verify all field-block shifts are safe (< 0.5 * option pitch)
    4. Ambiguity / Promotion gate:
       - 0 valid: preserve raw failure / return no_valid_rectified_candidate fail closed
       - 1 valid: promote that unique passing result with rectification={attempted: True, applied: True}
       - >1 valid: reject fail closed with error_code='ambiguous_rectification'
    """
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
        # Check warped dimensions
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

    # Multi-candidate ambiguity -> reject fail-closed
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


# ---------------------------------------------------------------------------
# Synthetic Adversarial Corpus Generators
# ---------------------------------------------------------------------------


def apply_perspective_distortion(
    image: np.ndarray,
    width_skew_pct: float = 0.15,
    height_skew_pct: float = 0.12,
) -> np.ndarray:
    """Apply realistic trapezoidal perspective distortion (severe perspective ~15% / ~12%)."""
    h, w = image.shape[:2]
    dw = int(round(w * width_skew_pct))
    dh = int(round(h * height_skew_pct))

    src_pts = np.array([[0, 0], [w - 1, 0], [w - 1, h - 1], [0, h - 1]], dtype=np.float32)
    dst_pts = np.array([[dw, dh], [w - 1, 0], [w - 1 - dw, h - 1], [0, h - 1 - dh]], dtype=np.float32)
    desk_margin = 120
    canvas_w = w + 2 * desk_margin
    canvas_h = h + 2 * desk_margin
    canvas = np.full((canvas_h, canvas_w, 3) if image.ndim == 3 else (canvas_h, canvas_w), 80, dtype=np.uint8)

    dst_pts_shifted = dst_pts + np.array([desk_margin, desk_margin], dtype=np.float32)
    M = cv2.getPerspectiveTransform(src_pts, dst_pts_shifted)
    warped = cv2.warpPerspective(
        image,
        M,
        (canvas_w, canvas_h),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_CONSTANT,
        borderValue=(80, 80, 80) if image.ndim == 3 else 80,
    )
    return warped


def apply_uneven_light(image: np.ndarray) -> np.ndarray:
    """Apply a smooth lighting / shadow gradient across the sheet."""
    h, w = image.shape[:2]
    gx = np.linspace(0.45, 1.0, w, dtype=np.float32).reshape(1, w, 1) if image.ndim == 3 else np.linspace(0.45, 1.0, w, dtype=np.float32).reshape(1, w)
    gy = np.linspace(0.45, 1.0, h, dtype=np.float32).reshape(h, 1, 1) if image.ndim == 3 else np.linspace(0.45, 1.0, h, dtype=np.float32).reshape(h, 1)
    grad = (gy + gx) * 0.5
    return np.clip(image.astype(np.float32) * grad, 0, 255).astype(np.uint8)


def apply_glare_highlight(image: np.ndarray) -> np.ndarray:
    """Apply a localized specular glare highlight near the center."""
    h, w = image.shape[:2]
    cx, cy = int(w * 0.4), int(h * 0.35)
    radius = int(min(w, h) * 0.25)
    glare_small = np.zeros((128, 128), dtype=np.float32)
    cv2.circle(glare_small, (64, 64), 50, 1.0, -1)
    glare_small = cv2.GaussianBlur(glare_small, (31, 31), 0)
    glare_mask = cv2.resize(glare_small, (radius * 2, radius * 2))
    x0, y0 = max(0, cx - radius), max(0, cy - radius)
    x1, y1 = min(w, cx + radius), min(h, cy + radius)
    crop = image[y0:y1, x0:x1].astype(np.float32)
    mask_crop = glare_mask[: y1 - y0, : x1 - x0]
    if image.ndim == 3:
        mask_crop = mask_crop[:, :, np.newaxis]
    res = image.copy()
    res[y0:y1, x0:x1] = np.clip(crop + 160.0 * mask_crop, 0, 255).astype(np.uint8)
    return res


def create_white_on_white_fixture(sheet: np.ndarray) -> tuple[np.ndarray, list[tuple[float, float]]]:
    """Place sheet on a nearly identical white/cream table background (low edge contrast)."""
    h, w = sheet.shape[:2]
    margin = 80
    bg = np.full((h + 2 * margin, w + 2 * margin, 3) if sheet.ndim == 3 else (h + 2 * margin, w + 2 * margin), 235, dtype=np.uint8)
    bg[margin : margin + h, margin : margin + w] = sheet
    true_corners = [(float(margin), float(margin)), (float(margin + w - 1), float(margin)),
                    (float(margin + w - 1), float(margin + h - 1)), (float(margin), float(margin + h - 1))]
    return bg, true_corners


def create_dark_clipboard_fixture(sheet: np.ndarray) -> np.ndarray:
    """Place sheet on a dark brown/black clipboard background."""
    h, w = sheet.shape[:2]
    margin = 100
    clip = np.full((h + 2 * margin, w + 2 * margin, 3) if sheet.ndim == 3 else (h + 2 * margin, w + 2 * margin), 35, dtype=np.uint8)
    cv2.rectangle(clip, (w // 2 - 150 + margin, 15), (w // 2 + 150 + margin, margin + 40), (160, 160, 160) if sheet.ndim == 3 else 160, -1)
    clip[margin : margin + h, margin : margin + w] = sheet
    return clip


def create_internal_strong_rectangle_fixture(sheet: np.ndarray) -> np.ndarray:
    """Embed high-contrast internal boxes inside the sheet."""
    modified = sheet.copy()
    h, w = modified.shape[:2]
    cv2.rectangle(modified, (int(w * 0.15), int(h * 0.1)), (int(w * 0.85), int(h * 0.3)), (20, 20, 20) if modified.ndim == 3 else 20, 8)
    return modified


def create_ambiguous_two_sheet_fixture(sheet: np.ndarray) -> np.ndarray:
    """Place two scaled sheets side-by-side to create an ambiguous multi-quad input."""
    h, w = sheet.shape[:2]
    small = cv2.resize(sheet, (w // 2, h // 2))
    sh, sw = small.shape[:2]
    canvas_w = sw * 2 + 100
    canvas_h = sh + 100
    canvas = np.full((canvas_h, canvas_w, 3) if sheet.ndim == 3 else (canvas_h, canvas_w), 50, dtype=np.uint8)
    canvas[50 : 50 + sh, 30 : 30 + sw] = small
    canvas[50 : 50 + sh, 70 + sw : 70 + 2 * sw] = small
    return canvas


# ---------------------------------------------------------------------------
# Test Execution
# ---------------------------------------------------------------------------


def test_adversarial_synthetic_corpus() -> None:
    print("--- Section 1: Synthetic Adversarial Corpus Verification ---")
    template = tc.characterization_template()
    limits = RectificationLimits()

    base_sheet = render_base_test_sheet(seed=7, skip_markers=False)
    base_bgr = cv2.cvtColor(base_sheet, cv2.COLOR_GRAY2BGR)

    # 1. Raw flat pass: Raw CropOnMarkers succeeds directly; rectification is bypassed
    res_case1 = evaluate_guarded_recovery(base_bgr, template, "case1-raw-pass", limits)
    check("Case 1: Raw flat pass succeeds directly", res_case1.outcome == "succeeded", str(res_case1.outcome))
    check("Case 1: Rectification is bypassed (attempted=False, applied=False)",
          res_case1.rectification_attempted is False and res_case1.rectification_applied is False)
    check("Case 1: Raw flat pass reports all 4 markers",
          len(res_case1.promoted_verification_result.get("marker_matches", {})) == 4)

    # 2. Perspective distortion: Raw CropOnMarkers fails, Rectifier recovers unique valid layout
    persp_bgr = apply_perspective_distortion(base_bgr, width_skew_pct=0.15, height_skew_pct=0.12)
    res_case2 = evaluate_guarded_recovery(persp_bgr, template, "case2-perspective", limits)
    check("Case 2: Severe perspective raw CropOnMarkers fails", res_case2.raw_verification_result.get("outcome") != "succeeded")
    check("Case 2: Rectifier attempted and applied", res_case2.rectification_attempted is True and res_case2.rectification_applied is True)
    check("Case 2: Promoted rectified result SUCCEEDED", res_case2.outcome == "succeeded", str(res_case2.outcome))
    matches_c2 = res_case2.promoted_verification_result.get("marker_matches", {})
    check("Case 2: All 4 quadrant marker matches >= 0.70", len(matches_c2) == 4 and all(v >= 0.70 for v in matches_c2.values()), str(matches_c2))
    check("Case 2: Unique candidate promoted", res_case2.valid_candidates_count == 1, str(res_case2.valid_candidates_count))

    # 3. Rotation: 90° clockwise
    rot90_img = cv2.rotate(persp_bgr, cv2.ROTATE_90_CLOCKWISE)
    target_rot = RectificationTarget(target_width=tc.PAPER[1] - 2 * INSET, target_height=tc.PAPER[0] - 2 * INSET)
    rect_rot = propose_rectifications(rot90_img, target_rot, limits)
    check("Case 3: Rotated sheet quad detected by rectifier", rect_rot.status == "success" and len(rect_rot.candidates) >= 1)

    # 4. Uneven lighting / shadow gradient
    shadow_img = apply_uneven_light(persp_bgr)
    res_case4 = evaluate_guarded_recovery(shadow_img, template, "case4-shadow", limits)
    check("Case 4: Uneven lighting recovered via guarded pipeline", res_case4.outcome == "succeeded" and res_case4.rectification_applied is True)

    # 5. Glare / specular highlight
    glare_img = apply_glare_highlight(persp_bgr)
    res_case5 = evaluate_guarded_recovery(glare_img, template, "case5-glare", limits)
    check("Case 5: Glare highlight recovered via guarded pipeline", res_case5.outcome == "succeeded" and res_case5.rectification_applied is True)

    # 6. White-on-white (low contrast background): quad detected on true sheet boundary
    wow_img, true_corners = create_white_on_white_fixture(base_bgr)
    target = RectificationTarget(target_width=template["pageDimensions"][0], target_height=template["pageDimensions"][1])
    rect_wow = propose_rectifications(wow_img, target, limits)
    check("Case 6: White-on-white quad detected", rect_wow.status == "success" and len(rect_wow.candidates) >= 1)
    if rect_wow.candidates:
        check("Case 6: Detected quad covers paper area ratio in [0.60..0.80]", 0.60 <= rect_wow.candidates[0].area_ratio <= 0.80)
        wow_verify, _ = run_layout_verification(rect_wow.candidates[0].warped_image, template, "case6-wow-cand")
        check("Case 6: Rectified white-on-white candidate verifies layout", wow_verify.get("outcome") == "succeeded")
    # 7. Dark clipboard background: quad detected on sheet boundary
    clip_img = create_dark_clipboard_fixture(base_bgr)
    res_case7 = evaluate_guarded_recovery(clip_img, template, "case7-clipboard", limits)
    check("Case 7: Dark clipboard sheet recovered and verified", res_case7.outcome == "succeeded")

    # 8. Partial sheet (top 30% cut off): fails closed
    h, w = base_bgr.shape[:2]
    partial_img = base_bgr[int(h * 0.3) :, :]
    res_case8 = evaluate_guarded_recovery(partial_img, template, "case8-partial", limits)
    check("Case 8: Partial sheet fails closed (not falsely recovered)", res_case8.outcome == "failed", str(res_case8.outcome))

    # 9. Missing markers: Quad is detected on paper, BUT layout verification fails (CropOnMarkers rejects missing fiducials)
    sheet_no_markers = render_base_test_sheet(seed=7, skip_markers=True)
    persp_no_markers = apply_perspective_distortion(cv2.cvtColor(sheet_no_markers, cv2.COLOR_GRAY2BGR))
    res_case9 = evaluate_guarded_recovery(persp_no_markers, template, "case9-missing-markers", limits)
    check("Case 9: Missing markers rejected fail-closed (CropOnMarkers rejects)", res_case9.outcome == "failed" and res_case9.valid_candidates_count == 0)

    # 10. Wrong / Mismatched markers: Sheet with non-matching fiducials rejected fail-closed
    sheet_wrong_markers = render_base_test_sheet(seed=7, wrong_markers=True)
    persp_wrong_markers = apply_perspective_distortion(cv2.cvtColor(sheet_wrong_markers, cv2.COLOR_GRAY2BGR))
    res_case10 = evaluate_guarded_recovery(persp_wrong_markers, template, "case10-wrong-markers", limits)
    check("Case 10: Wrong/mismatched markers rejected fail-closed", res_case10.outcome == "failed" and res_case10.valid_candidates_count == 0)

    # 11. Internal strong rectangle: Outer quad prioritized over internal box
    internal_rect_img = create_internal_strong_rectangle_fixture(base_bgr)
    persp_internal = apply_perspective_distortion(internal_rect_img)
    res_case11 = evaluate_guarded_recovery(persp_internal, template, "case11-internal-rect", limits)
    check("Case 11: Outer page quad prioritized over internal box", res_case11.outcome == "succeeded")
    if res_case11.rectification_result and res_case11.rectification_result.candidates:
        check("Case 11: Candidate area covers full page (>0.60)", res_case11.rectification_result.candidates[0].area_ratio >= 0.60)

    # 12. Ambiguity containment: isolate the ambiguity-decision contract
    # When multiple quads pass, fail closed strictly with ambiguous_rectification
    target_w, target_h = template["pageDimensions"][0], template["pageDimensions"][1]
    cand_img = np.zeros((target_h, target_w, 3), dtype=np.uint8)
    c0 = document_rectifier.RectifiedCandidate(
        candidate_index=0,
        quad_original=[(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)],
        quad_normalized=[(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)],
        area_ratio=0.85,
        edge_score=0.9,
        orthogonality_score=0.95,
        aspect_ratio_score=0.95,
        cross_map_count=3,
        contrast_score=0.8,
        composite_score=0.92,
        homography_matrix=[[1.0, 0.0, 0.0], [0.0, 1.0, 0.0], [0.0, 0.0, 1.0]],
        homography_hash="sha256:controlledambigcand0",
        warped_image=cand_img,
        detection_map_source="bgr_edges",
    )
    c1 = document_rectifier.RectifiedCandidate(
        candidate_index=1,
        quad_original=[(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)],
        quad_normalized=[(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)],
        area_ratio=0.84,
        edge_score=0.89,
        orthogonality_score=0.94,
        aspect_ratio_score=0.94,
        cross_map_count=3,
        contrast_score=0.79,
        composite_score=0.91,
        homography_matrix=[[1.0, 0.0, 0.0], [0.0, 1.0, 0.0], [0.0, 0.0, 1.0]],
        homography_hash="sha256:controlledambigcand1",
        warped_image=cand_img,
        detection_map_source="hsv_edges",
    )
    fake_ambig_res = RectificationResult(
        detector_version=document_rectifier.RECTIFIER_VERSION,
        status="success",
        input_shape=(persp_bgr.shape[0], persp_bgr.shape[1], 3),
        target_dimensions=(target_w, target_h),
        candidates=[c0, c1],
        diagnostics={"timings_ms": {"total": 50.0}},
    )
    mock_raw_failure = ({"outcome": "failed", "error_code": "marker_missing", "error_detail": "No corner markers"}, None)
    mock_cand0_success = ({"outcome": "succeeded", "marker_matches": {"quarter_1": 0.85, "quarter_2": 0.85, "quarter_3": 0.85, "quarter_4": 0.85}, "field_block_shifts": []}, b"fake-aligned")
    mock_cand1_success = ({"outcome": "succeeded", "marker_matches": {"quarter_1": 0.84, "quarter_2": 0.84, "quarter_3": 0.84, "quarter_4": 0.84}, "field_block_shifts": []}, b"fake-aligned")

    with patch(f"{__name__}.propose_rectifications", return_value=fake_ambig_res), \
         patch(f"{__name__}.run_layout_verification", side_effect=[mock_raw_failure, mock_cand0_success, mock_cand1_success]):
        res_case12 = evaluate_guarded_recovery(persp_bgr, template, "case12-ambig", limits)
    check(
        "Case 12: Ambiguous multi-sheet scene fails closed strictly with ambiguous_rectification",
        res_case12.outcome == "failed"
        and res_case12.error_code == "ambiguous_rectification"
        and res_case12.valid_candidates_count == 2,
        f"outcome={res_case12.outcome}, code={res_case12.error_code}, valid_count={res_case12.valid_candidates_count}",
    )
    # 13. Timeout and exception containment
    tight_limits = RectificationLimits(timeout_seconds=0.001)
    res_timeout = evaluate_guarded_recovery(persp_bgr, template, "case13-timeout", tight_limits)
    check("Case 13: Tight deadline returns timeout outcome", res_timeout.outcome == "failed" and res_timeout.error_code == "rectifier_timeout")


def test_private_samsung_replay() -> dict[str, Any]:
    print("\n--- Section 2: Private Samsung Scan (Asset 308) Replay ---")
    replay_results: dict[str, Any] = {
        "asset_308_available": False,
        "raw_pass_bypassed": False,
        "rectified_recovery_passed": False,
        "marker_matches": {},
        "normalized_quad": [],
        "homography_hash": "",
        "shifts": [],
        "unsafe_shifts": [],
        "timings_ms": {},
    }

    if not ASSET_308_PATH.is_file():
        print(f"[SKIP] Private Asset 308 replay skipped: Asset 308 not found at {ASSET_308_PATH}. Private qualification unavailable in this environment.")
        return replay_results
    replay_results["asset_308_available"] = True
    b308 = ASSET_308_PATH.read_bytes()

    # Bounded decode with IMREAD_IGNORE_ORIENTATION to prevent EXIF double-transform
    raw_img = bounded_decode_image(b308, max_bytes=25_000_000, max_pixels=60_000_000, color=True)
    orient = detect.exif_orientation(b308)
    exif_img = detect.apply_exif_orientation(raw_img, orient)
    img308 = cv2.rotate(exif_img, cv2.ROTATE_90_CLOCKWISE) if exif_img.shape[0] < exif_img.shape[1] else exif_img

    # Load draft 13 template from storage
    tmpl_files = list(Path("/storage/app/private/omr-canonical/drafts/13").glob("native_template-*.json"))
    marker_files = list(Path("/storage/app/private/omr-canonical/drafts/13").glob("marker_profile-*.jpg"))
    if not tmpl_files or not marker_files:
        print("Prerequisite not met: Draft 13 template or marker asset missing from storage.")
        return replay_results

    tmpl_data = json.loads(tmpl_files[0].read_text())
    page_dims = tmpl_data.get("pageDimensions", [2196, 3201])
    marker_bytes = marker_files[0].read_bytes()

    # Define template for CropOnMarkers verification
    template = {
        "bubbleDimensions": [53, 53],
        "pageDimensions": page_dims,
        "fieldBlocks": {
            "Examinee_ID_3": {"fieldType": "QTYPE_INT", "direction": "vertical", "bubblesGap": 68, "labelsGap": 68, "origin": [1634, 330], "fieldLabels": [f"A_{i}" for i in range(1, 8)]},
            "Part_1": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [106, 1134], "fieldLabels": [f"P1_{i}" for i in range(1, 26)]},
            "Part_2": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [460, 1134], "fieldLabels": [f"P2_{i}" for i in range(1, 26)]},
            "Part_3": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [815, 1134], "fieldLabels": [f"P3_{i}" for i in range(1, 26)]},
            "Part_4": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1169, 1134], "fieldLabels": [f"P4_{i}" for i in range(1, 26)]},
            "Part_5": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1523, 1134], "fieldLabels": [f"P5_{i}" for i in range(1, 26)]},
            "Part_6": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1889, 1134], "fieldLabels": [f"P6_{i}" for i in range(1, 26)]},
        },
        "preProcessors": [{"name": "CropOnMarkers", "options": {"relativePath": "omr_marker.jpg", "sheetToMarkerWidthRatio": 17}}],
        "customLabels": {},
    }

    # 1. Evaluate through the guarded recovery pipeline with template-derived target
    t0 = time.monotonic()
    recovery_outcome = evaluate_guarded_recovery(
        img308,
        template,
        run_id="samsung-asset-308",
        marker_asset_bytes=marker_bytes,
    )
    total_ms = round((time.monotonic() - t0) * 1000.0, 2)

    check("Asset 308 raw CropOnMarkers fails as expected", recovery_outcome.raw_verification_result.get("outcome") != "succeeded")
    check("Asset 308 guarded recovery attempted rectification", recovery_outcome.rectification_attempted is True)
    check("Asset 308 exactly one candidate passed all validation gates", recovery_outcome.valid_candidates_count == 1, str(recovery_outcome.valid_candidates_count))
    check("Asset 308 promoted outcome is SUCCEEDED", recovery_outcome.outcome == "succeeded", str(recovery_outcome.outcome))

    if recovery_outcome.promoted_verification_result:
        promoted = recovery_outcome.promoted_verification_result
        matches = promoted.get("marker_matches", {})
        shifts = promoted.get("field_block_shifts", [])
        unsafe = find_unsafe_shifts(shifts, template["fieldBlocks"])

        replay_results["marker_matches"] = matches
        replay_results["shifts"] = shifts
        replay_results["unsafe_shifts"] = unsafe
        replay_results["timings_ms"]["pipeline_ms"] = total_ms

        check("Asset 308 all 4 quadrant matches present", len(matches) == 4, str(matches))
        check("Asset 308 Quarter 1 match >= 0.70", float(matches.get("quarter_1", 0.0)) >= 0.70, str(matches.get("quarter_1")))
        check("Asset 308 Quarter 2 match >= 0.70", float(matches.get("quarter_2", 0.0)) >= 0.70, str(matches.get("quarter_2")))
        check("Asset 308 Quarter 3 match >= 0.70", float(matches.get("quarter_3", 0.0)) >= 0.70, str(matches.get("quarter_3")))
        check("Asset 308 Quarter 4 match >= 0.70", float(matches.get("quarter_4", 0.0)) >= 0.70, str(matches.get("quarter_4")))
        check("Asset 308 all field block shifts are SAFE under OmrDetectionResultValidator (< 0.5 * pitch)", len(unsafe) == 0, f"unsafe={unsafe}")

        if recovery_outcome.rectification_result and recovery_outcome.rectification_result.candidates:
            c0 = recovery_outcome.rectification_result.candidates[0]
            replay_results["normalized_quad"] = c0.quad_normalized
            replay_results["homography_hash"] = c0.homography_hash
            # Assert candidate warp matches target frame dimensions exactly
            check("Asset 308 candidate warp matches target dimensions exactly", c0.warped_image.shape[:2] == (page_dims[1], page_dims[0]), str(c0.warped_image.shape))

        replay_results["rectified_recovery_passed"] = True

    return replay_results


def test_raw_pass_capture_132() -> dict[str, Any]:
    print("\n--- Section 3: Raw Pass Capture 132 (Asset 304) Replay ---")
    results = {"asset_304_available": False, "raw_pass_succeeded": False, "rectification_bypassed": False, "marker_matches": {}}

    if not ASSET_304_PATH.is_file():
        print(f"[SKIP] Private Asset 304 replay skipped: Asset 304 not found at {ASSET_304_PATH}. Private qualification unavailable in this environment.")
        return results
    results["asset_304_available"] = True
    b304 = ASSET_304_PATH.read_bytes()
    img304 = bounded_decode_image(b304, max_bytes=25_000_000, max_pixels=60_000_000, color=True)

    tmpl_files = list(Path("/storage/app/private/omr-canonical/drafts/13").glob("native_template-*.json"))
    marker_files = list(Path("/storage/app/private/omr-canonical/drafts/13").glob("marker_profile-*.jpg"))
    if not tmpl_files or not marker_files:
        return results

    tmpl_data = json.loads(tmpl_files[0].read_text())
    page_dims = tmpl_data.get("pageDimensions", [2196, 3201])
    marker_bytes = marker_files[0].read_bytes()

    template = {
        "bubbleDimensions": [53, 53],
        "pageDimensions": page_dims,
        "fieldBlocks": {
            "Examinee_ID_3": {"fieldType": "QTYPE_INT", "direction": "vertical", "bubblesGap": 68, "labelsGap": 68, "origin": [1634, 330], "fieldLabels": [f"A_{i}" for i in range(1, 8)]},
            "Part_1": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [106, 1134], "fieldLabels": [f"P1_{i}" for i in range(1, 26)]},
            "Part_2": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [460, 1134], "fieldLabels": [f"P2_{i}" for i in range(1, 26)]},
            "Part_3": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [815, 1134], "fieldLabels": [f"P3_{i}" for i in range(1, 26)]},
            "Part_4": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1169, 1134], "fieldLabels": [f"P4_{i}" for i in range(1, 26)]},
            "Part_5": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1523, 1134], "fieldLabels": [f"P5_{i}" for i in range(1, 26)]},
            "Part_6": {"fieldType": "QTYPE_MCQ4", "direction": "horizontal", "bubblesGap": 71, "labelsGap": 80, "origin": [1889, 1134], "fieldLabels": [f"P6_{i}" for i in range(1, 26)]},
        },
        "preProcessors": [{"name": "CropOnMarkers", "options": {"relativePath": "omr_marker.jpg", "sheetToMarkerWidthRatio": 17}}],
        "customLabels": {},
    }

    # Evaluate through guarded recovery
    outcome = evaluate_guarded_recovery(img304, template, run_id="raw-pass-132", marker_asset_bytes=marker_bytes)

    check("Asset 304 raw CropOnMarkers succeeds directly", outcome.outcome == "succeeded", str(outcome.outcome))
    check("Asset 304 rectification was bypassed (attempted=False, applied=False)", outcome.rectification_attempted is False and outcome.rectification_applied is False)

    matches = outcome.promoted_verification_result.get("marker_matches", {})
    results["marker_matches"] = matches
    check("Asset 304 all 4 quadrant matches >= 0.50", len(matches) == 4 and all(v >= 0.50 for v in matches.values()), str(matches))
    results["raw_pass_succeeded"] = True
    results["rectification_bypassed"] = True
    return results


def test_deterministic_repeatability() -> None:
    print("\n--- Section 4: Deterministic Repeatability & Provenance Verification ---")
    sheet = render_base_test_sheet(seed=7, skip_markers=False)
    persp = apply_perspective_distortion(cv2.cvtColor(sheet, cv2.COLOR_GRAY2BGR))
    target = RectificationTarget(target_width=tc.PAPER[0] - 2 * INSET, target_height=tc.PAPER[1] - 2 * INSET)

    # Run 1
    res1 = propose_rectifications(persp, target)
    # Run 2
    res2 = propose_rectifications(persp, target)

    check("Deterministic status match", res1.status == res2.status == "success")
    check("Deterministic candidate count match", len(res1.candidates) == len(res2.candidates))

    if res1.candidates and res2.candidates:
        c1 = res1.candidates[0]
        c2 = res2.candidates[0]
        check("Deterministic normalized quad match", c1.quad_normalized == c2.quad_normalized, f"{c1.quad_normalized} vs {c2.quad_normalized}")
        check("Deterministic homography hash match", c1.homography_hash == c2.homography_hash, f"{c1.homography_hash} vs {c2.homography_hash}")
        check("Deterministic composite score match", c1.composite_score == c2.composite_score, f"{c1.composite_score} vs {c2.composite_score}")
        warped_hash1 = hashlib.sha256(c1.warped_image.tobytes()).hexdigest()
        warped_hash2 = hashlib.sha256(c2.warped_image.tobytes()).hexdigest()
        check("Deterministic warped raster byte equality", warped_hash1 == warped_hash2, f"{warped_hash1} vs {warped_hash2}")


def main() -> int:
    print("====================================================================")
    print("NEXIAM Document Rectifier Prototype Verification & Acceptance Gate")
    print("====================================================================")
    started_all = time.monotonic()

    test_adversarial_synthetic_corpus()
    samsung_res = test_private_samsung_replay()
    raw_res = test_raw_pass_capture_132()
    test_deterministic_repeatability()

    total_time = round((time.monotonic() - started_all) * 1000.0, 2)
    print("====================================================================")
    print(f"All corpus and replay test cases finished in {total_time} ms.")
    print("====================================================================")

    if not samsung_res.get("asset_308_available", False) or not raw_res.get("asset_304_available", False):
        summary = {
            "synthetic_corpus_status": "PASSED",
            "private_replay_status": "SKIPPED_ASSETS_ABSENT",
            "qualification_status": "unqualified",
            "samsung_asset_308_replay": samsung_res,
            "raw_pass_capture_132": raw_res,
        }
        print(json.dumps(summary, indent=2, sort_keys=True))
        print("Note: Private PII assets are not committed. Synthetic adversarial corpus PASSED (13 cases).")
        return 0

    gate_passed = (
        samsung_res.get("rectified_recovery_passed", False)
        and raw_res.get("raw_pass_succeeded", False)
    )

    summary = {
        "gate_status": "PASSED" if gate_passed else "FAILED",
        "samsung_asset_308_replay": samsung_res,
        "raw_pass_capture_132": raw_res,
    }
    print(json.dumps(summary, indent=2, sort_keys=True))
    return 0 if gate_passed else 1

if __name__ == "__main__":
    raise SystemExit(main())
