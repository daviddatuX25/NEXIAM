"""Worker layout-verification characterization (OpenSpec 36.3/36.8).

Runs inside the inference image and proves the read-only boundary:
`CropOnMarkers` registration is the only ordinary-capture authority, all four
profile markers are required, small skew/perspective is accepted after the
fixed marker path, aligned/overlay rasters are emitted, field-block shifts
remain typed diagnostics, and marker failures are typed. Hough, three-marker,
manual, arbitrary-angle, and external crop paths are not available here.

Usage:  python3 test_layout_verification.py   (from /app in the inference image)
"""

import json
import sys
import tempfile
from pathlib import Path

import cv2
import numpy as np

import detect
import test_marker_pipeline as t
import verify


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{name}: {detail}")
    print(f"ok - {name}")


def run_verify(image: np.ndarray, template: dict) -> tuple[dict, bytes, bytes]:
    ok, encoded = cv2.imencode(".png", image)
    assert ok
    image_bytes = encoded.tobytes()
    manifest = t.manifest_for(image, image_bytes, template)
    with tempfile.TemporaryDirectory(prefix="nexiam-omr-verify-") as directory:
        output = Path(directory)
        image_path = output / "input"
        image_path.write_bytes(image_bytes)
        result = verify.verify(manifest, image_path, output)
        aligned_bytes = (output / "aligned.png").read_bytes()
        overlay_bytes = (output / "layout_overlay.png").read_bytes()
        return result, aligned_bytes, overlay_bytes


def main() -> int:
    template = t.template_json()
    preprocessors = template["preProcessors"]
    check("layout template uses only CropOnMarkers", [entry.get("name") for entry in preprocessors] == ["CropOnMarkers"], preprocessors)
    check(
        "layout template has no alternate crop authority",
        not any(
            str(entry.get("name", "")).lower() in {"croppage", "hough", "manualcrop", "externalcrop", "quadrectifier"}
            for entry in preprocessors
        ),
        preprocessors,
    )

    # 1. Marker-bearing template: diagnostic succeeds with all artifacts.
    result, aligned_bytes, overlay_bytes = run_verify(t.render_sheet(), template)
    check("layout verification succeeded", result["outcome"] == "succeeded", result.get("outcome"))
    check("calibration stays unqualified", result["calibration_status"] == "unqualified", result["calibration_status"])
    check("aligned raster is the frame", result["aligned_image"]["width"] == t.FRAME[0] and result["aligned_image"]["height"] == t.FRAME[1], result["aligned_image"])
    check("overlay raster is the frame", result["layout_overlay"]["width"] == t.FRAME[0] and result["layout_overlay"]["height"] == t.FRAME[1], result["layout_overlay"])
    check("all four profile marker quadrants reported in order", list(result["marker_matches"]) == ["quarter_1", "quarter_2", "quarter_3", "quarter_4"], result["marker_matches"])
    check("marker scores clear the pinned threshold", all(np.isfinite(float(score)) and float(score) >= 0.3 for score in result["marker_matches"].values()), result["marker_matches"])
    check(
        "field-block shifts are complete typed diagnostics",
        [entry.get("block") for entry in result["field_block_shifts"]] == list(template["fieldBlocks"])
        and all(isinstance(entry.get("x"), int) and isinstance(entry.get("y"), int) for entry in result["field_block_shifts"]),
        result["field_block_shifts"],
    )
    check("per-bubble means reported", isinstance(result["bubble_means"], dict) and len(result["bubble_means"]) >= 1, list(result["bubble_means"].keys())[:3])
    all_means = [entry["mean"] for block in result["bubble_means"].values() for entry in block if entry.get("mean") is not None]
    check("bubble means are numeric and bounded", all(isinstance(v, (int, float)) and 0 <= v <= 255 for v in all_means), all_means[:3])

    # 2. The overlay raster is a distinct annotated PNG at frame dimensions.
    check("aligned and overlay are distinct PNGs", aligned_bytes != overlay_bytes and aligned_bytes[:8] == b"\x89PNG\r\n\x1a\n" and overlay_bytes[:8] == b"\x89PNG\r\n\x1a\n")
    overlay_image = cv2.imdecode(np.frombuffer(overlay_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    check("overlay decodes to the frame", overlay_image is not None and overlay_image.shape[0] == t.FRAME[1] and overlay_image.shape[1] == t.FRAME[0])

    # 3. Mild perspective remains on the same production CropOnMarkers path.
    perspective_bytes = (Path(__file__).resolve().parent / "fixtures" / "characterization" / "perspective-mild.png").read_bytes()
    perspective = cv2.imdecode(np.frombuffer(perspective_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    check("perspective fixture decodes", perspective is not None, "perspective-mild.png")
    perspective_result, _, _ = run_verify(perspective, template)
    check("small skew/perspective layout succeeds", perspective_result["outcome"] == "succeeded", perspective_result.get("outcome"))
    check("small skew/perspective still reports all four markers", len(perspective_result["marker_matches"]) == 4, perspective_result["marker_matches"])

    # 4. Marker-less (scan) template is rejected with a typed error.
    scan_template = dict(template)
    scan_template["preProcessors"] = [{"name": "CropPage", "options": {"morphKernel": [5, 5]}}]
    try:
        run_verify(t.render_sheet(), scan_template)
        raise AssertionError("expected marker_versions_only")
    except detect.DetectionError as error:
        check("marker-less template rejected", error.code == "marker_versions_only", error.code)

    # 5. Marker failure stays typed (no Hough fallback, no success).
    rng = np.random.default_rng(42)
    blank_frame = np.clip(200 + rng.normal(0, 6, (3000, 4000)), 0, 255).astype(np.uint8)
    try:
        run_verify(blank_frame, template)
        raise AssertionError("expected a typed marker failure")
    except detect.DetectionError as error:
        check("marker failure is typed", error.code in {"marker_missing", "marker_mismatch"}, error.code)

    print(json.dumps({"status": "ok", "frame": t.FRAME}, separators=(",", ":")))
    return 0



if __name__ == "__main__":
    raise SystemExit(main())
