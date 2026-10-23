"""Isolated unit tests for document_rectifier.py (Task 11.4).

Tests safety ceilings, target validation, limits, quad ordering, normalization,
homography matrix/hash, edge detection maps, contour quads, orthogonality,
aspect ratio, area bounds, non-mutation of buffers, and unenhanced pixel warping.
"""

from __future__ import annotations

import hashlib
import sys
import unittest
from pathlib import Path

import cv2
import numpy as np

# Ensure parent directory is on sys.path
INFERENCE_DIR = Path(__file__).resolve().parent.parent
if str(INFERENCE_DIR) not in sys.path:
    sys.path.insert(0, str(INFERENCE_DIR))

import document_rectifier
from document_rectifier import (
    MAX_CANDIDATES_CEILING,
    MAX_DECODED_PIXELS,
    MAX_DOWNSAMPLE_DIM_CEILING,
    MAX_TARGET_PIXELS,
    MIN_DIMENSION,
    RECTIFIER_VERSION,
    RectificationLimits,
    RectificationResult,
    RectificationTarget,
    RectifiedCandidate,
    bounded_decode_image,
    compute_homography_hash,
    compute_homography_matrix,
    inspect_image_header,
    normalize_quad,
    order_quad_points,
    propose_rectifications,
)


class TestDocumentRectifier(unittest.TestCase):
    def test_constants_and_safety_ceilings(self) -> None:
        self.assertEqual(RECTIFIER_VERSION, "nexiam.rectifier.v1")
        self.assertEqual(MAX_DECODED_PIXELS, 60_000_000)
        self.assertEqual(MAX_TARGET_PIXELS, 25_000_000)
        self.assertEqual(MIN_DIMENSION, 300)
        self.assertEqual(MAX_CANDIDATES_CEILING, 3)
        self.assertEqual(MAX_DOWNSAMPLE_DIM_CEILING, 1024)

    def test_rectification_target_validation(self) -> None:
        target = RectificationTarget(target_width=1638, target_height=2722)
        self.assertEqual(target.target_width, 1638)
        self.assertEqual(target.target_height, 2722)
        self.assertAlmostEqual(target.target_aspect_ratio, 1638.0 / 2722.0, places=4)

        with self.assertRaises(ValueError):
            RectificationTarget(target_width=200, target_height=2722)
        with self.assertRaises(ValueError):
            RectificationTarget(target_width=1638, target_height=9000)
        with self.assertRaises(ValueError):
            RectificationTarget(target_width=6000, target_height=6000)

    def test_rectification_limits_clamping(self) -> None:
        limits = RectificationLimits(max_candidates=10, max_downsample_dim=2000, timeout_seconds=5.0)
        self.assertEqual(limits.max_candidates, MAX_CANDIDATES_CEILING)
        self.assertEqual(limits.max_downsample_dim, MAX_DOWNSAMPLE_DIM_CEILING)
        self.assertEqual(limits.timeout_seconds, 1.0)

    def test_order_quad_points_determinism(self) -> None:
        pts = np.array([[100, 200], [10, 20], [10, 200], [100, 20]], dtype=np.float32)
        ordered = order_quad_points(pts)
        self.assertTrue(np.allclose(ordered[0], [10, 20]))
        self.assertTrue(np.allclose(ordered[1], [100, 20]))
        self.assertTrue(np.allclose(ordered[2], [100, 200]))
        self.assertTrue(np.allclose(ordered[3], [10, 200]))

    def test_normalize_quad(self) -> None:
        pts = np.array([[0, 0], [999, 0], [999, 1999], [0, 1999]], dtype=np.float32)
        norm = normalize_quad(pts, 1000, 2000)
        self.assertEqual(norm[0], (0.0, 0.0))
        self.assertEqual(norm[1], (1.0, 0.0))
        self.assertEqual(norm[2], (1.0, 1.0))
        self.assertEqual(norm[3], (0.0, 1.0))

    def test_homography_matrix_and_hash(self) -> None:
        src = np.array([[100, 100], [900, 120], [880, 1100], [120, 1080]], dtype=np.float32)
        dst_dims = (800, 1000)
        matrix = compute_homography_matrix(src, dst_dims)
        self.assertIsNotNone(matrix)
        self.assertEqual(matrix.shape, (3, 3))
        h_hash = compute_homography_hash(matrix)
        self.assertTrue(h_hash.startswith("sha256:"))
        self.assertEqual(len(h_hash), 71)

    def test_bounded_decode_image(self) -> None:
        img = np.full((600, 400, 3), 128, dtype=np.uint8)
        ok, buf = cv2.imencode(".png", img)
        self.assertTrue(ok)
        raw_bytes = buf.tobytes()

        decoded = bounded_decode_image(raw_bytes)
        self.assertEqual(decoded.shape, (600, 400, 3))

        with self.assertRaises(ValueError):
            bounded_decode_image(b"")
        with self.assertRaises(ValueError):
            bounded_decode_image(b"invalid corrupt bytes")
        with self.assertRaises(ValueError):
            bounded_decode_image(raw_bytes, max_bytes=10)

    def test_header_dimension_inspection_before_decode(self) -> None:
        # 1. Valid PNG header inspection and decode
        img_png = np.full((100, 200, 3), 200, dtype=np.uint8)
        ok, buf_png = cv2.imencode(".png", img_png)
        self.assertTrue(ok)
        dims_png = inspect_image_header(buf_png.tobytes())
        self.assertEqual(dims_png, (200, 100))
        dec_png = bounded_decode_image(buf_png.tobytes())
        self.assertEqual(dec_png.shape, (100, 200, 3))

        # 2. Valid JPEG header inspection and decode
        ok, buf_jpg = cv2.imencode(".jpg", img_png)
        self.assertTrue(ok)
        dims_jpg = inspect_image_header(buf_jpg.tobytes())
        self.assertEqual(dims_jpg, (200, 100))
        dec_jpg = bounded_decode_image(buf_jpg.tobytes())
        self.assertEqual(dec_jpg.shape, (100, 200, 3))

        # 3. Valid WebP header inspection and decode
        ok, buf_webp = cv2.imencode(".webp", img_png)
        self.assertTrue(ok)
        dims_webp = inspect_image_header(buf_webp.tobytes())
        self.assertEqual(dims_webp, (200, 100))
        dec_webp = bounded_decode_image(buf_webp.tobytes())
        self.assertEqual(dec_webp.shape, (100, 200, 3))

        # 4. Oversized header rejected before decode
        with self.assertRaises(ValueError):
            bounded_decode_image(buf_png.tobytes(), max_pixels=5000)

        # 5. Malformed recognized-signature headers rejected before decode
        with self.assertRaises(ValueError):
            # Truncated PNG header
            bounded_decode_image(b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR")
        with self.assertRaises(ValueError):
            # Corrupted JPEG SOI with no SOF marker
            bounded_decode_image(b"\xff\xd8\xff\xe0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00")
        with self.assertRaises(ValueError):
            # Truncated WebP header
            bounded_decode_image(b"RIFF\x14\x00\x00\x00WEBPVP8 ")
        target = RectificationTarget(target_width=1000, target_height=1500)
        self.assertEqual(propose_rectifications(None, target).status, "invalid_input")  # type: ignore
        self.assertEqual(propose_rectifications(np.zeros((100,), dtype=np.uint8), target).status, "invalid_input")
        self.assertEqual(propose_rectifications(np.zeros((200, 200, 3), dtype=np.uint8), target).status, "invalid_input")
        self.assertEqual(propose_rectifications(np.zeros((400, 400, 2), dtype=np.uint8), target).status, "invalid_input")
        self.assertEqual(propose_rectifications(np.zeros((400, 400, 3), dtype=np.float32), target).status, "invalid_input")  # type: ignore

    def test_timeout_containment(self) -> None:
        img = np.full((1200, 900, 3), 100, dtype=np.uint8)
        target = RectificationTarget(target_width=1000, target_height=1500)
        limits = RectificationLimits(timeout_seconds=0.001)
        res = propose_rectifications(img, target, limits)
        self.assertEqual(res.status, "timeout")
        self.assertEqual(len(res.candidates), 0)

    def test_unenhanced_pixel_preservation(self) -> None:
        target_w, target_h = 1000, 1400
        img = np.full((1800, 1400, 3), 40, dtype=np.uint8)
        pts = np.array([[200, 200], [1200, 200], [1200, 1600], [200, 1600]], dtype=np.int32)
        cv2.fillPoly(img, [pts], (240, 240, 240))
        cv2.rectangle(img, (400, 400), (600, 600), (123, 123, 123), -1)

        orig_sha = hashlib.sha256(img.tobytes()).hexdigest()
        target = RectificationTarget(target_width=target_w, target_height=target_h)
        res = propose_rectifications(img, target)

        self.assertEqual(res.status, "success")
        self.assertGreaterEqual(len(res.candidates), 1)
        self.assertEqual(hashlib.sha256(img.tobytes()).hexdigest(), orig_sha)

        warped = res.candidates[0].warped_image
        self.assertEqual(warped.shape, (target_h, target_w, 3))
        patch = warped[200:300, 200:300]
        self.assertTrue(np.allclose(patch.mean(), 123.0, atol=15.0))

    def test_fiducial_frame_recovers_real_phone_photo_from_distracting_floor(self) -> None:
        image = cv2.imread(
            str(Path(__file__).resolve().parent / "fixtures" / "hand-shaded-perspective-redacted.jpg"),
        )
        self.assertIsNotNone(image)
        target = RectificationTarget(
            target_width=2196,
            target_height=3201,
            marker_frame=(
                (0.057, 0.041),
                (0.943, 0.041),
                (0.943, 0.953),
                (0.057, 0.953),
            ),
        )

        result = propose_rectifications(
            image,
            target,
            RectificationLimits(timeout_seconds=1.0),
        )

        self.assertEqual(result.status, "success")
        fiducial = next(
            candidate
            for candidate in result.candidates
            if candidate.detection_map_source == "fiducial_frame"
        )
        self.assertGreater(fiducial.quad_normalized[0][0], 0.1)
        self.assertGreater(fiducial.quad_normalized[3][0], 0.1)
        self.assertLess(fiducial.quad_normalized[1][0], 0.95)
        self.assertLess(fiducial.quad_normalized[2][0], 0.95)

    def test_deadline_check_after_warp(self) -> None:
        target_w, target_h = 1000, 1400
        img = np.full((1800, 1400, 3), 40, dtype=np.uint8)
        pts = np.array([[200, 200], [1200, 200], [1200, 1600], [200, 1600]], dtype=np.int32)
        cv2.fillPoly(img, [pts], (240, 240, 240))
        target = RectificationTarget(target_width=target_w, target_height=target_h)
        # 0.0001s timeout will expire during or before warping
        limits = RectificationLimits(timeout_seconds=0.0001)
        res = propose_rectifications(img, target, limits)
        self.assertEqual(res.status, "timeout")
        self.assertEqual(len(res.candidates), 0)
if __name__ == "__main__":
    unittest.main()
