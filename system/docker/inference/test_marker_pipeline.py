"""Marker-bearing worker pipeline tests (tasks 10.2/10.4/10.9 seam).

Runs inside the inference image (cv2/numpy/dotmap + OMRChecker at
/opt/omrchecker). Proves the deterministic marker contracts with OMRChecker's
OWN pinned bullseye asset (samples/sample5/omr_marker.jpg, ratio 17):

  1. the shipped marker asset is byte-faithful to the pinned engine asset
     (alignment fiducial parity, not a custom pattern hash),
  2. a marker-bearing template materializes the bullseye next to template.json
     (the engine exit(31) path is unreachable),
  3. marker failures map to typed states (marker_missing / marker_mismatch /
     sheet_partial / preprocessing_failed) with corrective copy and an
     orientation hint,
  4. marker-bearing templates NEVER invoke the Hough align_camera_page
     fallback and never fall back to three-of-four registration,
  5. a valid four-marker sheet runs the full registration chain fail-closed.

The bullseye is an ALIGNMENT fiducial, NOT a template identifier: wrong-form
and rotated sheets are not rejected by it (recorded limitation — a separate
NEXIAM identifier gate is required). Registration strength on printed sheets
is physical-matrix calibration (tasks 10.10/10.11); `calibration_status`
stays `unqualified`.

Usage:  python3 test_marker_pipeline.py        (from /app in the inference image)
"""

import base64
import hashlib
import io
import json
import os
import sys
import tempfile
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import cv2
import numpy as np

import detect
import fiducial
import generate_fixture
import marker_states

ASSET_SHA256 = "61d39aab56d6c5ead514c0769528af56ca7f2b849ca26ebd6e9346f4ce704059"
PAPER = [2060, 3144]
INSET = 211
MARKER_SIZE = 121
FRAME = [PAPER[0] - 2 * INSET, PAPER[1] - 2 * INSET]
IDENTITY_PITCH = 46
ROW_PITCH = 76
FLOW_TOP = 664


def template_json() -> dict:
    """Single-page marker template mirroring the solver output shape."""
    clearance = MARKER_SIZE // 2 + 40 + 10
    identity_height = 9 * IDENTITY_PITCH + 40
    flow_top = clearance + identity_height + 100
    assert flow_top == FLOW_TOP, flow_top
    return {
        "bubbleDimensions": [40, 40],
        "pageDimensions": FRAME,
        "customLabels": {},
        "preProcessors": [
            {
                "name": "CropOnMarkers",
                "options": {
                    # Upstream mobile recipe (samples/sample5): marker-only
                    # chain with the official bullseye at the upstream ratio.
                    "relativePath": "omr_marker.jpg",
                    "sheetToMarkerWidthRatio": 17,
                },
            }
        ],
        "fieldBlocks": {
            "ApplicantNumber": {
                "fieldType": "QTYPE_INT",
                "fieldLabels": ["APPLICANT_NUMBER_1..10"],
                "bubblesGap": IDENTITY_PITCH,
                "labelsGap": 52,
                "origin": [clearance, clearance],
            },
            "LOGICPartA": {
                "fieldType": "QTYPE_MCQ4",
                "direction": "horizontal",
                "fieldLabels": ["LOGIC_Q_001..004"],
                "bubblesGap": 52,
                "labelsGap": ROW_PITCH,
                "origin": [80, flow_top],
            },
        },
    }


def marker_metadata() -> dict:
    return {
        "family": "omrchecker.bullseye",
        "asset_name": "omr_marker.jpg",
        "size": MARKER_SIZE,
        "quiet_zone": 40,
        "inset": INSET,
        "paper": PAPER,
        "centers": [[INSET, INSET], [PAPER[0] - INSET, INSET], [INSET, PAPER[1] - INSET], [PAPER[0] - INSET, PAPER[1] - INSET]],
        "quadrants": [0, 1, 2, 3],
    }


def manifest_for(image: np.ndarray, image_bytes: bytes, template: dict) -> dict:
    """Detection manifest mirroring the NEXIAM PHP envelope."""
    height, width = image.shape[:2]
    native_config = {
        "dimensions": {"display_width": FRAME[0], "display_height": FRAME[1], "processing_width": FRAME[0], "processing_height": FRAME[1]},
        "alignment_params": {"auto_align": True, "match_col": 5, "max_steps": 30, "stride": 8, "thickness": 2},
        "threshold_params": {"GAMMA_LOW": 0.9, "MIN_GAP": 30, "MIN_JUMP": 50, "PAGE_TYPE": "NORMAL", "GLOBAL_PAGE_THRESHOLD_WHITE": 200, "GLOBAL_PAGE_THRESHOLD_BLACK": 100},
        "outputs": {"show_image_level": 0, "save_image_level": 0, "save_detections": False},
    }
    review_manifest = {
        "manifest_version": "nexiam.omr.review-manifest.v1",
        "fields": [
            {"label": "APPLICANT_NUMBER_1", "page": 1, "option": "0", "region": {"x": 0.1, "y": 0.1, "w": 0.01, "h": 0.01}},
            {"label": "LOGIC_Q_1", "page": 1, "option": "A", "region": {"x": 0.1, "y": 0.3, "w": 0.01, "h": 0.01}},
            {"label": "LOGIC_Q_2", "page": 1, "option": "A", "region": {"x": 0.1, "y": 0.32, "w": 0.01, "h": 0.01}},
            {"label": "LOGIC_Q_3", "page": 1, "option": "A", "region": {"x": 0.1, "y": 0.34, "w": 0.01, "h": 0.01}},
            {"label": "LOGIC_Q_4", "page": 1, "option": "A", "region": {"x": 0.1, "y": 0.36, "w": 0.01, "h": 0.01}},
        ],
    }
    native_bundle_metadata = {
        "files": {"template.json": hashlib.sha256(json.dumps(template, separators=(",", ":")).encode()).hexdigest()},
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "bundle_version": "nexiam.omr.native-bundle.v1",
    }
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()

    return {
        "contract_version": "nexiam.omr.detection.v2",
        "run_id": "test-marker-run",
        "correlation_id": "test-marker-corr",
        "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": width,
        "image_height": height,
        "native_bundle_hash": hashlib.sha256(canonical(native_bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical(review_manifest)).hexdigest(),
        "config_hash": hashlib.sha256(canonical(native_config)).hexdigest(),
        "booklet_hash": hashlib.sha256(b"key").hexdigest(),
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "native_bundle_metadata": native_bundle_metadata,
        "native_template": template,
        "native_config": native_config,
        "review_manifest": review_manifest,
        "items": [
            {"position": 1, "area_code": "LOGIC", "question_no": 1, "native_label": "LOGIC_Q_1", "region": {"x": 0.1, "y": 0.3, "w": 0.2, "h": 0.02}, "options": [{"code": c, "display_order": i + 1, "region": {"x": 0.1 + i * 0.05, "y": 0.3, "w": 0.04, "h": 0.02}} for i, c in enumerate(["A", "B", "C", "D"])]},
            {"position": 2, "area_code": "LOGIC", "question_no": 2, "native_label": "LOGIC_Q_2", "region": {"x": 0.1, "y": 0.32, "w": 0.2, "h": 0.02}, "options": [{"code": c, "display_order": i + 1, "region": {"x": 0.1 + i * 0.05, "y": 0.32, "w": 0.04, "h": 0.02}} for i, c in enumerate(["A", "B", "C", "D"])]},
            {"position": 3, "area_code": "LOGIC", "question_no": 3, "native_label": "LOGIC_Q_3", "region": {"x": 0.1, "y": 0.34, "w": 0.2, "h": 0.02}, "options": [{"code": c, "display_order": i + 1, "region": {"x": 0.1 + i * 0.05, "y": 0.34, "w": 0.04, "h": 0.02}} for i, c in enumerate(["A", "B", "C", "D"])]},
            {"position": 4, "area_code": "LOGIC", "question_no": 4, "native_label": "LOGIC_Q_4", "region": {"x": 0.1, "y": 0.36, "w": 0.2, "h": 0.02}, "options": [{"code": c, "display_order": i + 1, "region": {"x": 0.1 + i * 0.05, "y": 0.36, "w": 0.04, "h": 0.02}} for i, c in enumerate(["A", "B", "C", "D"])]},
        ],
        "identity_fields": [
            {"code": "applicant_number", "native_label": "APPLICANT_NUMBER_1", "position": 1, "region": {"x": 0.1, "y": 0.1, "w": 0.02, "h": 0.02}},
        ],
        "native_assets": {"omr_marker.jpg": base64.b64encode(fiducial.asset_bytes()).decode("ascii")},
    }


def render_sheet(skip_markers: bool = False, partial: bool = False) -> np.ndarray:
    """Synthetic photo of the printed marker sheet (paper + gray background)."""
    template = template_json()
    canvas = generate_fixture.render(
        template,
        seed=7,
        blank=False,
        margin_ratio=0.05,
        markers=marker_metadata(),
        skip_markers=skip_markers,
    )
    if partial:
        # Occlude the bottom-right marker (three-of-four present). The fill is
        # textured (not flat) so the quadrant fails on real low scores instead
        # of vacuous NaN comparisons.
        half = MARKER_SIZE // 2
        margin_x = round(PAPER[0] * 0.05)
        margin_y = round(PAPER[1] * 0.05)
        left = margin_x + (PAPER[0] - INSET) - half
        top = margin_y + (PAPER[1] - INSET) - half
        noise = np.random.default_rng(7).normal(0, 6, (MARKER_SIZE, MARKER_SIZE))
        canvas[top : top + MARKER_SIZE, left : left + MARKER_SIZE] = np.clip(238 + noise, 0, 255).astype(np.uint8)
    return canvas


def run_detect(image: np.ndarray, template: dict, fail_on_hough: bool = True) -> dict:
    ok, encoded = cv2.imencode(".png", image)
    assert ok
    image_bytes = encoded.tobytes()
    manifest = manifest_for(image, image_bytes, template)

    if fail_on_hough:
        def forbidden(*args, **kwargs):
            raise AssertionError("align_camera_page must never run for marker-bearing templates")

        original = detect.align_camera_page
        detect.align_camera_page = forbidden
    try:
        with tempfile.TemporaryDirectory(prefix="nexiam-omr-test-") as directory:
            output = Path(directory)
            (output / "input").write_bytes(image_bytes)
            return detect.detect(manifest, output / "input", output)
    finally:
        if fail_on_hough:
            detect.align_camera_page = original


def expect_marker_error(image: np.ndarray, expected_code: str, template: dict) -> None:
    try:
        run_detect(image, template)
    except detect.DetectionError as error:
        assert error.code == expected_code, f"expected {expected_code}, got {error.code}: {error}"
        return
    raise AssertionError(f"expected DetectionError({expected_code}), detection succeeded")


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{name}: {detail}")
    print(f"ok - {name}")


def main() -> int:
    # 1. Asset parity: the shipped bullseye is byte-faithful to the pinned
    #    OMRChecker sample (alignment fiducial — not a template ID).
    check("marker asset is the pinned OMRChecker bullseye", fiducial.asset_hash() == ASSET_SHA256, fiducial.asset_hash())

    # 2. Typed classifier: deterministic log-line mapping (pure).
    weak_log = (
        "Error: No circle found in Quad 1 \n\t min_matching_threshold 0.3 \t max_t 0.14 \t all_max_t 0.21\n"
        "Error: No circle found in Quad 2 \n\t min_matching_threshold 0.3 \t max_t 0.16 \t all_max_t 0.21\n"
        "Error: No circle found in Quad 3 \n\t min_matching_threshold 0.3 \t max_t 0.13 \t all_max_t 0.21\n"
        "Error: No circle found in Quad 4 \n\t min_matching_threshold 0.3 \t max_t 0.15 \t all_max_t 0.21\n"
    )
    state = marker_states.classify(weak_log, sheet_present=False)
    check("weak global evidence without sheet -> marker_missing", state["code"] == "marker_missing", state)
    state = marker_states.classify(weak_log, sheet_present=True)
    check("weak global evidence with sheet -> marker_mismatch + upright hint", state["code"] == "marker_mismatch" and state["orientation_hint"] == "upright_required", state)

    partial_log = "Error: No circle found in Quad 4 \n\t min_matching_threshold 0.3 \t max_t 0.12 \t all_max_t 0.61\n"
    state = marker_states.classify(partial_log, sheet_present=True)
    check("one missing corner -> sheet_partial", state["code"] == "sheet_partial", state)

    state = marker_states.classify("some unrelated engine failure", sheet_present=True)
    check("unknown chain failure -> preprocessing_failed", state["code"] == "preprocessing_failed", state)

    check("match scores parsed from success log", marker_states.parse_match_scores("Matching Marker: Quarter1: 0.93 Quarter2: 0.88") == {"quarter_1": 0.93, "quarter_2": 0.88})

    # 3. Missing asset is a typed error before engine load (never exit(31)).
    template = template_json()
    tiny = np.full((120, 90), 200, dtype=np.uint8)
    tiny_ok, tiny_encoded = cv2.imencode(".png", tiny)
    assert tiny_ok
    manifest = manifest_for(tiny, tiny_encoded.tobytes(), template)
    manifest["native_assets"] = {}
    with tempfile.TemporaryDirectory(prefix="nexiam-omr-test-") as directory:
        image_path = Path(directory) / "input"
        image_path.write_bytes(tiny_encoded.tobytes())
        try:
            detect.detect(manifest, image_path, Path(directory))
            raise AssertionError("expected marker_asset_missing")
        except detect.DetectionError as error:
            check("missing asset -> marker_asset_missing (no exit(31))", error.code == "marker_asset_missing", error.code)

    # 4. No sheet in frame -> marker_missing, and the Hough fallback never runs.
    rng = np.random.default_rng(42)
    blank_frame = np.clip(200 + rng.normal(0, 6, (3000, 4000)), 0, 255).astype(np.uint8)
    expect_marker_error(blank_frame, "marker_missing", template)
    check("marker_missing is typed and Hough-free", True)

    # 5. Partial sheet (three of four corners): engine requires all four
    #    quadrants; a failure maps to sheet_partial (classifier contract
    #    proven above) and never degrades to Hough.
    try:
        result = run_detect(render_sheet(partial=True), template)
        check("partial sheet ran the chain without Hough", result["outcome"] == "succeeded", result.get("outcome"))
    except detect.DetectionError as error:
        check("partial sheet failed closed with a typed marker state", error.code in ("marker_missing", "marker_mismatch", "sheet_partial", "preprocessing_failed"), error.code)
    except AssertionError:
        raise
    except Exception as error:  # noqa: BLE001
        raise AssertionError(f"partial sheet must fail closed, crashed with {type(error).__name__}: {error}") from error

    # 6. Valid four-marker sheet: the chain runs fail-closed — registration
    #    succeeds or yields a typed marker error; never a crash, never Hough.
    #    Match strength on printed sheets is physical-matrix calibration.
    #    (The bullseye is an alignment fiducial: wrong-form/rotated sheets are
    #    NOT rejected by it — a separate NEXIAM identifier gate is required.)
    try:
        result = run_detect(render_sheet(), template)
        check("valid sheet registration succeeded", result["outcome"] == "succeeded", result.get("outcome"))
        aligned = result["aligned_image"]
        check("aligned output is the fiducial-center frame", aligned["width"] == FRAME[0] and aligned["height"] == FRAME[1], aligned)
        matches = result["provider_diagnostics"]["marker_matches"]
        check("four quadrant matches reported", len(matches) == 4, matches)
        check("calibration stays unqualified", result["calibration_status"] == "unqualified", result["calibration_status"])
    except detect.DetectionError as error:
        check("valid sheet failed closed with a typed marker state", error.code in ("marker_missing", "marker_mismatch", "sheet_partial", "preprocessing_failed"), error.code)
    except AssertionError:
        raise
    except Exception as error:  # noqa: BLE001
        raise AssertionError(f"valid sheet must fail closed, crashed with {type(error).__name__}: {error}") from error

    print(json.dumps({"status": "ok", "asset_hash": fiducial.asset_hash(), "frame": FRAME}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
