"""Read-only v5 layout verification for fiducial and document-bound targets.

Returns the clean aligned raster, the engine layout overlay, per-bubble means,
field-block shifts, item evidence, and registration provenance. Fiducial targets
retain the CropOnMarkers path. Document-bound targets use detect.py's explicit
server-side quad gates and exactly one rectification warp; client geometry is
diagnostic-only. Neither path qualifies calibration or creates answer authority.
"""

import base64
import io
import json
import time
from pathlib import Path
import sys
import cv2
import numpy as np

import detect

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
        is_vertical = str(getattr(block, "direction", "")).lower() == "vertical"
        for bubbles in block.traverse_bubbles:
            for pt in bubbles:
                if is_vertical:
                    x = int(pt.x)
                    y = int(pt.y + block.shift)
                else:
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
    try:
        native_config = detect.validate_manifest(manifest)
    except detect.DetectionError as error:
        if (
            error.code == "native_preprocessor_mismatch"
            and manifest.get("registration_mode") == "fiducial_anchored"
            and not detect.marker_bearing_template(manifest.get("native_template", {}))
        ):
            raise detect.DetectionError(
                "marker_versions_only",
                "Layout verification requires a marker-bearing template version; document-bound layouts use the v5 document rectifier.",
            ) from None
        raise
    detected = detect.detect(manifest, image_path, output_directory, require_expected_labels=False)
    native_template = detect.native_template_for_page(manifest)
    defaults, template_class, image_ops_class = detect.load_omrchecker()
    config = detect.merge_config(native_config, defaults)
    template_path = output_directory / "template.json"
    with detect.capture_engine_logs(io.StringIO()):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)

    shifts = detected.get("field_block_shifts", [])
    shifts_by_name = {
        str(shift.get("block")): shift
        for shift in shifts
        if isinstance(shift, dict) and isinstance(shift.get("block"), str)
    }
    for block in template.field_blocks:
        shift = shifts_by_name.get(str(block.name), {})
        block.shift = int(
            shift.get("y" if str(getattr(block, "direction", "")).lower() == "vertical" else "x", 0)
        )

    aligned_path = output_directory / "aligned.png"
    aligned = cv2.imread(str(aligned_path), cv2.IMREAD_GRAYSCALE)
    detect.require(isinstance(aligned, np.ndarray) and aligned.size > 0, "aligned_image_invalid", "Detector did not produce a readable aligned raster.")
    aligned_height, aligned_width = aligned.shape[:2]
    page_dimensions = native_template.get("pageDimensions")
    detect.require(
        isinstance(page_dimensions, (list, tuple))
        and len(page_dimensions) == 2
        and (aligned_width, aligned_height) == (int(page_dimensions[0]), int(page_dimensions[1])),
        "aligned_image_invalid",
        "Aligned image dimensions do not match the page-specific template.",
    )
    registered_items = detected.get("registered_items")
    detect.require(
        isinstance(registered_items, list) and len(registered_items) == len(manifest["items"]),
        "provider_output_invalid",
        "Detector omitted the registered item geometry.",
    )
    for declared, registered in zip(manifest["items"], registered_items):
        detect.require(
            isinstance(registered, dict)
            and registered.get("native_label") == declared["native_label"]
            and registered.get("position") == declared["position"]
            and registered.get("area_code") == declared["area_code"]
            and registered.get("question_no") == declared["question_no"]
            and isinstance(registered.get("options"), list)
            and len(registered["options"]) == len(declared["options"]),
            "provider_output_invalid",
            "Registered item identity differs from the declared mapping.",
        )
        for expected_option, registered_option in zip(declared["options"], registered["options"]):
            detect.require(
                isinstance(registered_option, dict)
                and registered_option.get("code") == expected_option["code"]
                and isinstance(registered_option.get("region"), dict),
                "provider_output_invalid",
                "Registered option identity differs from the declared mapping.",
            )
            region = registered_option["region"]
            values = [region.get(key) for key in ("x", "y", "w", "h")]
            detect.require(
                all(type(value) in (int, float) and np.isfinite(value) for value in values),
                "provider_output_invalid",
                "Registered option geometry is malformed.",
            )
            x, y, w, h = (float(value) for value in values)
            detect.require(
                x >= 0.0 and y >= 0.0 and w > 0.0 and h > 0.0 and x + w <= 1.0 and y + h <= 1.0
                and w == float(expected_option["region"]["w"])
                and h == float(expected_option["region"]["h"]),
                "provider_output_invalid",
                "Registered option geometry is outside its declared frame.",
            )
    detect.apply_registered_items_to_template(template, registered_items, aligned_width, aligned_height)

    overlay = image_ops_class.draw_template_layout(aligned, template, shifted=True, draw_qvals=True)
    aligned_ok, aligned_encoded = cv2.imencode(".png", aligned, [cv2.IMWRITE_PNG_COMPRESSION, 9])
    overlay_ok, overlay_encoded = cv2.imencode(".png", overlay, [cv2.IMWRITE_PNG_COMPRESSION, 9])
    detect.require(bool(aligned_ok) and bool(overlay_ok), "aligned_image_invalid", "Layout overlay encoding failed.")
    (output_directory / "aligned.png").write_bytes(aligned_encoded.tobytes())
    (output_directory / "layout_overlay.png").write_bytes(overlay_encoded.tobytes())

    means = bubble_means(aligned, template)
    field_block_shifts = detected.get("field_block_shifts", [])
    rectification = detected.get("rectification", {})
    unsafe_shifts = detect.find_unsafe_shifts(field_block_shifts, native_template.get("fieldBlocks", {}))
    if manifest["registration_mode"] == "document_bound":
        document_gates = rectification.get("document_gates", {})
        keep_original = (
            manifest.get("crop_policy") == "keep_original"
            or rectification.get("crop_policy") == "keep_original"
        )
        gates = {
            "accepted": (keep_original or document_gates.get("accepted") is True) and not unsafe_shifts,
            "registration_mode": "document_bound",
            "paper_quad_unique": document_gates.get("unique_quad") is True,
            "aspect_ratio": document_gates.get("aspect_ratio") is True,
            "homography": document_gates.get("homography") is True,
            "distributed_content": document_gates.get("distributed_content") is True,
            "unsafe_blocks": unsafe_shifts,
        }
    else:
        marker_matches = detected.get("provider_diagnostics", {}).get("marker_matches", {})
        gates = {
            "accepted": len(marker_matches) == 4 and all(float(score) >= 0.30 for score in marker_matches.values()) and not unsafe_shifts,
            "registration_mode": "fiducial_anchored",
            "marker_count": len(marker_matches),
            "registration": len(marker_matches) == 4 and all(float(score) >= 0.30 for score in marker_matches.values()),
            "dimensions": (aligned_width, aligned_height) == (int(page_dimensions[0]), int(page_dimensions[1])),
            "unsafe_blocks": unsafe_shifts,
        }

    source_image = manifest["source_image"]
    marker_matches = detected.get("provider_diagnostics", {}).get("marker_matches", {})
    return {
        "contract_version": detect.CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "registration_mode": manifest["registration_mode"],
        "expected_page_no": manifest["expected_page_no"],
        **detect.target_identity(manifest),
        "source_image": {
            "width": source_image["width"],
            "height": source_image["height"],
            "orientation": source_image["orientation"],
            "input_hash": source_image["input_hash"],
        },
        "outcome": "succeeded",
        "engine": detected["engine"],
        "input_hash": manifest["input_hash"],
        "native_bundle_hash": manifest["native_bundle_hash"],
        "manifest_hash": manifest["manifest_hash"],
        "config_hash": manifest["config_hash"],
        "calibration_status": "unqualified",
        "image": detected["image"],
        "marker_matches": marker_matches,
        "aligned_image": {"mime": "image/png", "width": int(aligned_width), "height": int(aligned_height)},
        "layout_overlay": {"mime": "image/png", "width": int(aligned_width), "height": int(aligned_height)},
        "field_block_shifts": field_block_shifts,
        "bubble_means": means,
        "gates": gates,
        "item_evidence": item_evidence(
            aligned,
            {
                **manifest,
                "items": registered_items,
                "field_block_shifts": field_block_shifts,
            },
            means,
        ),
        "source_hashes": {
            "source": manifest.get("input_hash"),
            "mapping": manifest.get("mapping_hash"),
            "bundle": manifest.get("native_bundle_hash"),
        },
        "timings": {
            "preprocess_ms": detected.get("timings", {}).get("preprocess_ms"),
            "verification_ms": round((time.monotonic() - started) * 1000.0, 3),
        },
        "rectification": rectification,
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
        payload = {"error": error.code, "detail": str(error)}
        if error.rectification:
            payload["rectification"] = error.rectification
        print(json.dumps(payload, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR layout verification failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
