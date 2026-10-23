import hashlib
import json
import struct
import tempfile
import unittest
from pathlib import Path

import cv2
import numpy as np

import detect
import rectify


class TestSourceRectification(unittest.TestCase):
    def test_staff_confirmed_quad_rectifies_to_requested_dimensions(self) -> None:
        with tempfile.TemporaryDirectory(prefix="rect-test-staff-") as td:
            tmp_path = Path(td)
            image = np.full((400, 300, 3), 255, dtype=np.uint8)
            cv2.rectangle(image, (20, 20), (280, 380), (0, 0, 0), 4)
            source = tmp_path / "source.png"
            cv2.imwrite(str(source), image)
            manifest = {
                "run_id": "rectify-test",
                "correlation_id": "rectify-test",
                "input_hash": hashlib.sha256(source.read_bytes()).hexdigest(),
                "image_mime": "image/png",
                "points": [[20, 20], [280, 20], [280, 380], [20, 380]],
                "target_width": 600,
                "target_height": 800,
            }

            result = rectify.rectify(manifest, source, tmp_path)
            self.assertEqual(result["outcome"], "rectified")
            self.assertEqual(result["rectified"], {"width": 600, "height": 800, "aspect_ratio": 0.75})
            decoded = cv2.imread(str(tmp_path / "rectified.png"))
            self.assertEqual(decoded.shape[:2], (800, 600))
            self.assertEqual(json.loads((tmp_path / "result.json").read_text())["run_id"], "rectify-test")

    def test_rectify_applies_exif_orientation_before_interpreting_points(self) -> None:
        with tempfile.TemporaryDirectory(prefix="rect-test-exif-") as td:
            tmp_path = Path(td)
            # Raw image: height 400, width 300
            raw_image = np.full((400, 300, 3), 255, dtype=np.uint8)
            cv2.rectangle(raw_image, (20, 20), (280, 380), (0, 0, 0), 4)
            ok, buf = cv2.imencode(".jpg", raw_image)
            self.assertTrue(ok)
            jpg_bytes = buf.tobytes()

            # Inject EXIF orientation 6 (ROTATE_90_CLOCKWISE)
            # Valid little-endian TIFF: 8-byte header, 2-byte count, 12-byte IFD entry, 4-byte next-IFD
            header = struct.pack("<2sHI", b"II", 42, 8)
            num_entries = struct.pack("<H", 1)
            ifd_entry = struct.pack("<HHIHH", 0x0112, 3, 1, 6, 0)
            next_ifd = struct.pack("<I", 0)
            tiff = header + num_entries + ifd_entry + next_ifd
            exif_segment = b"Exif\x00\x00" + tiff
            app1 = b"\xff\xe1" + struct.pack(">H", len(exif_segment) + 2) + exif_segment
            exif_jpg = jpg_bytes[:2] + app1 + jpg_bytes[2:]

            source = tmp_path / "source.jpg"
            source.write_bytes(exif_jpg)

            # Post-EXIF: height 300, width 400. Points up to x=360 are within bounds.
            manifest = {
                "run_id": "rectify-exif-test",
                "correlation_id": "rectify-exif-test",
                "input_hash": hashlib.sha256(exif_jpg).hexdigest(),
                "image_mime": "image/jpeg",
                "image_width": 300,
                "image_height": 400,
                "points": [[10, 10], [360, 10], [360, 260], [10, 260]],
                "target_width": 600,
                "target_height": 800,
            }

            out_dir = tmp_path / "out"
            out_dir.mkdir()
            result = rectify.rectify(manifest, source, out_dir)
            self.assertEqual(result["outcome"], "rectified")
            self.assertEqual(result["image"]["width"], 400)
            self.assertEqual(result["image"]["height"], 300)
            decoded = cv2.imread(str(out_dir / "rectified.png"))
            self.assertEqual(decoded.shape[:2], (800, 600))

    def test_crossed_corners_cannot_create_authoring_page(self) -> None:
        with tempfile.TemporaryDirectory(prefix="rect-test-crossed-") as td:
            tmp_path = Path(td)
            source = tmp_path / "source.png"
            cv2.imwrite(str(source), np.full((850, 600, 3), 255, dtype=np.uint8))
            manifest = {
                "run_id": "rectify-crossed",
                "correlation_id": "rectify-crossed",
                "input_hash": hashlib.sha256(source.read_bytes()).hexdigest(),
                "image_mime": "image/png",
                "points": [[30, 30], [570, 820], [570, 30], [30, 820]],
                "target_width": 600,
                "target_height": 850,
            }

            with self.assertRaises(detect.DetectionError) as caught:
                rectify.rectify(manifest, source, tmp_path)
            self.assertEqual(caught.exception.code, "quad_invalid")
            self.assertFalse((tmp_path / "rectified.png").exists())


if __name__ == "__main__":
    unittest.main()
