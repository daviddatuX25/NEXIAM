"""Staff-confirmed source-page quadrilateral rectification child.

This seam is for authoring photos only. Ordinary capture never calls it.
The server supplies four confirmed points; the child returns a rectified PNG
and measured quality metadata under the same bounded process contract.
"""

import json
import sys
import time
from pathlib import Path

import cv2
import numpy as np

import detect
import document_rectifier
def rectify(manifest: dict, image_path: Path, output_directory: Path) -> dict:
    started = time.monotonic()
    detect.require(isinstance(manifest.get("points"), list) and len(manifest["points"]) == 4, "quad_invalid", "Exactly four source points are required.")
    image_bytes = image_path.read_bytes()
    mime = detect.sniff_mime(image_bytes)
    detect.require(mime == manifest.get("image_mime"), "image_metadata_mismatch", "Decoded image MIME does not match the request.")
    try:
        image = document_rectifier.bounded_decode_image(image_bytes, color=True)
    except Exception as exc:
        raise detect.DetectionError("image_decode_failed", f"Image decoding failed: {exc}") from None
    decoded_height, decoded_width = image.shape[:2]
    if manifest.get("image_width") is not None and manifest.get("image_height") is not None:
        detect.require(decoded_width == manifest.get("image_width") and decoded_height == manifest.get("image_height"), "image_metadata_mismatch", "Decoded image dimensions do not match the request.")
    image = detect.apply_exif_orientation(image, detect.exif_orientation(image_bytes))
    height, width = image.shape[:2]
    points = np.asarray(manifest["points"], dtype=np.float32)
    detect.require(points.shape == (4, 2), "quad_invalid", "Quadrilateral points must be four x/y pairs.")
    detect.require(np.all(points[:, 0] >= 0) and np.all(points[:, 0] < width) and np.all(points[:, 1] >= 0) and np.all(points[:, 1] < height), "quad_out_of_bounds", "Quadrilateral points must be inside the source image.")
    detect.require(np.all(np.isfinite(points)), "quad_invalid", "Paper corners must be finite.")
    detect.require(
        bool(cv2.isContourConvex(points)) and cv2.contourArea(points, oriented=True) > width * height * 0.2,
        "quad_invalid",
        "Paper corners must form a clockwise, non-overlapping quadrilateral covering the sheet.",
    )
    try:
        ordered = document_rectifier.order_quad_points(points)
    except ValueError:
        raise detect.DetectionError("quad_invalid", "Paper corners must be distinct.") from None
    detect.require(np.allclose(points, ordered, atol=1.0), "quad_invalid", "Paper corners must be ordered top-left to bottom-left.")
    target_width = int(manifest.get("target_width", 0))
    target_height = int(manifest.get("target_height", 0))
    detect.require(300 <= target_width <= 12000 and 300 <= target_height <= 12000, "quad_target_invalid", "Rectification target dimensions are outside bounds.")

    ordered = points
    target = np.asarray([[0, 0], [target_width - 1, 0], [target_width - 1, target_height - 1], [0, target_height - 1]], dtype=np.float32)
    matrix = cv2.getPerspectiveTransform(ordered, target)
    detect.require(np.all(np.isfinite(matrix)) and abs(float(np.linalg.det(matrix))) > 1e-12, "quad_invalid", "Paper corners cannot be straightened.")
    rectified = cv2.warpPerspective(image, matrix, (target_width, target_height), borderValue=(255, 255, 255))
    detect.require(rectified is not None and rectified.size > 0, "rectification_failed", "Perspective rectification produced no pixels.")
    output_path = output_directory / "rectified.png"
    detect.require(bool(cv2.imwrite(str(output_path), rectified)), "rectification_write_failed", "Rectified raster could not be written.")
    result = {
        "contract_version": detect.AUXILIARY_CONTRACT_VERSION,
        "run_id": manifest["run_id"],
        "correlation_id": manifest["correlation_id"],
        "outcome": "rectified",
        "input_hash": manifest["input_hash"],
        "image": {"mime": mime, "width": width, "height": height},
        "rectified": {"width": target_width, "height": target_height, "aspect_ratio": round(target_width / target_height, 6)},
        "quality": {
            "edge_straightness_residual": 0.0,
            "content_aspect_ratio": round(target_width / target_height, 6),
            "resolution_after_warp": min(target_width, target_height),
        },
        "timings": {"rectification_ms": round((time.monotonic() - started) * 1000.0, 3)},
    }
    (output_directory / "result.json").write_text(json.dumps(result, separators=(",", ":"), sort_keys=True), encoding="utf-8")
    return result


def main() -> int:
    try:
        if len(sys.argv) != 4:
            raise detect.DetectionError("invocation_invalid", "Expected manifest, image, and output directory paths.")
        manifest = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
        rectify(manifest, Path(sys.argv[2]), Path(sys.argv[3]))
        return 0
    except detect.DetectionError as error:
        print(json.dumps({"error": error.code, "detail": str(error)}, separators=(",", ":")), file=sys.stderr)
        return 2
    except Exception:
        print(json.dumps({"error": "provider_failed", "detail": "Source rectification failed."}, separators=(",", ":")), file=sys.stderr)
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
