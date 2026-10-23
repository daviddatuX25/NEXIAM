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

import io
import json
import os
import sys
import time
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import cv2
import numpy as np

import detect
import document_rectifier
import marker_states
import rectification_recovery
def preflight(manifest: dict, image_path: Path, output_directory: Path) -> dict:
    started = time.monotonic()
    native_config = detect.validate_preflight_manifest(manifest)
    image_bytes = image_path.read_bytes()
    mime = detect.sniff_mime(image_bytes)
    detect.require(mime == manifest.get("image_mime"), "image_metadata_mismatch", "Decoded image MIME does not match the request.")

    try:
        image = document_rectifier.bounded_decode_image(image_bytes, color=False)
    except Exception as exc:
        raise detect.DetectionError("image_decode_failed", f"Image decoding failed: {exc}") from None
    decoded_height, decoded_width = image.shape[:2]
    detect.require(decoded_width == manifest.get("image_width") and decoded_height == manifest.get("image_height"), "image_metadata_mismatch", "Decoded image dimensions do not match the request.")
    image = detect.apply_exif_orientation(image, detect.exif_orientation(image_bytes))
    height, width = image.shape[:2]

    native_template = manifest.get("native_template", {})
    detect.require(
        detect.marker_bearing_template(native_template),
        "marker_versions_only",
        "This batch uses a scan (CropPage) template; camera preflight is not applicable. Upload the scan through the normal upload path.",
    )

    defaults, template_class, image_ops_class = detect.load_omrchecker()
    config = detect.merge_config(native_config, defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(native_template, separators=(",", ":")), encoding="utf-8")
    detect.materialize_native_assets(manifest, output_directory)

    engine_log = __import__("io").StringIO()
    with detect.capture_engine_logs(engine_log):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        page_dimensions = native_template.get("pageDimensions") or getattr(template, "page_dimensions", None)
        if page_dimensions and len(page_dimensions) == 2:
            target_width = int(page_dimensions[0])
            target_height = int(page_dimensions[1])
        else:
            target_width = int(config.dimensions.processing_width)
            target_height = int(config.dimensions.processing_height)
        processed = image_ops.apply_preprocessors(str(image_path), image, template)
    engine_text = engine_log.getvalue()
    sys.stderr.write(engine_text)
    preprocess_ms = (time.monotonic() - preprocess_started) * 1000.0

    raw_matches = marker_states.parse_match_scores(engine_text)

    raw_valid = (
        processed is not None
        and len(raw_matches) == 4
        and all(v >= 0.30 for v in raw_matches.values())
    )

    rectification_metadata = rectification_recovery.base_rectification_metadata()

    if not raw_valid:
        raw_state = marker_states.classify(engine_text, detect.sheet_likely_present(image))

        def evaluate_preflight_cand(cand: document_rectifier.RectifiedCandidate) -> rectification_recovery.EvaluatedCandidate | None:
            cand_log = io.StringIO()
            with detect.capture_engine_logs(cand_log):
                cand_template = template_class(template_path, config)
                cand_image_ops = image_ops_class(config)
                cand_processed = cand_image_ops.apply_preprocessors(str(image_path), cand.warped_image, cand_template)
            cand_engine_text = cand_log.getvalue()
            cand_matches = marker_states.parse_match_scores(cand_engine_text)
            if (
                cand_processed is not None
                and len(cand_matches) == 4
                and all(v >= 0.30 for v in cand_matches.values())
            ):
                return rectification_recovery.EvaluatedCandidate(
                    candidate=cand,
                    marker_matches=cand_matches,
                    payload=(cand_processed, cand_engine_text),
                )
            return None

        try:
            promoted, rectification_metadata = rectification_recovery.evaluate_guarded_recovery(
                image=image,
                manifest=manifest,
                raw_matches=marker_states.parse_raw_marker_telemetry(engine_text),
                raw_state=raw_state,
                target_width=target_width,
                target_height=target_height,
                evaluate_candidate=evaluate_preflight_cand,
            )
        except rectification_recovery.RecoveryRejected as rejected:
            raise detect.DetectionError(rejected.code, str(rejected), rectification=rejected.rectification) from None
        cand_processed, cand_engine_text = promoted.payload
        processed = cand_processed
        engine_text = cand_engine_text
    measured_height, measured_width = processed.shape[:2]
    qr_detector = cv2.QRCodeDetector()
    qr_payload = None
    try:
        payload_raw, _, _ = qr_detector.detectAndDecode(image)
        if payload_raw:
            qr_payload = str(payload_raw)
        elif processed is not None:
            payload_proc, _, _ = qr_detector.detectAndDecode(processed)
            if payload_proc:
                qr_payload = str(payload_proc)
    except Exception:
        qr_payload = None
    return {
        "contract_version": detect.PREFLIGHT_CONTRACT_VERSION,
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
        "declared_dimensions": {"width": target_width, "height": target_height},
        "measured_dimensions": {"width": int(measured_width), "height": int(measured_height)},
        "orientation_hint": "upright",
        "qr_payload": qr_payload if qr_payload else None,
        "timings": {"preprocess_ms": round(preprocess_ms, 3), "preflight_ms": round((time.monotonic() - started) * 1000.0, 3)},
        "rectification": rectification_metadata,
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
        payload = {"error": error.code, "detail": str(error)}
        if error.rectification:
            payload["rectification"] = error.rectification
        print(json.dumps(payload, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR capture preflight failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
