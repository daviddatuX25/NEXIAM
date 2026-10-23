"""Focused v5 contract and single-warp document-bound inference checks."""

import hashlib
import json
import tempfile
from pathlib import Path
from unittest.mock import patch

import cv2
import numpy as np
import test_marker_pipeline as marker_fixture
import verify

import detect
import document_rectifier


def canonical(value: object) -> bytes:
    return json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()

def with_patch_identity(manifest: dict, patch_identity: str) -> dict:
    updated = json.loads(json.dumps(manifest))
    identity = dict(updated["engine"], patch_identity=patch_identity)
    updated["engine"] = identity
    updated["native_bundle_metadata"]["engine"] = identity
    updated["native_bundle_metadata"]["files"]["template.json"] = hashlib.sha256(
        canonical(updated["native_template"])
    ).hexdigest()
    updated["native_bundle_hash"] = hashlib.sha256(canonical(updated["native_bundle_metadata"])).hexdigest()
    return updated


def manifest_for() -> dict:
    native_template = marker_fixture.template_json()
    native_template["pageDimensions"] = [600, 600]
    native_template["preProcessors"] = []
    native_template["fieldBlocks"] = {
        "Questions": {
            **native_template["fieldBlocks"]["LOGICPartA"],
            "fieldLabels": ["ITEM_1"],
            "origin": [100, 220],
            "bubblesGap": 45,
            "labelsGap": 65,
        }
    }
    native_config = {
        "dimensions": {
            "display_width": 600,
            "display_height": 600,
            "processing_width": 600,
            "processing_height": 600,
        },
        "alignment_params": {"auto_align": True, "match_col": 5, "max_steps": 30, "stride": 8, "thickness": 2},
        "threshold_params": {
            "GAMMA_LOW": 0.9,
            "MIN_GAP": 30,
            "MIN_JUMP": 50,
            "PAGE_TYPE": "NORMAL",
            "GLOBAL_PAGE_THRESHOLD_WHITE": 200,
            "GLOBAL_PAGE_THRESHOLD_BLACK": 100,
        },
        "outputs": {"show_image_level": 0, "save_image_level": 0, "save_detections": False},
    }
    review_manifest = {}
    config_bytes = canonical(native_config)
    bundle_metadata = {
        "files": {
            "template.json": hashlib.sha256(canonical(native_template)).hexdigest(),
            "config.json": hashlib.sha256(config_bytes).hexdigest(),
        },
        "engine": {
            "name": detect.ENGINE_NAME,
            "commit": detect.ENGINE_COMMIT,
            "patch_identity": detect.PATCH_IDENTITY,
        },
        "bundle_version": "nexiam.omr.native-bundle.v1",
    }
    source_hash = "a" * 64
    return {
        "contract_version": detect.CONTRACT_VERSION,
        "registration_mode": "document_bound",
        "template_version_id": 7,
        "expected_page_no": 1,
        "source_image": {"width": 600, "height": 600, "orientation": 1, "input_hash": source_hash},
        "run_id": "doc-bound-test",
        "correlation_id": "doc-bound-test",
        "input_hash": source_hash,
        "image_mime": "image/png",
        "image_width": 600,
        "image_height": 600,
        "native_bundle_hash": hashlib.sha256(canonical(bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical(review_manifest)).hexdigest(),
        "config_hash": hashlib.sha256(config_bytes).hexdigest(),
        "engine": {
            "name": detect.ENGINE_NAME,
            "commit": detect.ENGINE_COMMIT,
            "patch_identity": detect.PATCH_IDENTITY,
        },
        "native_bundle_metadata": bundle_metadata,
        "native_template": native_template,
        "native_config_json": config_bytes.decode("utf-8"),
        "review_manifest": review_manifest,
        "items": [
            {
                "native_label": "ITEM_1",
                "region": {"x": 0.1, "y": 0.1, "w": 0.2, "h": 0.1},
                "options": [
                    {"code": code, "display_order": index + 1, "region": {"x": 0.1 + index * 0.05, "y": 0.1, "w": 0.04, "h": 0.08}}
                    for index, code in enumerate(("A", "B", "C", "D"))
                ],
                "position": 1,
                "area_code": "LOGIC",
                "question_no": 1,
            }
        ],
        "identity_fields": [
            {"native_label": "ID_1", "position": 1, "region": {"x": 0.7, "y": 0.7, "w": 0.1, "h": 0.1}}
        ],
        "native_assets": {},
    }


def content_image() -> np.ndarray:
    image = np.full((600, 600), 255, dtype=np.uint8)
    # Print edge-rich content in two spatially separated expected regions.
    for left, top, right, bottom in ((80, 80, 180, 130), (420, 420, 470, 470)):
        for y in range(top, bottom, 5):
            cv2.line(image, (left, y), (right, y), 0, 1)
        for x in range(left, right, 5):
            cv2.line(image, (x, top), (x, bottom), 0, 1)
    return image


def candidate_for(points: list[tuple[float, float]] | None = None, index: int = 0) -> document_rectifier.RectifiedCandidate:
    points = points or [(20, 20), (580, 20), (580, 580), (20, 580)]
    point_array = np.asarray(points, dtype=np.float32)
    matrix = document_rectifier.compute_homography_matrix(point_array, (600, 600))
    assert matrix is not None
    return document_rectifier.RectifiedCandidate(
        candidate_index=index,
        quad_original=points,
        quad_normalized=document_rectifier.normalize_quad(point_array, 600, 600),
        area_ratio=float(cv2.contourArea(point_array)) / (600 * 600),
        edge_score=0.8,
        orthogonality_score=0.9,
        aspect_ratio_score=0.95,
        cross_map_count=3,
        contrast_score=0.9,
        composite_score=0.9,
        homography_matrix=matrix.tolist(),
        homography_hash=document_rectifier.compute_homography_hash(matrix),
        warped_image=None,
        detection_map_source="test-candidate",
    )


def rectification_result(candidates: list[document_rectifier.RectifiedCandidate], status: str = "success", diagnostics: dict | None = None):
    return document_rectifier.RectificationResult(
        detector_version="test-rectifier",
        status=status,
        input_shape=(600, 600, 1),
        target_dimensions=(600, 600),
        candidates=candidates,
        diagnostics=diagnostics or {},
    )


def expect_error(code: str, manifest: dict, result: document_rectifier.RectificationResult, image: np.ndarray | None = None) -> detect.DetectionError:
    with patch.object(detect.document_rectifier, "propose_rectifications", return_value=result):
        try:
            detect.rectify_document_image(image if image is not None else content_image(), manifest, 600, 600)
        except detect.DetectionError as error:
            assert error.code == code, (error.code, str(error))
            return error
    raise AssertionError(f"expected {code}")



def document_capture(width: int, height: int, paper_boxes: list[tuple[int, int, int, int]]) -> np.ndarray:
    image = np.full((height, width, 3), 42, dtype=np.uint8)
    for left, top, right, bottom in paper_boxes:
        cv2.rectangle(image, (left, top), (right, bottom), (245, 245, 245), -1)
        first_left, first_right = left + 60, min(left + 180, right - 4)
        first_top, first_bottom = top + 85, min(top + 170, bottom - 4)
        for y in range(first_top, first_bottom, 8):
            cv2.line(image, (first_left, y), (first_right, y), (20, 20, 20), 1)
        for x in range(first_left, first_right, 8):
            cv2.line(image, (x, first_top), (x, first_bottom), (20, 20, 20), 1)

        second_left, second_right = left + 420, min(left + 480, right - 4)
        second_top, second_bottom = top + 595, min(top + 680, bottom - 4)
        for y in range(second_top, second_bottom, 8):
            cv2.line(image, (second_left, y), (second_right, y), (20, 20, 20), 1)
        for x in range(second_left, second_right, 8):
            cv2.line(image, (x, second_top), (x, second_bottom), (20, 20, 20), 1)
    return image


def expect_actual_error(
    code: str,
    manifest: dict,
    image: np.ndarray,
    target_width: int,
    target_height: int,
) -> detect.DetectionError:
    try:
        detect.rectify_document_image(image, manifest, target_width, target_height)
    except detect.DetectionError as error:
        assert error.code == code, (error.code, str(error))
        return error
    raise AssertionError(f"expected actual proposal path to reject with {code}")


def main() -> int:
    manifest = manifest_for()
    detect.validate_manifest(manifest)

    historical_bundle = with_patch_identity(manifest, detect.LEGACY_PATCH_IDENTITY)
    detect.validate_manifest(historical_bundle)

    explicit_template = json.loads(json.dumps(manifest))
    explicit_template["native_template"]["fieldBlocks"]["Questions"]["bubblePositions"] = [
        [[100, 220], [145, 220], [190, 220], [235, 220]]
    ]
    explicit_current_bundle = with_patch_identity(explicit_template, detect.CURRENT_PATCH_IDENTITY)
    detect.validate_manifest(explicit_current_bundle)

    explicit_legacy_bundle = with_patch_identity(explicit_template, detect.LEGACY_PATCH_IDENTITY)
    try:
        detect.validate_manifest(explicit_legacy_bundle)
        raise AssertionError("legacy patch identity accepted explicit bubble positions")
    except detect.DetectionError as error:
        assert error.code == "engine_mismatch"

    missing_mode = dict(manifest)
    missing_mode.pop("registration_mode")
    try:
        detect.validate_manifest(missing_mode)
        raise AssertionError("missing registration mode was accepted")
    except detect.DetectionError as error:
        assert error.code == "registration_mode_invalid"

    unknown_mode = dict(manifest, registration_mode="implicit_hough")
    try:
        detect.validate_manifest(unknown_mode)
        raise AssertionError("unknown registration mode was accepted")
    except detect.DetectionError as error:
        assert error.code == "registration_mode_invalid"

    invalid_identity = dict(manifest, template_version_id=0, candidate_target_id="candidate:" + "b" * 64)
    try:
        detect.validate_manifest(invalid_identity)
        raise AssertionError("ambiguous target identity was accepted")
    except detect.DetectionError as error:
        assert error.code == "target_identity_invalid"

    image = content_image()
    candidate = candidate_for()
    quad = [[x, y] for x, y in candidate.quad_normalized]
    manifest["client_quad"] = {
        "points": quad,
        "width": 600,
        "height": 600,
        "orientation": 1,
        "input_hash": manifest["input_hash"],
        "page_no": 1,
    }
    proposal = rectification_result([candidate])
    original_warp = cv2.warpPerspective
    with patch.object(detect.document_rectifier, "propose_rectifications", return_value=proposal) as propose:
        with patch.object(detect.cv2, "warpPerspective", wraps=original_warp) as warp:
            aligned, diagnostics = detect.rectify_document_image(image, manifest, 600, 600)
    assert aligned.shape == (600, 600)
    assert propose.call_args.kwargs["warp_candidates"] is False
    assert warp.call_count == 1
    assert np.array_equal(warp.call_args.args[0], image)
    assert diagnostics["homography_count"] == diagnostics["warp_count"] == 1
    assert diagnostics["document_gates"]["accepted"] is True
    assert diagnostics["content_gate"]["accepted"] is True
    assert diagnostics["client_quad"]["status"] == "accepted"
    assert diagnostics["client_quad"]["used_for_warp"] is False
    assert diagnostics["homography_hash"] == candidate.homography_hash

    stale_hint = dict(manifest)
    stale_hint["client_quad"] = {**manifest["client_quad"], "page_no": 2}
    with patch.object(detect.document_rectifier, "propose_rectifications", return_value=proposal):
        _, stale_diagnostics = detect.rectify_document_image(image, stale_hint, 600, 600)
    assert stale_diagnostics["client_quad"]["status"] == "rejected"
    assert stale_diagnostics["client_quad"]["reason"] == "binding_mismatch"

    ambiguous = rectification_result([candidate, candidate_for(index=1)])
    object_hint = dict(manifest)
    object_hint["client_quad"] = {
        **manifest["client_quad"],
        "points": [{"x": x, "y": y} for x, y in quad],
    }
    with patch.object(detect.document_rectifier, "propose_rectifications", return_value=proposal):
        _, object_hint_diagnostics = detect.rectify_document_image(image, object_hint, 600, 600)
    assert object_hint_diagnostics["client_quad"]["status"] == "rejected"
    assert object_hint_diagnostics["client_quad"]["reason"] == "malformed_points"
    error = expect_error("ambiguous_rectification", manifest, ambiguous)
    assert error.rectification["document_gates"]["accepted"] is False
    no_aspect_candidate = rectification_result([], "no_candidates", {"rejected_by_gate": {"aspect_ratio": 1}})
    expect_error("document_aspect_mismatch", manifest, no_aspect_candidate)

    blank_page = np.full((600, 600), 255, dtype=np.uint8)
    content_error = expect_error("document_content_missing", manifest, proposal, blank_page)
    assert content_error.rectification["applied"] is True
    assert content_error.rectification["content_gate"]["accepted"] is False
    assert content_error.rectification["document_gates"]["accepted"] is False

    actual_capture = document_capture(1000, 1200, [(200, 150, 799, 999)])
    actual_manifest = manifest_for()
    original_capture_hash = hashlib.sha256(actual_capture.tobytes()).hexdigest()
    actual_aligned, actual_rectification = detect.rectify_document_image(actual_capture, actual_manifest, 600, 850)
    assert actual_aligned.shape == (850, 600, 3)
    assert actual_rectification["applied"] is True
    assert actual_rectification["document_gates"]["accepted"] is True
    assert actual_rectification["content_gate"]["accepted"] is True
    assert actual_rectification["homography_count"] == actual_rectification["warp_count"] == 1
    assert actual_rectification["source_dimensions"] == [1000, 1200]
    assert hashlib.sha256(actual_capture.tobytes()).hexdigest() == original_capture_hash

    two_page_capture = document_capture(1300, 900, [(25, 25, 624, 874), (675, 25, 1274, 874)])
    ambiguous_error = expect_actual_error("ambiguous_rectification", manifest_for(), two_page_capture, 600, 850)
    assert ambiguous_error.rectification["plausible_candidate_count"] >= 2
    assert ambiguous_error.rectification["document_gates"]["accepted"] is False

    square_capture = document_capture(1000, 1000, [(180, 180, 819, 819)])
    aspect_error = expect_actual_error("document_aspect_mismatch", manifest_for(), square_capture, 600, 850)
    assert aspect_error.rectification["document_gates"]["accepted"] is False
    assert aspect_error.rectification["rectifier_diagnostics"]["rejected_by_gate"]["aspect_ratio"] > 0

    partial_capture = np.full((1000, 1000, 3), 42, dtype=np.uint8)
    cv2.rectangle(partial_capture, (175, -270), (824, 649), (245, 245, 245), -1)
    try:
        detect.rectify_document_image(partial_capture, manifest_for(), 600, 850)
        raise AssertionError("partial paper was accepted as a complete document")
    except detect.DetectionError as partial_error:
        assert partial_error.code in {"document_aspect_mismatch", "document_quad_not_found"}
        assert partial_error.rectification["document_gates"]["accepted"] is False

    raw_rotated_capture = cv2.rotate(actual_capture, cv2.ROTATE_90_COUNTERCLOCKWISE)
    orientation_manifest = manifest_for()
    orientation_manifest["source_image"] = {
        "width": int(raw_rotated_capture.shape[1]),
        "height": int(raw_rotated_capture.shape[0]),
        "orientation": 6,
        "input_hash": "b" * 64,
    }
    orientation_manifest["image_width"] = int(raw_rotated_capture.shape[1])
    orientation_manifest["image_height"] = int(raw_rotated_capture.shape[0])
    exif_oriented_capture = detect.apply_exif_orientation(raw_rotated_capture, 6)
    assert np.array_equal(exif_oriented_capture, actual_capture)
    _, orientation_rectification = detect.rectify_document_image(
        exif_oriented_capture,
        orientation_manifest,
        600,
        850,
    )
    assert orientation_rectification["document_gates"]["accepted"] is True
    assert orientation_rectification["source_dimensions"] == [1000, 1200]

    encoded_ok, encoded_image = cv2.imencode(".png", image)
    assert encoded_ok
    image_bytes = encoded_image.tobytes()
    source_hash = hashlib.sha256(image_bytes).hexdigest()
    manifest["input_hash"] = source_hash
    manifest["source_image"]["input_hash"] = source_hash
    manifest["image_width"] = manifest["source_image"]["width"]
    manifest["image_height"] = manifest["source_image"]["height"]
    manifest["client_quad"]["input_hash"] = source_hash
    with tempfile.TemporaryDirectory(prefix="document-bound-verify-") as directory:
        output = Path(directory)
        image_path = output / "input.png"
        image_path.write_bytes(image_bytes)
        events: list[str] = []
        warp_sources: list[np.ndarray] = []
        _, _, image_ops_class = detect.load_omrchecker()
        original_read = image_ops_class.read_omr_response

        def tracked_warp(source: np.ndarray, *args: object, **kwargs: object) -> np.ndarray:
            events.append("warp")
            warp_sources.append(source.copy())
            return original_warp(source, *args, **kwargs)

        def tracked_read(self: object, *args: object, **kwargs: object) -> object:
            events.append("omrchecker")
            return original_read(self, *args, **kwargs)

        with patch.object(detect.document_rectifier, "propose_rectifications", return_value=proposal):
            with patch.object(detect.cv2, "warpPerspective", side_effect=tracked_warp) as inference_warps:
                with patch.object(image_ops_class, "read_omr_response", new=tracked_read):
                    verification = verify.verify(manifest, image_path, output)
        assert inference_warps.call_count == 1
        assert events == ["warp", "omrchecker"]
        assert len(warp_sources) == 1 and np.array_equal(warp_sources[0], image)
        assert events.index("warp") < events.index("omrchecker")
        overlay_written = (output / "layout_overlay.png").is_file()
    assert verification["outcome"] == "succeeded"
    assert verification["registration_mode"] == "document_bound"
    assert verification["template_version_id"] == 7
    assert verification["source_image"]["input_hash"] == source_hash
    assert verification["rectification"]["homography_count"] == 1
    assert verification["rectification"]["warp_count"] == 1
    assert verification["rectification"]["document_gates"]["accepted"] is True
    assert "Questions" in verification["bubble_means"]
    assert overlay_written
    print(json.dumps({"status": "ok", "contract": detect.CONTRACT_VERSION}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
