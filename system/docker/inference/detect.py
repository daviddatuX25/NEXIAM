import base64
import contextlib
import hashlib
import io
import json
import logging
import math
import os
import re
import struct
import sys
import time
from contextlib import redirect_stderr, redirect_stdout
from functools import lru_cache
from pathlib import Path
import cv2
import numpy as np
from dotmap import DotMap

import document_rectifier
import marker_states
import rectification_recovery

ENGINE_NAME = "OMRChecker"
ENGINE_COMMIT = os.environ.get("OMRCHECKER_COMMIT", "")
PATCH_IDENTITY = os.environ.get("NEXIAM_PATCH_IDENTITY", "")
CURRENT_PATCH_IDENTITY = "nexiam-explicit-bubble-positions-v1"
LEGACY_PATCH_IDENTITY = "nexiam-headless-overlay-v1"
CONTRACT_VERSION = "nexiam.omr.detection.v5"
AUXILIARY_CONTRACT_VERSION = "nexiam.omr.detection.v3"
PREFLIGHT_CONTRACT_VERSION = "nexiam.omr.detection.v4"
SUPPORTED_REGISTRATION_MODES = frozenset({"fiducial_anchored", "document_bound"})
SOURCE_HASH_RE = re.compile(r"^[0-9a-f]{64}$")
CLIENT_TARGET_RE = re.compile(r"^candidate:[0-9a-f]{64}$")
LOCAL_SUPPORT_DELTA = 10.0


def _contains_explicit_bubble_positions(value: object) -> bool:
    if isinstance(value, dict):
        return "bubblePositions" in value or any(_contains_explicit_bubble_positions(child) for child in value.values())
    if isinstance(value, list):
        return any(_contains_explicit_bubble_positions(child) for child in value)
    return False


def _expected_patch_identities(native_template: dict) -> frozenset[str]:
    if _contains_explicit_bubble_positions(native_template):
        return frozenset({CURRENT_PATCH_IDENTITY})
    return frozenset({CURRENT_PATCH_IDENTITY, LEGACY_PATCH_IDENTITY})


def _engine_patch_identity(identity: dict) -> object:
    return identity.get("patch_identity") if "patch_identity" in identity else identity.get("patch_set")


def _validate_engine_identity(identity: object, accepted_patch_identities: frozenset[str]) -> None:
    require(isinstance(identity, dict), "manifest_invalid", "Engine identity is missing.")
    require(identity.get("name") == ENGINE_NAME, "engine_mismatch", "Unexpected engine name.")
    require(identity.get("commit") == ENGINE_COMMIT, "engine_mismatch", "Unexpected engine commit.")
    require(
        _engine_patch_identity(identity) in accepted_patch_identities,
        "engine_mismatch",
        "Unexpected engine patch identity for native template geometry.",
    )
 


class DetectionError(Exception):
    def __init__(self, code: str, detail: str, rectification: dict | None = None):
        super().__init__(detail)
        self.code = code
        self.rectification = rectification


@contextlib.contextmanager
def capture_engine_logs(log_stream: io.StringIO):
    """Capture stdout, stderr, and standard logging handlers deterministically."""
    handler = logging.StreamHandler(log_stream)
    handler.setLevel(logging.DEBUG)
    root_logger = logging.getLogger()
    root_logger.addHandler(handler)
    try:
        with redirect_stdout(log_stream), redirect_stderr(log_stream):
            yield
    finally:
        root_logger.removeHandler(handler)


def option_pitch(block: dict) -> tuple[float | None, float | None]:
    """Native option pitch in pixels for one field block."""
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
    """Port of OmrDetectionResultValidator::unsafeFieldBlockShifts."""
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

@lru_cache(maxsize=32)
def _ring_contrast_kernels(
    minimum_radius: int,
    maximum_radius: int,
) -> tuple[tuple[np.ndarray, ...], ...]:
    """Cache immutable full-boundary kernels without quantizing geometry."""
    kernel_radius = maximum_radius + 5
    extent = kernel_radius * 2 + 1
    yy, xx = np.ogrid[:extent, :extent]
    dx = xx - kernel_radius
    dy = yy - kernel_radius
    radius_grid = np.hypot(dx, dy)
    angles = np.mod(np.degrees(np.arctan2(dy, dx)), 360.0)
    sectors = (
        (angles <= 35.0) | (angles >= 325.0),
        (angles >= 55.0) & (angles <= 125.0),
        (angles >= 145.0) & (angles <= 215.0),
    )
    banks = []
    for radius in range(minimum_radius, maximum_radius + 1):
        ring_mask = np.abs(radius_grid - float(radius)) <= 1.4
        halo_mask = (
            (radius_grid >= float(radius) + 1.8)
            & (radius_grid <= float(radius) + 4.2)
        )
        kernels = []
        for sector in sectors:
            ring_sector = ring_mask & sector
            halo_sector = halo_mask & sector
            if not np.any(ring_sector) or not np.any(halo_sector):
                kernels = []
                break
            kernel = (
                ring_sector.astype(np.float32) / float(np.count_nonzero(ring_sector))
                - halo_sector.astype(np.float32) / float(np.count_nonzero(halo_sector))
            )
            kernel.flags.writeable = False
            kernels.append(kernel)
        if len(kernels) == 3:
            banks.append(tuple(kernels))
    return tuple(banks)


def _ring_contrast_at(
    gray: np.ndarray,
    x: int,
    y: int,
    minimum_radius: int,
    maximum_radius: int,
    contrast_tiles: dict[tuple[int, int, int, int], np.ndarray],
) -> float:
    """Read interior-point evidence from a cache owned by this raster's caller."""
    tile_size = 64
    tile_x, tile_y = x // tile_size, y // tile_size
    key = (minimum_radius, maximum_radius, tile_x, tile_y)
    tile = contrast_tiles.get(key)
    if tile is None:
        height, width = gray.shape[:2]
        kernel_radius = maximum_radius + 5
        tile_left = tile_x * tile_size
        tile_top = tile_y * tile_size
        tile_right = min(width, tile_left + tile_size)
        tile_bottom = min(height, tile_top + tile_size)
        roi_left = max(0, tile_left - kernel_radius)
        roi_top = max(0, tile_top - kernel_radius)
        roi_right = min(width, tile_right + kernel_radius)
        roi_bottom = min(height, tile_bottom + kernel_radius)
        darkness = gray[roi_top:roi_bottom, roi_left:roi_right].astype(np.float32)
        np.subtract(np.float32(255.0), darkness, out=darkness)
        output_slice = (
            slice(tile_top - roi_top, tile_bottom - roi_top),
            slice(tile_left - roi_left, tile_right - roi_left),
        )
        tile = np.zeros((tile_bottom - tile_top, tile_right - tile_left), dtype=np.float32)
        for kernels in _ring_contrast_kernels(minimum_radius, maximum_radius):
            sector_maps = [
                cv2.filter2D(darkness, cv2.CV_32F, kernel)[output_slice]
                for kernel in kernels
            ]
            supported = np.minimum.reduce(sector_maps) >= 4.0
            candidate_contrast = (sector_maps[0] + sector_maps[1] + sector_maps[2]) / 3.0
            np.maximum(tile, np.where(supported, candidate_contrast, 0.0), out=tile)
        if len(contrast_tiles) >= 512:
            contrast_tiles.pop(next(iter(contrast_tiles)))
        contrast_tiles[key] = tile
    return float(tile[y - tile_y * tile_size, x - tile_x * tile_size])


def refine_option_centers(
    gray: np.ndarray,
    options: list[dict],
    width: int,
    height: int,
    diameter: float,
    search: int,
    claimed_centers: list[tuple[float, float, float, int]] | None = None,
    owner_row_idx: int = -1,
    preserve_nominal: bool = False,
    contrast_tiles: dict[tuple[int, int, int, int], np.ndarray] | None = None,
) -> list[tuple[float, float]] | None:
    """Verify nominal points or refine using small reusable raster-evidence tiles."""
    predicted = [
        (
            (float(option["region"]["x"]) + float(option["region"]["w"]) / 2.0) * width,
            (float(option["region"]["y"]) + float(option["region"]["h"]) / 2.0) * height,
        )
        for option in options
    ]
    if len(predicted) < 2:
        return None
    minimum_radius = max(3, int(round(diameter * 0.35)))
    maximum_radius = max(4, int(round(diameter * 0.54)))
    kernel_radius = maximum_radius + 5
    maximum_adjustment = max(3, min(8, int(round(diameter * 0.28))))

    def is_available(center_x: float, center_y: float) -> bool:
        return not claimed_centers or not any(
            owner != owner_row_idx
            and math.hypot(center_x - claimed_x, center_y - claimed_y) < exclusion_radius
            for claimed_x, claimed_y, exclusion_radius, owner in claimed_centers
        )

    def sample_location(center_x: float, center_y: float) -> tuple[int, int] | None:
        x, y = int(round(center_x)), int(round(center_y))
        if (
            x < kernel_radius
            or y < kernel_radius
            or x >= width - kernel_radius
            or y >= height - kernel_radius
        ):
            return None
        return x, y

    if preserve_nominal:
        locations = []
        for center_x, center_y in predicted:
            if not is_available(center_x, center_y):
                return None
            location = sample_location(center_x, center_y)
            if location is None:
                return None
            locations.append(location)
        banks = _ring_contrast_kernels(minimum_radius, maximum_radius)
        for x, y in locations:
            patch = gray[
                y - kernel_radius:y + kernel_radius + 1,
                x - kernel_radius:x + kernel_radius + 1,
            ].astype(np.float32)
            np.subtract(np.float32(255.0), patch, out=patch)
            supported = False
            for kernels in banks:
                values = np.fromiter(
                    (
                        np.einsum("ij,ij->", patch, kernel, dtype=np.float32, optimize=False)
                        for kernel in kernels
                    ),
                    dtype=np.float32,
                    count=3,
                )
                if np.min(values) < 4.0:
                    continue
                contrast = (values[0] + values[1] + values[2]) / np.float32(3.0)
                if contrast >= 12.0:
                    supported = True
                    break
            if not supported:
                return None
        return predicted

    if contrast_tiles is None:
        contrast_tiles = {}

    def contrast_at(center_x: float, center_y: float) -> float:
        location = sample_location(center_x, center_y)
        if location is None:
            return float("-inf")
        x, y = location
        return _ring_contrast_at(
            gray, x, y, minimum_radius, maximum_radius, contrast_tiles,
        )

    candidates: list[tuple[float, float, float, float, float]] = []
    for expected_x, expected_y in predicted:
        best = (float("-inf"), 0.0, 0.0, expected_x, expected_y)
        for offset_y in range(-maximum_adjustment, maximum_adjustment + 1):
            for offset_x in range(-maximum_adjustment, maximum_adjustment + 1):
                center_x = expected_x + offset_x
                center_y = expected_y + offset_y
                if not is_available(center_x, center_y):
                    continue
                score = contrast_at(center_x, center_y)
                score -= 0.02 * (offset_x * offset_x + offset_y * offset_y)
                if score > best[0]:
                    best = (
                        score, float(offset_x), float(offset_y), center_x, center_y,
                    )
        candidates.append(best)
    confident = [candidate for candidate in candidates if candidate[0] >= 8.0]
    if len(confident) < max(2, (len(predicted) + 1) // 2):
        return None
    med_dx = float(np.median([candidate[1] for candidate in confident]))
    med_dy = float(np.median([candidate[2] for candidate in confident]))
    refined = []
    for index, (score, offset_x, offset_y, center_x, center_y) in enumerate(candidates):
        if score < 8.0:
            center_x = predicted[index][0] + med_dx
            center_y = predicted[index][1] + med_dy
        refined.append((center_x, center_y))
    deviations = [
        math.hypot(candidate[1] - med_dx, candidate[2] - med_dy)
        for candidate in confident
    ]
    if max(deviations) <= 1.0:
        refined = [(x + med_dx, y + med_dy) for x, y in predicted]
    if not all(is_available(center_x, center_y) for center_x, center_y in refined):
        return None
    if sum(contrast_at(x, y) >= 8.0 for x, y in refined) < max(2, (len(predicted) + 1) // 2):
        return None
    return refined


def _coarse_item_candidates(
    edges: np.ndarray,
    centers: list[tuple[float, float]],
    diameter: float,
    width: int,
    height: int,
    search: int,
    max_candidates: int = 6,
    is_permitted: object | None = None,
) -> list[tuple[float, float, float, float]]:
    """Find distinct whole-constellation translations, strongest first."""
    radii = range(max(3, int(round(diameter * 0.34))), max(4, int(round(diameter * 0.54))) + 1)
    max_radius = max(radii)
    base_left = int(np.floor(min(point[0] for point in centers) - max_radius - 2))
    base_top = int(np.floor(min(point[1] for point in centers) - max_radius - 2))
    base_right = int(np.ceil(max(point[0] for point in centers) + max_radius + 2))
    base_bottom = int(np.ceil(max(point[1] for point in centers) + max_radius + 2))
    roi_left = max(0, base_left - search)
    roi_top = max(0, base_top - search)
    roi_right = min(width, base_right + search)
    roi_bottom = min(height, base_bottom + search)
    template_width = base_right - base_left
    template_height = base_bottom - base_top
    roi = edges[roi_top:roi_bottom, roi_left:roi_right]
    if roi.shape[1] < template_width or roi.shape[0] < template_height:
        return []
    score_map = None
    for radius in radii:
        ring_template = np.zeros((template_height, template_width), dtype=np.uint8)
        for center_x, center_y in centers:
            cv2.ellipse(
                ring_template,
                (int(round(center_x - base_left)), int(round(center_y - base_top))),
                (radius, radius),
                0,
                -45,
                225,
                255,
                2,
                lineType=cv2.LINE_AA,
            )
        candidate_scores = cv2.matchTemplate(roi, ring_template, cv2.TM_CCORR_NORMED)
        score_map = candidate_scores if score_map is None else np.maximum(score_map, candidate_scores)
    if score_map is None:
        return []

    y_idx, x_idx = np.ogrid[:score_map.shape[0], :score_map.shape[1]]
    shift_x_map = (roi_left + x_idx - base_left).astype(np.float32)
    shift_y_map = (roi_top + y_idx - base_top).astype(np.float32)
    dist_map = np.hypot(shift_x_map, shift_y_map)
    valid_disk = (dist_map <= float(search)) & (score_map >= 0.25)
    weighted_map = np.where(
        valid_disk,
        score_map * (1.0 - 0.60 * dist_map / max(1.0, float(search))),
        0.0,
    ).astype(np.float32)
    if callable(is_permitted):
        for loc_y, loc_x in np.argwhere(weighted_map > 0.0):
            dx = float(roi_left + int(loc_x) - base_left)
            dy = float(roi_top + int(loc_y) - base_top)
            if not is_permitted(dx, dy):
                weighted_map[loc_y, loc_x] = 0.0
    candidates: list[tuple[float, float, float, float]] = []
    suppressed = weighted_map.copy()
    nms_radius = max(3, int(round(diameter * 0.35)))
    for _ in range(max_candidates):
        _, total_score, _, location = cv2.minMaxLoc(suppressed)
        if total_score <= 0.0:
            break
        edge_score = float(score_map[location[1], location[0]])
        if edge_score < 0.25:
            break
        shift_x = float(roi_left + location[0] - base_left)
        shift_y = float(roi_top + location[1] - base_top)
        candidates.append((shift_x, shift_y, edge_score, float(total_score)))
        nearby = np.hypot(x_idx - location[0], y_idx - location[1]) <= nms_radius
        suppressed[nearby] = 0.0
    candidates.sort(key=lambda candidate: candidate[3], reverse=True)
    return candidates


def _candidate_is_unclaimed(
    row: dict,
    dx: float,
    dy: float,
    claimed_centers: list[tuple[float, float, float, int]],
    owner_row_idx: int,
) -> bool:
    for center_x, center_y in row["centers"]:
        moved_x, moved_y = center_x + dx, center_y + dy
        for claimed_x, claimed_y, foreign_excl, owner in claimed_centers:
            if owner != owner_row_idx and math.hypot(moved_x - claimed_x, moved_y - claimed_y) < foreign_excl:
                return False
    target_y = float(row["y"]) + dy
    for other in row.get("column", []):
        if other is row or other.get("shift") is None:
            continue
        other_y = float(other["y"]) + float(other["shift"][1])
        if float(other["y"]) < float(row["y"]) - 1.0 and not (other_y < target_y - 2.0):
            return False
        if float(other["y"]) > float(row["y"]) + 1.0 and not (other_y > target_y + 2.0):
            return False
    return True


def _row_option_pitch(centers: list[tuple[float, float]]) -> float:
    pitches = [
        math.hypot(right[0] - left[0], right[1] - left[1])
        for left, right in zip(centers, centers[1:])
    ]
    return float(np.median(pitches)) if pitches else 1.0


def _row_fits_image(row: dict, dx: float, dy: float) -> bool:
    return all(
        0 <= (float(option["region"]["x"]) + dx / row["width"])
        and 0 <= (float(option["region"]["y"]) + dy / row["height"])
        and (float(option["region"]["x"]) + dx / row["width"] + float(option["region"]["w"])) <= 1
        and (float(option["region"]["y"]) + dy / row["height"] + float(option["region"]["h"])) <= 1
        for option in row["options"]
    )


def register_item_geometry(
    image: np.ndarray,
    items: list[dict],
    search_radius_mm: float = 5.0,
) -> list[dict]:
    """Register bubble rows by competing whole-question constellation evidence."""
    registered = [
        {
            **item,
            "_geometry_registered": False,
            "region": dict(item.get("region", {})),
            "options": [
                {**option, "region": dict(option.get("region", {}))}
                if isinstance(option, dict) else option
                for option in item.get("options", [])
            ],
        }
        if isinstance(item, dict) else item
        for item in items
    ]
    if image is None or image.size == 0:
        return registered
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    edges = cv2.Canny(cv2.GaussianBlur(gray, (3, 3), 0), 40, 120)
    height, width = gray.shape[:2]
    mm_search_px = int(round(max(2.0, min(12.0, float(search_radius_mm))) * (width / 210.0)))
    rows = []
    for item_idx, item in enumerate(registered):
        options = item.get("options") if isinstance(item, dict) else None
        if not isinstance(options, list) or len(options) < 2:
            continue
        centers, diameters = [], []
        for option in options:
            region = option.get("region") if isinstance(option, dict) else None
            try:
                x, y, w, h = (float(region[key]) for key in ("x", "y", "w", "h"))
            except (TypeError, KeyError, ValueError):
                centers = []
                break
            if not (0 <= x < 1 and 0 <= y < 1 and w > 0 and h > 0 and x + w <= 1 and y + h <= 1):
                centers = []
                break
            centers.append(((x + w / 2) * width, (y + h / 2) * height))
            diameters.append(min(w * width, h * height))
        if centers:
            search = max(4, min(160, max(
                int(round(float(np.median(diameters)) * 0.65)),
                mm_search_px,
            )))
            rows.append({
                "item": item,
                "options": options,
                "centers": centers,
                "diameter": float(np.median(diameters)),
                "x": float(np.median([point[0] for point in centers])),
                "y": float(np.median([point[1] for point in centers])),
                "item_idx": item_idx,
                "width": width,
                "height": height,
                "search": search,
                "candidates": [],
                "shift": None,
            })

    columns: list[list[dict]] = []
    for row in sorted(rows, key=lambda value: value["x"]):
        option_codes = tuple(str(option.get("code", "")) for option in row["options"])
        pitch = _row_option_pitch(row["centers"])
        anchor_tolerance = max(
            3.0 * width / 210.0,
            min(12.0 * width / 210.0, 0.75 * pitch),
        )
        column = next(
            (
                group
                for group in columns
                if tuple(str(option.get("code", "")) for option in group[0]["options"]) == option_codes
                and abs(
                    row["centers"][0][0]
                    - float(np.median([entry["centers"][0][0] for entry in group]))
                ) <= anchor_tolerance
                and abs(
                    row["centers"][-1][0]
                    - float(np.median([entry["centers"][-1][0] for entry in group]))
                ) <= anchor_tolerance
            ),
            None,
        )
        if column is None:
            columns.append([row])
        else:
            column.append(row)
    for column in columns:
        column.sort(key=lambda value: value["y"])
        for row in column:
            row["column"] = column
            neighboring_gaps = [
                abs(row["y"] - other["y"])
                for other in column if other is not row
            ]
            row_spacing = min(neighboring_gaps) if neighboring_gaps else float(height)
            row["row_spacing"] = row_spacing
            row["exclusion"] = min(
                0.75 * _row_option_pitch(row["centers"]),
                max(0.60 * float(row["diameter"]), 0.45 * row_spacing),
            )

    claimed_centers: list[tuple[float, float, float, int]] = []
    contrast_tiles: dict[tuple[int, int, int, int], np.ndarray] = {}
    unassigned = {row["item_idx"] for row in rows}
    planned_shifts: dict[int, tuple[float, float, float, float]] = {}

    def _commit_row_candidate(
        row: dict,
        shift_x: float,
        shift_y: float,
    ) -> bool:
        row_idx = row["item_idx"]
        if not _row_fits_image(row, shift_x, shift_y):
            return False
        if not _candidate_is_unclaimed(row, shift_x, shift_y, claimed_centers, row_idx):
            return False
        original_options = [dict(option["region"]) for option in row["options"]]
        translated = []
        for option in row["options"]:
            region = option["region"]
            moved = {
                "x": float(region["x"]) + shift_x / width,
                "y": float(region["y"]) + shift_y / height,
                "w": float(region["w"]),
                "h": float(region["h"]),
            }
            option["region"] = moved
            translated.append(moved)
        centers_refined = refine_option_centers(
            gray, row["options"], width, height, row["diameter"], row["search"],
            claimed_centers=claimed_centers, owner_row_idx=row_idx,
            contrast_tiles=contrast_tiles,
        )
        if centers_refined is None:
            for option, orig_region in zip(row["options"], original_options):
                option["region"] = orig_region
            return False
        refined = []
        for option, (center_x, center_y) in zip(row["options"], centers_refined):
            region = option["region"]
            moved = {
                "x": center_x / width - float(region["w"]) / 2,
                "y": center_y / height - float(region["h"]) / 2,
                "w": float(region["w"]),
                "h": float(region["h"]),
            }
            if moved["x"] < 0 or moved["y"] < 0 or moved["x"] + moved["w"] > 1 or moved["y"] + moved["h"] > 1:
                for opt, orig_region in zip(row["options"], original_options):
                    opt["region"] = orig_region
                return False
            refined.append(moved)
        translated = refined
        for option, region in zip(row["options"], refined):
            option["region"] = region
        row["shift"] = (shift_x, shift_y)
        row["item"]["_geometry_registered"] = True
        row["item"]["region"] = region_union(translated)
        unassigned.discard(row_idx)
        planned_shifts.pop(row_idx, None)
        for option in row["options"]:
            region = option["region"]
            center_x = (float(region["x"]) + float(region["w"]) / 2) * width
            center_y = (float(region["y"]) + float(region["h"]) / 2) * height
            claimed_centers.append((center_x, center_y, row["exclusion"], row_idx))
        return True

    for row in rows:
        row_idx = row["item_idx"]
        if not _candidate_is_unclaimed(row, 0.0, 0.0, claimed_centers, row_idx):
            continue
        nominal_centers = refine_option_centers(
            gray,
            row["options"],
            width,
            height,
            row["diameter"],
            row["search"],
            claimed_centers=claimed_centers,
            owner_row_idx=row_idx,
            preserve_nominal=True,
            contrast_tiles=contrast_tiles,
        )
        if nominal_centers is None:
            continue
        row["shift"] = (0.0, 0.0)
        row["item"]["_geometry_registered"] = True
        row["item"]["region"] = region_union([option["region"] for option in row["options"]])
        unassigned.discard(row_idx)
        claimed_centers.extend(
            (center_x, center_y, row["exclusion"], row_idx)
            for center_x, center_y in nominal_centers
        )

    for row in rows:
        if row["shift"] is not None:
            continue
        row["candidates"] = _coarse_item_candidates(
            edges,
            row["centers"],
            row["diameter"],
            width,
            height,
            row["search"],
            max_candidates=24,
        )

    for column in columns:
        gaps = [
            float(column[idx]["y"] - column[idx - 1]["y"])
            for idx in range(1, len(column))
            if float(column[idx]["y"] - column[idx - 1]["y"]) > 0
        ]
        col_spacing = float(np.median(gaps)) if gaps else float(column[0]["row_spacing"])
        order_gap = max(2.0, 0.25 * col_spacing)
        states: list[tuple[float, dict | None, tuple[float, float, float, float] | None, object]] = [
            (0.0, None, None, None)
        ]
        for row in column:
            if row["shift"] is not None:
                continue
            row_idx = row["item_idx"]
            next_states = list(states)
            for candidate in row["candidates"]:
                dx, dy, edge_score, total_score = candidate
                if not _row_fits_image(row, dx, dy) or not _candidate_is_unclaimed(
                    row, dx, dy, claimed_centers, row_idx
                ):
                    continue
                target_x = float(row["x"]) + dx
                target_y = float(row["y"]) + dy
                best_pred = None
                best_score = float("-inf")
                for state in states:
                    state_score, prev_row, prev_cand, _ = state
                    penalty = 0.0
                    if prev_row is not None and prev_cand is not None:
                        prev_dx, prev_dy, _, _ = prev_cand
                        prev_target_x = float(prev_row["x"]) + prev_dx
                        prev_target_y = float(prev_row["y"]) + prev_dy
                        if prev_target_y >= target_y - order_gap:
                            continue
                        excl = max(float(prev_row["exclusion"]), float(row["exclusion"]))
                        if math.hypot(target_x - prev_target_x, target_y - prev_target_y) <= excl:
                            continue
                        expected_gap = float(row["y"] - prev_row["y"])
                        penalty = 0.08 * abs((target_y - prev_target_y) - expected_gap) / max(1.0, col_spacing)
                    cand_score = state_score + float(total_score) - penalty
                    if cand_score > best_score:
                        best_score = cand_score
                        best_pred = state
                if best_pred is not None and best_score > 0.0:
                    next_states.append((best_score, row, candidate, best_pred))
            states = next_states

        best_state = max(states, key=lambda item: item[0])
        cursor = best_state
        while cursor is not None:
            _, state_row, state_cand, prev_cursor = cursor
            if state_row is not None and state_cand is not None:
                planned_shifts[state_row["item_idx"]] = state_cand
            cursor = prev_cursor

        for row in column:
            if row["shift"] is not None:
                continue
            row_idx = row["item_idx"]
            selected = planned_shifts.get(row_idx)
            attempted_shifts: set[tuple[float, float]] = set()
            if selected is not None:
                attempted_shifts.add((selected[0], selected[1]))
                if _commit_row_candidate(row, selected[0], selected[1]):
                    continue
            committed = False
            fallback_attempts = 0
            for candidate in row["candidates"]:
                dx, dy = candidate[0], candidate[1]
                if (dx, dy) in attempted_shifts:
                    continue
                if not _row_fits_image(row, dx, dy) or not _candidate_is_unclaimed(
                    row, dx, dy, claimed_centers, row_idx
                ):
                    continue
                attempted_shifts.add((dx, dy))
                fallback_attempts += 1
                if _commit_row_candidate(row, dx, dy):
                    committed = True
                    break
                if fallback_attempts >= 4:
                    break
            if not committed and row["candidates"]:
                researched = _coarse_item_candidates(
                    edges,
                    row["centers"],
                    row["diameter"],
                    width,
                    height,
                    row["search"],
                    max_candidates=4,
                    is_permitted=lambda cand_dx, cand_dy, current_row=row, current_idx=row_idx: (
                        (cand_dx, cand_dy) not in attempted_shifts
                        and _row_fits_image(current_row, cand_dx, cand_dy)
                        and _candidate_is_unclaimed(
                            current_row, cand_dx, cand_dy, claimed_centers, current_idx
                        )
                    ),
                )
                for candidate in researched:
                    if _commit_row_candidate(row, candidate[0], candidate[1]):
                        break
    return registered






def _search_radius_mm_from_config(native_config: dict) -> float:
    alignment = native_config.get("alignment_params") or {}
    try:
        value = float(alignment.get("smart_snap_search_mm", 5.0))
    except (TypeError, ValueError):
        value = 5.0
    return max(2.0, min(12.0, value))


def apply_registered_items_to_template(
    template: object,
    registered_items: list[dict],
    width: int,
    height: int,
) -> None:
    """Inject registered bubble coordinates before OMRChecker reads the page."""
    lookup = {
        (str(item.get("native_label")), str(option.get("code"))): option.get("region")
        for item in registered_items
        if isinstance(item, dict)
        for option in item.get("options", [])
        if isinstance(option, dict) and isinstance(option.get("region"), dict)
    }
    for block in getattr(template, "field_blocks", []):
        applied = False
        box_w, box_h = getattr(block, "bubble_dimensions", (0, 0))
        for bubbles in getattr(block, "traverse_bubbles", []):
            for point in bubbles:
                region = lookup.get((str(point.field_label), str(point.field_value)))
                if region is None:
                    continue
                new_x = int(round(float(region["x"]) * width))
                new_y = int(round(float(region["y"]) * height))
                point.x = max(0, min(width - int(box_w) - 1, new_x))
                point.y = max(0, min(height - int(box_h) - 1, new_y))
                applied = True
        if applied:
            block.shift = 0


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


def materialize_native_assets(
    manifest: dict,
    output_directory: Path,
    native_template: dict | None = None,
) -> None:
    """Write the page-specific marker asset before loading its native template."""
    native_template = native_template or manifest.get("native_template")
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


def option_mark_evidence(
    image: np.ndarray,
    item: dict,
    white_threshold: float = 200.0,
) -> dict[str, dict[str, float]]:
    """Compare interior mean and median with nearby unprinted-paper intensity."""
    evidence: dict[str, dict[str, float]] = {}
    image_height, image_width = image.shape[:2]
    for option in item.get("options", []):
        if not isinstance(option, dict) or not isinstance(option.get("region"), dict):
            continue
        code = str(option.get("code", ""))
        if code == "":
            continue
        region = option["region"]
        diameter = min(float(region["w"]) * image_width, float(region["h"]) * image_height)
        center_x = (float(region["x"]) + float(region["w"]) / 2.0) * image_width
        center_y = (float(region["y"]) + float(region["h"]) / 2.0) * image_height
        interior_radius = diameter * 0.27
        paper_inner_radius = diameter * 0.68
        paper_outer_radius = diameter * 0.85
        left = max(0, int(np.floor(center_x - paper_outer_radius)))
        top = max(0, int(np.floor(center_y - paper_outer_radius)))
        right = min(image_width, int(np.ceil(center_x + paper_outer_radius)) + 1)
        bottom = min(image_height, int(np.ceil(center_y + paper_outer_radius)) + 1)
        crop = image[top:bottom, left:right]
        if crop.size == 0:
            continue
        yy, xx = np.ogrid[:crop.shape[0], :crop.shape[1]]
        dx = xx - (center_x - left)
        dy = yy - (center_y - top)
        distance_squared = dx * dx + dy * dy
        interior_mask = distance_squared <= interior_radius * interior_radius
        paper_mask = (
            (distance_squared >= paper_inner_radius * paper_inner_radius)
            & (distance_squared <= paper_outer_radius * paper_outer_radius)
            & (dy >= -np.abs(dx))
        )
        interior_values = crop[interior_mask]
        paper_values = crop[paper_mask]
        if interior_values.size == 0 or paper_values.size == 0:
            continue
        paper_median = float(np.median(paper_values))
        if paper_median < white_threshold or paper_median <= 0.0:
            continue
        scale = 255.0 / paper_median
        evidence[code] = {
            "mean": max(0.0, min(255.0, float(interior_values.mean()) * scale)),
            "median": max(0.0, min(255.0, float(np.median(interior_values)) * scale)),
        }
    return evidence


def _possible_mark_codes(evidence: dict[str, dict[str, float]]) -> list[str]:
    blank_floor = 255.0 - LOCAL_SUPPORT_DELTA
    return [
        code for code, values in evidence.items()
        if values["mean"] < blank_floor or values["median"] < blank_floor
    ]


def _selected_mark_from_evidence(
    evidence: dict[str, dict[str, float]],
    white_threshold: float = 200.0,
) -> str | None:
    if len(evidence) < 2:
        return None
    possible = _possible_mark_codes(evidence)
    if len(possible) != 1:
        return None
    code = possible[0]
    values = evidence[code]
    if values["mean"] >= white_threshold or values["median"] >= white_threshold:
        return None
    return code


def classify_item_options(
    item: dict,
    evidence: dict[str, dict[str, float]],
    white_threshold: float,
) -> tuple[str, str | None, list[str], list[str]]:
    """Classify located rows without consulting answer-key or expected truth."""
    option_codes = [str(option["code"]) for option in item["options"]]
    if not item.get("_geometry_registered", False) or set(evidence) != set(option_codes):
        return "unreadable", None, [], ["candidate_evidence_incomplete"]
    possible_set = set(_possible_mark_codes(evidence))
    possible = [code for code in option_codes if code in possible_set]
    if not possible:
        return "blank", None, [], []
    selected = _selected_mark_from_evidence(evidence, white_threshold)
    if selected is not None:
        return "marked", selected, [selected], []
    if len(possible) >= 2:
        return "ambiguous", None, possible, ["ambiguous_mark"]
    return "unreadable", None, possible, ["candidate_evidence_incomplete"]
def locally_supported_option(image: np.ndarray, item: dict) -> str | None:
    """Return one corroborated interior mark, not merely the relative winner."""
    return _selected_mark_from_evidence(option_mark_evidence(image, item))
def detected_item_payload(
    item: dict,
    state: str,
    selected: str | None,
    observed_options: list[str],
    strengths: dict[str, float],
    issue_codes: list[str],
) -> dict:
    observations = [
        {
            "code": str(option["code"]),
            "mean": strengths.get(str(option["code"])),
            "classification": (
                "observed" if str(option["code"]) in observed_options else "not_observed"
            ),
        }
        for option in item.get("options", [])
        if isinstance(option, dict) and str(option.get("code", "")) != ""
    ]
    payload = {
        "schema_version": "nexiam.omr.detected-item.v1",
        "position": int(item["position"]),
        "area_code": str(item["area_code"]),
        "question_no": int(item["question_no"]),
        "selected_option": selected,
        "state": state,
        "confidence": None,
        "region": item["region"],
        "observed_options": observed_options,
        "option_observations": observations,
        "issue_codes": issue_codes,
        "legacy_evidence_limited": False,
    }
    return payload

def selected_option_is_supported(image: np.ndarray, item: dict, selected: str) -> bool:
    """Reject provider marks whose selected bubble is not the local winner."""
    return locally_supported_option(image, item) == selected


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


def _validate_manifest_artifacts(manifest: dict) -> dict:
    native_template = manifest.get("native_template")
    require(isinstance(native_template, dict), "manifest_invalid", "Native template is missing.")
    accepted_patch_identities = _expected_patch_identities(native_template)
    engine = manifest.get("engine")
    _validate_engine_identity(engine, accepted_patch_identities)
    require("native_config" not in manifest, "manifest_invalid", "Obsolete native config object is not accepted.")
    require(isinstance(manifest.get("items"), list) and manifest["items"], "manifest_invalid", "Expected items are missing.")
    require(isinstance(manifest.get("identity_fields"), list), "manifest_invalid", "Identity field declarations are missing.")
    require(isinstance(manifest.get("native_bundle_metadata"), dict), "manifest_invalid", "Native bundle metadata is missing.")
    require(isinstance(manifest.get("review_manifest"), dict), "manifest_invalid", "Review manifest is missing.")
    bundle_metadata = manifest["native_bundle_metadata"]
    _validate_engine_identity(bundle_metadata.get("engine"), accepted_patch_identities)
    require(
        _engine_patch_identity(bundle_metadata["engine"]) == _engine_patch_identity(engine),
        "engine_mismatch",
        "Manifest and native bundle engine identities differ.",
    )
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
    bundle_identity = {
        "files": bundle_metadata.get("files"),
        "engine": bundle_metadata.get("engine"),
        "bundle_version": bundle_metadata.get("bundle_version"),
    }
    require(hashlib.sha256(canonical(bundle_identity)).hexdigest() == manifest.get("native_bundle_hash"), "bundle_hash_mismatch", "Native bundle hash does not match.")
    config_json = manifest.get("native_config_json")
    require(isinstance(config_json, str), "manifest_invalid", "Native config JSON bytes are missing.")
    try:
        config_bytes = config_json.encode("utf-8")
    except UnicodeEncodeError:
        raise DetectionError("manifest_invalid", "Native config JSON is not valid UTF-8.") from None
    config_digest = hashlib.sha256(config_bytes).hexdigest()
    files = bundle_metadata.get("files")
    require(
        isinstance(files, dict)
        and config_digest == manifest.get("config_hash") == files.get("config.json"),
        "config_hash_mismatch",
        "Native config bytes do not match the manifest and bundle file digests.",
    )

    def reject_nonstandard_constant(value: str) -> None:
        raise ValueError(f"Invalid JSON constant: {value}")

    def parse_finite_float(value: str) -> float:
        number = float(value)
        if not math.isfinite(number):
            raise ValueError(f"Non-finite JSON number: {value}")
        return number

    try:
        decoded_config = json.loads(
            config_bytes.decode("utf-8"),
            parse_constant=reject_nonstandard_constant,
            parse_float=parse_finite_float,
        )
    except (UnicodeDecodeError, json.JSONDecodeError, ValueError):
        raise DetectionError("manifest_invalid", "Native config JSON is malformed.") from None
    require(isinstance(decoded_config, dict), "manifest_invalid", "Native config JSON must decode to an object.")
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
    return decoded_config


def target_identity(manifest: dict) -> dict[str, int | str]:
    has_version_field = "template_version_id" in manifest
    has_candidate_field = "candidate_target_id" in manifest
    require(has_version_field != has_candidate_field, "target_identity_invalid", "Exactly one immutable template version or candidate target identity is required.")
    if has_version_field:
        version_id = manifest["template_version_id"]
        require(type(version_id) is int and version_id > 0, "target_identity_invalid", "Template version identity must be a positive immutable ID.")
        return {"template_version_id": version_id}
    candidate_id = manifest["candidate_target_id"]
    require(isinstance(candidate_id, str) and bool(CLIENT_TARGET_RE.fullmatch(candidate_id)), "target_identity_invalid", "Candidate target identity is malformed.")
    return {"candidate_target_id": candidate_id}


def native_template_for_page(manifest: dict) -> dict:
    """Return the immutable flat OMRChecker template selected by the v5 page target."""
    native_template = manifest.get("native_template")
    require(isinstance(native_template, dict), "manifest_invalid", "Native template is missing.")
    return native_template


def _preprocessor_names(native_template: dict) -> list[str]:
    preprocessors = native_template.get("preProcessors", [])
    require(isinstance(preprocessors, list), "native_preprocessor_invalid", "Native preprocessor declarations are malformed.")
    names = []
    for preprocessor in preprocessors:
        require(isinstance(preprocessor, dict) and isinstance(preprocessor.get("name"), str), "native_preprocessor_invalid", "Native preprocessor declaration is malformed.")
        names.append(preprocessor["name"])
    return names


def validate_native_registration_mode(manifest: dict) -> dict:
    """Bind the explicit v5 mode to one already page-selected native template."""
    mode = manifest["registration_mode"]
    native_template = native_template_for_page(manifest)

    if mode == "fiducial_anchored":
        require(
            _preprocessor_names(native_template) == ["CropOnMarkers"],
            "native_preprocessor_mismatch",
            "Fiducial-anchored templates require exactly one CropOnMarkers preprocessor and forbid CropPage.",
        )
        return native_template

    require(mode == "document_bound", "registration_mode_invalid", "Unsupported registration mode.")
    require(
        _preprocessor_names(native_template) == [],
        "native_preprocessor_mismatch",
        "Document-bound templates must not contain geometry-mutating or unsupported preprocessors.",
    )
    require("markers" not in native_template, "native_marker_state_incompatible", "Document-bound templates cannot carry marker geometry.")
    require("marker_profile" not in manifest, "native_marker_state_incompatible", "Document-bound targets cannot carry a marker profile.")
    assets = manifest.get("native_assets", {})
    require(isinstance(assets, dict) and not assets, "native_marker_state_incompatible", "Document-bound targets cannot carry marker assets.")
    page_dimensions = native_template.get("pageDimensions")
    require(isinstance(page_dimensions, list) and len(page_dimensions) == 2, "template_dimensions_invalid", "Page-specific paper dimensions are missing.")
    width, height = page_dimensions
    require(
        type(width) is int
        and type(height) is int
        and document_rectifier.MIN_DIMENSION <= width <= document_rectifier.MAX_DIMENSION
        and document_rectifier.MIN_DIMENSION <= height <= document_rectifier.MAX_DIMENSION
        and width * height <= document_rectifier.MAX_TARGET_PIXELS,
        "template_dimensions_invalid",
        "Page-specific document target dimensions are invalid or exceed rectifier limits.",
    )
    return native_template


def _validate_source_image_envelope(manifest: dict) -> None:
    source = manifest.get("source_image")
    require(isinstance(source, dict), "manifest_invalid", "Source image identity is missing.")
    width = source.get("width")
    height = source.get("height")
    orientation = source.get("orientation")
    input_hash = source.get("input_hash")
    require(type(width) is int and 1 <= width <= document_rectifier.MAX_DIMENSION, "source_image_invalid", "Source image width is invalid.")
    require(type(height) is int and 1 <= height <= document_rectifier.MAX_DIMENSION, "source_image_invalid", "Source image height is invalid.")
    require(type(orientation) is int and 1 <= orientation <= 8, "source_image_invalid", "Source EXIF orientation must be an integer from 1 through 8.")
    require(isinstance(input_hash, str) and bool(SOURCE_HASH_RE.fullmatch(input_hash)), "source_image_invalid", "Source image SHA-256 identity is malformed.")
    require(manifest.get("input_hash") == input_hash, "input_hash_mismatch", "Top-level and source image hashes differ.")
    for key, expected in (("image_width", width), ("image_height", height)):
        if key in manifest:
            require(type(manifest[key]) is int and manifest[key] == expected, "image_metadata_mismatch", f"Legacy {key} metadata differs from source_image.")


def _validate_v5_envelope(manifest: dict) -> dict:
    require(manifest.get("contract_version") == CONTRACT_VERSION, "contract_mismatch", "Unsupported contract version.")
    mode = manifest.get("registration_mode")
    require(isinstance(mode, str) and mode in SUPPORTED_REGISTRATION_MODES, "registration_mode_invalid", "Detection requires an explicit supported registration mode.")
    target_identity(manifest)
    page_no = manifest.get("expected_page_no")
    require(type(page_no) is int and 1 <= page_no <= 4, "page_target_invalid", "Expected page number must be an integer from one through four.")
    _validate_source_image_envelope(manifest)
    native_config = _validate_manifest_artifacts(manifest)
    validate_native_registration_mode(manifest)
    return native_config


def validate_manifest(manifest: dict) -> dict:
    """Validate a v5 detection or layout-verification request; no implicit v4 dispatch."""
    require(isinstance(manifest, dict), "manifest_invalid", "Detection manifest must be an object.")
    return _validate_v5_envelope(manifest)


def validate_preflight_manifest(manifest: dict) -> dict:
    """Validate marker-only registration preflight on the v4 auxiliary contract."""
    require(isinstance(manifest, dict), "manifest_invalid", "Preflight manifest must be an object.")
    require(manifest.get("contract_version") == PREFLIGHT_CONTRACT_VERSION, "contract_mismatch", "Unsupported preflight contract version.")
    native_config = _validate_manifest_artifacts(manifest)
    require(isinstance(manifest.get("input_hash"), str) and bool(SOURCE_HASH_RE.fullmatch(manifest["input_hash"])), "manifest_invalid", "Preflight source hash is malformed.")
    require(type(manifest.get("image_width")) is int and manifest["image_width"] > 0, "manifest_invalid", "Preflight image width is invalid.")
    require(type(manifest.get("image_height")) is int and manifest["image_height"] > 0, "manifest_invalid", "Preflight image height is invalid.")
    return native_config

def validate_source_image(manifest: dict, image_bytes: bytes, image: np.ndarray) -> tuple[dict, int]:
    """Bind the raw bytes, decoded dimensions, and EXIF transform to the v4 source identity."""
    source = manifest["source_image"]
    height, width = image.shape[:2]
    orientation = exif_orientation(image_bytes)
    require(
        (source["width"], source["height"]) == (width, height),
        "source_image_mismatch",
        "Decoded raw image dimensions differ from the v4 source identity.",
    )
    require(source["orientation"] == orientation, "source_image_mismatch", "Decoded EXIF orientation differs from the v4 source identity.")
    actual_hash = hashlib.sha256(image_bytes).hexdigest()
    require(actual_hash == source["input_hash"] == manifest["input_hash"], "input_hash_mismatch", "Uploaded image bytes differ from the v4 source identity.")
    return source, orientation


def _client_quad_sensor_points(client_quad: object, source: dict, expected_page_no: int) -> tuple[np.ndarray | None, str | None]:
    if not isinstance(client_quad, dict):
        return None, "malformed"
    expected = {
        "width": source["width"],
        "height": source["height"],
        "orientation": source["orientation"],
        "input_hash": source["input_hash"],
        "page_no": expected_page_no,
    }
    for key, value in expected.items():
        if type(client_quad.get(key)) is not type(value) or client_quad.get(key) != value:
            return None, "binding_mismatch"
    raw_points = client_quad.get("points")
    if not isinstance(raw_points, list) or len(raw_points) != 4:
        return None, "malformed_points"
    coordinates: list[list[float]] = []
    for point in raw_points:
        if not isinstance(point, list) or len(point) != 2:
            return None, "malformed_points"
        if any(type(value) not in (int, float) or not math.isfinite(float(value)) or not 0.0 <= float(value) <= 1.0 for value in point):
            return None, "point_out_of_bounds"
        coordinates.append([float(point[0]) * (source["width"] - 1), float(point[1]) * (source["height"] - 1)])
    sensor_points = np.asarray(coordinates, dtype=np.float32)
    try:
        canonical = document_rectifier.order_quad_points(sensor_points)
    except Exception:
        return None, "invalid_geometry"
    if not np.allclose(canonical, sensor_points, atol=1.0):
        return None, "noncanonical_point_order"
    area = float(cv2.contourArea(sensor_points))
    source_area = float(source["width"] * source["height"])
    if area < source_area * 0.15 or area > source_area * 0.99:
        return None, "implausible_area"
    return sensor_points, None


def _orient_client_quad(points: np.ndarray, orientation: int, source_width: int, source_height: int) -> np.ndarray:
    transformed = []
    for x, y in points:
        if orientation == 2:
            point = (source_width - 1 - x, y)
        elif orientation == 3:
            point = (source_width - 1 - x, source_height - 1 - y)
        elif orientation == 4:
            point = (x, source_height - 1 - y)
        elif orientation == 5:
            point = (y, x)
        elif orientation == 6:
            point = (source_height - 1 - y, x)
        elif orientation == 7:
            point = (source_height - 1 - y, source_width - 1 - x)
        elif orientation == 8:
            point = (y, source_width - 1 - x)
        else:
            point = (x, y)
        transformed.append(point)
    return document_rectifier.order_quad_points(np.asarray(transformed, dtype=np.float32))


def _quad_overlap_ratio(quad_a: np.ndarray, quad_b: np.ndarray) -> float:
    area_a = float(cv2.contourArea(quad_a))
    area_b = float(cv2.contourArea(quad_b))
    if area_a <= 0.0 or area_b <= 0.0:
        return 0.0
    try:
        intersection, _ = cv2.intersectConvexConvex(
            np.asarray(quad_a, dtype=np.float32),
            np.asarray(quad_b, dtype=np.float32),
        )
    except cv2.error:
        return 0.0
    union = area_a + area_b - float(intersection)
    return float(intersection) / union if union > 0.0 else 0.0


def _document_content_evidence(image: np.ndarray, manifest: dict) -> dict:
    gray = image if image.ndim == 2 else cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    height, width = gray.shape[:2]
    regions = []
    for item in manifest["items"]:
        region = item.get("region") if isinstance(item, dict) else None
        if isinstance(region, dict):
            regions.append(region)
    for field in manifest["identity_fields"]:
        region = field.get("region") if isinstance(field, dict) else None
        if isinstance(region, dict):
            regions.append(region)

    supported = []
    cells = set()
    for region in regions:
        values = [region.get(key) for key in ("x", "y", "w", "h")]
        if any(type(value) not in (int, float) or not math.isfinite(float(value)) for value in values):
            continue
        x, y, w, h = (float(value) for value in values)
        if w <= 0.0 or h <= 0.0 or x < 0.0 or y < 0.0 or x + w > 1.0 or y + h > 1.0:
            continue
        left = max(0, min(width - 1, int(math.floor(x * width))))
        top = max(0, min(height - 1, int(math.floor(y * height))))
        right = max(left + 1, min(width, int(math.ceil((x + w) * width))))
        bottom = max(top + 1, min(height, int(math.ceil((y + h) * height))))
        crop = gray[top:bottom, left:right]
        if crop.size < 16:
            continue
        edge_density = float(cv2.countNonZero(cv2.Canny(crop, 40, 120))) / float(crop.size)
        contrast = float(crop.std())
        is_supported = contrast >= 5.0 and edge_density >= 0.003
        if is_supported:
            supported.append({"contrast": round(contrast, 3), "edge_density": round(edge_density, 5)})
            cells.add((min(2, int((x + w / 2.0) * 3)), min(2, int((y + h / 2.0) * 3))))

    required_regions = min(2, len(regions))
    distributed = required_regions <= 1 or len(cells) >= 2
    accepted = len(supported) >= required_regions and distributed and required_regions > 0
    return {
        "accepted": accepted,
        "expected_region_count": len(regions),
        "supported_region_count": len(supported),
        "supported_cells": [list(cell) for cell in sorted(cells)],
        "minimum_supported_regions": required_regions,
        "spatially_distributed": distributed,
        "thresholds": {"minimum_contrast": 5.0, "minimum_edge_density": 0.003},
    }


def rectify_document_image(
    image: np.ndarray,
    manifest: dict,
    target_width: int,
    target_height: int,
) -> tuple[np.ndarray, dict]:
    """Find one plausible paper boundary, corroborate (never trust) a hint, and apply one warp."""
    source = manifest["source_image"]
    expected_page_no = manifest["expected_page_no"]
    client_points, hint_error = _client_quad_sensor_points(manifest.get("client_quad"), source, expected_page_no) if "client_quad" in manifest else (None, None)
    hint_evidence: dict[str, object] = {"status": "absent", "used_for_selection": False}
    if "client_quad" in manifest:
        hint_evidence = {
            "status": "rejected" if hint_error else "pending_corroboration",
            "reason": hint_error,
            "used_for_selection": False,
            "used_for_warp": False,
        }

    target = document_rectifier.RectificationTarget(target_width=target_width, target_height=target_height)
    limits = document_rectifier.RectificationLimits(max_candidates=3, max_aspect_ratio_deviation=0.18)
    rectification_started = time.monotonic()
    result = document_rectifier.propose_rectifications(
        image,
        target,
        limits,
        warp_candidates=False,
    )
    elapsed_ms = round((time.monotonic() - rectification_started) * 1000.0, 3)
    candidate_summaries = [
        {
            "candidate_index": int(candidate.candidate_index),
            "area_ratio": float(candidate.area_ratio),
            "edge_score": float(candidate.edge_score),
            "aspect_ratio_score": float(candidate.aspect_ratio_score),
            "contrast_score": float(candidate.contrast_score),
            "composite_score": float(candidate.composite_score),
            "quad_normalized": candidate.quad_normalized,
        }
        for candidate in result.candidates
    ]
    base = {
        "attempted": True,
        "applied": False,
        "mode": "document_bound",
        "detector_version": result.detector_version,
        "status": result.status,
        "candidate_count": len(result.candidates),
        "candidate_summaries": candidate_summaries,
        "target_dimensions": [target_width, target_height],
        "target_aspect_ratio": round(target_width / target_height, 6),
        "aspect_ratio_tolerance": limits.max_aspect_ratio_deviation,
        "source_dimensions": [int(image.shape[1]), int(image.shape[0])],
        "homography_count": 0,
        "warp_count": 0,
        "duration_ms": elapsed_ms,
        "rectifier_diagnostics": result.diagnostics,
        "client_quad": hint_evidence,
        "document_gates": {
            "accepted": False,
            "unique_quad": False,
            "aspect_ratio": False,
            "homography": False,
            "distributed_content": False,
        },
    }
    if result.status != "success" or not result.candidates:
        rejected = result.diagnostics.get("rejected_by_gate", {})
        if result.status == "timeout":
            code = "rectifier_timeout"
        elif result.status == "rectifier_exception":
            code = "rectifier_failed"
        elif isinstance(rejected, dict) and rejected.get("aspect_ratio", 0) > 0:
            code = "document_aspect_mismatch"
        else:
            code = "document_quad_not_found"
        base["reason"] = code
        if "client_quad" in manifest and not hint_error:
            base["client_quad"] = {
                "status": "rejected",
                "reason": "no_server_candidate",
                "used_for_selection": False,
                "used_for_warp": False,
            }
        raise DetectionError(code, "No paper quadrilateral passed server geometry and aspect-ratio gates.", rectification=base)

    plausible = [
        candidate
        for candidate in result.candidates
        if candidate.composite_score >= 0.48
        and candidate.aspect_ratio_score >= 0.25
        and candidate.edge_score >= 0.03
        and candidate.contrast_score >= 0.45
    ]
    if len(plausible) != 1:
        base["plausible_candidate_count"] = len(plausible)
        base["reason"] = "multiple_plausible_quads" if plausible else "no_plausible_quad"
        code = "ambiguous_rectification" if len(plausible) > 1 else "document_quad_not_found"
        if "client_quad" in manifest and not hint_error:
            base["client_quad"] = {
                "status": "rejected",
                "reason": "server_quad_ambiguous" if len(plausible) > 1 else "no_plausible_server_quad",
                "used_for_selection": False,
                "used_for_warp": False,
            }
        raise DetectionError(code, "Document boundary was ambiguous or did not meet independent quality gates.", rectification=base)

    candidate = plausible[0]
    base["document_gates"] = {
        "accepted": False,
        "unique_quad": True,
        "aspect_ratio": candidate.aspect_ratio_score >= 0.25,
        "homography": False,
        "distributed_content": False,
    }
    source_height, source_width = image.shape[:2]
    server_points = np.asarray(candidate.quad_original, dtype=np.float32)
    if client_points is not None:
        oriented_points = _orient_client_quad(client_points, source["orientation"], source["width"], source["height"])
        overlap = _quad_overlap_ratio(server_points, oriented_points)
        diagonal = math.hypot(source_width, source_height)
        corner_error = float(np.sqrt(np.mean(np.sum((server_points - oriented_points) ** 2, axis=1)))) / diagonal
        if overlap >= 0.70 and corner_error <= 0.06:
            hint_evidence = {
                "status": "accepted",
                "reason": "independently_corroborated",
                "corroborated": True,
                "overlap_ratio": round(overlap, 5),
                "normalized_corner_error": round(corner_error, 5),
                "used_for_selection": False,
                "used_for_warp": False,
            }
        else:
            hint_evidence = {
                "status": "rejected",
                "reason": "not_corroborated",
                "corroborated": False,
                "overlap_ratio": round(overlap, 5),
                "normalized_corner_error": round(corner_error, 5),
                "used_for_selection": False,
                "used_for_warp": False,
            }
    matrix = np.asarray(candidate.homography_matrix, dtype=np.float64)
    try:
        warped = cv2.warpPerspective(
            image,
            matrix,
            (target_width, target_height),
            flags=cv2.INTER_LINEAR,
            borderMode=cv2.BORDER_CONSTANT,
            borderValue=(255, 255, 255),
        )
    except cv2.error as error:
        base["reason"] = "homography_failed"
        base["client_quad"] = hint_evidence
        raise DetectionError("document_homography_failed", "Server homography could not rectify the selected paper boundary.", rectification=base) from error

    content = _document_content_evidence(warped, manifest)
    base.update(
        {
            "applied": True,
            "status": "success",
            "plausible_candidate_count": 1,
            "selected_candidate_index": int(candidate.candidate_index),
            "selection_reason": "unique_server_quad",
            "quad_normalized": candidate.quad_normalized,
            "quad_area_ratio": float(candidate.area_ratio),
            "quad_edge_score": float(candidate.edge_score),
            "quad_aspect_ratio_score": float(candidate.aspect_ratio_score),
            "quad_contrast_score": float(candidate.contrast_score),
            "composite_score": float(candidate.composite_score),
            "homography_hash": candidate.homography_hash,
            "homography_count": 1,
            "warp_count": 1,
            "content_gate": content,
            "client_quad": hint_evidence,
            "document_gates": {
                "accepted": content["accepted"] is True,
                "unique_quad": True,
                "aspect_ratio": candidate.aspect_ratio_score >= 0.25,
                "homography": True,
                "distributed_content": content["accepted"] is True and content["spatially_distributed"] is True,
            },
        }
    )
    require(
        warped.shape[:2] == (target_height, target_width),
        "document_homography_failed",
        "Server rectification did not produce the immutable paper dimensions.",
    )
    if not content["accepted"]:
        base["reason"] = "expected_content_missing"
        raise DetectionError("document_content_missing", "Candidate boundary encloses no distributed expected OMR content.", rectification=base)
    return warped, base


def detect(
    manifest: dict,
    image_path: Path,
    output_directory: Path,
    *,
    require_expected_labels: bool = True,
) -> dict:
    started = time.monotonic()
    native_config = validate_manifest(manifest)
    image_bytes = image_path.read_bytes()
    mime = sniff_mime(image_bytes)
    require(mime == manifest.get("image_mime"), "image_metadata_mismatch", "Decoded image MIME does not match the request.")

    try:
        raw_image = document_rectifier.bounded_decode_image(image_bytes, color=False)
    except Exception as exc:
        raise DetectionError("image_decode_failed", f"Image decoding failed: {exc}") from None
    decoded_height, decoded_width = raw_image.shape[:2]
    source_image, orientation = validate_source_image(manifest, image_bytes, raw_image)
    image = apply_exif_orientation(raw_image, orientation)
    height, width = image.shape[:2]
    registration_mode = manifest["registration_mode"]
    crop_policy = str(manifest.get("crop_policy", "auto_document_quad")).lower()
    require(crop_policy in {"auto_document_quad", "keep_original"}, "crop_policy_invalid", "The crop policy is not supported.")
    native_template = validate_native_registration_mode(manifest)
    defaults, template_class, image_ops_class = load_omrchecker()
    config = merge_config(native_config, defaults)
    template_path = output_directory / "template.json"
    template_path.write_text(json.dumps(native_template, separators=(",", ":")), encoding="utf-8")
    marker_bearing = marker_bearing_template(native_template)
    materialize_native_assets(manifest, output_directory, native_template)

    engine_log = io.StringIO()
    with capture_engine_logs(engine_log):
        template = template_class(template_path, config)
        image_ops = image_ops_class(config)
        preprocess_started = time.monotonic()
        page_dimensions = native_template.get("pageDimensions") or getattr(template, "page_dimensions", None)
        require(isinstance(page_dimensions, (list, tuple)) and len(page_dimensions) == 2, "template_dimensions_invalid", "Page-specific template dimensions are missing.")
        target_width = int(page_dimensions[0])
        target_height = int(page_dimensions[1])
        require(target_width > 0 and target_height > 0, "template_dimensions_invalid", "Page-specific template dimensions are invalid.")
        camera_input = width != target_width or height != target_height
        if registration_mode == "document_bound":
            if crop_policy == "keep_original":
                processed = image
                if processed.shape[1] != target_width or processed.shape[0] != target_height:
                    processed = cv2.resize(processed, (target_width, target_height), interpolation=cv2.INTER_AREA)
                content = _document_content_evidence(processed, manifest)
                rectification_metadata = {
                    "applied": False,
                    "crop_policy": "keep_original",
                    "status": "success",
                    "mode": "document_bound",
                    "detector_version": "nexiam.rectifier.v1",
                    "plausible_candidate_count": 1,
                    "selected_candidate_index": 0,
                    "warp_count": 0,
                    "homography_count": 0,
                    "quad_normalized": [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
                    "homography_hash": "sha256:" + "0" * 64,
                    "content_gate": content,
                    "document_gates": {
                        "accepted": content["accepted"] is True,
                        "unique_quad": True,
                        "aspect_ratio": True,
                        "homography": True,
                        "distributed_content": content["accepted"] is True and content["spatially_distributed"] is True,
                    },
                    "target_dimensions": [target_width, target_height],
                    "source_dimensions": [int(image.shape[1]), int(image.shape[0])],
                }
                if not content["accepted"]:
                    rectification_metadata["reason"] = "expected_content_missing"
                    raise DetectionError("document_content_missing", "Candidate boundary encloses no distributed expected OMR content.", rectification=rectification_metadata)
            else:
                processed, rectification_metadata = rectify_document_image(image, manifest, target_width, target_height)
        else:
            processed = image_ops.apply_preprocessors(str(image_path), image, template)
            rectification_metadata = rectification_recovery.base_rectification_metadata()
    engine_text = engine_log.getvalue()
    sys.stderr.write(engine_text)
    preprocess_ms = (time.monotonic() - preprocess_started) * 1000.0

    raw_matches = marker_states.parse_match_scores(engine_text) if marker_bearing else {}
    registered_items = None
    raw_four_markers = (
        registration_mode == "fiducial_anchored"
        and processed is not None
        and marker_bearing
        and len(raw_matches) == 4
        and all(value >= 0.30 for value in raw_matches.values())
    )

    response = None
    aligned = None
    field_block_shifts = []

    if registration_mode == "document_bound":
        marker_matches = {}
        raw_processed = normalize_camera_lighting(processed) if camera_input else processed
        registered_items = register_item_geometry(
            raw_processed, manifest["items"], _search_radius_mm_from_config(native_config)
        )
        apply_registered_items_to_template(template, registered_items, target_width, target_height)
        if hasattr(template, "options") and isinstance(template.options, dict):
            template.options.get("alignment_params", {})["auto_align"] = False
        if hasattr(config, "alignment_params") and isinstance(config.alignment_params, dict):
            config.alignment_params["auto_align"] = False
        if hasattr(image_ops, "tuning_config") and hasattr(image_ops.tuning_config, "alignment_params"):
            image_ops.tuning_config.alignment_params["auto_align"] = False
        try:
            with redirect_stdout(sys.stderr):
                response, aligned, _, _ = image_ops.read_omr_response(template, raw_processed, image_path.name, save_dir=None)
        except Exception as exc:
            raise DetectionError("provider_output_invalid", f"OMRChecker recognition failed: {exc}", rectification=rectification_metadata) from None
        processed = raw_processed
    elif raw_four_markers:
        raw_processed = normalize_camera_lighting(processed) if camera_input else processed
        registered_items = register_item_geometry(
            raw_processed, manifest["items"], _search_radius_mm_from_config(native_config)
        )
        apply_registered_items_to_template(template, registered_items, target_width, target_height)
        try:
            with redirect_stdout(sys.stderr):
                raw_response, raw_aligned, _, _ = image_ops.read_omr_response(template, raw_processed, image_path.name, save_dir=None)
        except Exception as exc:
            raise DetectionError("provider_output_invalid", f"OMRChecker recognition failed: {exc}") from None
        if not isinstance(raw_response, dict):
            raise DetectionError("provider_output_invalid", "OMRChecker returned malformed observations.")
        if not (isinstance(raw_aligned, np.ndarray) and raw_aligned.size > 0):
            raise DetectionError("aligned_image_invalid", "OMRChecker returned no aligned image.")
        response = raw_response
        aligned = raw_aligned
        processed = raw_processed
        marker_matches = raw_matches
    elif marker_bearing:
        raw_state = marker_states.classify(engine_text, sheet_likely_present(image))
        recovery_manifest = {**manifest, "native_template": native_template}

        def evaluate_detect_cand(cand: document_rectifier.RectifiedCandidate) -> rectification_recovery.EvaluatedCandidate | None:
            if cand.warped_image is None:
                return None
            cand_log = io.StringIO()
            with capture_engine_logs(cand_log):
                cand_template = template_class(template_path, config)
                cand_image_ops = image_ops_class(config)
                cand_processed = cand_image_ops.apply_preprocessors(str(image_path), cand.warped_image, cand_template)
            cand_engine_text = cand_log.getvalue()
            cand_matches = marker_states.parse_match_scores(cand_engine_text)
            if not (
                cand_processed is not None
                and len(cand_matches) == 4
                and all(value >= 0.30 for value in cand_matches.values())
            ):
                return None
            try:
                with redirect_stdout(sys.stderr):
                    cand_response, cand_aligned, _, _ = cand_image_ops.read_omr_response(cand_template, cand_processed, image_path.name, save_dir=None)
                if not (isinstance(cand_response, dict) and isinstance(cand_aligned, np.ndarray) and cand_aligned.size > 0):
                    return None
                cand_shifts = [
                    {
                        "block": str(block.name),
                        "x": int(block.shift) if str(getattr(block, "direction", "")).lower() != "vertical" else 0,
                        "y": int(block.shift) if str(getattr(block, "direction", "")).lower() == "vertical" else 0,
                    }
                    for block in getattr(cand_template, "field_blocks", [])
                ]
                cand_unsafe = find_unsafe_shifts(cand_shifts, native_template.get("fieldBlocks", {}))
                if cand_unsafe:
                    return None
                return rectification_recovery.EvaluatedCandidate(
                    candidate=cand,
                    marker_matches=cand_matches,
                    payload=(cand_processed, cand_engine_text, cand_template, cand_image_ops, cand_response, cand_aligned, cand_shifts),
                )
            except Exception:
                return None

        try:
            promoted, rectification_metadata = rectification_recovery.evaluate_guarded_recovery(
                image=image,
                manifest=recovery_manifest,
                raw_matches=marker_states.parse_raw_marker_telemetry(engine_text),
                raw_state=raw_state,
                target_width=target_width,
                target_height=target_height,
                evaluate_candidate=evaluate_detect_cand,
            )
        except rectification_recovery.RecoveryRejected as rejected:
            raise DetectionError(rejected.code, str(rejected), rectification=rejected.rectification) from None
        (
            processed,
            engine_text,
            template,
            image_ops,
            response,
            aligned,
            field_block_shifts,
        ) = promoted.payload
        marker_matches = promoted.marker_matches
    else:
        raise DetectionError("native_preprocessor_mismatch", "No explicit registration pipeline matches the immutable detection mode.")

    failure_rectification = rectification_metadata if registration_mode == "document_bound" else None
    if not isinstance(response, dict):
        raise DetectionError("provider_output_invalid", "OMRChecker returned malformed observations.", rectification=failure_rectification)
    if not (isinstance(aligned, np.ndarray) and aligned.size > 0):
        raise DetectionError("aligned_image_invalid", "OMRChecker returned no aligned image.", rectification=failure_rectification)

    observations = []
    issues = []
    raw_values: dict[str, str] = {}
    mark_evidence: dict[str, dict[str, object]] = {}
    expected_labels = {item["native_label"] for item in manifest["items"]}
    if require_expected_labels and not expected_labels.issubset(response.keys()):
        raise DetectionError("provider_output_invalid", "OMRChecker omitted expected labels.", rectification=failure_rectification)
    if registered_items is None:
        registered_items = register_item_geometry(
            aligned, manifest["items"], _search_radius_mm_from_config(native_config)
        )


    white_threshold = float(config.threshold_params.GLOBAL_PAGE_THRESHOLD_WHITE)
    for item in registered_items:
        label = item["native_label"]
        raw_value = str(response.get(label, "")).strip()
        raw_values[label] = raw_value
        strengths = option_strengths(aligned, item)
        interior_evidence = option_mark_evidence(aligned, item, white_threshold)
        state, selected, observed_options, item_issue_codes = classify_item_options(
            item, interior_evidence, white_threshold,
        )
        if selected is not None:
            mark_evidence[label] = {
                "selected": selected,
                "means": strengths,
                "normalized_interior_evidence": interior_evidence,
                "engine_value": raw_value,
            }
        if state in {"ambiguous", "unreadable"}:
            issues.append(issue(
                "sheet_misaligned" if not item.get("_geometry_registered", False) else "ambiguous_mark",
                "required",
                item,
            ))
        observations.append(
            detected_item_payload(
                item, state, selected, observed_options, strengths, item_issue_codes,
            )
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
    if not bool(encoded_ok):
        raise DetectionError("aligned_image_invalid", "Aligned image encoding failed.", rectification=failure_rectification)
    aligned_path.write_bytes(encoded_aligned.tobytes())
    aligned_height, aligned_width = aligned.shape[:2]
    if aligned_width != target_width or aligned_height != target_height:
        raise DetectionError("aligned_image_invalid", "Aligned image dimensions do not match target frame.", rectification=failure_rectification)
    field_block_shifts = [
        {
            "block": str(block.name),
            "x": int(block.shift) if str(getattr(block, "direction", "")).lower() != "vertical" else 0,
            "y": int(block.shift) if str(getattr(block, "direction", "")).lower() == "vertical" else 0,
        }
        for block in template.field_blocks
    ]
    unsafe_shifts = find_unsafe_shifts(field_block_shifts, native_template.get("fieldBlocks", {}))
    if unsafe_shifts and manifest.get("items"):
        issues.append(issue("sheet_misaligned", "required", manifest["items"][0]))
    detection_ms = (time.monotonic() - started) * 1000.0
    return {
        "contract_version": CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "registration_mode": registration_mode,
        "expected_page_no": manifest["expected_page_no"],
        **target_identity(manifest),
        "source_image": {
            "width": source_image["width"],
            "height": source_image["height"],
            "orientation": source_image["orientation"],
            "input_hash": source_image["input_hash"],
        },
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
            "rectification": rectification_metadata,
            "thresholds": threshold_diagnostics(config),
        },
        "rectification": rectification_metadata,
        "registered_items": registered_items,
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
        payload = {"error": error.code, "detail": str(error)}
        if error.rectification:
            payload["rectification"] = error.rectification
        print(json.dumps(payload, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "OMR analysis failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
