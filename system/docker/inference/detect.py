import hashlib
import json
import os
import sys
import time
from contextlib import redirect_stdout
from pathlib import Path

import cv2
import numpy as np
from dotmap import DotMap


ENGINE_NAME = "OMRChecker"
ENGINE_COMMIT = os.environ.get("OMRCHECKER_COMMIT", "")
PATCH_IDENTITY = os.environ.get("NEXIAM_PATCH_IDENTITY", "")
CONTRACT_VERSION = "nexiam.omr.detection.v2"


class DetectionError(Exception):
    def __init__(self, code: str, detail: str):
        super().__init__(detail)
        self.code = code


def load_omrchecker():
    with redirect_stdout(sys.stderr):
        from src.core import ImageInstanceOps
        from src.defaults import CONFIG_DEFAULTS
        from src.template import Template

    return CONFIG_DEFAULTS, Template, ImageInstanceOps


def merge_config(overrides: dict, defaults: DotMap) -> DotMap:
    config = DotMap(defaults.toDict(), _dynamic=False)
    for section, values in overrides.items():
        if not isinstance(values, dict):
            continue
        if section not in config:
            config[section] = DotMap({}, _dynamic=False)
        for key, value in values.items():
            config[section][key] = value

    config.outputs.show_image_level = 0
    config.outputs.save_image_level = 0
    config.outputs.save_detections = False
    return config


def require(condition: bool, code: str, detail: str) -> None:
    if not condition:
        raise DetectionError(code, detail)


def sniff_mime(image: bytes) -> str:
    if image.startswith(b"\x89PNG\r\n\x1a\n"):
        return "image/png"
    if image.startswith(b"\xff\xd8\xff"):
        return "image/jpeg"
    if image.startswith(b"RIFF") and image[8:12] == b"WEBP":
        return "image/webp"
    raise DetectionError("image_type_invalid", "Unsupported image encoding.")


def region_union(regions: list[dict]) -> dict:
    left = min(float(region["x"]) for region in regions)
    top = min(float(region["y"]) for region in regions)
    right = max(float(region["x"]) + float(region["w"]) for region in regions)
    bottom = max(float(region["y"]) + float(region["h"]) for region in regions)
    return {"x": left, "y": top, "w": right - left, "h": bottom - top}


def issue(code: str, severity: str, item: dict | None = None) -> dict:
    value = {"code": code, "severity": severity}
    if item is not None:
        value["area_code"] = item["area_code"]
        value["question_no"] = item["question_no"]
    return value


def validate_manifest(manifest: dict) -> None:
    require(manifest.get("contract_version") == CONTRACT_VERSION, "contract_mismatch", "Unsupported contract version.")
    engine = manifest.get("engine")
    require(isinstance(engine, dict), "manifest_invalid", "Engine identity is missing.")
    require(engine.get("name") == ENGINE_NAME, "engine_mismatch", "Unexpected engine name.")
    require(engine.get("commit") == ENGINE_COMMIT, "engine_mismatch", "Unexpected engine commit.")
    require(engine.get("patch_identity") == PATCH_IDENTITY, "engine_mismatch", "Unexpected patch identity.")
    require(isinstance(manifest.get("native_template"), dict), "manifest_invalid", "Native template is missing.")
    require(isinstance(manifest.get("native_config"), dict), "manifest_invalid", "Native config is missing.")
    require(isinstance(manifest.get("items"), list) and manifest["items"], "manifest_invalid", "Expected items are missing.")
    require(isinstance(manifest.get("identity_fields"), list), "manifest_invalid", "Identity field declarations are missing.")
    require(isinstance(manifest.get("native_bundle_metadata"), dict), "manifest_invalid", "Native bundle metadata is missing.")
    require(isinstance(manifest.get("review_manifest"), dict), "manifest_invalid", "Review manifest is missing.")
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
    require(hashlib.sha256(canonical(manifest["native_bundle_metadata"])).hexdigest() == manifest.get("native_bundle_hash"), "bundle_hash_mismatch", "Native bundle hash does not match.")
    require(hashlib.sha256(canonical(manifest["native_config"])).hexdigest() == manifest.get("config_hash"), "config_hash_mismatch", "Native config hash does not match.")
    require(hashlib.sha256(canonical(manifest["review_manifest"])).hexdigest() == manifest.get("manifest_hash"), "manifest_hash_mismatch", "Review manifest hash does not match.")

    labels: set[str] = set()
    for item in manifest["items"]:
        require(isinstance(item, dict), "manifest_invalid", "An expected item is malformed.")
        label = item.get("native_label")
        require(isinstance(label, str) and label and label not in labels, "manifest_invalid", "Expected item labels must be unique.")
        labels.add(label)
        options = item.get("options")
        require(isinstance(options, list) and options, "manifest_invalid", "Expected item options are missing.")
        option_codes = [option.get("code") for option in options if isinstance(option, dict)]
        require(len(option_codes) == len(options) and len(set(option_codes)) == len(options), "manifest_invalid", "Expected item options are malformed.")


def detect(manifest: dict, image_path: Path, output_directory: Path) -> dict:
    started = time.monotonic()
    validate_manifest(manifest)
    image_bytes = image_path.read_bytes()
    mime = sniff_mime(image_bytes)
    require(mime == manifest.get("image_mime"), "image_metadata_mismatch", "Decoded image MIME does not match the request.")

    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    image = cv2.imdecode(encoded, cv2.IMREAD_GRAYSCALE)
    require(image is not None and image.size > 0, "image_decode_failed", "Image decoding failed.")
    height, width = image.shape[:2]
    require(width == manifest.get("image_width") and height == manifest.get("image_height"), "image_metadata_mismatch", "Decoded image dimensions do not match the request.")

    defaults, template_class, image_ops_class = load_omrchecker()
    config = merge_config(manifest["native_config"], defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(manifest["native_template"], separators=(",", ":")), encoding="utf-8")

    with redirect_stdout(sys.stderr):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        processed = image_ops.apply_preprocessors(str(image_path), image, template)
        require(processed is not None, "preprocessing_failed", "Image preprocessing failed.")
        preprocess_ms = (time.monotonic() - preprocess_started) * 1000.0
        response, aligned, _, _ = image_ops.read_omr_response(template, processed, image_path.name, save_dir=None)

    require(isinstance(response, dict), "provider_output_invalid", "OMRChecker returned malformed observations.")
    require(isinstance(aligned, np.ndarray) and aligned.size > 0, "aligned_image_invalid", "OMRChecker returned no aligned image.")

    observations = []
    issues = []
    expected_labels = {item["native_label"] for item in manifest["items"]}
    require(expected_labels.issubset(response.keys()), "provider_output_invalid", "OMRChecker omitted expected labels.")

    for item in manifest["items"]:
        label = item["native_label"]
        raw_value = str(response.get(label, "")).strip()
        option_codes = [str(option["code"]) for option in item["options"]]
        if raw_value == "":
            state = "blank"
            selected = None
            issues.append(issue("blank_mark", "required", item))
        elif raw_value in option_codes:
            state = "marked"
            selected = raw_value
        else:
            state = "ambiguous"
            selected = None
            issues.append(issue("ambiguous_mark", "required", item))

        observations.append(
            {
                "position": int(item["position"]),
                "area_code": str(item["area_code"]),
                "question_no": int(item["question_no"]),
                "selected_option": selected,
                "state": state,
                "confidence": None,
                "region": item["region"],
            }
        )

    identity_observations = []
    applicant_digits = []
    identity_regions = []
    for field in manifest["identity_fields"]:
        label = str(field["native_label"])
        raw_value = str(response.get(label, "")).strip()
        value = raw_value if len(raw_value) == 1 and raw_value.isdigit() else None
        applicant_digits.append(value)
        identity_regions.append(field["region"])
        identity_observations.append(
            {
                "code": "applicant_number",
                "native_label": label,
                "position": int(field["position"]),
                "value": value,
                "confidence": None,
                "region": field["region"],
            }
        )

    applicant_number = "".join(applicant_digits) if applicant_digits and all(value is not None for value in applicant_digits) else None
    if applicant_number is None:
        issues.append(issue("identity_unresolved", "required"))

    aligned_path = output_directory / "aligned.png"
    encoded_ok, encoded_aligned = cv2.imencode(".png", aligned, [cv2.IMWRITE_PNG_COMPRESSION, 9])
    require(bool(encoded_ok), "aligned_image_invalid", "Aligned image encoding failed.")
    aligned_path.write_bytes(encoded_aligned.tobytes())
    aligned_height, aligned_width = aligned.shape[:2]

    field_block_shifts = [
        {"block": str(block.name), "x": int(block.shift), "y": 0}
        for block in template.field_blocks
    ]
    detection_ms = (time.monotonic() - started) * 1000.0

    return {
        "contract_version": CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "outcome": "succeeded",
        "engine": {"name": ENGINE_NAME, "version": ENGINE_COMMIT[:12], "commit": ENGINE_COMMIT, "patch_identity": PATCH_IDENTITY},
        "input_hash": manifest["input_hash"],
        "native_bundle_hash": manifest["native_bundle_hash"],
        "manifest_hash": manifest["manifest_hash"],
        "config_hash": manifest["config_hash"],
        "booklet_hash": manifest.get("booklet_hash"),
        "image": {"mime": mime, "width": width, "height": height},
        "calibration_status": "unqualified",
        "sheet_confidence": None,
        "applicant_number_proposal": applicant_number,
        "applicant_number_confidence": None,
        "identity_fields": identity_observations,
        "identity_region": region_union(identity_regions) if identity_regions else None,
        "items": observations,
        "issues": issues,
        "field_block_shifts": field_block_shifts,
        "aligned_image": {"mime": "image/png", "width": int(aligned_width), "height": int(aligned_height)},
        "timings": {"preprocess_ms": round(preprocess_ms, 3), "detection_ms": round(detection_ms, 3)},
    }


def self_test() -> int:
    require(ENGINE_COMMIT != "", "engine_unconfigured", "Engine commit is not configured.")
    require(PATCH_IDENTITY != "", "engine_unconfigured", "Patch identity is not configured.")
    load_omrchecker()
    print(json.dumps({"status": "ready", "engine": ENGINE_NAME, "commit": ENGINE_COMMIT, "patch_identity": PATCH_IDENTITY}, separators=(",", ":")))
    return 0


def main() -> int:
    try:
        if len(sys.argv) == 2 and sys.argv[1] == "--self-test":
            return self_test()
        if len(sys.argv) != 4:
            raise DetectionError("invocation_invalid", "Expected manifest, image, and output directory paths.")
        manifest = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
        result = detect(manifest, Path(sys.argv[2]), Path(sys.argv[3]))
        result_path = Path(sys.argv[3]) / "result.json"
        result_path.write_text(json.dumps(result, separators=(",", ":"), sort_keys=True), encoding="utf-8")
        return 0
    except DetectionError as error:
        print(json.dumps({"error": error.code, "detail": str(error)}, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR analysis failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
