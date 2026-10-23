"""NEXIAM-owned Python/OpenCV document rectification module.

Provides deterministic document quad detection, validation gates, ranking,
and perspective warping to known target dimensions for camera capture recovery.

This deep module hides the multi-channel edge/mask detection pipelines and
homography mathematics behind a clean immutable result interface. It never
mutates input buffers and never applies pixel-altering enhancements (Magic Color,
Otsu binarization, sharpening) to the output rasters.
"""

from __future__ import annotations

import hashlib
import itertools
import json
import struct
import time
from dataclasses import dataclass, field
from typing import Any

import cv2
import numpy as np

RECTIFIER_VERSION = "nexiam.rectifier.v1"
MAX_DECODED_PIXELS = 60_000_000  # 60 MP safety ceiling
MAX_TARGET_PIXELS = 25_000_000  # 25 MP target allocation ceiling
MIN_DIMENSION = 300
MAX_DIMENSION = 8000
MAX_CANDIDATES_CEILING = 3
MAX_DOWNSAMPLE_DIM_CEILING = 1024


def inspect_image_header(data: bytes) -> tuple[int, int] | None:
    """Extract (width, height) from raw image bytes without full decompression."""
    if not isinstance(data, (bytes, bytearray)) or len(data) < 24:
        return None
    # PNG: \x89PNG\r\n\x1a\n ... IHDR chunk at offset 12 carries width/height at 16..24
    if data.startswith(b"\x89PNG\r\n\x1a\n") and len(data) >= 24 and data[12:16] == b"IHDR":
        w, h = struct.unpack(">II", data[16:24])
        if w > 0 and h > 0:
            return (int(w), int(h))
        return None
    # JPEG: \xff\xd8 SOI marker
    if data.startswith(b"\xff\xd8"):
        idx = 2
        data_len = len(data)
        while idx < data_len - 1:
            if data[idx] != 0xFF:
                idx += 1
                continue
            while idx < data_len and data[idx] == 0xFF:
                idx += 1
            if idx >= data_len:
                break
            marker = data[idx]
            idx += 1
            if marker in (0xD8, 0xD9, 0x00):
                continue
            if idx + 2 > data_len:
                break
            length = struct.unpack(">H", data[idx:idx + 2])[0]
            if marker in (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF):
                if idx + 7 <= data_len:
                    h, w = struct.unpack(">HH", data[idx + 3:idx + 7])
                    if w > 0 and h > 0:
                        return (int(w), int(h))
            idx += length
        return None
    # WebP: RIFF....WEBP
    if data.startswith(b"RIFF") and len(data) >= 16 and data[8:12] == b"WEBP":
        chunk = data[12:16]
        if chunk == b"VP8 " and len(data) >= 30:
            w, h = struct.unpack("<HH", data[26:30])
            w_val, h_val = int(w & 0x3FFF), int(h & 0x3FFF)
            if w_val > 0 and h_val > 0:
                return (w_val, h_val)
        elif chunk == b"VP8L" and len(data) >= 25 and data[20] == 0x2F:
            b0, b1, b2, b3 = data[21:25]
            w = 1 + (((b1 & 0x3F) << 8) | b0)
            h = 1 + (((b3 & 0x0F) << 10) | (b2 << 2) | ((b1 & 0xC0) >> 6))
            if w > 0 and h > 0:
                return (int(w), int(h))
        elif chunk == b"VP8X" and len(data) >= 30:
            w = 1 + int.from_bytes(data[24:27], "little")
            h = 1 + int.from_bytes(data[27:30], "little")
            if w > 0 and h > 0:
                return (int(w), int(h))
    return None


def bounded_decode_image(
    image_bytes: bytes,
    max_bytes: int = 25_000_000,
    max_pixels: int = 60_000_000,
    color: bool = True,
) -> np.ndarray:
    """Safely decode image bytes within byte and pixel limits, requiring valid headers before full decode."""
    if not isinstance(image_bytes, (bytes, bytearray)):
        raise ValueError("image_bytes must be bytes")
    if len(image_bytes) == 0:
        raise ValueError("image_bytes cannot be empty")
    effective_max_bytes = min(max_bytes, 25_000_000)
    if len(image_bytes) > effective_max_bytes:
        raise ValueError(f"Image payload size ({len(image_bytes)} bytes) exceeds limit ({effective_max_bytes} bytes)")

    header_dims = inspect_image_header(image_bytes)
    if header_dims is None:
        raise ValueError("Image header inspection failed (unrecognized, corrupt, or unsupported format)")

    hw, hh = header_dims
    effective_max_pixels = min(max_pixels, MAX_DECODED_PIXELS)
    if hw * hh > effective_max_pixels:
        raise ValueError(f"Image header dimensions ({hw}x{hh} = {hw * hh} px) exceed pixel limit ({effective_max_pixels} px)")
    if hw > MAX_DIMENSION or hh > MAX_DIMENSION:
        raise ValueError(f"Image header dimension exceeds ceiling ({MAX_DIMENSION} px): {hw}x{hh}")

    encoded = np.frombuffer(image_bytes, dtype=np.uint8)
    flags = (cv2.IMREAD_COLOR if color else cv2.IMREAD_GRAYSCALE) | getattr(cv2, "IMREAD_IGNORE_ORIENTATION", 0)
    image = cv2.imdecode(encoded, flags)
    if image is None or image.size == 0:
        raise ValueError("Image decoding failed (corrupt or unsupported format)")

    h, w = image.shape[:2]
    if h * w > effective_max_pixels:
        raise ValueError(f"Decoded dimensions ({w}x{h} = {h * w} px) exceed pixel limit ({effective_max_pixels} px)")
    if w > MAX_DIMENSION or h > MAX_DIMENSION:
        raise ValueError(f"Decoded dimension exceeds ceiling ({MAX_DIMENSION} px): {w}x{h}")
    return image

@dataclass(frozen=True)
class RectificationTarget:
    """Target document geometry contract."""

    target_width: int
    target_height: int
    target_aspect_ratio: float | None = None
    marker_frame: tuple[
        tuple[float, float],
        tuple[float, float],
        tuple[float, float],
        tuple[float, float],
    ] | None = None

    def __post_init__(self) -> None:
        if not isinstance(self.target_width, int) or not isinstance(self.target_height, int):
            raise ValueError(f"Target dimensions must be integers, got {type(self.target_width)} and {type(self.target_height)}")
        if not (MIN_DIMENSION <= self.target_width <= MAX_DIMENSION and MIN_DIMENSION <= self.target_height <= MAX_DIMENSION):
            raise ValueError(f"Target dimensions out of bounds [{MIN_DIMENSION}..{MAX_DIMENSION}]: got {self.target_width}x{self.target_height}")
        if self.target_width * self.target_height > MAX_TARGET_PIXELS:
            raise ValueError(f"Target pixel count exceeds ceiling ({MAX_TARGET_PIXELS}): {self.target_width * self.target_height}")

        expected_aspect = float(self.target_width) / float(self.target_height)
        if self.target_aspect_ratio is None:
            object.__setattr__(self, "target_aspect_ratio", expected_aspect)
        else:
            if not isinstance(self.target_aspect_ratio, (int, float)) or not np.isfinite(self.target_aspect_ratio) or self.target_aspect_ratio <= 0:
                raise ValueError(f"Invalid target_aspect_ratio: {self.target_aspect_ratio}")
            if abs(float(self.target_aspect_ratio) - expected_aspect) > 1e-4:
                raise ValueError(f"target_aspect_ratio mismatch: expected {expected_aspect:.6f}, got {self.target_aspect_ratio:.6f}")
            object.__setattr__(self, "target_aspect_ratio", expected_aspect)
        if self.marker_frame is not None:
            if len(self.marker_frame) != 4:
                raise ValueError("marker_frame must contain four normalized points")
            normalized = tuple((float(x), float(y)) for x, y in self.marker_frame)
            if any(not (0.0 < x < 1.0 and 0.0 < y < 1.0) for x, y in normalized):
                raise ValueError("marker_frame points must be inside normalized paper bounds")
            object.__setattr__(self, "marker_frame", normalized)


@dataclass(frozen=True)
class RectificationLimits:
    """Configurable execution and geometry bounds."""

    max_candidates: int = 3
    max_downsample_dim: int = 1024
    min_area_ratio: float = 0.35
    max_area_ratio: float = 0.99
    max_orthogonality_cosine: float = 0.35
    max_aspect_ratio_deviation: float = 0.35
    max_edge_ratio_skew: float = 0.35
    timeout_seconds: float = 0.25

    def __post_init__(self) -> None:
        clamped_cand = min(MAX_CANDIDATES_CEILING, max(1, int(self.max_candidates)))
        object.__setattr__(self, "max_candidates", clamped_cand)

        clamped_down = min(MAX_DOWNSAMPLE_DIM_CEILING, max(64, int(self.max_downsample_dim)))
        object.__setattr__(self, "max_downsample_dim", clamped_down)

        timeout = max(0.001, min(1.0, float(self.timeout_seconds)))
        if not np.isfinite(timeout):
            timeout = 0.25
        object.__setattr__(self, "timeout_seconds", timeout)

        for field_name in ("min_area_ratio", "max_area_ratio", "max_orthogonality_cosine", "max_aspect_ratio_deviation", "max_edge_ratio_skew"):
            val = float(getattr(self, field_name))
            if not np.isfinite(val) or val <= 0.0:
                raise ValueError(f"Invalid limit value for {field_name}: {val}")
        if self.min_area_ratio >= self.max_area_ratio:
            raise ValueError("min_area_ratio must be less than max_area_ratio")


@dataclass(frozen=True)
class RectifiedCandidate:
    """A verified, ranked document quadrilateral proposal."""

    candidate_index: int
    quad_original: list[tuple[float, float]]
    quad_normalized: list[tuple[float, float]]
    area_ratio: float
    edge_score: float
    orthogonality_score: float
    aspect_ratio_score: float
    cross_map_count: int
    contrast_score: float
    composite_score: float
    homography_matrix: list[list[float]]
    homography_hash: str
    warped_image: np.ndarray | None
    detection_map_source: str


@dataclass(frozen=True)
class RectificationResult:
    """Complete immutable rectification outcome with forensic diagnostics."""

    detector_version: str
    status: str  # "success" | "no_candidates" | "timeout" | "invalid_input" | "rectifier_exception"
    input_shape: tuple[int, int, int]
    target_dimensions: tuple[int, int]
    candidates: list[RectifiedCandidate] = field(default_factory=list)
    diagnostics: dict[str, Any] = field(default_factory=dict)


def order_quad_points(pts: np.ndarray) -> np.ndarray:
    """Order four points as [Top-Left, Top-Right, Bottom-Right, Bottom-Left]."""
    pts = np.asarray(pts, dtype=np.float32).reshape(4, 2)
    if not np.all(np.isfinite(pts)):
        raise ValueError("Non-finite coordinates in quad points")
    sums = pts[:, 0] + pts[:, 1]
    differences = pts[:, 0] - pts[:, 1]
    return np.array(
        [
            pts[np.argmin(sums)],
            pts[np.argmax(differences)],
            pts[np.argmax(sums)],
            pts[np.argmin(differences)],
        ],
        dtype=np.float32,
    )


def _order_quad_cyclic(pts: np.ndarray) -> np.ndarray:
    """Order convex quad vertices cyclically for arbitrary in-plane rotations."""
    points = np.asarray(pts, dtype=np.float32)
    if points.size != 8:
        raise ValueError("Quad points must contain exactly four coordinate pairs")
    points = points.reshape(4, 2)
    if not np.all(np.isfinite(points)):
        raise ValueError("Non-finite coordinates in quad points")
    if np.unique(points, axis=0).shape[0] != 4:
        raise ValueError("Quad points must be distinct")

    center = np.mean(points, axis=0)
    angles = np.arctan2(points[:, 1] - center[1], points[:, 0] - center[0])
    ordered = points[np.argsort(angles, kind="stable")]
    start = int(np.lexsort((ordered[:, 0], ordered[:, 1], ordered[:, 0] + ordered[:, 1]))[0])
    ordered = np.roll(ordered, -start, axis=0)

    edges = np.roll(ordered, -1, axis=0) - ordered
    next_edges = np.roll(edges, -1, axis=0)
    turns = edges[:, 0] * next_edges[:, 1] - edges[:, 1] * next_edges[:, 0]
    if np.any(turns <= 1e-6):
        raise ValueError("Quad points must form a non-degenerate convex polygon")
    return ordered


def _normalize_ordered_quad(ordered: np.ndarray, width: int, height: int) -> list[tuple[float, float]]:
    w_max = float(max(1, width - 1))
    h_max = float(max(1, height - 1))
    return [
        (
            round(float(np.clip(point[0] / w_max, 0.0, 1.0)), 6),
            round(float(np.clip(point[1] / h_max, 0.0, 1.0)), 6),
        )
        for point in ordered
    ]


def normalize_quad(pts: np.ndarray, width: int, height: int) -> list[tuple[float, float]]:
    """Convert quad points to [0.0..1.0] coordinates relative to image width and height."""
    return _normalize_ordered_quad(order_quad_points(pts), width, height)


def _homography_from_ordered_quad(ordered: np.ndarray, dst_dims: tuple[int, int]) -> np.ndarray | None:
    try:
        src_arr = np.asarray(ordered, dtype=np.float32).reshape(4, 2)
        if not np.all(np.isfinite(src_arr)):
            return None
        dst_w, dst_h = dst_dims
        if dst_w <= 1 or dst_h <= 1:
            return None
        dst = np.array(
            [[0.0, 0.0], [float(dst_w - 1), 0.0], [float(dst_w - 1), float(dst_h - 1)], [0.0, float(dst_h - 1)]],
            dtype=np.float32,
        )
        matrix = cv2.getPerspectiveTransform(src_arr, dst)
        if matrix is None or matrix.shape != (3, 3) or not np.all(np.isfinite(matrix)):
            return None
        det = np.linalg.det(matrix)
        if not np.isfinite(det) or abs(det) < 1e-12:
            return None
        return matrix
    except Exception:
        return None


def compute_homography_matrix(src_quad: np.ndarray, dst_dims: tuple[int, int]) -> np.ndarray | None:
    """Compute 3x3 perspective transform matrix from quad to (dst_w, dst_h)."""
    try:
        return _homography_from_ordered_quad(order_quad_points(src_quad), dst_dims)
    except Exception:
        return None

def compute_homography_hash(matrix: np.ndarray) -> str:
    """Deterministic SHA-256 hash of the canonical homography matrix."""
    canonical_list = [[round(float(v), 8) for v in row] for row in matrix.tolist()]
    canonical_bytes = json.dumps(canonical_list, separators=(",", ":")).encode("utf-8")
    return f"sha256:{hashlib.sha256(canonical_bytes).hexdigest()}"


def _build_detection_maps(small_image: np.ndarray) -> dict[str, np.ndarray]:
    """Generate multi-channel edge and threshold maps for quad proposal extraction."""
    maps: dict[str, np.ndarray] = {}
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))

    if small_image.ndim == 3 and small_image.shape[2] == 3:
        gray = cv2.cvtColor(small_image, cv2.COLOR_BGR2GRAY)
    elif small_image.ndim == 3 and small_image.shape[2] == 1:
        gray = small_image[:, :, 0]
    else:
        gray = small_image

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)

    # Map 1: Grayscale Canny edge map
    canny_edges = cv2.Canny(blurred, 50, 150)
    maps["grayscale_edge"] = cv2.dilate(canny_edges, kernel, iterations=2)

    # Map 2: Otsu bright-paper threshold mask
    _, otsu = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    closed = cv2.morphologyEx(otsu, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_RECT, (7, 7)))
    opened = cv2.morphologyEx(closed, cv2.MORPH_OPEN, kernel)
    otsu_edges = cv2.Canny(opened, 100, 200)
    maps["otsu_mask"] = cv2.dilate(otsu_edges, kernel, iterations=1)

    # Map 3: LAB L-channel edge map
    if small_image.ndim == 3 and small_image.shape[2] == 3:
        lab = cv2.cvtColor(small_image, cv2.COLOR_BGR2LAB)
        l_chan = lab[:, :, 0]
        l_blurred = cv2.GaussianBlur(l_chan, (5, 5), 0)
        l_edges = cv2.Canny(l_blurred, 40, 120)
        maps["lab_edge"] = cv2.dilate(l_edges, kernel, iterations=2)

        # Map 4: HSV Value/Saturation combined edge map
        hsv = cv2.cvtColor(small_image, cv2.COLOR_BGR2HSV)
        s_chan = hsv[:, :, 1]
        v_chan = hsv[:, :, 2]
        sv_combined = cv2.addWeighted(v_chan, 0.7, 255 - s_chan, 0.3, 0)
        sv_blurred = cv2.GaussianBlur(sv_combined, (5, 5), 0)
        sv_edges = cv2.Canny(sv_blurred, 50, 150)
        maps["hsv_edge"] = cv2.dilate(sv_edges, kernel, iterations=2)

    return maps


def _extract_contour_quads(edge_map: np.ndarray) -> list[np.ndarray]:
    """Find candidate 4-point convex quadrilaterals from an edge map."""
    quads: list[np.ndarray] = []
    contours, _ = cv2.findContours(edge_map, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return quads

    def _contour_key(cnt: np.ndarray) -> tuple[float, float, int, int]:
        area = float(cv2.contourArea(cnt))
        peri = float(cv2.arcLength(cnt, True))
        p0 = cnt[0][0] if cnt.ndim == 3 else cnt[0]
        return (-area, -peri, int(p0[0]), int(p0[1]))

    for cnt in sorted(contours, key=_contour_key)[:8]:
        area = cv2.contourArea(cnt)
        if area < 100:
            continue
        peri = cv2.arcLength(cnt, True)
        if peri <= 0:
            continue

        # Try multiple approximation factors
        found_approx = False
        for eps_factor in (0.02, 0.015, 0.03, 0.04):
            approx = cv2.approxPolyDP(cnt, eps_factor * peri, True)
            if len(approx) == 4 and cv2.isContourConvex(approx):
                quads.append(approx.reshape(4, 2).astype(np.float32))
                found_approx = True
                break

        if not found_approx:
            # Fallback to convex hull approximation
            hull = cv2.convexHull(cnt)
            hull_peri = cv2.arcLength(hull, True)
            for eps_factor in (0.02, 0.03, 0.04):
                approx_hull = cv2.approxPolyDP(hull, eps_factor * hull_peri, True)
                if len(approx_hull) == 4 and cv2.isContourConvex(approx_hull):
                    quads.append(approx_hull.reshape(4, 2).astype(np.float32))
                    break

    return quads


def _extract_fiducial_frame_quad(
    gray_small: np.ndarray,
    target: RectificationTarget,
) -> tuple[np.ndarray | None, dict[str, Any]]:
    """Infer paper corners from four large corner bullseyes.

    The downstream CropOnMarkers gate remains authoritative. This proposal only
    supplies a perspective candidate when floor seams or shadows corrupt the
    paper contour.
    """
    if target.marker_frame is None:
        return None, {"attempted": False}

    height, width = gray_small.shape[:2]
    blurred = cv2.GaussianBlur(gray_small, (5, 5), 1.2)
    circles = cv2.HoughCircles(
        blurred,
        cv2.HOUGH_GRADIENT,
        dp=1.2,
        minDist=max(16, int(min(width, height) * 0.02)),
        param1=100,
        param2=32,
        minRadius=max(6, int(min(width, height) * 0.009)),
        maxRadius=max(18, int(min(width, height) * 0.035)),
    )
    if circles is None:
        return None, {"attempted": True, "circle_count": 0}

    detected = [tuple(float(value) for value in circle) for circle in circles[0]]
    quadrants = [
        [circle for circle in detected if circle[0] < width * 0.45 and circle[1] < height * 0.40],
        [circle for circle in detected if circle[0] > width * 0.55 and circle[1] < height * 0.40],
        [circle for circle in detected if circle[0] > width * 0.55 and circle[1] > height * 0.60],
        [circle for circle in detected if circle[0] < width * 0.45 and circle[1] > height * 0.60],
    ]
    if any(not quadrant for quadrant in quadrants):
        return None, {"attempted": True, "circle_count": len(detected), "complete_quadrants": False}

    marker_destination = np.asarray(
        [
            [x * (target.target_width - 1), y * (target.target_height - 1)]
            for x, y in target.marker_frame
        ],
        dtype=np.float32,
    )
    paper_destination = np.asarray(
        [
            [0.0, 0.0],
            [float(target.target_width - 1), 0.0],
            [float(target.target_width - 1), float(target.target_height - 1)],
            [0.0, float(target.target_height - 1)],
        ],
        dtype=np.float32,
    ).reshape(1, 4, 2)

    best_quad: np.ndarray | None = None
    best_area = 0.0
    for combination in itertools.product(*(quadrant[:6] for quadrant in quadrants)):
        radii = [circle[2] for circle in combination]
        if min(radii) <= 0 or max(radii) / min(radii) > 1.75:
            continue
        marker_source = np.asarray(
            [[circle[0], circle[1]] for circle in combination],
            dtype=np.float32,
        )
        if not cv2.isContourConvex(marker_source.astype(np.int32)):
            continue
        marker_area = float(cv2.contourArea(marker_source))
        if marker_area < width * height * 0.25:
            continue
        transform = cv2.getPerspectiveTransform(marker_destination, marker_source)
        paper_source = cv2.perspectiveTransform(paper_destination, transform).reshape(4, 2)
        if not np.all(np.isfinite(paper_source)):
            continue
        paper_area = float(cv2.contourArea(paper_source))
        if paper_area > best_area:
            best_area = paper_area
            best_quad = paper_source

    return best_quad, {
        "attempted": True,
        "circle_count": len(detected),
        "complete_quadrants": True,
        "selected": best_quad is not None,
    }


def _evaluate_quad(
    quad_small: np.ndarray,
    small_shape: tuple[int, int],
    gray_small: np.ndarray,
    grad_norm: np.ndarray,
    scale: float,
    target: RectificationTarget,
    limits: RectificationLimits,
    source_map: str,
    rejected_by_gate: dict[str, int] | None = None,
) -> dict[str, Any] | None:
    """Evaluate a candidate quadrilateral against hard gates and calculate component scores."""
    sh, sw = small_shape

    def reject(gate: str) -> None:
        if rejected_by_gate is not None:
            rejected_by_gate[gate] = rejected_by_gate.get(gate, 0) + 1

    try:
        ordered_small = _order_quad_cyclic(quad_small) if target.marker_frame is None else order_quad_points(quad_small)
    except Exception:
        reject("quad_order")
        return None

    margin_x = 0.02 * sw
    margin_y = 0.02 * sh
    for p in ordered_small:
        if p[0] < -margin_x or p[0] > sw + margin_x or p[1] < -margin_y or p[1] > sh + margin_y:
            reject("out_of_bounds")
            return None

    area = cv2.contourArea(ordered_small)
    frame_area = float(sw * sh)
    area_ratio = area / frame_area
    if area_ratio < limits.min_area_ratio or area_ratio > limits.max_area_ratio:
        reject("area_ratio")
        return None

    cosines = []
    for i in range(4):
        p_prev = ordered_small[(i - 1) % 4]
        p_curr = ordered_small[i]
        p_next = ordered_small[(i + 1) % 4]
        v1 = p_prev - p_curr
        v2 = p_next - p_curr
        norm1 = np.linalg.norm(v1)
        norm2 = np.linalg.norm(v2)
        if norm1 < 1e-4 or norm2 < 1e-4:
            reject("orthogonality")
            return None
        cos = np.dot(v1, v2) / (norm1 * norm2)
        cosines.append(abs(float(cos)))
    max_cos = max(cosines)
    if max_cos > limits.max_orthogonality_cosine:
        reject("orthogonality")
        return None

    w_top = float(np.linalg.norm(ordered_small[1] - ordered_small[0]))
    w_bot = float(np.linalg.norm(ordered_small[2] - ordered_small[3]))
    h_left = float(np.linalg.norm(ordered_small[3] - ordered_small[0]))
    h_right = float(np.linalg.norm(ordered_small[2] - ordered_small[1]))
    if max(w_top, w_bot) < 1.0 or max(h_left, h_right) < 1.0:
        reject("edge_length")
        return None
    w_skew = abs(w_top - w_bot) / max(w_top, w_bot)
    h_skew = abs(h_left - h_right) / max(h_left, h_right)
    if w_skew > limits.max_edge_ratio_skew or h_skew > limits.max_edge_ratio_skew:
        reject("edge_skew")
        return None

    measured_aspect = ((w_top + w_bot) / 2.0) / ((h_left + h_right) / 2.0)
    target_aspect = float(target.target_aspect_ratio or (float(target.target_width) / float(target.target_height)))
    aspect_dev = abs(measured_aspect - target_aspect) / target_aspect
    if target.marker_frame is None:
        rotated_aspect_dev = abs((1.0 / measured_aspect) - target_aspect) / target_aspect
        if rotated_aspect_dev < aspect_dev:
            ordered_small = np.roll(ordered_small, -1, axis=0)
            aspect_dev = rotated_aspect_dev
    if aspect_dev > limits.max_aspect_ratio_deviation:
        reject("aspect_ratio")
        return None

    quad_orig = ordered_small / scale
    matrix = _homography_from_ordered_quad(quad_orig, (target.target_width, target.target_height))
    if matrix is None:
        reject("homography")
        return None

    edge_samples = []
    for i in range(4):
        p1 = ordered_small[i]
        p2 = ordered_small[(i + 1) % 4]
        for alpha in np.linspace(0.05, 0.95, 25):
            sample_pt = p1 + alpha * (p2 - p1)
            sx = int(np.clip(round(sample_pt[0]), 0, sw - 1))
            sy = int(np.clip(round(sample_pt[1]), 0, sh - 1))
            edge_samples.append(grad_norm[sy, sx])
    edge_score = float(np.mean(edge_samples)) if edge_samples else 0.0

    mask = np.zeros((sh, sw), dtype=np.uint8)
    cv2.fillPoly(mask, [ordered_small.astype(np.int32)], 255)
    interior_mean = float(cv2.mean(gray_small, mask=mask)[0])
    exterior_mask = cv2.bitwise_not(mask)
    exterior_mean = float(cv2.mean(gray_small, mask=exterior_mask)[0])
    contrast_score = max(0.0, min(1.0, (interior_mean - exterior_mean + 128.0) / 255.0))

    ortho_score = max(0.0, 1.0 - (max_cos / limits.max_orthogonality_cosine))
    aspect_score = max(0.0, 1.0 - (aspect_dev / limits.max_aspect_ratio_deviation))

    return {
        "quad_small": ordered_small,
        "quad_original": [(float(p[0]), float(p[1])) for p in quad_orig],
        "area_ratio": round(area_ratio, 4),
        "edge_score": round(edge_score, 4),
        "orthogonality_score": round(ortho_score, 4),
        "aspect_ratio_score": round(aspect_score, 4),
        "contrast_score": round(contrast_score, 4),
        "homography_matrix": matrix,
        "source_map": source_map,
    }


def _quad_iou(quad_a: np.ndarray, quad_b: np.ndarray, shape: tuple[int, int]) -> float:
    """Compute Intersection over Union between two quads on downsampled frame."""
    mask_a = np.zeros(shape, dtype=np.uint8)
    mask_b = np.zeros(shape, dtype=np.uint8)
    cv2.fillPoly(mask_a, [quad_a.astype(np.int32)], 255)
    cv2.fillPoly(mask_b, [quad_b.astype(np.int32)], 255)
    intersection = cv2.bitwise_and(mask_a, mask_b)
    union = cv2.bitwise_or(mask_a, mask_b)
    area_i = float(cv2.countNonZero(intersection))
    area_u = float(cv2.countNonZero(union))
    return (area_i / area_u) if area_u > 0 else 0.0


def _deduplicate_and_rank(
    raw_evaluations: list[dict[str, Any]],
    small_shape: tuple[int, int],
    limits: RectificationLimits,
) -> list[dict[str, Any]]:
    """Deduplicate similar proposals across maps and rank by composite score."""
    unique_candidates: list[dict[str, Any]] = []

    for ev in raw_evaluations:
        matched = False
        for cand in unique_candidates:
            iou = _quad_iou(ev["quad_small"], cand["quad_small"], small_shape)
            if iou >= 0.88:
                cand["contributing_maps"].add(ev["source_map"])
                cand["cross_map_count"] = len(cand["contributing_maps"])
                # If the new evaluation has strictly higher edge score, atomically adopt its full evaluation
                if ev["edge_score"] > cand["edge_score"]:
                    preserved_maps = cand["contributing_maps"]
                    cand.clear()
                    cand.update(ev)
                    cand["contributing_maps"] = preserved_maps
                    cand["cross_map_count"] = len(preserved_maps)
                matched = True
                break

        if not matched:
            unique_candidates.append({**ev, "contributing_maps": {ev["source_map"]}, "cross_map_count": 1})

    # Compute composite scores
    for cand in unique_candidates:
        cross_bonus = min(1.0, float(cand["cross_map_count"]) / 3.0)
        cand["composite_score"] = round(
            0.35 * cand["edge_score"]
            + 0.25 * cand["aspect_ratio_score"]
            + 0.15 * cand["orthogonality_score"]
            + 0.10 * cand["area_ratio"]
            + 0.10 * cand["contrast_score"]
            + 0.05 * cross_bonus,
            4,
        )

    # Sort with deterministic total-order tie-breaker
    def sort_key(c: dict[str, Any]) -> tuple[float, float, float, float, float, float, float, float, float, str]:
        q = c["quad_original"]
        return (
            -float(c["composite_score"]),
            round(q[0][0], 4),
            round(q[0][1], 4),
            round(q[1][0], 4),
            round(q[1][1], 4),
            round(q[2][0], 4),
            round(q[2][1], 4),
            round(q[3][0], 4),
            round(q[3][1], 4),
            str(c.get("source_map", "")),
        )

    unique_candidates.sort(key=sort_key)
    return unique_candidates[: limits.max_candidates]


def propose_rectifications(
    image: np.ndarray,
    target: RectificationTarget,
    limits: RectificationLimits = RectificationLimits(),
    *,
    warp_candidates: bool = True,
) -> RectificationResult:
    """Detect document quads and produce deterministic perspective-rectified candidates.

    Args:
        image: Original input image array (BGR or grayscale uint8).
        target: Target rectification dimensions and aspect ratio.
        limits: Execution bounds, thresholds, and candidate limits.

        RectificationResult containing up to max_candidates proposals. Set
        warp_candidates=False to inspect geometry before applying one selected homography.
    """
    started = time.monotonic()
    diagnostics: dict[str, Any] = {"maps_analyzed": [], "timings_ms": {}}

    try:
        # 1. Strict input validation & sanity checks
        if (
            image is None
            or not isinstance(image, np.ndarray)
            or image.size == 0
            or image.dtype != np.uint8
            or image.ndim not in (2, 3)
            or (image.ndim == 3 and image.shape[2] not in (1, 3))
            or image.shape[0] < MIN_DIMENSION
            or (image.ndim < 2 or image.shape[1] < MIN_DIMENSION)
        ):
            h = int(image.shape[0]) if (image is not None and isinstance(image, np.ndarray) and image.ndim >= 1) else 0
            w = int(image.shape[1]) if (image is not None and isinstance(image, np.ndarray) and image.ndim >= 2) else 0
            c = int(image.shape[2]) if (image is not None and isinstance(image, np.ndarray) and image.ndim >= 3) else 1
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="invalid_input",
                input_shape=(h, w, c),
                target_dimensions=(target.target_width, target.target_height),
                diagnostics={"error": "Input image must be a non-empty uint8 numpy array with 1 or 3 channels and min dimension >= 300."},
            )
        orig_h, orig_w = image.shape[:2]
        channels = image.shape[2] if image.ndim == 3 else 1
        input_shape = (orig_h, orig_w, channels)

        if orig_h * orig_w > MAX_DECODED_PIXELS:
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="invalid_input",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                diagnostics={"error": f"Image exceeds maximum pixel limit: {orig_h * orig_w} > {MAX_DECODED_PIXELS}"},
            )

        # Monotonic deadline check
        if (time.monotonic() - started) > limits.timeout_seconds:
            diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="timeout",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                candidates=[],
                diagnostics=diagnostics,
            )

        # 2. Bounded downsampling for contour/edge detection
        longest_edge = max(orig_h, orig_w)
        scale = float(min(limits.max_downsample_dim, longest_edge)) / float(longest_edge)
        small_w = max(1, int(round(orig_w * scale)))
        small_h = max(1, int(round(orig_h * scale)))
        small_img = cv2.resize(image, (small_w, small_h), interpolation=cv2.INTER_AREA)

        if small_img.ndim == 3 and small_img.shape[2] == 3:
            gray_small = cv2.cvtColor(small_img, cv2.COLOR_BGR2GRAY)
        elif small_img.ndim == 3 and small_img.shape[2] == 1:
            gray_small = small_img[:, :, 0]
        else:
            gray_small = small_img

        # Compute normalized gradient magnitude for edge scoring
        sobel_x = cv2.Sobel(gray_small, cv2.CV_32F, 1, 0, ksize=3)
        sobel_y = cv2.Sobel(gray_small, cv2.CV_32F, 0, 1, ksize=3)
        grad_mag = np.sqrt(sobel_x**2 + sobel_y**2)
        grad_norm = np.clip(grad_mag / (grad_mag.max() + 1e-6), 0.0, 1.0)

        # 3. Multi-channel detection maps
        t_maps_start = time.monotonic()
        detection_maps = _build_detection_maps(small_img)
        diagnostics["timings_ms"]["maps_generation"] = round((time.monotonic() - t_maps_start) * 1000.0, 2)
        diagnostics["maps_analyzed"] = list(detection_maps.keys())

        # Monotonic deadline check after maps
        if (time.monotonic() - started) > limits.timeout_seconds:
            diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="timeout",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                candidates=[],
                diagnostics=diagnostics,
            )

        # 4. Extract and evaluate candidate quads across maps
        rejected_by_gate: dict[str, int] = {}
        raw_evaluations: list[dict[str, Any]] = []
        t_eval_start = time.monotonic()
        timeout_occurred = False
        fiducial_quad, fiducial_diagnostics = _extract_fiducial_frame_quad(
            gray_small,
            target,
        )
        diagnostics["fiducial_frame"] = fiducial_diagnostics
        if fiducial_quad is not None:
            fiducial_evaluation = _evaluate_quad(
                fiducial_quad,
                (small_h, small_w),
                gray_small,
                grad_norm,
                scale,
                target,
                limits,
                "fiducial_frame",
                rejected_by_gate,
            )
            if fiducial_evaluation is not None:
                raw_evaluations.append(fiducial_evaluation)


        for map_name, edge_map in detection_maps.items():
            if (time.monotonic() - started) > limits.timeout_seconds:
                timeout_occurred = True
                diagnostics["timeout_triggered"] = True
                break

            quads = _extract_contour_quads(edge_map)
            for quad in quads:
                ev = _evaluate_quad(
                    quad,
                    (small_h, small_w),
                    gray_small,
                    grad_norm,
                    scale,
                    target,
                    limits,
                    map_name,
                    rejected_by_gate,
                )
                if ev is not None:
                    raw_evaluations.append(ev)

        diagnostics["timings_ms"]["quad_evaluation"] = round((time.monotonic() - t_eval_start) * 1000.0, 2)
        diagnostics["raw_candidates_found"] = len(raw_evaluations)
        diagnostics["rejected_by_gate"] = rejected_by_gate

        if timeout_occurred or (time.monotonic() - started) > limits.timeout_seconds:
            diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="timeout",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                candidates=[],
                diagnostics=diagnostics,
            )

        if not raw_evaluations:
            diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="no_candidates",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                candidates=[],
                diagnostics=diagnostics,
            )

        # 5. Deduplicate across maps and rank
        ranked_evals = _deduplicate_and_rank(raw_evaluations, (small_h, small_w), limits)

        t_warp_start = time.monotonic()
        candidates: list[RectifiedCandidate] = []

        for idx, ev in enumerate(ranked_evals):
            if warp_candidates and (time.monotonic() - started) > limits.timeout_seconds:
                diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
                return RectificationResult(
                    detector_version=RECTIFIER_VERSION,
                    status="timeout",
                    input_shape=input_shape,
                    target_dimensions=(target.target_width, target.target_height),
                    candidates=[],
                    diagnostics=diagnostics,
                )

            matrix = ev["homography_matrix"]
            warped = None
            if warp_candidates:
                warped = cv2.warpPerspective(
                    image,
                    matrix,
                    (target.target_width, target.target_height),
                    flags=cv2.INTER_LINEAR,
                    borderMode=cv2.BORDER_CONSTANT,
                    borderValue=(255, 255, 255),
                )

                if (time.monotonic() - started) > limits.timeout_seconds:
                    diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
                    return RectificationResult(
                        detector_version=RECTIFIER_VERSION,
                        status="timeout",
                        input_shape=input_shape,
                        target_dimensions=(target.target_width, target.target_height),
                        candidates=[],
                        diagnostics=diagnostics,
                    )

            quad_orig_pts = np.asarray(ev["quad_original"], dtype=np.float32)
            quad_norm = (
                normalize_quad(quad_orig_pts, orig_w, orig_h)
                if ev["source_map"] == "fiducial_frame"
                else _normalize_ordered_quad(quad_orig_pts, orig_w, orig_h)
            )
            h_hash = compute_homography_hash(matrix)

            candidates.append(
                RectifiedCandidate(
                    candidate_index=idx,
                    quad_original=ev["quad_original"],
                    quad_normalized=quad_norm,
                    area_ratio=ev["area_ratio"],
                    edge_score=ev["edge_score"],
                    orthogonality_score=ev["orthogonality_score"],
                    aspect_ratio_score=ev["aspect_ratio_score"],
                    cross_map_count=ev["cross_map_count"],
                    contrast_score=ev["contrast_score"],
                    composite_score=ev["composite_score"],
                    homography_matrix=[[round(float(v), 8) for v in row] for row in matrix.tolist()],
                    homography_hash=h_hash,
                    warped_image=warped,
                    detection_map_source=ev["source_map"],
                )
            )

        diagnostics["timings_ms"]["warping"] = round((time.monotonic() - t_warp_start) * 1000.0, 2)
        diagnostics["warp_candidates"] = warp_candidates
        diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)

        if (time.monotonic() - started) > limits.timeout_seconds:
            return RectificationResult(
                detector_version=RECTIFIER_VERSION,
                status="timeout",
                input_shape=input_shape,
                target_dimensions=(target.target_width, target.target_height),
                candidates=[],
                diagnostics=diagnostics,
            )

        return RectificationResult(
            detector_version=RECTIFIER_VERSION,
            status="success",
            input_shape=input_shape,
            target_dimensions=(target.target_width, target.target_height),
            candidates=candidates,
            diagnostics=diagnostics,
        )

    except Exception as exc:
        diagnostics["timings_ms"]["total"] = round((time.monotonic() - started) * 1000.0, 2)
        return RectificationResult(
            detector_version=RECTIFIER_VERSION,
            status="rectifier_exception",
            input_shape=(0, 0, 0),
            target_dimensions=(target.target_width, target.target_height),
            candidates=[],
            diagnostics={"error": str(exc)},
        )
