"""Worker capture-preflight tests (tasks 10.7/10.18 worker protocol).

Runs inside the inference image. Proves the registration-only preflight
contract: a marker-bearing sheet validates with per-quadrant match scores and
declared/measured frame dimensions; marker-less templates are rejected with
`marker_versions_only`; marker failures stay typed. No recognition output is
produced (no items, no aligned raster).

Usage:  python3 test_preflight.py   (from /app in the inference image)
"""

import json
import sys
import tempfile
from pathlib import Path

import cv2
import numpy as np

import detect
import preflight
import test_marker_pipeline as t


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{name}: {detail}")
    print(f"ok - {name}")


def run_preflight(image: np.ndarray, template: dict) -> dict:
    ok, encoded = cv2.imencode(".png", image)
    assert ok
    image_bytes = encoded.tobytes()
    manifest = t.manifest_for(image, image_bytes, template)
    with tempfile.TemporaryDirectory(prefix="nexiam-omr-preflight-") as directory:
        output = Path(directory)
        image_path = output / "input"
        image_path.write_bytes(image_bytes)
        return preflight.preflight(manifest, image_path, output)


def main() -> int:
    template = t.template_json()

    # 1. Marker-bearing sheet validates with scores and dimensions.
    result = run_preflight(t.render_sheet(), template)
    check("preflight validated", result["outcome"] == "validated", result.get("outcome"))
    check("calibration stays unqualified", result["calibration_status"] == "unqualified", result["calibration_status"])
    check("four quadrant matches reported", len(result["marker_matches"]) == 4, result["marker_matches"])
    check("declared frame dimensions match the template", result["declared_dimensions"]["width"] == t.FRAME[0] and result["declared_dimensions"]["height"] == t.FRAME[1], result["declared_dimensions"])
    check("measured dimensions are frame-sized", result["measured_dimensions"]["width"] > 0 and result["measured_dimensions"]["height"] > 0, result["measured_dimensions"])
    check("orientation hint present", result["orientation_hint"] == "upright", result["orientation_hint"])
    check("no recognition output produced", "items" not in result and "aligned_image" not in result, list(result.keys()))

    # 2. Marker-less (scan) template is rejected with a typed error.
    scan_template = dict(template)
    scan_template["preProcessors"] = [{"name": "CropPage", "options": {"morphKernel": [5, 5]}}]
    try:
        run_preflight(t.render_sheet(), scan_template)
        raise AssertionError("expected marker_versions_only")
    except detect.DetectionError as error:
        check("scan template rejected", error.code == "marker_versions_only", error.code)

    # 3. Marker failure stays typed (no Hough fallback, no success).
    rng = np.random.default_rng(42)
    blank_frame = np.clip(200 + rng.normal(0, 6, (3000, 4000)), 0, 255).astype(np.uint8)
    try:
        run_preflight(blank_frame, template)
        raise AssertionError("expected a typed marker failure")
    except detect.DetectionError as error:
        check("marker failure is typed", error.code.startswith("marker_") or error.code == "preprocessing_failed", error.code)

    print(json.dumps({"status": "ok", "frame": t.FRAME}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
