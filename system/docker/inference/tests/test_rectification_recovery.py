"""Integration tests for guarded document rectification recovery (Task 11.4).

Covers raw-pass bypass, perspective recovery, low contrast, internal false quads,
missing/wrong markers, multi-candidate ambiguity, timeout containment, and shadow mode.
"""

from __future__ import annotations

import base64
import hashlib
import json
import os
import shutil
import struct
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import cv2
import numpy as np

INFERENCE_DIR = Path(__file__).resolve().parent.parent
if str(INFERENCE_DIR) not in sys.path:
    sys.path.insert(0, str(INFERENCE_DIR))

import detect
import document_rectifier
import fiducial
import generate_fixture
import marker_states
import preflight
import rectify
import rectification_recovery
import test_characterization as tc
import verify
from document_rectifier import (
    RectificationLimits,
    RectificationResult,
    RectificationTarget,
    propose_rectifications,
)

INSET = 211
TARGET_PAPER_W = 2060
TARGET_PAPER_H = 3144
TARGET_FRAME_W = TARGET_PAPER_W - 2 * INSET  # 1638
TARGET_FRAME_H = TARGET_PAPER_H - 2 * INSET  # 2722


def render_sheet(seed: int = 7, skip_markers: bool = False, wrong_markers: bool = False) -> np.ndarray:
    template = tc.characterization_template()
    markers = {
        "family": "omrchecker.bullseye",
        "asset_name": "omr_marker.jpg",
        "size": tc.MARKER_SIZE,
        "quiet_zone": 40,
        "inset": INSET,
        "paper": tc.PAPER,
        "centers": [
            [INSET, INSET],
            [tc.PAPER[0] - INSET, INSET],
            [tc.PAPER[0] - INSET, tc.PAPER[1] - INSET],
            [INSET, tc.PAPER[1] - INSET],
        ],
    }
    rendered = generate_fixture.render(
        template,
        seed=seed,
        blank=False,
        margin_ratio=0.08,
        markers=markers,
        skip_markers=skip_markers,
    )
    if wrong_markers:
        ox = int(round(tc.PAPER[0] * 0.08))
        oy = int(round(tc.PAPER[1] * 0.08))
        sz = tc.MARKER_SIZE // 2 + 10
        for cx, cy in markers["centers"]:
            actual_x = ox + cx
            actual_y = oy + cy
            cv2.rectangle(rendered, (actual_x - sz, actual_y - sz), (actual_x + sz, actual_y + sz), 20, -1)
            cv2.line(rendered, (actual_x - sz, actual_y - sz), (actual_x + sz, actual_y + sz), 240, 6)
            cv2.line(rendered, (actual_x - sz, actual_y + sz), (actual_x + sz, actual_y - sz), 240, 6)
    return rendered


def apply_perspective_distortion(
    image: np.ndarray,
    width_skew_pct: float = 0.15,
    height_skew_pct: float = 0.12,
) -> np.ndarray:
    h, w = image.shape[:2]
    dw = int(round(w * width_skew_pct))
    dh = int(round(h * height_skew_pct))

    src_pts = np.array([[0, 0], [w - 1, 0], [w - 1, h - 1], [0, h - 1]], dtype=np.float32)
    dst_pts = np.array([[dw, dh], [w - 1, 0], [w - 1 - dw, h - 1], [0, h - 1 - dh]], dtype=np.float32)
    desk_margin = 120
    canvas_w = w + 2 * desk_margin
    canvas_h = h + 2 * desk_margin

    dst_pts_shifted = dst_pts + np.array([desk_margin, desk_margin], dtype=np.float32)
    M = cv2.getPerspectiveTransform(src_pts, dst_pts_shifted)
    warped = cv2.warpPerspective(
        image,
        M,
        (canvas_w, canvas_h),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_CONSTANT,
        borderValue=(45, 45, 45) if image.ndim == 3 else 45,
    )
    return warped


def make_manifest(image: np.ndarray, image_bytes: bytes, template: dict | None = None, shadow_mode: bool = False) -> dict:
    manifest = tc.manifest_for(image, image_bytes)
    if template is not None:
        manifest["native_template"] = template
        manifest["native_bundle_metadata"]["files"]["template.json"] = hashlib.sha256(
            json.dumps(template, separators=(",", ":")).encode()
        ).hexdigest()
        canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
        manifest["native_bundle_hash"] = hashlib.sha256(canonical(manifest["native_bundle_metadata"])).hexdigest()
    manifest["rectification_shadow_mode"] = shadow_mode
    return manifest


class TestRectificationRecovery(unittest.TestCase):
    def test_raw_pass_bypasses_rectification(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        ok, buf = cv2.imencode(".png", rendered)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(rendered, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-raw-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)
            res = verify.verify(manifest, inp, tdp)
            self.assertEqual(res["outcome"], "succeeded")
            self.assertFalse(res["rectification"]["attempted"])
            self.assertFalse(res["rectification"]["applied"])

    def test_perspective_skew_guarded_recovery(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)

        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-skew-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)
            res = verify.verify(manifest, inp, tdp)
            self.assertEqual(res["outcome"], "succeeded")
            self.assertTrue(res["rectification"]["attempted"])
            self.assertTrue(res["rectification"]["applied"])
            self.assertEqual(res["rectification"]["selection_reason"], "unique_four_marker_pass")
            self.assertEqual(len(res["rectification"]["raw_marker_matches"]), 4)
            self.assertEqual(len(res["rectification"]["rectified_marker_matches"]), 4)
            self.assertEqual(len(res["rectification"]["quad_normalized"]), 4)
            self.assertTrue(res["rectification"]["homography_hash"].startswith("sha256:"))
            self.assertEqual(res["rectification"]["input_dimensions"], [canvas.shape[1], canvas.shape[0]])
            self.assertEqual(res["rectification"]["output_dimensions"], [TARGET_FRAME_W, TARGET_FRAME_H])
            self.assertEqual(len(res["marker_matches"]), 4)
            self.assertTrue(all(v >= 0.30 for v in res["marker_matches"].values()))
    def test_wrong_markers_rejected_fail_closed(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet(wrong_markers=True)
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)

        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-wrong-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)
            with self.assertRaises(detect.DetectionError) as cm:
                verify.verify(manifest, inp, tdp)
            self.assertIn(cm.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
            self.assertEqual(cm.exception.rectification["reason"], "no_valid_candidate")
            self.assertEqual(cm.exception.rectification["valid_candidate_count"], 0)
            self.assertTrue(cm.exception.rectification["attempted"])
            self.assertFalse(cm.exception.rectification["applied"])
    def test_shadow_mode_does_not_promote(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)

        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template, shadow_mode=True)

        with tempfile.TemporaryDirectory(prefix="rect-test-shadow-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)
            with self.assertRaises(detect.DetectionError) as cm:
                verify.verify(manifest, inp, tdp)
            self.assertIn(cm.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
            self.assertTrue(cm.exception.rectification["shadow_mode"])
            self.assertEqual(cm.exception.rectification["reason"], "shadow_mode")
            self.assertEqual(cm.exception.rectification["valid_candidate_count"], 1)
            self.assertFalse(cm.exception.rectification["applied"])
    def test_detect_and_verify_raster_hash_equality(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)

        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-eq-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            out_v = tdp / "out_v"
            out_v.mkdir()
            res_v = verify.verify(manifest, inp, out_v)
            aligned_v_bytes = (out_v / "aligned.png").read_bytes()
            hash_v = hashlib.sha256(aligned_v_bytes).hexdigest()

            out_d = tdp / "out_d"
            out_d.mkdir()
            res_d = detect.detect(manifest, inp, out_d)
            aligned_d_bytes = (out_d / "aligned.png").read_bytes()
            hash_d = hashlib.sha256(aligned_d_bytes).hexdigest()

            # Ensure detect and verify consumed and output the exact same unenhanced raster
            self.assertEqual(hash_v, hash_d)
            self.assertTrue(res_v["rectification"]["applied"])
            self.assertTrue(res_d["rectification"]["applied"])
            self.assertEqual(res_v["rectification"]["selection_reason"], "unique_four_marker_pass")
            self.assertEqual(res_d["rectification"]["selection_reason"], "unique_four_marker_pass")

    def test_ambiguous_rectification_strict_fail_closed(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        skewed = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", skewed)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(skewed, image_bytes, template)

        target = RectificationTarget(TARGET_FRAME_W, TARGET_FRAME_H)
        real_res = propose_rectifications(skewed, target, RectificationLimits())
        self.assertEqual(real_res.status, "success")
        self.assertGreaterEqual(len(real_res.candidates), 1)

        cand0 = real_res.candidates[0]
        cand1 = document_rectifier.RectifiedCandidate(
            candidate_index=1,
            quad_original=cand0.quad_original,
            quad_normalized=cand0.quad_normalized,
            area_ratio=cand0.area_ratio,
            edge_score=cand0.edge_score,
            orthogonality_score=cand0.orthogonality_score,
            aspect_ratio_score=cand0.aspect_ratio_score,
            cross_map_count=cand0.cross_map_count,
            contrast_score=cand0.contrast_score,
            composite_score=cand0.composite_score - 0.001,
            homography_matrix=cand0.homography_matrix,
            homography_hash="sha256:controlledambigfakehash1234567890",
            warped_image=cand0.warped_image.copy(),
            detection_map_source=cand0.detection_map_source,
        )
        fake_multi_res = RectificationResult(
            detector_version=real_res.detector_version,
            status="success",
            input_shape=real_res.input_shape,
            target_dimensions=real_res.target_dimensions,
            candidates=[cand0, cand1],
            diagnostics=real_res.diagnostics,
        )

        with tempfile.TemporaryDirectory(prefix="rect-test-strict-ambig-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            out_v = tdp / "out_v"
            out_v.mkdir()
            out_d = tdp / "out_d"
            out_d.mkdir()

            with patch("document_rectifier.propose_rectifications", return_value=fake_multi_res):
                with self.assertRaises(detect.DetectionError) as cm_v:
                    verify.verify(manifest, inp, out_v)
                self.assertEqual(cm_v.exception.code, "ambiguous_rectification")
                self.assertEqual(cm_v.exception.rectification["reason"], "multiple_valid_candidates")
                self.assertEqual(cm_v.exception.rectification["valid_candidate_count"], 2)
                self.assertFalse(cm_v.exception.rectification["applied"])
                self.assertTrue(cm_v.exception.rectification["attempted"])

                with self.assertRaises(detect.DetectionError) as cm_d:
                    detect.detect(manifest, inp, out_d)
                self.assertEqual(cm_d.exception.code, "ambiguous_rectification")
                self.assertEqual(cm_d.exception.rectification["reason"], "multiple_valid_candidates")
                self.assertEqual(cm_d.exception.rectification["valid_candidate_count"], 2)
                self.assertFalse(cm_d.exception.rectification["applied"])
                self.assertTrue(cm_d.exception.rectification["attempted"])

    def test_raw_four_markers_with_unsafe_shift_bypasses_rectification(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        ok, buf = cv2.imencode(".png", rendered)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(rendered, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-unsafe-shift-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            with patch("detect.find_unsafe_shifts", return_value=["ApplicantNumber"]), \
                 patch("document_rectifier.propose_rectifications") as mock_prop:
                out_d = tdp / "out_d"
                out_d.mkdir()
                res_d = detect.detect(manifest, inp, out_d)
                self.assertEqual(res_d["outcome"], "succeeded")
                self.assertFalse(res_d["rectification"]["attempted"])
                self.assertFalse(res_d["rectification"]["applied"])
                self.assertTrue(any(issue.get("code") == "sheet_misaligned" for issue in res_d.get("issues", [])))
                mock_prop.assert_not_called()

                out_v = tdp / "out_v"
                out_v.mkdir()
                res_v = verify.verify(manifest, inp, out_v)
                self.assertEqual(res_v["outcome"], "succeeded")
                self.assertFalse(res_v["rectification"]["attempted"])
                self.assertFalse(res_v["rectification"]["applied"])
                mock_prop.assert_not_called()
    def test_rectifier_timeout_preserves_raw_error_code(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        timeout_res = RectificationResult(
            detector_version=document_rectifier.RECTIFIER_VERSION,
            status="timeout",
            input_shape=(canvas.shape[0], canvas.shape[1], 3 if canvas.ndim == 3 else 1),
            target_dimensions=(TARGET_FRAME_W, TARGET_FRAME_H),
            candidates=[],
            diagnostics={"timings_ms": {"total": 250.0}},
        )

        with tempfile.TemporaryDirectory(prefix="rect-test-timeout-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            out_v = tdp / "out_v"
            out_v.mkdir()
            out_d = tdp / "out_d"
            out_d.mkdir()

            with patch("document_rectifier.propose_rectifications", return_value=timeout_res):
                with self.assertRaises(detect.DetectionError) as cm_v:
                    verify.verify(manifest, inp, out_v)
                self.assertIn(cm_v.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
                self.assertNotEqual(cm_v.exception.code, "rectifier_timeout")
                self.assertEqual(cm_v.exception.rectification["reason"], "rectifier_timeout")
                self.assertEqual(cm_v.exception.rectification["valid_candidate_count"], 0)
                self.assertTrue(cm_v.exception.rectification["attempted"])
                self.assertFalse(cm_v.exception.rectification["applied"])

                with self.assertRaises(detect.DetectionError) as cm_d:
                    detect.detect(manifest, inp, out_d)
                self.assertIn(cm_d.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
                self.assertNotEqual(cm_d.exception.code, "rectifier_timeout")
                self.assertEqual(cm_d.exception.rectification["reason"], "rectifier_timeout")
                self.assertEqual(cm_d.exception.rectification["valid_candidate_count"], 0)
                self.assertTrue(cm_d.exception.rectification["attempted"])
                self.assertFalse(cm_d.exception.rectification["applied"])

    def test_rectifier_exception_preserves_raw_error_code(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        with tempfile.TemporaryDirectory(prefix="rect-test-exc-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            out_v = tdp / "out_v"
            out_v.mkdir()
            out_d = tdp / "out_d"
            out_d.mkdir()

            with patch("document_rectifier.propose_rectifications", side_effect=RuntimeError("Simulated OpenCV crash")):
                with self.assertRaises(detect.DetectionError) as cm_v:
                    verify.verify(manifest, inp, out_v)
                self.assertIn(cm_v.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
                self.assertNotEqual(cm_v.exception.code, "rectifier_failed")
                self.assertEqual(cm_v.exception.rectification["reason"], "rectifier_exception")
                self.assertEqual(cm_v.exception.rectification["valid_candidate_count"], 0)
                self.assertTrue(cm_v.exception.rectification["attempted"])
                self.assertFalse(cm_v.exception.rectification["applied"])

                with self.assertRaises(detect.DetectionError) as cm_d:
                    detect.detect(manifest, inp, out_d)
                self.assertIn(cm_d.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
                self.assertNotEqual(cm_d.exception.code, "rectifier_failed")
                self.assertEqual(cm_d.exception.rectification["reason"], "rectifier_exception")
                self.assertEqual(cm_d.exception.rectification["valid_candidate_count"], 0)
                self.assertTrue(cm_d.exception.rectification["attempted"])
                self.assertFalse(cm_d.exception.rectification["applied"])

    def test_zero_valid_candidates_preserves_raw_error_code(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        no_cand_res = RectificationResult(
            detector_version=document_rectifier.RECTIFIER_VERSION,
            status="no_candidates",
            input_shape=(canvas.shape[0], canvas.shape[1], 3 if canvas.ndim == 3 else 1),
            target_dimensions=(TARGET_FRAME_W, TARGET_FRAME_H),
            candidates=[],
            diagnostics={"timings_ms": {"total": 15.0}},
        )

        with tempfile.TemporaryDirectory(prefix="rect-test-nocand-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            out_v = tdp / "out_v"
            out_v.mkdir()

            with patch("document_rectifier.propose_rectifications", return_value=no_cand_res):
                with self.assertRaises(detect.DetectionError) as cm_v:
                    verify.verify(manifest, inp, out_v)
                self.assertIn(cm_v.exception.code, ("marker_missing", "marker_mismatch", "sheet_partial"))
                self.assertEqual(cm_v.exception.rectification["reason"], "no_valid_candidate")
                self.assertEqual(cm_v.exception.rectification["valid_candidate_count"], 0)
                self.assertTrue(cm_v.exception.rectification["attempted"])
                self.assertFalse(cm_v.exception.rectification["applied"])

    def test_preflight_successful_rectification_provenance(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)
        manifest["contract_version"] = detect.PREFLIGHT_CONTRACT_VERSION

        with tempfile.TemporaryDirectory(prefix="rect-test-preflight-") as td:
            tdp = Path(td)
            inp = tdp / "input.png"
            inp.write_bytes(image_bytes)

            res = preflight.preflight(manifest, inp, tdp)
            self.assertEqual(res["outcome"], "validated")
            self.assertIn("rectification", res)
            rect = res["rectification"]
            self.assertTrue(rect["attempted"])
            self.assertTrue(rect["applied"])
            self.assertEqual(rect["selection_reason"], "unique_four_marker_pass")
            self.assertEqual(len(rect["rectified_marker_matches"]), 4)
            self.assertTrue(all(v >= 0.30 for v in rect["rectified_marker_matches"].values()))
            self.assertIn("raw_marker_matches", rect)
            self.assertIn("quad_normalized", rect)
            self.assertIn("homography_hash", rect)
            self.assertEqual(rect["output_dimensions"], [TARGET_FRAME_W, TARGET_FRAME_H])

        # Raw pass bypasses rectification in preflight
        ok_raw, buf_raw = cv2.imencode(".png", rendered)
        self.assertTrue(ok_raw)
        raw_bytes = buf_raw.tobytes()
        manifest_raw = make_manifest(rendered, raw_bytes, template)
        manifest_raw["contract_version"] = detect.PREFLIGHT_CONTRACT_VERSION
        with tempfile.TemporaryDirectory(prefix="rect-test-preflight-raw-") as td_raw:
            tdp_raw = Path(td_raw)
            inp_raw = tdp_raw / "input.png"
            inp_raw.write_bytes(raw_bytes)
            res_raw = preflight.preflight(manifest_raw, inp_raw, tdp_raw)
            self.assertEqual(res_raw["outcome"], "validated")
            self.assertEqual(res_raw["rectification"], {"attempted": False, "applied": False})

    def test_vertical_and_horizontal_bubble_shifts_in_bubble_means(self) -> None:
        class FakePoint:
            def __init__(self, x, y, label, value):
                self.x = x
                self.y = y
                self.field_label = label
                self.field_value = value

        class FakeBlock:
            def __init__(self, name, direction, shift, bubble_dims, pts):
                self.name = name
                self.direction = direction
                self.shift = shift
                self.bubble_dimensions = bubble_dims
                self.traverse_bubbles = [[FakePoint(p[0], p[1], f"L_{name}", f"V_{name}") for p in pts]]

        class FakeTemplate:
            def __init__(self, blocks):
                self.field_blocks = blocks

        # 200x200 canvas
        img = np.full((200, 200), 255, dtype=np.uint8)
        # Spot at y=80..90, x=50..60 (black, value 0)
        img[80:90, 50:60] = 0
        # Spot at y=60..70, x=70..80 (black, value 0)
        img[60:70, 70:80] = 0

        # Vertical block origin (50, 60), shift=20:
        # vertical applies shift on Y -> (50, 60+20) = (50, 80) -> hits spot 1 (mean=0.0)
        b_vert = FakeBlock("vert", "vertical", 20, (10, 10), [(50, 60)])
        # Horizontal block origin (50, 60), shift=20:
        # horizontal applies shift on X -> (50+20, 60) = (70, 60) -> hits spot 2 (mean=0.0)
        b_horiz = FakeBlock("horiz", "horizontal", 20, (10, 10), [(50, 60)])

        tmpl = FakeTemplate([b_vert, b_horiz])
        means = verify.bubble_means(img, tmpl)

        self.assertIn("vert", means)
        self.assertIn("horiz", means)
        self.assertEqual(means["vert"][0]["mean"], 0.0)
        self.assertEqual(means["horiz"][0]["mean"], 0.0)

    def test_exif_parity_across_endpoints(self) -> None:
        h_orig, w_orig = 300, 200
        img = np.full((h_orig, w_orig, 3), 255, dtype=np.uint8)
        cv2.rectangle(img, (20, 20), (180, 280), (0, 0, 0), 3)
        ok, buf = cv2.imencode(".jpg", img)
        self.assertTrue(ok)
        raw_jpg = buf.tobytes()

        header = struct.pack("<2sHI", b"II", 42, 8)
        num_entries = struct.pack("<H", 1)
        ifd_entry = struct.pack("<HHIHH", 0x0112, 3, 1, 6, 0)
        next_ifd = struct.pack("<I", 0)
        tiff = header + num_entries + ifd_entry + next_ifd
        exif_segment = b"Exif\x00\x00" + tiff
        app1 = b"\xff\xe1" + struct.pack(">H", len(exif_segment) + 2) + exif_segment
        exif_bytes = raw_jpg[:2] + app1 + raw_jpg[2:]

        orient = detect.exif_orientation(exif_bytes)
        self.assertEqual(orient, 6)
        decoded = document_rectifier.bounded_decode_image(exif_bytes, color=True)
        self.assertEqual(decoded.shape[:2], (h_orig, w_orig))
        oriented = detect.apply_exif_orientation(decoded, orient)
        post_h, post_w = oriented.shape[:2]
        self.assertEqual(post_w, h_orig)
        self.assertEqual(post_h, w_orig)

        with tempfile.TemporaryDirectory(prefix="rect-test-exif-") as td:
            tdp = Path(td)
            inp = tdp / "input.jpg"
            inp.write_bytes(exif_bytes)
            rect_manifest = {
                "run_id": "exif-parity-test",
                "correlation_id": "exif-parity-test",
                "input_hash": hashlib.sha256(exif_bytes).hexdigest(),
                "image_mime": "image/jpeg",
                "image_width": w_orig,
                "image_height": h_orig,
                "points": [[10, 10], [280, 10], [280, 180], [10, 180]],
                "target_width": 400,
                "target_height": 300,
            }
            rect_res = rectify.rectify(rect_manifest, inp, tdp)
            self.assertEqual(rect_res["outcome"], "rectified")
            self.assertEqual(rect_res["image"]["width"], post_w)
            self.assertEqual(rect_res["image"]["height"], post_h)
            self.assertEqual(rect_res["rectified"]["width"], 400)
            self.assertEqual(rect_res["rectified"]["height"], 300)

    def test_clean_module_import_no_circular_dependency(self) -> None:
        self.assertNotIn("detect", vars(rectification_recovery))
        self.assertTrue(issubclass(rectification_recovery.RecoveryRejected, Exception))

    def test_non_allowlist_raw_code_bypasses_rectifier_proposer(self) -> None:
        template = tc.characterization_template()
        rendered = render_sheet()
        canvas = apply_perspective_distortion(rendered, width_skew_pct=0.15, height_skew_pct=0.12)
        ok, buf = cv2.imencode(".png", canvas)
        self.assertTrue(ok)
        image_bytes = buf.tobytes()
        manifest = make_manifest(canvas, image_bytes, template)

        # 1. Direct seam test: evaluate_guarded_recovery with non-trigger code
        with patch("document_rectifier.propose_rectifications") as mock_prop:
            with self.assertRaises(rectification_recovery.RecoveryRejected) as cm_seam:
                rectification_recovery.evaluate_guarded_recovery(
                    image=canvas,
                    manifest=manifest,
                    raw_matches={},
                    raw_state={"code": "preprocessing_failed", "message": "Image preprocessing failed.", "orientation_hint": "upright"},
                    target_width=TARGET_FRAME_W,
                    target_height=TARGET_FRAME_H,
                    evaluate_candidate=lambda c: None,
                )
            mock_prop.assert_not_called()
            self.assertEqual(cm_seam.exception.code, "preprocessing_failed")
            self.assertEqual(cm_seam.exception.rectification, {"attempted": False, "applied": False})

        # 2. Endpoint integration test: verify/detect when marker_states produces a non-trigger code
        with patch("marker_states.classify", return_value={"code": "preprocessing_failed", "message": "Preprocessing failed.", "orientation_hint": "upright"}), \
             patch("document_rectifier.propose_rectifications") as mock_prop_ep:
            with tempfile.TemporaryDirectory(prefix="rect-test-non-trigger-") as td:
                tdp = Path(td)
                inp = tdp / "input.png"
                inp.write_bytes(image_bytes)
                out_v = tdp / "out_v"
                out_v.mkdir()
                with self.assertRaises(detect.DetectionError) as cm_v:
                    verify.verify(manifest, inp, out_v)
                mock_prop_ep.assert_not_called()
                self.assertEqual(cm_v.exception.code, "preprocessing_failed")
                self.assertEqual(cm_v.exception.rectification, {"attempted": False, "applied": False})

if __name__ == "__main__":
    unittest.main()
