"""Registration-only capture preflight child for POST /v1/preflight (tasks 10.7/10.18).

Runs ONLY the marker registration chain (`CropOnMarkers`) against the
batch's marker-bearing template. It never runs `read_omr_response`, AutoAlign,
thresholding, or scoring, and it never falls back to the Hough
`align_camera_page` path — a marker failure is a typed terminal state.

A marker-less (scan/CropPage) template is rejected with
`marker_versions_only`; the NEXIAM capture page handles scan batches with the
normal upload path instead of pretending camera validation passed.

Output is a single JSON result (no rasters): per-quadrant match scores, the
declared frame dimensions from the template, the measured registration
dimensions, and `calibration_status=unqualified`. Never modifies anything.
"""

import json
import sys
import time
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import cv2
import numpy as np

import detect
import marker_states


def preflight(manifest: dict, image_path: Path, output_directory: Path) -> dict:
    started = time.monotonic()
    detect.validate_manifest(manifest)
    image_bytes = image_path.read_bytes()
    mime = detect.sniff_mime(image_bytes)
    detect.require(mime == manifest.get("image_mime"), "image_metadata_mismatch", "Decoded image MIME does not match the request.")

    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    decode_flags = cv2.IMREAD_GRAYSCALE | getattr(cv2, "IMREAD_IGNORE_ORIENTATION", 0)
    image = cv2.imdecode(encoded, decode_flags)
    detect.require(image is not None and image.size > 0, "image_decode_failed", "Image decoding failed.")
    decoded_height, decoded_width = image.shape[:2]
    detect.require(decoded_width == manifest.get("image_width") and decoded_height == manifest.get("image_height"), "image_metadata_mismatch", "Decoded image dimensions do not match the request.")
    image = detect.apply_exif_orientation(image, detect.exif_orientation(image_bytes))

    native_template = manifest.get("native_template", {})
    detect.require(
        detect.marker_bearing_template(native_template),
        "marker_versions_only",
        "This batch uses a scan (CropPage) template; camera preflight is not applicable. Upload the scan through the normal upload path.",
    )

    defaults, template_class, image_ops_class = detect.load_omrchecker()
    config = detect.merge_config(manifest["native_config"], defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(native_template, separators=(",", ":")), encoding="utf-8")
    detect.materialize_native_assets(manifest, output_directory)

    engine_log_capture = __import__("io").StringIO()
    with redirect_stdout(engine_log_capture), redirect_stderr(engine_log_capture):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        processing_width = int(config.dimensions.processing_width)
        processing_height = int(config.dimensions.processing_height)
        processed = image_ops.apply_preprocessors(str(image_path), image, template)
    engine_text = engine_log_capture.getvalue()
    sys.stderr.write(engine_text)
    preprocess_ms = (time.monotonic() - preprocess_started) * 1000.0

    if processed is None:
        state = marker_states.classify(engine_text, detect.sheet_likely_present(image))
        raise detect.DetectionError(
            state["code"],
            state["message"] + f" (orientation_hint={state['orientation_hint']})",
        )

    measured_height, measured_width = processed.shape[:2]

    return {
        "contract_version": detect.CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "outcome": "validated",
        "engine": {"name": detect.ENGINE_NAME, "version": detect.ENGINE_COMMIT[:12], "commit": detect.ENGINE_COMMIT, "patch_identity": detect.PATCH_IDENTITY},
        "input_hash": manifest["input_hash"],
        "native_bundle_hash": manifest["native_bundle_hash"],
        "manifest_hash": manifest["manifest_hash"],
        "config_hash": manifest["config_hash"],
        "calibration_status": "unqualified",
        "image": {"mime": mime, "width": decoded_width, "height": decoded_height},
        "marker_matches": marker_states.parse_match_scores(engine_text),
        "declared_dimensions": {"width": processing_width, "height": processing_height},
        "measured_dimensions": {"width": int(measured_width), "height": int(measured_height)},
        "orientation_hint": "upright",
        "timings": {"preprocess_ms": round(preprocess_ms, 3), "preflight_ms": round((time.monotonic() - started) * 1000.0, 3)},
    }


def main() -> int:
    try:
        if len(sys.argv) != 4:
            raise detect.DetectionError("invocation_invalid", "Expected manifest, image, and output directory paths.")
        manifest = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
        result = preflight(manifest, Path(sys.argv[2]), Path(sys.argv[3]))
        result_path = Path(sys.argv[3]) / "result.json"
        result_path.write_text(json.dumps(result, separators=(",", ":"), sort_keys=True), encoding="utf-8")
        return 0
    except detect.DetectionError as error:
        print(json.dumps({"error": error.code, "detail": str(error)}, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR capture preflight failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
