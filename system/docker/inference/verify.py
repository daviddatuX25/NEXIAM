"""Read-only layout-verification child for POST /v1/layout-verification (task 10.5).

Runs the SAME marker registration pipeline as detect.py (shared helpers) for a
marker-bearing template, then returns the clean aligned raster, the engine
`draw_template_layout(aligned, shifted=True, draw_qvals=True)` overlay PNG,
per-bubble mean intensities, marker match diagnostics, and field-block shifts.

Strictly read-only: it mutates nothing in NEXIAM (no activation, pairs, answer
keys, or scores) and runs in the same isolated child-process envelope as
detection. Marker-less templates are rejected with a typed error
(`marker_versions_only`); a marker failure is a typed terminal state, never a
degrade into the Hough fallback (shared with detect.py).

Never modify this file to change registration or recognition behavior.
"""

import base64
import json
import time
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import cv2
import numpy as np

import detect
import marker_states


def bubble_means(aligned: np.ndarray, template) -> dict:
    """Per-bubble mean intensities over the aligned frame.

    The rectangles are exactly the ones `draw_template_layout(shifted=True)`
    annotates: bubble rects offset by the mutated field-block shift, clipped
    to the frame. Values are observations for the diagnostic view, never
    answer proposals.
    """
    means: dict[str, list] = {}
    height, width = aligned.shape[:2]
    for block in template.field_blocks:
        box_w, box_h = block.bubble_dimensions
        entries: list[dict] = []
        for bubbles in block.traverse_bubbles:
            for pt in bubbles:
                x = int(pt.x + block.shift)
                y = int(pt.y)
                right = min(width, x + box_w)
                bottom = min(height, y + box_h)
                if x < 0 or y < 0 or right <= x or bottom <= y:
                    entries.append({"label": str(pt.field_label), "option": str(pt.field_value), "mean": None})
                    continue
                crop = aligned[y:bottom, x:right]
                entries.append({
                    "label": str(pt.field_label),
                    "option": str(pt.field_value),
                    "mean": round(float(cv2.mean(crop)[0]), 1),
                })
        means[str(block.name)] = entries
    return means


def item_evidence(aligned: np.ndarray, manifest: dict, means: dict) -> list[dict]:
    """Project declared option geometry into read-only evidence rows.

    The declared option set comes from the authenticated manifest; this
    function never invents labels/options and never returns recognition data.
    """
    height, width = aligned.shape[:2]
    means_by_label = {}
    for entries in means.values():
        for entry in entries:
            means_by_label[(entry["label"], entry["option"])] = entry.get("mean")
    evidence = []
    for item in manifest.get("items", []):
        if not isinstance(item, dict):
            continue
        label = str(item.get("native_label", ""))
        item_region = item.get("region", {})
        option_rows = []
        for option in item.get("options", []):
            if not isinstance(option, dict):
                continue
            region = option.get("region", {})
            x = int(round(float(region.get("x", 0)) * width)) if float(region.get("x", 0)) <= 1 else int(region.get("x", 0))
            y = int(round(float(region.get("y", 0)) * height)) if float(region.get("y", 0)) <= 1 else int(region.get("y", 0))
            w = int(round(float(region.get("w", 0)) * width)) if float(region.get("w", 0)) <= 1 else int(region.get("w", 0))
            h = int(round(float(region.get("h", 0)) * height)) if float(region.get("h", 0)) <= 1 else int(region.get("h", 0))
            x0, y0 = max(0, x), max(0, y)
            x1, y1 = min(width, x + max(1, w)), min(height, y + max(1, h))
            crop = aligned[y0:y1, x0:x1]
            crop_b64 = ""
            if crop.size:
                crop = cv2.resize(crop, (min(96, crop.shape[1]), min(96, crop.shape[0])), interpolation=cv2.INTER_AREA)
                encoded, buffer = cv2.imencode(".png", crop, [cv2.IMWRITE_PNG_COMPRESSION, 9])
                if encoded:
                    crop_b64 = base64.b64encode(buffer.tobytes()).decode("ascii")
            option_code = str(option.get("code", option.get("display_order", "")))
            mean = means_by_label.get((label, option_code))
            option_rows.append({
                "option": option_code,
                "rect": {"x": x, "y": y, "w": w, "h": h},
                "crop_png_base64": crop_b64,
                "bubble_mean": mean,
                "threshold_delta": None if mean is None else round(200.0 - float(mean), 1),
                "threshold_status": "Unqualified",
            })
        evidence.append({
            "label": label,
            "page": int(item.get("page", 1)),
            "item_rect": item_region,
            "options": option_rows,
            "field_block_shifts": manifest.get("field_block_shifts", []),
            "source_hash": manifest.get("input_hash"),
            "mapping_hash": manifest.get("mapping_hash"),
            "bundle_hash": manifest.get("native_bundle_hash"),
        })
    return evidence


def verify(manifest: dict, image_path: Path, output_directory: Path) -> dict:
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

    # Layout verification is a marker-bearing diagnostic only.
    detect.require(
        detect.marker_bearing_template(manifest.get("native_template", {})),
        "marker_versions_only",
        "Layout verification requires a marker-bearing template version; scan (CropPage) profiles are not supported.",
    )

    defaults, template_class, image_ops_class = detect.load_omrchecker()
    config = detect.merge_config(manifest["native_config"], defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(manifest["native_template"], separators=(",", ":")), encoding="utf-8")
    detect.materialize_native_assets(manifest, output_directory)

    engine_log_capture = __import__("io").StringIO()
    with redirect_stdout(engine_log_capture), redirect_stderr(engine_log_capture):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        processing_width = int(config.dimensions.processing_width)
        processing_height = int(config.dimensions.processing_height)
        camera_input = decoded_width != processing_width or decoded_height != processing_height
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
    if camera_input:
        processed = detect.normalize_camera_lighting(processed)

    marker_matches = marker_states.parse_match_scores(engine_text)

    with redirect_stdout(sys.stderr):
        # Patched contract: the second tuple value is the clean aligned raster.
        response, aligned, _, _ = image_ops.read_omr_response(template, processed, image_path.name, save_dir=None)

    detect.require(isinstance(response, dict), "provider_output_invalid", "OMRChecker returned malformed observations.")
    detect.require(isinstance(aligned, np.ndarray) and aligned.size > 0, "aligned_image_invalid", "OMRChecker returned no aligned image.")

    overlay = image_ops_class.draw_template_layout(aligned, template, shifted=True, draw_qvals=True)

    aligned_ok, aligned_encoded = cv2.imencode(".png", aligned, [cv2.IMWRITE_PNG_COMPRESSION, 9])
    overlay_ok, overlay_encoded = cv2.imencode(".png", overlay, [cv2.IMWRITE_PNG_COMPRESSION, 9])
    detect.require(bool(aligned_ok) and bool(overlay_ok), "aligned_image_invalid", "Layout overlay encoding failed.")
    (output_directory / "aligned.png").write_bytes(aligned_encoded.tobytes())
    (output_directory / "layout_overlay.png").write_bytes(overlay_encoded.tobytes())
    aligned_height, aligned_width = aligned.shape[:2]

    field_block_shifts = [
        {"block": str(block.name), "x": int(block.shift), "y": 0}
        for block in template.field_blocks
    ]
    means = bubble_means(aligned, template)

    return {
        "contract_version": detect.CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "outcome": "succeeded",
        "engine": {"name": detect.ENGINE_NAME, "version": detect.ENGINE_COMMIT[:12], "commit": detect.ENGINE_COMMIT, "patch_identity": detect.PATCH_IDENTITY},
        "input_hash": manifest["input_hash"],
        "native_bundle_hash": manifest["native_bundle_hash"],
        "manifest_hash": manifest["manifest_hash"],
        "config_hash": manifest["config_hash"],
        "calibration_status": "unqualified",
        "image": {"mime": mime, "width": decoded_width, "height": decoded_height},
        "marker_matches": marker_matches,
        "aligned_image": {"mime": "image/png", "width": int(aligned_width), "height": int(aligned_height)},
        "layout_overlay": {"mime": "image/png", "width": int(aligned_width), "height": int(aligned_height)},
        "field_block_shifts": field_block_shifts,
        "bubble_means": means,
        "item_evidence": item_evidence(aligned, {**manifest, "field_block_shifts": field_block_shifts}, means),
        "source_hashes": {"source": manifest.get("input_hash"), "mapping": manifest.get("mapping_hash"), "bundle": manifest.get("native_bundle_hash")},
        "timings": {"preprocess_ms": round(preprocess_ms, 3), "verification_ms": round((time.monotonic() - started) * 1000.0, 3)},
    }


def main() -> int:
    try:
        if len(sys.argv) != 4:
            raise detect.DetectionError("invocation_invalid", "Expected manifest, image, and output directory paths.")
        manifest = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
        result = verify(manifest, Path(sys.argv[2]), Path(sys.argv[3]))
        result_path = Path(sys.argv[3]) / "result.json"
        result_path.write_text(json.dumps(result, separators=(",", ":"), sort_keys=True), encoding="utf-8")
        return 0
    except detect.DetectionError as error:
        print(json.dumps({"error": error.code, "detail": str(error)}, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR layout verification failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
