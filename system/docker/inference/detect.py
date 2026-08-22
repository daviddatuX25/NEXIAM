import base64
import hashlib
import io
import json
import os
import re
import struct
import sys
import time
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import cv2
import numpy as np
from dotmap import DotMap

import marker_states


ENGINE_NAME = "OMRChecker"
ENGINE_COMMIT = os.environ.get("OMRCHECKER_COMMIT", "")
PATCH_IDENTITY = os.environ.get("NEXIAM_PATCH_IDENTITY", "")
CONTRACT_VERSION = "nexiam.omr.detection.v2"
LOCAL_SUPPORT_DELTA = 10.0


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


def exif_orientation_from_tiff(tiff: bytes) -> int:
    if len(tiff) < 8:
        return 1
    if tiff[:2] == b"II":
        endian = "<"
    elif tiff[:2] == b"MM":
        endian = ">"
    else:
        return 1

    try:
        if struct.unpack_from(endian + "H", tiff, 2)[0] != 42:
            return 1
        ifd_offset = struct.unpack_from(endian + "I", tiff, 4)[0]
        if ifd_offset + 2 > len(tiff):
            return 1
        entry_count = struct.unpack_from(endian + "H", tiff, ifd_offset)[0]
        for index in range(entry_count):
            entry_offset = ifd_offset + 2 + index * 12
            if entry_offset + 12 > len(tiff):
                return 1
            tag, value_type, value_count = struct.unpack_from(endian + "HHI", tiff, entry_offset)
            if tag != 0x0112 or value_type != 3 or value_count < 1:
                continue
            if value_count == 1:
                value = struct.unpack_from(endian + "H", tiff, entry_offset + 8)[0]
            else:
                value_offset = struct.unpack_from(endian + "I", tiff, entry_offset + 8)[0]
                if value_offset + 2 > len(tiff):
                    return 1
                value = struct.unpack_from(endian + "H", tiff, value_offset)[0]
            return value if 1 <= value <= 8 else 1
    except struct.error:
        return 1

    return 1


def exif_orientation(image: bytes) -> int:
    """Read the bounded JPEG EXIF orientation tag without trusting image metadata."""
    if not image.startswith(bytes((255, 216, 255))):
        return 1

    offset = 2
    while offset + 4 <= len(image):
        if image[offset] != 0xFF:
            return 1
        marker = image[offset + 1]
        offset += 2
        if marker == 0xDA:
            return 1
        if marker in (0xD8, 0xD9):
            continue
        segment_length = int.from_bytes(image[offset:offset + 2], "big")
        if segment_length < 2 or offset + segment_length > len(image):
            return 1
        segment = image[offset + 2:offset + segment_length]
        if marker == 0xE1 and segment.startswith(bytes((69, 120, 105, 102, 0, 0))):
            return exif_orientation_from_tiff(segment[6:])
        offset += segment_length

    return 1


def apply_exif_orientation(image: np.ndarray, orientation: int) -> np.ndarray:
    if orientation == 2:
        return cv2.flip(image, 1)
    if orientation == 3:
        return cv2.rotate(image, cv2.ROTATE_180)
    if orientation == 4:
        return cv2.flip(image, 0)
    if orientation == 5:
        return cv2.transpose(image)
    if orientation == 6:
        return cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE)
    if orientation == 7:
        return cv2.flip(cv2.transpose(image), -1)
    if orientation == 8:
        return cv2.rotate(image, cv2.ROTATE_90_COUNTERCLOCKWISE)
    return image


def camera_option_rows(manifest: dict, page_width: int, page_height: int) -> list[list[tuple[float, float]]]:
    points = []
    for item in manifest["items"]:
        for option in item.get("options", []):
            region = option.get("region") if isinstance(option, dict) else None
            if not isinstance(region, dict):
                continue
            try:
                points.append((
                    (float(region["x"]) + float(region["w"]) / 2.0) * page_width,
                    (float(region["y"]) + float(region["h"]) / 2.0) * page_height,
                ))
            except (KeyError, TypeError, ValueError):
                continue

    rows: list[list[tuple[float, float]]] = []
    tolerance = max(2.0, page_height * 0.002)
    for point in sorted(points, key=lambda value: value[1]):
        if not rows or abs(point[1] - float(np.median([value[1] for value in rows[-1]]))) > tolerance:
            rows.append([point])
        else:
            rows[-1].append(point)

    return [
        sorted(row, key=lambda value: value[0])
        for row in rows
        if float(np.median([value[1] for value in row])) > page_height * 0.2
    ]


def hough_circle_rows(
    image: np.ndarray,
    min_radius: int,
    max_radius: int,
    min_distance: int,
    cluster_gap: float,
    bounds: tuple[float, float, float, float],
    param2: float = 22,
) -> list[list[np.ndarray]]:
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    circles = cv2.HoughCircles(
        cv2.medianBlur(gray, 5),
        cv2.HOUGH_GRADIENT,
        dp=1.2,
        minDist=min_distance,
        param1=80,
        param2=param2,
        minRadius=min_radius,
        maxRadius=max_radius,
    )
    if circles is None:
        return []

    height, width = gray.shape[:2]
    min_x, min_y, max_x, max_y = bounds
    points = np.round(circles[0]).astype(int)
    points = points[
        (points[:, 0] > width * min_x)
        & (points[:, 0] < width * max_x)
        & (points[:, 1] > height * min_y)
        & (points[:, 1] < height * max_y)
        & (points[:, 2] <= max_radius)
    ]
    points = points[np.argsort(points[:, 1])]

    rows: list[list[np.ndarray]] = []
    for point in points:
        if not rows or point[1] - float(np.median([value[1] for value in rows[-1]])) > cluster_gap:
            rows.append([point])
        else:
            rows[-1].append(point)

    return rows


def select_row_points(
    actual_row: list[np.ndarray],
    expected_row: list[tuple[float, float]],
    max_error: float,
) -> list[np.ndarray] | None:
    def fit_subset(
        actual_values: list[np.ndarray],
        expected_values: list[tuple[float, float]],
    ) -> list[np.ndarray] | None:
        expected_count = len(expected_values)
        if len(actual_values) < expected_count:
            return None
        if len(actual_values) == expected_count:
            return sorted(actual_values, key=lambda value: value[0])

        actual_values = sorted(actual_values, key=lambda value: value[0])
        expected_x = np.asarray([point[0] for point in expected_values], dtype=np.float32)
        best: tuple[float, list[np.ndarray]] | None = None
        for start in range(len(actual_values) - expected_count + 1):
            subset = actual_values[start:start + expected_count]
            source_x = np.asarray([point[0] for point in subset], dtype=np.float32)
            design = np.column_stack((source_x, np.ones(expected_count, dtype=np.float32)))
            coefficients, _, _, _ = np.linalg.lstsq(design, expected_x, rcond=None)
            error = float(np.mean(np.abs(design @ coefficients - expected_x)))
            if best is None or error < best[0]:
                best = (error, subset)

        return None if best is None or best[0] > max_error else best[1]

    actual = sorted(actual_row, key=lambda value: value[0])
    expected = sorted(expected_row, key=lambda value: value[0])
    expected_count = len(expected)
    if len(actual) < expected_count:
        return None
    if len(actual) == expected_count:
        return actual

    # Camera rows contain six four-bubble blocks. Hough may also return
    # printed row numbers or letters inside a block; fit each block rather
    # than selecting one contiguous 24-point window across those distractors.
    expected_x = np.asarray([point[0] for point in expected], dtype=np.float32)
    gaps = np.diff(expected_x)
    positive_gaps = gaps[gaps > 0]
    if len(positive_gaps) > 1:
        base_gap = float(np.median(positive_gaps))
        expected_groups: list[list[tuple[float, float]]] = []
        group_start = 0
        for index, gap in enumerate(gaps):
            if gap > base_gap * 1.75:
                expected_groups.append(expected[group_start:index + 1])
                group_start = index + 1
        expected_groups.append(expected[group_start:])

        if len(expected_groups) > 1:
            actual_groups: list[list[np.ndarray]] = []
            group_start = 0
            split_gap = max(base_gap * 1.75, 100.0)
            for index, gap in enumerate(np.diff([point[0] for point in actual])):
                if gap > split_gap:
                    actual_groups.append(actual[group_start:index + 1])
                    group_start = index + 1
            actual_groups.append(actual[group_start:])

            best_groups: tuple[float, list[np.ndarray]] | None = None
            for start in range(len(actual_groups) - len(expected_groups) + 1):
                selected_groups = actual_groups[start:start + len(expected_groups)]
                if any(len(actual_group) < len(expected_group)
                       for actual_group, expected_group in zip(selected_groups, expected_groups)):
                    continue

                selected: list[np.ndarray] = []
                total_error = 0.0
                for actual_group, expected_group in zip(selected_groups, expected_groups):
                    subset = fit_subset(actual_group, expected_group)
                    if subset is None:
                        selected = []
                        break
                    selected.extend(subset)
                    source_x = np.asarray([point[0] for point in subset], dtype=np.float32)
                    target_x = np.asarray([point[0] for point in expected_group], dtype=np.float32)
                    design = np.column_stack((source_x, np.ones(len(target_x), dtype=np.float32)))
                    coefficients, _, _, _ = np.linalg.lstsq(design, target_x, rcond=None)
                    total_error += float(np.mean(np.abs(design @ coefficients - target_x)))

                if len(selected) == expected_count and (
                    best_groups is None or total_error < best_groups[0]
                ):
                    best_groups = (total_error, selected)

            if best_groups is not None:
                return best_groups[1]

    return fit_subset(actual, expected)


def choose_camera_rows(
    candidates: list[list[np.ndarray]],
    expected_rows: list[list[tuple[float, float]]],
    source_scale: float,
) -> list[list[np.ndarray]] | None:
    row_count = len(expected_rows)
    if len(candidates) < row_count:
        return None

    target_y = [float(np.median([point[1] for point in row])) for row in expected_rows]
    expected_gap = np.median(np.diff(target_y)) * source_scale if row_count > 1 else 0.0
    best: tuple[float, list[list[np.ndarray]]] | None = None
    for start in range(len(candidates) - row_count + 1):
        window = candidates[start:start + row_count]
        score = 0.0
        medians = [float(np.median([point[1] for point in row])) for row in window]
        for actual_row, expected_row in zip(window, expected_rows):
            score += min(len(actual_row), len(expected_row)) - abs(len(actual_row) - len(expected_row)) * 2
        if expected_gap > 0.0:
            for left, right in zip(medians, medians[1:]):
                ratio = (right - left) / expected_gap
                score -= (ratio - 1.0) ** 2 * 6.0
                if ratio < 0.4 or ratio > 1.8:
                    score -= 20.0
        if best is None or score > best[0]:
            best = (score, window)

    return best[1] if best is not None else None




def align_camera_page(
    image: np.ndarray,
    manifest: dict,
    page_width: int,
    page_height: int,
) -> np.ndarray | None:
    expected_rows = camera_option_rows(manifest, page_width, page_height)
    if len(expected_rows) < 4:
        return None

    sizes = [
        (float(option["region"]["w"]) * page_width, float(option["region"]["h"]) * page_height)
        for item in manifest["items"]
        for option in item.get("options", [])
        if isinstance(option, dict) and isinstance(option.get("region"), dict)
    ]
    if not sizes:
        return None
    bubble_size = min(float(np.median([size[0] for size in sizes])), float(np.median([size[1] for size in sizes])))
    source_height, source_width = image.shape[:2]
    source_scale = min(source_width / page_width, source_height / page_height)
    min_radius = max(6, int(bubble_size * source_scale * 0.24))
    max_radius = max(min_radius + 5, int(bubble_size * source_scale * 0.9))
    expected_y = [float(np.median([point[1] for point in row])) for row in expected_rows]
    row_gap = float(np.median(np.diff(expected_y)) * source_scale) if len(expected_y) > 1 else 80.0
    minimum_count = max(4, min(len(row) for row in expected_rows) - 2)

    # Lower gap factors split a perspective row into several Hough clusters;
    # start with the stable grouping for phone photos, then keep fallbacks for
    # scans with tighter or looser vertical spacing.
    for gap_factor in (0.45, 0.35, 0.5, 0.65):
        rows = hough_circle_rows(
            image,
            min_radius,
            max_radius,
            max(10, int(bubble_size * source_scale * 0.6)),
            max(min_radius * 1.5, row_gap * gap_factor),
            (0.03, 0.18, 0.97, 0.98),
            param2=18,
        )
        candidates = [row for row in rows if len(row) >= minimum_count]
        selected = choose_camera_rows(candidates, expected_rows, source_scale)
        if selected is None:
            continue

        source_points = []
        destination_points = []
        matched_rows = 0
        for actual_row, expected_row in zip(selected, expected_rows):
            points = select_row_points(actual_row, expected_row, max(10.0, bubble_size * 0.4))
            if points is None:
                continue
            matched_rows += 1
            source_points.extend([[float(point[0]), float(point[1])] for point in points])
            destination_points.extend([[float(point[0]), float(point[1])] for point in expected_row])

        if matched_rows < 3 or len(source_points) < 16:
            continue
        matrix, inlier_mask = cv2.findHomography(
            np.asarray(source_points, dtype=np.float32),
            np.asarray(destination_points, dtype=np.float32),
            cv2.RANSAC,
            max(6.0, bubble_size * 0.25),
        )
        if matrix is None or inlier_mask is None or int(inlier_mask.sum()) < max(16, int(len(source_points) * 0.45)):
            continue

        aligned = cv2.warpPerspective(image, matrix, (page_width, page_height))
        return aligned

    return None


def normalize_camera_lighting(image: np.ndarray) -> np.ndarray:
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    sigma = max(25.0, min(gray.shape[:2]) * 0.024)
    background = cv2.GaussianBlur(gray, (0, 0), sigmaX=sigma, sigmaY=sigma)
    return cv2.divide(gray, background, scale=220)


def region_union(regions: list[dict]) -> dict:
    left = min(float(region["x"]) for region in regions)
    top = min(float(region["y"]) for region in regions)
    right = max(float(region["x"]) + float(region["w"]) for region in regions)
    bottom = max(float(region["y"]) + float(region["h"]) for region in regions)
    return {"x": left, "y": top, "w": right - left, "h": bottom - top}


def marker_bearing_template(native_template: dict) -> bool:
    """A template is marker-bearing when CropOnMarkers is its preprocessor."""
    for pre_processor in native_template.get("preProcessors", []):
        if isinstance(pre_processor, dict) and pre_processor.get("name") == "CropOnMarkers":
            return True
    return False


def marker_asset_name(native_template: dict) -> str:
    """The fiducial asset the template declares next to template.json."""
    for pre_processor in native_template.get("preProcessors", []):
        if not isinstance(pre_processor, dict) or pre_processor.get("name") != "CropOnMarkers":
            continue
        options = pre_processor.get("options") or {}
        name = options.get("relativePath")
        if isinstance(name, str) and re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._-]{0,63}", name):
            return name
        return "fiducial.png"
    return "fiducial.png"


def materialize_native_assets(manifest: dict, output_directory: Path) -> None:
    """Write marker assets next to template.json BEFORE the engine loads it.

    The PHP bundle validation guarantees the asset exists; this defensive
    materialization means the engine's `exit(31)` on a missing marker file can
    never trigger — absence here is a typed DetectionError instead.
    """
    native_template = manifest.get("native_template")
    if not isinstance(native_template, dict) or not marker_bearing_template(native_template):
        return

    name = marker_asset_name(native_template)
    assets = manifest.get("native_assets")
    if not isinstance(assets, dict):
        raise DetectionError("marker_asset_missing", "The marker-bearing manifest carried no fiducial asset.")
    encoded = assets.get(name)
    if not isinstance(encoded, str) or not encoded:
        raise DetectionError("marker_asset_missing", f"The marker-bearing manifest carried no {name} asset.")

    try:
        decoded = base64.b64decode(encoded, validate=True)
    except (ValueError, TypeError):
        raise DetectionError("marker_asset_missing", f"The {name} asset is not valid base64.") from None
    if not decoded or len(decoded) > 1_000_000:
        raise DetectionError("marker_asset_missing", f"The {name} asset bytes are invalid.")

    (output_directory / name).write_bytes(decoded)


def sheet_likely_present(image: np.ndarray) -> bool:
    """Cheap evidence that a sheet-like quad dominates the frame.

    Used only to distinguish `marker_missing` (nothing in frame) from
    `marker_mismatch` (a sheet is present but its markers do not match — wrong
    form or rotated). Never used to register or recognize.
    """
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, threshold = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    contours, _ = cv2.findContours(threshold, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    total = float(gray.shape[0] * gray.shape[1])
    if not contours:
        return False

    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:5]:
        area = float(cv2.contourArea(contour))
        if area < total * 0.25 or area > total * 0.9:
            continue
        perimeter = cv2.arcLength(contour, True)
        if perimeter <= 0:
            continue
        approx = cv2.approxPolyDP(contour, 0.02 * perimeter, True)
        if len(approx) == 4:
            return True
    return False


def issue(code: str, severity: str, item: dict | None = None) -> dict:
    value = {"code": code, "severity": severity}
    if item is not None:
        value["area_code"] = item["area_code"]
        value["question_no"] = item["question_no"]
    return value


def option_strengths(image: np.ndarray, item: dict) -> dict[str, float]:
    strengths: dict[str, float] = {}
    for option in item.get("options", []):
        region = option.get("region") if isinstance(option, dict) else None
        code = str(option.get("code", "")) if isinstance(option, dict) else ""
        if not isinstance(region, dict) or code == "":
            continue

        width = float(region.get("w", 0.0)) * image.shape[1]
        height = float(region.get("h", 0.0)) * image.shape[0]
        center_x = (float(region.get("x", 0.0)) + float(region.get("w", 0.0)) / 2.0) * image.shape[1]
        center_y = (float(region.get("y", 0.0)) + float(region.get("h", 0.0)) / 2.0) * image.shape[0]
        radius = min(width, height) * 0.27
        left = max(0, int(center_x - radius))
        top = max(0, int(center_y - radius))
        right = min(image.shape[1], int(center_x + radius + 1))
        bottom = min(image.shape[0], int(center_y + radius + 1))
        crop = image[top:bottom, left:right]
        if crop.size == 0:
            continue
        yy, xx = np.ogrid[:crop.shape[0], :crop.shape[1]]
        mask = (xx - (center_x - left)) ** 2 + (yy - (center_y - top)) ** 2 <= radius ** 2
        values = crop[mask]
        if values.size > 0:
            strengths[code] = float(values.mean())

    return strengths


def selected_option_is_supported(image: np.ndarray, item: dict, selected: str) -> bool:
    """Reject provider marks whose selected bubble is not darker than peers."""
    strengths = option_strengths(image, item)
    selected_strength = strengths.get(selected)
    competing = [value for code, value in strengths.items() if code != selected]

    return selected_strength is not None and len(competing) > 0 and selected_strength + LOCAL_SUPPORT_DELTA < min(competing)


def threshold_diagnostics(config: DotMap) -> dict[str, int | float | str]:
    values: dict[str, int | float | str] = {}

    def visit(section: object, prefix: str = "") -> None:
        if isinstance(section, dict):
            entries = section.items()
        elif isinstance(section, DotMap):
            entries = section.toDict().items()
        else:
            return
        for key, value in entries:
            name = f"{prefix}.{key}" if prefix else str(key)
            if isinstance(value, (dict, DotMap)):
                visit(value, name)
            elif any(token in str(key).lower() for token in ("threshold", "thr")) and isinstance(value, (int, float, str)):
                values[name] = value

    visit(config)
    return values


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
    decode_flags = cv2.IMREAD_GRAYSCALE | getattr(cv2, "IMREAD_IGNORE_ORIENTATION", 0)
    image = cv2.imdecode(encoded, decode_flags)
    require(image is not None and image.size > 0, "image_decode_failed", "Image decoding failed.")
    decoded_height, decoded_width = image.shape[:2]
    require(decoded_width == manifest.get("image_width") and decoded_height == manifest.get("image_height"), "image_metadata_mismatch", "Decoded image dimensions do not match the request.")
    image = apply_exif_orientation(image, exif_orientation(image_bytes))
    height, width = image.shape[:2]

    defaults, template_class, image_ops_class = load_omrchecker()
    config = merge_config(manifest["native_config"], defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(manifest["native_template"], separators=(",", ":")), encoding="utf-8")
    marker_bearing = marker_bearing_template(manifest["native_template"])
    materialize_native_assets(manifest, output_directory)

    # Capture the engine's own logs during load + preprocessing: the marker
    # match scores and quadrant failures are the evidence for typed states.
    engine_log = io.StringIO()
    with redirect_stdout(engine_log), redirect_stderr(engine_log):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        processing_width = int(config.dimensions.processing_width)
        processing_height = int(config.dimensions.processing_height)
        camera_input = width != processing_width or height != processing_height
        processed = image_ops.apply_preprocessors(str(image_path), image, template)
    engine_text = engine_log.getvalue()
    sys.stderr.write(engine_text)
    preprocess_ms = (time.monotonic() - preprocess_started) * 1000.0

    if processed is None:
        if marker_bearing:
            # Invariant #4: no Hough fallback on marker-bearing versions. A
            # marker failure is a typed terminal state, never a degrade into
            # the brittle row-clustering path that produced false marks.
            state = marker_states.classify(engine_text, sheet_likely_present(image))
            raise DetectionError(
                state["code"],
                state["message"] + f" (orientation_hint={state['orientation_hint']})",
            )
        if not camera_input:
            processed = image
        else:
            processed = align_camera_page(image, manifest, processing_width, processing_height)
            if processed is None:
                raise DetectionError("preprocessing_failed", "Image preprocessing failed.")
    if camera_input:
        processed = normalize_camera_lighting(processed)

    marker_matches = marker_states.parse_match_scores(engine_text) if marker_bearing else {}

    with redirect_stdout(sys.stderr):
        # Patched contract: the second tuple value is OMRChecker's clean
        # final_align raster (never the debug-annotated final_marked), so the
        # stored aligned evidence is unannotated and safe for review/cropping.
        response, aligned, _, _ = image_ops.read_omr_response(template, processed, image_path.name, save_dir=None)

    require(isinstance(response, dict), "provider_output_invalid", "OMRChecker returned malformed observations.")
    require(isinstance(aligned, np.ndarray) and aligned.size > 0, "aligned_image_invalid", "OMRChecker returned no aligned image.")

    observations = []
    issues = []
    raw_values: dict[str, str] = {}
    mark_evidence: dict[str, dict[str, object]] = {}
    expected_labels = {item["native_label"] for item in manifest["items"]}
    require(expected_labels.issubset(response.keys()), "provider_output_invalid", "OMRChecker omitted expected labels.")

    for item in manifest["items"]:
        label = item["native_label"]
        raw_value = str(response.get(label, "")).strip()
        raw_values[label] = raw_value
        option_codes = [str(option["code"]) for option in item["options"]]
        if raw_value in option_codes:
            mark_evidence[label] = {
                "selected": raw_value,
                "means": option_strengths(processed, item),
            }
        if raw_value == "":
            state = "blank"
            selected = None
        elif raw_value in option_codes and selected_option_is_supported(processed, item, raw_value):
            state = "marked"
            selected = raw_value
        elif raw_value in option_codes:
            state = "ambiguous"
            selected = None
            issues.append(issue("ambiguous_mark", "required", item))
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
        raw_values[label] = raw_value
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
        "image": {"mime": mime, "width": decoded_width, "height": decoded_height},
        "calibration_status": "unqualified",
        "sheet_confidence": None,
        "applicant_number_proposal": applicant_number,
        "applicant_number_confidence": None,
        "identity_fields": identity_observations,
        "identity_region": region_union(identity_regions) if identity_regions else None,
        "provider_diagnostics": {
            "raw_values": raw_values,
            "mark_evidence": mark_evidence,
            "local_support_delta": LOCAL_SUPPORT_DELTA,
            "marker_matches": marker_matches,
            "thresholds": threshold_diagnostics(config),
        },
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
