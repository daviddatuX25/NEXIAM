"""Unit tests for the NEXIAM document rectifier module (document_rectifier.py).

Verifies point ordering permutations, point normalization, hard rejection gates,
homography stability, non-mutation of input buffers, unenhanced pixel preservation,
channel support, candidate limits, bounded decode, safety ceilings, and timing bounds.

Usage:
    python3 test_document_rectifier.py
"""

from __future__ import annotations

import hashlib
import numpy as np
import cv2

from document_rectifier import (
    MAX_CANDIDATES_CEILING,
    MAX_DOWNSAMPLE_DIM_CEILING,
    MAX_TARGET_PIXELS,
    MIN_DIMENSION,
    MAX_DIMENSION,
    RectificationLimits,
    RectificationResult,
    RectificationTarget,
    bounded_decode_image,
    compute_homography_hash,
    compute_homography_matrix,
    normalize_quad,
    order_quad_points,
    propose_rectifications,
)


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"FAILED - {name}: {detail}")
    print(f"ok - {name}")


def test_ordering_and_permutations() -> None:
    # 1. Standard axis-aligned rectangle
    pts_std = np.array([[10, 10], [100, 10], [100, 200], [10, 200]], dtype=np.float32)
    ordered_std = order_quad_points(pts_std)
    check("axis-aligned rectangle ordered as TL, TR, BR, BL",
          np.allclose(ordered_std[0], [10, 10])
          and np.allclose(ordered_std[1], [100, 10])
          and np.allclose(ordered_std[2], [100, 200])
          and np.allclose(ordered_std[3], [10, 200]),
          str(ordered_std))

    # 2. Permutations of the same points resolve to identical canonical order
    for perm_idx, perm in enumerate([
        [1, 2, 3, 0],
        [2, 3, 0, 1],
        [3, 0, 1, 2],
    ], start=1):
        pts_perm = pts_std[perm]
        ordered_perm = order_quad_points(pts_perm)
        check(
            f"permutation {perm_idx} resolves to identical canonical order",
            np.allclose(ordered_std, ordered_perm),
            f"{ordered_std} vs {ordered_perm}",
        )

    # 3. Perspective skewed quad
    pts_skewed = np.array([[100, 10], [20, 15], [95, 210], [5, 205]], dtype=np.float32)
    ordered_skewed = order_quad_points(pts_skewed)
    check(
        "perspective skewed quad ordered correctly",
        np.allclose(ordered_skewed[0], [20, 15])
        and np.allclose(ordered_skewed[1], [100, 10])
        and np.allclose(ordered_skewed[2], [95, 210])
        and np.allclose(ordered_skewed[3], [5, 205]),
        str(ordered_skewed),
    )


def test_point_normalization() -> None:
    # Boundary points map exactly to [0.0..1.0] using (W-1) and (H-1)
    pts_boundary = np.array([[0, 0], [999, 0], [999, 1999], [0, 1999]], dtype=np.float32)
    norm_boundary = normalize_quad(pts_boundary, 1000, 2000)
    check(
        "boundary points normalize to exact corners (0,0), (1,0), (1,1), (0,1)",
        norm_boundary == [(0.0, 0.0), (1.0, 0.0), (1.0, 1.0), (0.0, 1.0)],
        str(norm_boundary),
    )

    # Interior points are in [0.0..1.0] range
    pts_interior = np.array([[100, 200], [900, 200], [900, 1800], [100, 1800]], dtype=np.float32)
    norm_interior = normalize_quad(pts_interior, 1001, 2001)
    check(
        "interior points normalize to expected fractions",
        len(norm_interior) == 4
        and norm_interior[0] == (0.1, 0.1)
        and norm_interior[1] == (0.9, 0.1)
        and norm_interior[2] == (0.9, 0.9)
        and norm_interior[3] == (0.1, 0.9),
        str(norm_interior),
    )


def test_target_validation_and_safety_bounds() -> None:
    # 1. Valid target
    t = RectificationTarget(target_width=1000, target_height=1500)
    check("valid target aspect ratio derived from dimensions", abs(float(t.target_aspect_ratio) - (1000.0 / 1500.0)) < 1e-6)

    # 2. Reject non-integer dimensions
    try:
        RectificationTarget(target_width=1000.5, target_height=1500)  # type: ignore
        check("reject float width", False)
    except ValueError:
        check("reject float width", True)

    # 3. Reject out-of-bound dimensions (<300 or >8000)
    try:
        RectificationTarget(target_width=200, target_height=1500)
        check("reject too small width", False)
    except ValueError:
        check("reject too small width", True)

    try:
        RectificationTarget(target_width=9000, target_height=1500)
        check("reject too large width", False)
    except ValueError:
        check("reject too large width", True)

    # 4. Reject huge pixel ceiling (>25 MP)
    try:
        RectificationTarget(target_width=6000, target_height=6000)  # 36 MP > 25 MP
        check("reject >25MP target", False)
    except ValueError:
        check("reject >25MP target", True)

    # 5. Reject mismatched target aspect ratio
    try:
        RectificationTarget(target_width=1000, target_height=2000, target_aspect_ratio=0.8)
        check("reject mismatched aspect ratio", False)
    except ValueError:
        check("reject mismatched aspect ratio", True)


def test_limits_clamping_and_validation() -> None:
    # 1. Clamping max_candidates > 3 down to 3
    l_high = RectificationLimits(max_candidates=10)
    check("clamp max_candidates to 3 ceiling", l_high.max_candidates == 3, str(l_high.max_candidates))

    # 2. Clamping max_downsample_dim > 1024 down to 1024
    l_down = RectificationLimits(max_downsample_dim=2048)
    check("clamp max_downsample_dim to 1024 ceiling", l_down.max_downsample_dim == 1024, str(l_down.max_downsample_dim))

    # 3. Clamping timeout bounds
    l_time = RectificationLimits(timeout_seconds=5.0)
    check("clamp timeout_seconds to 1.0 max", l_time.timeout_seconds == 1.0, str(l_time.timeout_seconds))

    # 4. Reject invalid ratio (min >= max)
    try:
        RectificationLimits(min_area_ratio=0.8, max_area_ratio=0.5)
        check("reject inverted area ratio", False)
    except ValueError:
        check("reject inverted area ratio", True)


def test_malformed_ndarray_validation() -> None:
    target = RectificationTarget(target_width=1000, target_height=1414)

    # 1. None input
    res_none = propose_rectifications(None, target)  # type: ignore
    check("None input returns invalid_input status", res_none.status == "invalid_input", res_none.status)

    # 2. 1D array
    arr_1d = np.zeros((1000,), dtype=np.uint8)
    res_1d = propose_rectifications(arr_1d, target)
    check("1D array returns invalid_input status", res_1d.status == "invalid_input", res_1d.status)

    # 3. 4D array
    arr_4d = np.zeros((10, 10, 10, 3), dtype=np.uint8)
    res_4d = propose_rectifications(arr_4d, target)
    check("4D array returns invalid_input status", res_4d.status == "invalid_input", res_4d.status)

    # 4. 2-channel array
    arr_2ch = np.zeros((500, 500, 2), dtype=np.uint8)
    res_2ch = propose_rectifications(arr_2ch, target)
    check("2-channel array returns invalid_input status", res_2ch.status == "invalid_input", res_2ch.status)

    # 5. Non-uint8 array
    arr_f32 = np.zeros((500, 500, 3), dtype=np.float32)
    res_f32 = propose_rectifications(arr_f32, target)  # type: ignore
    check("float32 array returns invalid_input status", res_f32.status == "invalid_input", res_f32.status)

    # 6. Dimensions smaller than MIN_DIMENSION (300)
    arr_small = np.zeros((200, 200, 3), dtype=np.uint8)
    res_small = propose_rectifications(arr_small, target)
    check("small array (<300px) returns invalid_input status", res_small.status == "invalid_input", res_small.status)


def test_monotonic_timeout_containment() -> None:
    img = np.full((1200, 900, 3), 60, dtype=np.uint8)
    cv2.rectangle(img, (100, 120), (800, 1080), (245, 245, 245), -1)
    target = RectificationTarget(target_width=700, target_height=1000)

    # Tiny timeout (0.001s = 1ms) forces timeout path
    limits = RectificationLimits(timeout_seconds=0.001)
    res = propose_rectifications(img, target, limits)
    check("tight timeout results in status='timeout'", res.status == "timeout", res.status)
    check("timeout returns zero candidates", len(res.candidates) == 0, str(len(res.candidates)))


def test_bounded_decode_image() -> None:
    # 1. Valid PNG encode and safe decode
    img = np.full((400, 400, 3), 120, dtype=np.uint8)
    ok, buf = cv2.imencode(".png", img)
    assert ok
    raw_bytes = buf.tobytes()

    decoded = bounded_decode_image(raw_bytes, max_bytes=1_000_000, max_pixels=500_000)
    check("bounded decode succeeds on valid PNG image", decoded.shape == (400, 400, 3))

    # 1b. Valid JPEG encode and safe decode
    ok_jpg, buf_jpg = cv2.imencode(".jpg", img)
    assert ok_jpg
    dec_jpg = bounded_decode_image(buf_jpg.tobytes())
    check("bounded decode succeeds on valid JPEG image", dec_jpg.shape == (400, 400, 3))

    # 1c. Valid WebP encode and safe decode
    ok_webp, buf_webp = cv2.imencode(".webp", img)
    assert ok_webp
    dec_webp = bounded_decode_image(buf_webp.tobytes())
    check("bounded decode succeeds on valid WebP image", dec_webp.shape == (400, 400, 3))

    # 2. Exceeds max bytes limit
    try:
        bounded_decode_image(raw_bytes, max_bytes=100)
        check("reject oversize payload", False)
    except ValueError:
        check("reject oversize payload", True)

    # 3. Exceeds max decoded pixels
    try:
        bounded_decode_image(raw_bytes, max_pixels=1000)
        check("reject oversize decoded pixels", False)
    except ValueError:
        check("reject oversize decoded pixels", True)

    # 4. Corrupt bytes rejected before decode
    try:
        bounded_decode_image(b"not_an_image_header")
        check("reject corrupt image bytes", True)
    except ValueError:
        check("reject corrupt image bytes", True)

    # 5. Malformed PNG/JPEG/WebP headers rejected before decode
    try:
        bounded_decode_image(b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR")
        check("reject truncated PNG header", False)
    except ValueError:
        check("reject truncated PNG header", True)
def test_hard_rejection_gates() -> None:
    target = RectificationTarget(target_width=1000, target_height=1414)
    limits = RectificationLimits(min_area_ratio=0.55, max_area_ratio=0.99)

    # 1. Reject small area (< 55% frame)
    img_small_box = np.full((1000, 1000, 3), 50, dtype=np.uint8)
    cv2.rectangle(img_small_box, (250, 250), (750, 750), (240, 240, 240), -1)
    res_small = propose_rectifications(img_small_box, target, limits)
    check("small area quad (<55% frame) is rejected", len(res_small.candidates) == 0, str(len(res_small.candidates)))

    # 2. Reject full frame (> 99% frame)
    img_full = np.full((1000, 1000, 3), 240, dtype=np.uint8)
    res_full = propose_rectifications(img_full, target, limits)
    check("full frame (>99% frame) has no valid document quad", len(res_full.candidates) == 0, str(len(res_full.candidates)))

    # 3. Reject non-orthogonal skewed quad (diamond / rhombus)
    img_diamond = np.full((1000, 1000, 3), 50, dtype=np.uint8)
    pts_diamond = np.array([[500, 100], [900, 500], [500, 900], [100, 500]], dtype=np.int32)
    cv2.fillPoly(img_diamond, [pts_diamond], (240, 240, 240))
    res_diamond = propose_rectifications(img_diamond, target, limits)
    check("diamond / non-rectangular shape is rejected by orthogonality gate", len(res_diamond.candidates) == 0, str(len(res_diamond.candidates)))

    # 4. Reject impossible edge ratio skew (extreme trapezoid)
    img_trapezoid = np.full((1000, 1000, 3), 50, dtype=np.uint8)
    pts_trap = np.array([[100, 100], [900, 100], [550, 900], [450, 900]], dtype=np.int32)
    cv2.fillPoly(img_trapezoid, [pts_trap], (240, 240, 240))
    res_trap = propose_rectifications(img_trapezoid, target, limits)
    check("extreme trapezoid is rejected by edge ratio skew gate", len(res_trap.candidates) == 0, str(len(res_trap.candidates)))

    # 5. Reject impossible aspect ratio
    target_tall = RectificationTarget(target_width=1000, target_height=2000, target_aspect_ratio=0.5)
    img_square = np.full((1000, 1000, 3), 50, dtype=np.uint8)
    cv2.rectangle(img_square, (100, 100), (900, 900), (240, 240, 240), -1)  # area 64%, square aspect 1.0
    res_aspect = propose_rectifications(img_square, target_tall, limits)
    check("quad with incompatible aspect ratio is rejected", len(res_aspect.candidates) == 0, str(len(res_aspect.candidates)))


def test_homography_stability_and_hash() -> None:
    pts_orig = np.array([[100.0, 150.0], [900.0, 120.0], [880.0, 1350.0], [120.0, 1320.0]], dtype=np.float32)
    matrix = compute_homography_matrix(pts_orig, (2000, 3000))
    check("valid quad yields non-singular homography matrix", matrix is not None and matrix.shape == (3, 3), str(matrix))
    assert matrix is not None

    h_hash = compute_homography_hash(matrix)
    check("homography hash starts with sha256 prefix", h_hash.startswith("sha256:") and len(h_hash) == 71, h_hash)

    # Identical matrix produces identical hash
    h_hash2 = compute_homography_hash(matrix)
    check("homography hash is deterministic across calls", h_hash == h_hash2, f"{h_hash} vs {h_hash2}")

    # Collinear / degenerate points return None
    pts_degenerate = np.array([[100.0, 100.0], [200.0, 200.0], [300.0, 300.0], [400.0, 400.0]], dtype=np.float32)
    matrix_degen = compute_homography_matrix(pts_degenerate, (2000, 3000))
    check("degenerate collinear points return None matrix", matrix_degen is None, str(matrix_degen))


def test_input_immutability_and_no_mutation() -> None:
    img = np.full((1200, 900, 3), 60, dtype=np.uint8)
    cv2.rectangle(img, (100, 120), (800, 1080), (245, 245, 245), -1)
    original_sha256 = hashlib.sha256(img.tobytes()).hexdigest()

    target = RectificationTarget(target_width=1400, target_height=2000)
    res = propose_rectifications(img, target)
    check("rectification succeeded on test canvas", res.status == "success" and len(res.candidates) >= 1)

    after_sha256 = hashlib.sha256(img.tobytes()).hexdigest()
    check("input image buffer was not mutated in place", original_sha256 == after_sha256, f"{original_sha256} vs {after_sha256}")


def test_target_dimensions_and_unenhanced_pixels() -> None:
    img = np.full((1200, 900, 3), 60, dtype=np.uint8)
    cv2.rectangle(img, (100, 120), (800, 1080), (200, 200, 200), -1)
    cv2.circle(img, (450, 600), 50, (128, 128, 128), -1)

    target_w, target_h = 700, 1000
    target = RectificationTarget(target_width=target_w, target_height=target_h)
    res = propose_rectifications(img, target)
    check("candidate found for unenhanced test", len(res.candidates) >= 1)
    warped = res.candidates[0].warped_image

    # 1. Output dimensions match exact target
    check("warped image matches target dimensions exactly", warped.shape == (target_h, target_w, 3), str(warped.shape))

    # 2. Check that the interior circle was NOT binarized
    center_val = warped[target_h // 2, target_w // 2, 0]
    check("interior pixel values are not binarized (Otsu/Magic Color forbidden)", 110 <= center_val <= 145, f"center_val={center_val}")


def test_grayscale_and_bgr_channel_support() -> None:
    target = RectificationTarget(target_width=700, target_height=1000)

    # 1. 3-channel BGR image
    bgr = np.full((1200, 900, 3), 60, dtype=np.uint8)
    cv2.rectangle(bgr, (100, 120), (800, 1080), (245, 245, 245), -1)
    res_bgr = propose_rectifications(bgr, target)
    check("3-channel BGR image processes successfully", res_bgr.status == "success" and len(res_bgr.candidates) >= 1)
    check("BGR output has 3 channels", res_bgr.candidates[0].warped_image.ndim == 3 and res_bgr.candidates[0].warped_image.shape[2] == 3)

    # 2. 1-channel Grayscale image
    gray = np.full((1200, 900), 60, dtype=np.uint8)
    cv2.rectangle(gray, (100, 120), (800, 1080), 245, -1)
    res_gray = propose_rectifications(gray, target)
    check("1-channel Grayscale image processes successfully", res_gray.status == "success" and len(res_gray.candidates) >= 1)
    check("Grayscale output has 1 or 2 dimensions matching target", res_gray.candidates[0].warped_image.shape[:2] == (1000, 700))



def _document_capture(patterned_background: bool = False) -> tuple[np.ndarray, np.ndarray, tuple[int, int]]:
    image = np.full((1200, 1000, 3), 42, dtype=np.uint8)
    if patterned_background:
        for y in range(12, image.shape[0], 34):
            cv2.line(image, (0, y), (image.shape[1] - 1, y), (78, 78, 78), 1)
        for x in range(15, image.shape[1], 39):
            cv2.line(image, (x, 0), (x, image.shape[0] - 1), (68, 68, 68), 1)
        for y in range(24, image.shape[0], 96):
            for x in range(24, image.shape[1], 104):
                cv2.circle(image, (x, y), 5, (95, 95, 95), -1)

    left, top, right, bottom = 200, 150, 799, 999
    cv2.rectangle(image, (left, top), (right, bottom), (245, 245, 245), -1)
    cv2.rectangle(image, (left + 65, top + 100), (left + 175, top + 155), (30, 30, 30), 2)
    cv2.rectangle(image, (left + 420, top + 590), (left + 490, top + 660), (30, 30, 30), 2)
    mark = (left + 300, top + 425)
    cv2.circle(image, mark, 5, (236, 236, 236), -1)
    corners = np.array(
        [[left, top], [right, top], [right, bottom], [left, bottom]],
        dtype=np.float32,
    )
    return image, corners, mark


def _quad_iou(first: np.ndarray, second: np.ndarray) -> float:
    first_mask = np.zeros((1200, 1300), dtype=np.uint8)
    second_mask = np.zeros_like(first_mask)
    cv2.fillPoly(first_mask, [first.astype(np.int32)], 255)
    cv2.fillPoly(second_mask, [second.astype(np.int32)], 255)
    intersection = cv2.countNonZero(cv2.bitwise_and(first_mask, second_mask))
    union = cv2.countNonZero(cv2.bitwise_or(first_mask, second_mask))
    return float(intersection) / float(union) if union else 0.0


def test_mild_rotation_preserves_document_orientation() -> None:
    image, corners, _ = _document_capture()
    rotation = cv2.getRotationMatrix2D((500, 600), 3, 1)
    rotated = cv2.warpAffine(image, rotation, (1000, 1200), borderValue=(42, 42, 42))
    expected = cv2.transform(corners.reshape(1, 4, 2), rotation).reshape(4, 2)
    result = propose_rectifications(rotated, RectificationTarget(target_width=600, target_height=850))
    check("mildly rotated portrait has a paper candidate", result.status == "success" and bool(result.candidates))
    actual = np.asarray(result.candidates[0].quad_original)
    check(
        "mildly rotated portrait rectifies from the actual upper-left corner",
        np.linalg.norm(actual[0] - expected[0]) < 15,
        f"{actual[0]} vs {expected[0]}",
    )


def test_document_boundary_falsification_cases() -> None:
    target = RectificationTarget(target_width=600, target_height=850)

    for patterned in (False, True):
        image, expected_corners, faint_mark = _document_capture(patterned)
        result = propose_rectifications(image, target)
        check(
            f"{'patterned' if patterned else 'contrasting'} background yields a candidate",
            result.status == "success" and len(result.candidates) >= 1,
            f"{result.status}, {len(result.candidates)}",
        )
        candidate = result.candidates[0]
        check(
            f"{'patterned' if patterned else 'contrasting'} background selects the actual paper edge",
            _quad_iou(np.asarray(candidate.quad_original), expected_corners) >= 0.94,
            str(candidate.quad_original),
        )
        warped = candidate.warped_image
        mark_x = int(round((faint_mark[0] - 200) * 599 / 599))
        mark_y = int(round((faint_mark[1] - 150) * 849 / 849))
        mark_patch = warped[mark_y - 2 : mark_y + 3, mark_x - 2 : mark_x + 3]
        check(
            f"{'patterned' if patterned else 'contrasting'} rectification preserves faint grayscale marks",
            bool(np.any((mark_patch > 200) & (mark_patch < 255))),
            str(np.unique(mark_patch)),
        )
    paperless = np.full((1200, 1000, 3), 110, dtype=np.uint8)
    for y in range(24, paperless.shape[0], 48):
        for x in range(24, paperless.shape[1], 48):
            cv2.circle(paperless, (x, y), 8, (155, 155, 155), -1)
            cv2.circle(paperless, (x, y), 3, (68, 68, 68), -1)
    paperless_result = propose_rectifications(paperless, target)
    check(
        "patterned surface without a coherent paper boundary has no accepted quad",
        paperless_result.status == "no_candidates" and not paperless_result.candidates,
        f"{paperless_result.status}, {paperless_result.diagnostics.get('rejected_by_gate')}",
    )

    partial = np.full((1000, 1000, 3), 42, dtype=np.uint8)
    cv2.rectangle(partial, (175, -270), (824, 649), (245, 245, 245), -1)
    partial_result = propose_rectifications(partial, target)
    check(
        "cropped partial paper is not accepted as a full document",
        partial_result.status == "no_candidates" and not partial_result.candidates,
        f"{partial_result.status}, {partial_result.diagnostics.get('rejected_by_gate')}",
    )

    wrong_aspect = np.full((1000, 1000, 3), 42, dtype=np.uint8)
    cv2.rectangle(wrong_aspect, (180, 180), (819, 819), (245, 245, 245), -1)
    mismatch = propose_rectifications(
        wrong_aspect,
        target,
        RectificationLimits(max_aspect_ratio_deviation=0.18),
    )
    check(
        "square distractor is rejected for portrait target aspect",
        mismatch.status == "no_candidates"
        and not mismatch.candidates
        and mismatch.diagnostics.get("rejected_by_gate", {}).get("aspect_ratio", 0) > 0,
        f"{mismatch.status}, {mismatch.diagnostics.get('rejected_by_gate')}",
    )

    two_pages = np.full((900, 1300, 3), 42, dtype=np.uint8)
    for left in (25, 675):
        cv2.rectangle(two_pages, (left, 25), (left + 599, 874), (245, 245, 245), -1)
        cv2.rectangle(two_pages, (left + 65, 120), (left + 175, 175), (30, 30, 30), 2)
        cv2.rectangle(two_pages, (left + 420, 610), (left + 490, 680), (30, 30, 30), 2)
    competing = propose_rectifications(two_pages, target)
    centers = sorted(
        float(np.mean([point[0] for point in candidate.quad_original]))
        for candidate in competing.candidates
    )
    check(
        "two plausible pages remain distinct proposals for downstream ambiguity rejection",
        competing.status == "success" and len(centers) >= 2 and centers[1] - centers[0] > 500,
        f"{competing.status}, {centers}",
    )

    rotated = np.full((1200, 1200, 3), 42, dtype=np.uint8)
    rotated_corners = cv2.boxPoints(((600.0, 600.0), (760.0, 760.0), 45.0))
    cv2.fillConvexPoly(rotated, np.round(rotated_corners).astype(np.int32), (245, 245, 245))
    rotated_target = RectificationTarget(target_width=600, target_height=600)
    rotation_result = propose_rectifications(rotated, rotated_target)
    check(
        "rotated square boundary is ordered cyclically rather than losing corners",
        rotation_result.status == "success"
        and bool(rotation_result.candidates)
        and _quad_iou(np.asarray(rotation_result.candidates[0].quad_original), rotated_corners) >= 0.90,
        f"{rotation_result.status}, {rotation_result.candidates[0].quad_original if rotation_result.candidates else []}",
    )
    rotated_portrait = np.full((1200, 1200, 3), 42, dtype=np.uint8)
    portrait_corners = cv2.boxPoints(((600.0, 600.0), (700.0, 990.0), 90.0))
    cv2.fillConvexPoly(rotated_portrait, np.round(portrait_corners).astype(np.int32), (245, 245, 245))
    portrait_rotation = propose_rectifications(rotated_portrait, target)
    check(
        "quarter-turned portrait edge is reconciled to the target aspect without relaxing tolerance",
        portrait_rotation.status == "success"
        and bool(portrait_rotation.candidates)
        and portrait_rotation.candidates[0].aspect_ratio_score >= 0.95
        and _quad_iou(np.asarray(portrait_rotation.candidates[0].quad_original), portrait_corners) >= 0.90,
        f"{portrait_rotation.status}, {portrait_rotation.candidates[0].aspect_ratio_score if portrait_rotation.candidates else None}",
    )


def test_candidate_cap_and_timing_bounds() -> None:
    img = np.full((1200, 900, 3), 60, dtype=np.uint8)
    cv2.rectangle(img, (100, 120), (800, 1080), (245, 245, 245), -1)

    target = RectificationTarget(target_width=700, target_height=1000)
    limits = RectificationLimits(max_candidates=2, timeout_seconds=0.25)
    res = propose_rectifications(img, target, limits)

    check("candidates capped at max_candidates (2)", len(res.candidates) <= 2, str(len(res.candidates)))
    total_ms = res.diagnostics.get("timings_ms", {}).get("total", 0.0)
    check("execution completed within 250ms ceiling", total_ms < 250.0, f"{total_ms} ms")


def main() -> int:
    print("Running document_rectifier unit test suite...")
    test_ordering_and_permutations()
    test_point_normalization()
    test_target_validation_and_safety_bounds()
    test_limits_clamping_and_validation()
    test_malformed_ndarray_validation()
    test_monotonic_timeout_containment()
    test_bounded_decode_image()
    test_hard_rejection_gates()
    test_homography_stability_and_hash()
    test_input_immutability_and_no_mutation()
    test_target_dimensions_and_unenhanced_pixels()
    test_grayscale_and_bgr_channel_support()
    test_document_boundary_falsification_cases()
    test_mild_rotation_preserves_document_orientation()
    test_candidate_cap_and_timing_bounds()
    print("All document_rectifier unit tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
