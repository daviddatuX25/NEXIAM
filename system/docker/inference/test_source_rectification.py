import json
from pathlib import Path

import cv2
import numpy as np

import rectify


def test_staff_confirmed_quad_rectifies_to_requested_dimensions(tmp_path: Path) -> None:
    image = np.full((400, 300, 3), 255, dtype=np.uint8)
    cv2.rectangle(image, (20, 20), (280, 380), (0, 0, 0), 4)
    source = tmp_path / "source.png"
    cv2.imwrite(str(source), image)
    manifest = {
        "run_id": "rectify-test",
        "correlation_id": "rectify-test",
        "input_hash": __import__("hashlib").sha256(source.read_bytes()).hexdigest(),
        "image_mime": "image/png",
        "points": [[20, 20], [280, 20], [280, 380], [20, 380]],
        "target_width": 600,
        "target_height": 800,
    }

    result = rectify.rectify(manifest, source, tmp_path)
    assert result["outcome"] == "rectified"
    assert result["rectified"] == {"width": 600, "height": 800, "aspect_ratio": 0.75}
    decoded = cv2.imread(str(tmp_path / "rectified.png"))
    assert decoded.shape[:2] == (800, 600)
    assert json.loads((tmp_path / "result.json").read_text())["run_id"] == "rectify-test"
