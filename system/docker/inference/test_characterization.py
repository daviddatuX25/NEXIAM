"""Pinned worker characterization (OpenSpec 36.3/36.8).

Runs the REAL worker (the same detect/verify seam the NEXIAM PHP envelope
calls) over deterministic marker-bearing sheets and the committed severity
fixtures. The characterization freezes:

  - exact answer-vector recovery on clean, perspective/skew, and mild variants;
  - typed severe marker failures with no false accepts;
  - Run 31 portrait/landscape dimensions and the app-owned quarter-turn order;
  - all-four marker, field-block shift, unsafe-layout, and option-set evidence;
  - deterministic aligned/overlay hashes at the existing CropOnMarkers seam.

The worker remains registration/alignment authority only. Hough/three-marker
fallbacks, manual ordinary-capture crop, arbitrary-angle search, external crop
providers, and threshold changes are explicitly out of this contract.

Usage (Compose inference image):
    docker run --rm -v "$PWD/docker/inference":/app -w /app --entrypoint python3 \
        nexiam-system-inference test_characterization.py
"""

import base64
import hashlib
import json
import os
import sys
import tempfile
from pathlib import Path
from types import SimpleNamespace

import math

from src.core import ImageInstanceOps
from src.template import FieldBlock
import cv2
import numpy as np

import detect
import fiducial
import generate_fixture
import test_marker_pipeline as t
import verify
import marker_states

FIXTURES = Path(__file__).resolve().parent / "fixtures" / "characterization"

PAPER = [2060, 3144]
INSET = 211
MARKER_SIZE = 121
FRAME = [PAPER[0] - 2 * INSET, PAPER[1] - 2 * INSET]
IDENTITY_PITCH = 46
SEED = 7
ORIENTATIONS = (0, 90, 180, 270)
RUN31_FIXTURES = ("run31-landscape", "run31-portrait")

# MCQ4 / MCQ5 / int declared option sets (A-D, A-E, 0-9).
MCQ4_OPTIONS = ["A", "B", "C", "D"]
MCQ5_OPTIONS = ["A", "B", "C", "D", "E"]
INT_OPTIONS = [str(value) for value in range(10)]

MILD_VARIANTS = ["perspective", "rotation", "uneven_light", "blur", "glare", "low_resolution"]
SEVERE_VARIANTS = [f"{variant}-severe" for variant in MILD_VARIANTS] + ["partial_page"]

# Pinned aligned/overlay SHA-256 for the clean sheet and every mild variant
# (observed once on the pinned image; engine output is deterministic).
PINNED_ALIGNED_HASHES = {
    "clean": "96957ec118715c86ee18d4fe5a09fc5f1164a200df5a40f6c2289c1d4c3a0009",
    "perspective": "9ce3bca04343a3e7a558a8f9486e7a10511e860a22c4a463319e0fa91be5f5ee",
    "rotation": "5d182ae2c6ad0ef59dd969b837fb989d5c2c20d3eaac965b29eeedbeb16adb6b",
    "uneven_light": "3a8ca6c5651fea8aff1d359d817ea164056d329da178c1118c156ec77ea73488",
    "blur": "9ae1f92e66492f456b6357c780bdbfba2e78101f0a2f2655689a66eb22a82f0f",
    "glare": "194374c4b037fbb0c8faa875ea13d8152d9bcce73133a3b715fc93b3236f17ae",
    "low_resolution": "6c4c8c22d95ffdda8d19624b748335beac1a32cc11768cdb09a3a06bdd713d23",
}
PINNED_OVERLAY_HASHES = {
    "clean": "86ea86da69ac9e90fc527ebc5e851be66efd9e46f8cc7c8c147729e96af55b40",
    "perspective": "8f21bf4de3ac0512c7d7fa13083fd9ce49c28cb3f336759b3251d91f89c08a6c",
    "rotation": "005f1c7724ab34f424495b8f4c27aedeef1e1bdb53f20bb06b092cbc278288f5",
    "uneven_light": "12926e7707f3a7c1b5d95e4fd41a9c6b463bed3e7813a9aa3d5148d11445722d",
    "blur": "9fecc188cc2f1742cc983f644b3345d5d61e406b108813625647f6f3d1801149",
    "glare": "5c6cf71d2fb046c6125e2ffc59db79ffc498d8f39dba7f5bfb22a9ce8e6d3d04",
    "low_resolution": "bd41d1210379808ffec8836aa1444ff6f950af4993aa51c0ad0ff29f6f3579bb",
}

# Deterministic per-fixture ambiguity sets (worker-level ambiguous rows on the
# committed sheet; the decided vector is always exactly the expected one).
PINNED_AMBIGUITY = {
    "clean": [],
    "perspective-mild": [],
    "rotation-mild": [],
    "uneven_light-mild": [
        "LOGIC_Q_10", "LOGIC_Q_11", "LOGIC_Q_12", "LOGIC_Q_13", "LOGIC_Q_14",
        "LOGIC_Q_15", "LOGIC_Q_16", "LOGIC_Q_17", "LOGIC_Q_18", "LOGIC_Q_19", "LOGIC_Q_2",
        "LOGIC_Q_20", "LOGIC_Q_3", "LOGIC_Q_4", "LOGIC_Q_5", "LOGIC_Q_6", "LOGIC_Q_7",
        "LOGIC_Q_8", "LOGIC_Q_9",
    ],
    "blur-mild": [],
    "glare-mild": [],
    "low_resolution-mild": [],
}

# Typed rejection codes observed per severe fixture (marker_/sheet_/image_
# families — never a success).
PINNED_SEVERE_CODES = {
    "perspective-severe": "ambiguous_rectification",
    "rotation-severe": "marker_mismatch",
    "uneven_light-severe": "sheet_partial",
    "blur-severe": "marker_missing",
    "glare-severe": "marker_missing",
    "low_resolution-severe": "marker_missing",
    "partial_page": "sheet_partial",
}


def characterization_template() -> dict:
    """Canonical-like single-page marker-bearing template.

    Mirror of the production marker-bearing shape: 300-PPI frame, bullseye
    fiducials, MCQ4 + MCQ5 response blocks and a 10-digit identity matrix,
    with item geometry derived from the same field-block declarations the
    PHP review manifest uses. Committed in this file: changing it changes
    the pinned fixtures and their expected vector.
    """
    return {
        "bubbleDimensions": [44, 44],
        "pageDimensions": FRAME,
        "customLabels": {},
        "preProcessors": [
            {
                "name": "CropOnMarkers",
                "options": {
                    "relativePath": "omr_marker.jpg",
                    "sheetToMarkerWidthRatio": 17,
                },
            }
        ],
        "fieldBlocks": {
            "ApplicantNumber": {
                "fieldType": "QTYPE_INT",
                "fieldLabels": ["APPLICANT_NUMBER_1..10"],
                "bubblesGap": IDENTITY_PITCH,
                "labelsGap": 56,
                "origin": [110, 110],
            },
            "LOGICPartA": {
                "fieldType": "QTYPE_MCQ4",
                "direction": "horizontal",
                "fieldLabels": ["LOGIC_Q_001..020"],
                "bubblesGap": 56,
                "labelsGap": 72,
                "origin": [700, 850],
            },
            "SpatialPartA": {
                "fieldType": "QTYPE_MCQ5",
                "direction": "horizontal",
                "fieldLabels": ["SPATIAL_Q_001..010"],
                "bubblesGap": 52,
                "labelsGap": 72,
                "origin": [110, 1200],
            },
        },
    }


def marker_metadata() -> dict:
    return {
        "family": "omrchecker.bullseye",
        "asset_name": "omr_marker.jpg",
        "size": MARKER_SIZE,
        "quiet_zone": 40,
        "inset": INSET,
        "paper": PAPER,
        "centers": [[INSET, INSET], [PAPER[0] - INSET, INSET], [INSET, PAPER[1] - INSET], [PAPER[0] - INSET, PAPER[1] - INSET]],
        "quadrants": [0, 1, 2, 3],
    }


def render_sheet() -> np.ndarray:
    """Deterministic machine-filled photo of the printed characterization sheet."""
    return generate_fixture.render(
        characterization_template(),
        seed=SEED,
        blank=False,
        margin_ratio=0.05,
        markers=marker_metadata(),
    )


def expanded_block_labels(block: dict) -> list[str]:
    labels: list[str] = []
    for declaration in block.get("fieldLabels", []):
        labels.extend(generate_fixture.expand_label(str(declaration)))
    return labels


def derive_review_manifest() -> dict:
    """Item/identity declarations derived from the template geometry.

    Mirrors the PHP review-manifest derivation: option regions are the
    template bubble rects normalized to the frame, declared option codes in
    display order, identity fields from the applicant-number block.
    """
    frame_width, frame_height = FRAME
    bubble_w, bubble_h = characterization_template()["bubbleDimensions"]
    fields: list[dict] = []
    items: list[dict] = []
    position = 0
    area_codes = {"LOGICPartA": "LOGIC", "SpatialPartA": "SPATIAL"}
    option_sets = {"LOGICPartA": MCQ4_OPTIONS, "SpatialPartA": MCQ5_OPTIONS}

    for block_name, block in characterization_template()["fieldBlocks"].items():
        if block_name == "ApplicantNumber":
            continue
        labels = expanded_block_labels(block)
        origin_x, origin_y = map(int, block["origin"])
        labels_gap = int(block["labelsGap"])
        bubbles_gap = int(block["bubblesGap"])
        area_code = area_codes[block_name]
        options = option_sets[block_name]
        for label_index, _label in enumerate(labels):
            position += 1
            question_no = label_index + 1
            region = {
                "x": round(origin_x / frame_width, 6),
                "y": round((origin_y + label_index * labels_gap) / frame_height, 6),
                "w": round(bubble_w / frame_width, 6),
                "h": round(bubble_h / frame_height, 6),
            }
            option_regions = []
            for option_index, code in enumerate(options):
                option_regions.append({
                    "code": code,
                    "display_order": option_index + 1,
                    "region": {
                        "x": round((origin_x + option_index * bubbles_gap) / frame_width, 6),
                        "y": round((origin_y + label_index * labels_gap) / frame_height, 6),
                        "w": round(bubble_w / frame_width, 6),
                        "h": round(bubble_h / frame_height, 6),
                    },
                })
            native_label = f"{area_code}_Q_{question_no}"
            fields.append({
                "label": native_label,
                "page": 1,
                "option": options[0],
                "region": region,
            })
            items.append({
                "position": position,
                "area_code": area_code,
                "question_no": question_no,
                "native_label": native_label,
                "region": region,
                "options": option_regions,
            })

    identity_fields = []
    identity_labels = expanded_block_labels(characterization_template()["fieldBlocks"]["ApplicantNumber"])
    identity_origin = characterization_template()["fieldBlocks"]["ApplicantNumber"]["origin"]
    identity_labels_gap = characterization_template()["fieldBlocks"]["ApplicantNumber"]["labelsGap"]
    for digit_index, label in enumerate(identity_labels):
        identity_fields.append({
            "code": "applicant_number",
            "native_label": label,
            "position": digit_index + 1,
            "region": {
                "x": round(int(identity_origin[0]) / frame_width, 6),
                "y": round((int(identity_origin[1]) + digit_index * int(identity_labels_gap)) / frame_height, 6),
                "w": round(bubble_w / frame_width, 6),
                "h": round(bubble_h / frame_height, 6),
            },
        })

    return {
        "manifest_version": "nexiam.omr.review-manifest.v1",
        "fields": fields,
        "items": items,
        "identity_fields": identity_fields,
    }


def expected_vector() -> dict[str, str]:
    """Deterministic marked vector the renderer shades for seed 7."""
    expected: dict[str, str] = {}
    template = characterization_template()
    blocks = {"LOGICPartA": MCQ4_OPTIONS, "SpatialPartA": MCQ5_OPTIONS}
    for block_name, options in blocks.items():
        for label_index, _label in enumerate(expanded_block_labels(template["fieldBlocks"][block_name])):
            chosen = generate_fixture.selected_index(block_name, label_index, options, SEED)
            area_code = {"LOGICPartA": "LOGIC", "SpatialPartA": "SPATIAL"}[block_name]
            expected[f"{area_code}_Q_{label_index + 1}"] = options[chosen]
    identity_labels = expanded_block_labels(template["fieldBlocks"]["ApplicantNumber"])
    for digit_index, label in enumerate(identity_labels):
        expected[label] = INT_OPTIONS[(digit_index + SEED) % 10]
    return expected


def manifest_for(image: np.ndarray, image_bytes: bytes) -> dict:
    """Detection manifest mirroring the NEXIAM PHP envelope."""
    height, width = image.shape[:2]
    template = characterization_template()
    review_manifest = derive_review_manifest()
    native_config = {
        "dimensions": {"display_width": FRAME[0], "display_height": FRAME[1], "processing_width": FRAME[0], "processing_height": FRAME[1]},
        "alignment_params": {"auto_align": True, "match_col": 5, "max_steps": 30, "stride": 8, "thickness": 2},
        "threshold_params": {"GAMMA_LOW": 0.9, "MIN_GAP": 30, "MIN_JUMP": 50, "PAGE_TYPE": "NORMAL", "GLOBAL_PAGE_THRESHOLD_WHITE": 200, "GLOBAL_PAGE_THRESHOLD_BLACK": 100},
        "outputs": {"show_image_level": 0, "save_image_level": 0, "save_detections": False},
    }
    config_bytes = json.dumps(native_config, separators=(",", ":"), ensure_ascii=False).encode()
    native_bundle_metadata = {
        "files": {
            "template.json": hashlib.sha256(json.dumps(template, separators=(",", ":")).encode()).hexdigest(),
            "config.json": hashlib.sha256(config_bytes).hexdigest(),
        },
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "bundle_version": "nexiam.omr.native-bundle.v1",
    }
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()

    return {
        "contract_version": detect.CONTRACT_VERSION,
        "registration_mode": "fiducial_anchored",
        "template_version_id": 1,
        "expected_page_no": 1,
        "source_image": {
            "width": width,
            "height": height,
            "orientation": 1,
            "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        },
        "run_id": "characterization",
        "correlation_id": "characterization",
        "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": width,
        "image_height": height,
        "native_bundle_hash": hashlib.sha256(canonical(native_bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical(review_manifest)).hexdigest(),
        "config_hash": hashlib.sha256(config_bytes).hexdigest(),
        "booklet_hash": hashlib.sha256(b"key").hexdigest(),
        "engine": {"name": "OMRChecker", "commit": os.environ.get("OMRCHECKER_COMMIT", ""), "patch_identity": os.environ.get("NEXIAM_PATCH_IDENTITY", "")},
        "native_bundle_metadata": native_bundle_metadata,
        "native_template": template,
        "native_config_json": config_bytes.decode("utf-8"),
        "review_manifest": review_manifest,
        "items": review_manifest["items"],
        "identity_fields": review_manifest["identity_fields"],
        "native_assets": {"omr_marker.jpg": base64.b64encode(fiducial.asset_bytes()).decode("ascii")},
    }


def marked_vector(result: dict) -> dict[str, str]:
    # Detection observations carry area_code/question_no (the PHP envelope
    # maps them back to expected labels); identity fields carry native_label.
    return {
        f"{item['area_code']}_Q_{item['question_no']}": str(item["selected_option"])
        for item in result["items"]
        if item.get("state") == "marked" and item.get("selected_option") is not None
    }


def expected_items() -> dict[str, str]:
    return {label: option for label, option in expected_vector().items() if not label.startswith("APPLICANT")}


def item_ambiguity(result: dict) -> list[str]:
    decided = marked_vector(result)
    return sorted(label for label in expected_items() if label not in decided)


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{name}: {detail}")
    print(f"ok - {name}")

def explicit_grid_points(
    field_count: int,
    value_count: int,
    origin: tuple[float, float],
    pitch_x: float,
    pitch_y: float,
    angle_degrees: float,
    bubble_size: int,
) -> list[list[list[int]]]:
    """Return a rigidly rotated field-label × value grid of bubble top-lefts."""
    angle = math.radians(angle_degrees)
    cosine, sine = math.cos(angle), math.sin(angle)
    pivot_x, pivot_y = origin[0] + bubble_size / 2, origin[1] + bubble_size / 2
    points = []
    for field_index in range(field_count):
        row = []
        for value_index in range(value_count):
            center_x = origin[0] + value_index * pitch_x + bubble_size / 2
            center_y = origin[1] + field_index * pitch_y + bubble_size / 2
            delta_x, delta_y = center_x - pivot_x, center_y - pivot_y
            x = pivot_x + cosine * delta_x - sine * delta_y - bubble_size / 2
            y = pivot_y + sine * delta_x + cosine * delta_y - bubble_size / 2
            row.append([round(x), round(y)])
        points.append(row)
    return points


def read_explicit_grids() -> tuple[dict, list[FieldBlock]]:
    """Run real pinned FieldBlock coordinates through OMRChecker classification."""
    page_dimensions = [600, 500]
    bubble_size = 12
    option_values = ["A", "B", "C", "D"]
    identity_values = [str(value) for value in range(10)]
    option_points = explicit_grid_points(
        1, len(option_values), (100, 340), 28, 0, 0, bubble_size
    )
    identity_points = explicit_grid_points(
        10, len(identity_values), (200, 80), 18, 18, 3, bubble_size
    )
    blocks = [
        FieldBlock(
            "TiltedQuestion",
            {
                "bubbleDimensions": [bubble_size, bubble_size],
                "bubbleValues": option_values,
                "bubblesGap": 28,
                "direction": "horizontal",
                "fieldLabels": ["TILTED_Q_1"],
                "fieldType": "QTYPE_MCQ4",
                "labelsGap": 24,
                "origin": [100, 340],
                "bubblePositions": option_points,
                "emptyValue": "",
            },
            page_dimensions,
        ),
        FieldBlock(
            "TiltedApplicantNumber",
            {
                "bubbleDimensions": [bubble_size, bubble_size],
                "bubbleValues": identity_values,
                "bubblesGap": 18,
                "direction": "vertical",
                "fieldLabels": [f"APPLICANT_NUMBER_{value + 1}" for value in range(10)],
                "fieldType": "QTYPE_INT",
                "labelsGap": 18,
                "origin": [200, 80],
                "bubblePositions": identity_points,
                "emptyValue": "",
            },
            page_dimensions,
        ),
    ]
    image = np.full((page_dimensions[1], page_dimensions[0]), 255, dtype=np.uint8)
    marks = [(option_points[0][2], "C")]
    for digit_index, row in enumerate(identity_points):
        digit = str((digit_index + 2) % 10)
        marks.append((row[int(digit)], digit))
    for (x, y), _value in marks:
        cv2.rectangle(image, (x + 2, y + 2), (x + bubble_size - 3, y + bubble_size - 3), 0, -1)

    class ThresholdParams(SimpleNamespace):
        def get(self, key: str):
            return getattr(self, key)

    config = SimpleNamespace(
        alignment_params=SimpleNamespace(auto_align=False),
        dimensions=SimpleNamespace(
            processing_width=page_dimensions[0],
            processing_height=page_dimensions[1],
            display_height=page_dimensions[1],
        ),
        outputs=SimpleNamespace(show_image_level=0, save_image_level=0, save_detections=False),
        threshold_params=ThresholdParams(
            PAGE_TYPE_FOR_THRESHOLD="white",
            MIN_JUMP=20,
            JUMP_DELTA=20,
            MIN_GAP=20,
            CONFIDENT_SURPLUS=10,
        ),
    )
    operations = ImageInstanceOps(config)
    template = type(
        "ExplicitPositionTemplate",
        (),
        {"field_blocks": blocks, "page_dimensions": page_dimensions},
    )()
    response, _overlay, _multi_marked, _multi_roll = operations.read_omr_response(
        template, image, "explicit-position"
    )
    return response, blocks


def check_explicit_position_recognition() -> None:
    response, blocks = read_explicit_grids()
    expected = {"TILTED_Q_1": "C"}
    expected.update({
        f"APPLICANT_NUMBER_{index + 1}": str((index + 2) % 10)
        for index in range(10)
    })
    check("explicit question and 0–9 grids classify their own coordinates", response == expected, response)
    check(
        "explicit blocks retain value order and encompass all bubble extents",
        blocks[0].traverse_bubbles[0][2].field_value == "C"
        and blocks[1].traverse_bubbles[9][9].field_value == "9"
        and all(
            block.dimensions[0] >= max(bubble.x for row in block.traverse_bubbles for bubble in row)
            - block.origin[0]
            + block.bubble_dimensions[0]
            and block.dimensions[1] >= max(bubble.y for row in block.traverse_bubbles for bubble in row)
            - block.origin[1]
            + block.bubble_dimensions[1]
            for block in blocks
        ),
        [block.dimensions for block in blocks],
    )
    try:
        FieldBlock(
            "Malformed",
            {
                "bubbleDimensions": [12, 12],
                "bubbleValues": ["A", "B"],
                "fieldLabels": ["ROW_1"],
                "fieldType": "QTYPE_MCQ4",
                "origin": [10, 10],
                "bubblePositions": [[[10, 10]]],
                "emptyValue": "",
            },
            [100, 100],
        )
    except ValueError:
        check("malformed explicit grid dimensions fail closed", True)
    else:
        check("malformed explicit grid dimensions fail closed", False)


def load_image(name: str) -> bytes:
    path = FIXTURES / f"{name}.png"
    if not path.exists():
        raise AssertionError(f"missing fixture {path} — regenerate from bin/omr-variant.php")
    return path.read_bytes()
def orientation_candidates(image_bytes: bytes) -> list[tuple[int, bytes, tuple[int, int]]]:
    """Mirror the app adapter's fixed candidate order without crop/detection."""
    decoded = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    if decoded is None or decoded.size == 0:
        raise AssertionError("orientation fixture did not decode")
    rotations = {
        0: None,
        90: cv2.ROTATE_90_CLOCKWISE,
        180: cv2.ROTATE_180,
        270: cv2.ROTATE_90_COUNTERCLOCKWISE,
    }
    candidates = []
    for orientation in ORIENTATIONS:
        rotated = decoded if rotations[orientation] is None else cv2.rotate(decoded, rotations[orientation])
        if orientation == 0:
            candidate_bytes = image_bytes
        else:
            ok, encoded = cv2.imencode(".png", rotated, [cv2.IMWRITE_PNG_COMPRESSION, 9])
            assert ok
            candidate_bytes = encoded.tobytes()
        candidates.append((orientation, candidate_bytes, (rotated.shape[1], rotated.shape[0])))
    return candidates


def run_detect_bytes(image_bytes: bytes, template: dict, output: Path) -> dict:
    decoded = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    if decoded is None or decoded.size == 0:
        raise AssertionError("worker fixture did not decode")
    path = output / f"input-{hashlib.sha256(image_bytes).hexdigest()}.png"
    path.write_bytes(image_bytes)
    return detect.detect(manifest_for(decoded, image_bytes), path, output)


def run_detect(name: str, image_bytes: bytes, out: Path) -> dict:
    path = FIXTURES / f"{name}.png"
    decoded = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    return detect.detect(manifest_for(decoded, image_bytes), path, out)


def run_verify(name: str, image_bytes: bytes, out: Path) -> dict:
    path = FIXTURES / f"{name}.png"
    decoded = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    return verify.verify(manifest_for(decoded, image_bytes), path, out)


def main() -> int:
    check_explicit_position_recognition()
    template = characterization_template()
    expected = expected_vector()
    check("expected vector covers every declared item and identity digit", len(expected) == 40, len(expected))
    preprocessors = template["preProcessors"]
    check("characterization uses only CropOnMarkers", [entry.get("name") for entry in preprocessors] == ["CropOnMarkers"], preprocessors)
    check(
        "characterization has no alternate crop authority",
        not any(
            str(entry.get("name", "")).lower() in {"croppage", "hough", "manualcrop", "externalcrop", "quadrectifier"}
            for entry in preprocessors
        ),
        preprocessors,
    )

    # 0. The committed clean fixture is byte-identical to the deterministic render.
    rendered = render_sheet()
    ok, encoded = cv2.imencode(".png", rendered)
    assert ok
    check("committed base fixture matches the deterministic render", load_image("base") == encoded.tobytes())

    with tempfile.TemporaryDirectory(prefix="nexiam-characterization-") as directory:
        out = Path(directory)

        # Run 31 fixtures pin the app-owned candidate order and preserve the
        # forensic input bytes while the worker receives one candidate at a time.
        run31_landscape = load_image("run31-landscape")
        run31_portrait = load_image("run31-portrait")
        landscape_image = cv2.imdecode(np.frombuffer(run31_landscape, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
        portrait_image = cv2.imdecode(np.frombuffer(run31_portrait, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
        check("Run 31 landscape fixture is wider than tall", landscape_image is not None and landscape_image.shape[1] > landscape_image.shape[0], landscape_image.shape if landscape_image is not None else None)
        check("Run 31 portrait fixture is taller than wide", portrait_image is not None and portrait_image.shape[0] > portrait_image.shape[1], portrait_image.shape if portrait_image is not None else None)
        run31_candidates = orientation_candidates(run31_landscape)
        check("Run 31 candidate order is exactly 0/90/180/270", [candidate[0] for candidate in run31_candidates] == list(ORIENTATIONS), [candidate[0] for candidate in run31_candidates])
        check(
            "Run 31 candidates swap dimensions only",
            [candidate[2] for candidate in run31_candidates]
            == [
                (landscape_image.shape[1], landscape_image.shape[0]),
                (landscape_image.shape[0], landscape_image.shape[1]),
                (landscape_image.shape[1], landscape_image.shape[0]),
                (landscape_image.shape[0], landscape_image.shape[1]),
            ],
            [candidate[2] for candidate in run31_candidates],
        )
        check("Run 31 zero-degree candidate retains original bytes", run31_candidates[0][1] == run31_landscape)
        check(
            "Run 31 quarter-turn candidate hashes are deterministic",
            [hashlib.sha256(candidate[1]).hexdigest() for candidate in run31_candidates]
            == [hashlib.sha256(candidate[1]).hexdigest() for candidate in orientation_candidates(run31_landscape)],
        )
        restored_portrait = cv2.imdecode(np.frombuffer(run31_candidates[3][1], dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
        check("Run 31 270-degree candidate restores portrait pixels", restored_portrait is not None and np.array_equal(restored_portrait, portrait_image))
        for orientation, candidate_bytes, dimensions in run31_candidates:
            candidate_image = cv2.imdecode(np.frombuffer(candidate_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
            candidate_manifest = manifest_for(candidate_image, candidate_bytes)
            check(
                f"Run 31 {orientation}-degree candidate is bound by its own input hash",
                candidate_manifest["input_hash"] == hashlib.sha256(candidate_bytes).hexdigest()
                and (candidate_manifest["image_width"], candidate_manifest["image_height"]) == dimensions,
                candidate_manifest,
            )

        # The portrait candidate is usable through the real marker path; the
        # landscape source remains a typed marker failure until the app adapter
        # supplies a quarter-turn candidate.
        portrait_result = run_detect("run31-portrait", run31_portrait, out)
        check("Run 31 portrait candidate succeeds", portrait_result["outcome"] == "succeeded", portrait_result.get("outcome"))
        check("Run 31 portrait candidate reports all four markers", list(portrait_result["provider_diagnostics"]["marker_matches"]) == ["quarter_1", "quarter_2", "quarter_3", "quarter_4"], portrait_result.get("provider_diagnostics"))
        try:
            run_detect("run31-landscape", run31_landscape, out)
            raise AssertionError("Run 31 landscape source must not register before quarter-turn normalization")
        except detect.DetectionError as error:
            check("Run 31 landscape source fails with a typed marker state", error.code in {"marker_missing", "marker_mismatch"}, error.code)

        # Local contrast, not OMRChecker's concatenated text, owns mark choice.
        local_item = {
            "options": [
                {"code": code, "region": {"x": index * 0.25, "y": 0.0, "w": 0.25, "h": 1.0}}
                for index, code in enumerate(("A", "B", "C", "D"))
            ],
        }
        locally_marked = np.full((100, 400), 220, dtype=np.uint8)
        cv2.circle(locally_marked, (50, 50), 20, 80, -1)
        check(
            "unique local mark wins independently of provider text",
            detect.locally_supported_option(locally_marked, local_item) == "A",
        )
        competing_marks = locally_marked.copy()
        cv2.circle(competing_marks, (150, 50), 20, 85, -1)
        check(
            "competing local marks remain ambiguous",
            detect.locally_supported_option(competing_marks, local_item) is None,
        )

        # 1. Clean sheet: exact answer-vector recovery on the real worker.
        base_bytes = load_image("base")
        base_result = run_detect("base", base_bytes, out)
        check("clean detection succeeds", base_result["outcome"] == "succeeded", base_result.get("outcome"))
        check("clean recovery is exact", marked_vector(base_result) == {k: v for k, v in expected_items().items() if k not in PINNED_AMBIGUITY["clean"]}, marked_vector(base_result))
        check("clean ambiguity set is pinned", item_ambiguity(base_result) == PINNED_AMBIGUITY["clean"], item_ambiguity(base_result))
        check("clean identity proposal is exact", base_result.get("applicant_number_proposal") == "7890123456", base_result.get("applicant_number_proposal"))
        base_matches = base_result["provider_diagnostics"]["marker_matches"]
        check("clean marker matches contain all four profile quadrants", list(base_matches) == ["quarter_1", "quarter_2", "quarter_3", "quarter_4"] and all(np.isfinite(float(score)) and float(score) >= marker_states.MIN_MATCHING_THRESHOLD for score in base_matches.values()), base_matches)
        base_shifts = base_result["field_block_shifts"]
        check(
            "clean fieldBlocks retain declared order and typed safe shifts",
            [entry.get("block") for entry in base_shifts] == list(template["fieldBlocks"])
            and all(isinstance(entry.get("x"), int) and isinstance(entry.get("y"), int) and entry["y"] == 0 for entry in base_shifts),
            base_shifts,
        )
        check("clean local support threshold remains pinned", base_result["provider_diagnostics"]["local_support_delta"] == detect.LOCAL_SUPPORT_DELTA == 10.0, base_result["provider_diagnostics"])

        # 2. Mild perturbations recover the exact vector with pinned ambiguity.
        for variant in MILD_VARIANTS:
            image_bytes = load_image(f"{variant}-mild")
            result = run_detect(f"{variant}-mild", image_bytes, out)
            check(f"mild {variant} detection succeeds", result["outcome"] == "succeeded", result.get("outcome"))
            check(f"mild {variant} recovery is exact", marked_vector(result) == {k: v for k, v in expected_items().items() if k not in PINNED_AMBIGUITY[f"{variant}-mild"]}, marked_vector(result))
            check(
                f"mild {variant} ambiguity set is pinned",
                item_ambiguity(result) == PINNED_AMBIGUITY[f"{variant}-mild"],
                item_ambiguity(result),
            )
            check(f"mild {variant} identity proposal is exact", result.get("applicant_number_proposal") == "7890123456", result.get("applicant_number_proposal"))

        # 3. Severe perturbations and partial page are typed rejections with
        #    the pinned code.
        for name in SEVERE_VARIANTS:
            image_bytes = load_image(name)
            try:
                run_detect(name, image_bytes, out)
                raise AssertionError(f"severe {name} must be a typed rejection")
            except detect.DetectionError as error:
                check(f"severe {name} typed as {PINNED_SEVERE_CODES[name]}", error.code == PINNED_SEVERE_CODES[name], error.code)

        # 4. Layout verification: aligned/overlay hashes are pinned and the
        #    item/ID evidence option cardinality matches the declared sets.
        verify_result = run_verify("base", base_bytes, out)
        check("layout verification succeeds", verify_result["outcome"] == "succeeded", verify_result.get("outcome"))
        aligned_hash = hashlib.sha256((out / "aligned.png").read_bytes()).hexdigest()
        overlay_hash = hashlib.sha256((out / "layout_overlay.png").read_bytes()).hexdigest()
        check("aligned hash is pinned", aligned_hash == PINNED_ALIGNED_HASHES["clean"], aligned_hash)
        check("overlay hash is pinned", overlay_hash == PINNED_OVERLAY_HASHES["clean"], overlay_hash)
        check("aligned and overlay are distinct", aligned_hash != overlay_hash)

        for variant in MILD_VARIANTS:
            image_bytes = load_image(f"{variant}-mild")
            verify_result = run_verify(f"{variant}-mild", image_bytes, out)
            check(f"mild {variant} layout verification succeeds", verify_result["outcome"] == "succeeded", verify_result.get("outcome"))
            aligned_hash = hashlib.sha256((out / "aligned.png").read_bytes()).hexdigest()
            overlay_hash = hashlib.sha256((out / "layout_overlay.png").read_bytes()).hexdigest()
            check(f"mild {variant} aligned hash is pinned", aligned_hash == PINNED_ALIGNED_HASHES[variant], aligned_hash)
            check(f"mild {variant} overlay hash is pinned", overlay_hash == PINNED_OVERLAY_HASHES[variant], overlay_hash)

        evidence = verify_result["item_evidence"]
        option_counts = {}
        for item in evidence:
            option_counts[item["label"]] = len(item["options"])
        check("MCQ4 item evidence exposes exactly 4 declared options", all(option_counts[label] == 4 for label in [f"LOGIC_Q_{n}" for n in range(1, 21)]), option_counts)
        check("MCQ5 item evidence exposes exactly 5 declared options", all(option_counts[label] == 5 for label in [f"SPATIAL_Q_{n}" for n in range(1, 11)]), option_counts)
        check("declared option codes are distinct in display order", verify_result["item_evidence"][0]["options"][0]["option"] == "A", verify_result["item_evidence"][0]["options"][0])

        # 5. Template mismatch at the worker seam is a typed rejection.
        scan_template = dict(template)
        scan_template["preProcessors"] = [{"name": "CropPage", "options": {"morphKernel": [5, 5]}}]
        mismatch_manifest = manifest_for(
            cv2.imdecode(np.frombuffer(base_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE),
            base_bytes,
        )
        mismatch_manifest["native_template"] = scan_template
        try:
            verify.verify(mismatch_manifest, FIXTURES / "base.png", out)
            raise AssertionError("expected marker_versions_only for a marker-less template")
        except detect.DetectionError as error:
            check("marker-less template rejected typed", error.code == "marker_versions_only", error.code)

        wrong_dims = dict(manifest_for(cv2.imdecode(np.frombuffer(base_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE), base_bytes))
        wrong_dims["image_width"] = wrong_dims["image_width"] + 1
        try:
            verify.verify(wrong_dims, FIXTURES / "base.png", out)
            raise AssertionError("expected image_metadata_mismatch for wrong dimensions")
        except detect.DetectionError as error:
            check("dimension mismatch rejected typed", error.code == "image_metadata_mismatch", error.code)

    print(json.dumps({"status": "ok", "expected_items": len(expected)}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
