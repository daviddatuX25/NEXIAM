import copy
import cv2
import numpy as np

import detect
from verify import item_evidence

def test_item_evidence_uses_declared_option_set_and_marks_threshold_unqualified() -> None:
    aligned = np.full((100, 100), 180, dtype=np.uint8)
    manifest = {
        "input_hash": "source",
        "native_bundle_hash": "bundle",
        "mapping_hash": "mapping",
        "items": [
            {
                "native_label": "Q_001",
                "page": 1,
                "region": {"x": 0.1, "y": 0.1, "w": 0.2, "h": 0.2},
                "options": [
                    {"code": "A", "display_order": 0, "region": {"x": 0.1, "y": 0.1, "w": 0.05, "h": 0.05}},
                    {"code": "B", "display_order": 1, "region": {"x": 0.16, "y": 0.1, "w": 0.05, "h": 0.05}},
                    {"code": "C", "display_order": 2, "region": {"x": 0.22, "y": 0.1, "w": 0.05, "h": 0.05}},
                    {"code": "D", "display_order": 3, "region": {"x": 0.28, "y": 0.1, "w": 0.05, "h": 0.05}},
                ],
            }
        ],
    }
    evidence = item_evidence(aligned, manifest, {"block": [{"label": "Q_001", "option": "A", "mean": 180.0}]})
    assert len(evidence) == 1
    assert [option["option"] for option in evidence[0]["options"]] == ["A", "B", "C", "D"]
    assert evidence[0]["options"][0]["threshold_status"] == "Unqualified"
    assert evidence[0]["options"][0]["threshold_delta"] == 20.0


def _create_synthetic_test_item() -> tuple[dict, list[tuple[int, int]], int, int]:
    width, height = 400, 200
    bubble_size = 24
    nominal_centers = [(100, 100), (150, 100), (200, 100), (250, 100)]
    options = []
    for idx, (cx, cy) in enumerate(nominal_centers):
        code = chr(ord("A") + idx)
        options.append({
            "code": code,
            "display_order": idx + 1,
            "region": {
                "x": (cx - bubble_size // 2) / width,
                "y": (cy - bubble_size // 2) / height,
                "w": bubble_size / width,
                "h": bubble_size / height,
            },
        })
    min_x = min(opt["region"]["x"] for opt in options)
    min_y = min(opt["region"]["y"] for opt in options)
    max_x = max(opt["region"]["x"] + opt["region"]["w"] for opt in options)
    max_y = max(opt["region"]["y"] + opt["region"]["h"] for opt in options)
    item = {
        "native_label": "Q_1",
        "page": 1,
        "region": {
            "x": min_x,
            "y": min_y,
            "w": max_x - min_x,
            "h": max_y - min_y,
        },
        "options": options,
    }
    return item, nominal_centers, width, height


def _render_synthetic_rings_image(
    width: int,
    height: int,
    centers: list[tuple[int, int]],
    fill_index: int | None = None,
    radius: int = 10,
    ring_thickness: int = 2,
) -> np.ndarray:
    canvas = np.full((height, width), 255, dtype=np.uint8)
    for idx, center in enumerate(centers):
        cv2.circle(canvas, center, radius, 0, ring_thickness, lineType=cv2.LINE_AA)
        if fill_index is not None and idx == fill_index:
            cv2.circle(canvas, center, max(2, radius - 4), 30, -1, lineType=cv2.LINE_AA)
    return canvas


def _assert_registration_invariants(
    original_items: list[dict],
    items_arg: list[dict],
    registered_items: list[dict],
    nominal_centers: list[tuple[int, int]],
    printed_centers: list[tuple[int, int]],
    width: int,
    height: int,
    expected_shift: tuple[int, int],
    fallback: bool = False,
) -> None:
    # Input item dictionaries must not be mutated
    assert items_arg == original_items, "input items must not be mutated"
    assert registered_items is not items_arg, "output should be a new list"
    assert len(registered_items) == len(items_arg)

    shift_x, shift_y = expected_shift

    for orig_item, reg_item in zip(original_items, registered_items):
        assert reg_item is not orig_item, "item dictionaries must be copies"
        assert reg_item["region"] is not orig_item["region"]
        assert reg_item["options"] is not orig_item["options"]

        # All option spacing and sizes must be preserved
        for orig_opt, reg_opt in zip(orig_item["options"], reg_item["options"]):
            assert reg_opt is not orig_opt
            assert reg_opt["region"] is not orig_opt["region"]
            assert abs(reg_opt["region"]["w"] - orig_opt["region"]["w"]) < 1e-6
            assert abs(reg_opt["region"]["h"] - orig_opt["region"]["h"]) < 1e-6

        for i in range(len(reg_item["options"]) - 1):
            orig_spacing_x = orig_item["options"][i + 1]["region"]["x"] - orig_item["options"][i]["region"]["x"]
            reg_spacing_x = reg_item["options"][i + 1]["region"]["x"] - reg_item["options"][i]["region"]["x"]
            assert abs(reg_spacing_x - orig_spacing_x) < 1e-6, "horizontal option spacing must be preserved"

            orig_spacing_y = orig_item["options"][i + 1]["region"]["y"] - orig_item["options"][i]["region"]["y"]
            reg_spacing_y = reg_item["options"][i + 1]["region"]["y"] - reg_item["options"][i]["region"]["y"]
            assert abs(reg_spacing_y - orig_spacing_y) < 1e-6, "vertical option spacing must be preserved"

        # Item region follows the translated option union
        opt_union_min_x = min(opt["region"]["x"] for opt in reg_item["options"])
        opt_union_min_y = min(opt["region"]["y"] for opt in reg_item["options"])
        opt_union_max_x = max(opt["region"]["x"] + opt["region"]["w"] for opt in reg_item["options"])
        opt_union_max_y = max(opt["region"]["y"] + opt["region"]["h"] for opt in reg_item["options"])

        assert abs(reg_item["region"]["x"] - opt_union_min_x) < 1e-6
        assert abs(reg_item["region"]["y"] - opt_union_min_y) < 1e-6
        assert abs((reg_item["region"]["x"] + reg_item["region"]["w"]) - opt_union_max_x) < 1e-6
        assert abs((reg_item["region"]["y"] + reg_item["region"]["h"]) - opt_union_max_y) < 1e-6

        # Recovered translation is within 1 pixel of expected shift
        for idx, (orig_opt, reg_opt) in enumerate(zip(orig_item["options"], reg_item["options"])):
            reg_center_x = (reg_opt["region"]["x"] + reg_opt["region"]["w"] / 2.0) * width
            reg_center_y = (reg_opt["region"]["y"] + reg_opt["region"]["h"] / 2.0) * height
            nom_center_x = (orig_opt["region"]["x"] + orig_opt["region"]["w"] / 2.0) * width
            nom_center_y = (orig_opt["region"]["y"] + orig_opt["region"]["h"] / 2.0) * height

            recovered_dx = reg_center_x - nom_center_x
            recovered_dy = reg_center_y - nom_center_y
            assert abs(recovered_dx - shift_x) <= 1.0, f"recovered dx {recovered_dx} outside 1px of shift {shift_x}"
            assert abs(recovered_dy - shift_y) <= 1.0, f"recovered dy {recovered_dy} outside 1px of shift {shift_y}"

            # Visible concentricity: registered overlay center aligns with printed bubble center
            target_center_x, target_center_y = printed_centers[idx]
            concentricity_dist = np.hypot(reg_center_x - target_center_x, reg_center_y - target_center_y)
            if not fallback:
                assert concentricity_dist <= 1.0, f"option {reg_opt['code']} not concentric with printed ring: dist={concentricity_dist}"
            else:
                # In fallback, registered center stays strictly at nominal center
                assert abs(reg_center_x - nom_center_x) < 1e-6
                assert abs(reg_center_y - nom_center_y) < 1e-6


def test_register_item_geometry_translated_row_with_filled_option() -> None:
    item, nominal_centers, width, height = _create_synthetic_test_item()
    shift_x, shift_y = 4, -3
    printed_centers = [(cx + shift_x, cy + shift_y) for cx, cy in nominal_centers]
    image = _render_synthetic_rings_image(width, height, printed_centers, fill_index=1)

    items = [item]
    original_items = copy.deepcopy(items)

    registered = detect.register_item_geometry(image, items)

    _assert_registration_invariants(
        original_items=original_items,
        items_arg=items,
        registered_items=registered,
        nominal_centers=nominal_centers,
        printed_centers=printed_centers,
        width=width,
        height=height,
        expected_shift=(shift_x, shift_y),
        fallback=False,
    )


def test_register_item_geometry_translated_row_all_blank() -> None:
    item, nominal_centers, width, height = _create_synthetic_test_item()
    shift_x, shift_y = -3, 5
    printed_centers = [(cx + shift_x, cy + shift_y) for cx, cy in nominal_centers]
    image = _render_synthetic_rings_image(width, height, printed_centers, fill_index=None)

    items = [item]
    original_items = copy.deepcopy(items)

    registered = detect.register_item_geometry(image, items)

    _assert_registration_invariants(
        original_items=original_items,
        items_arg=items,
        registered_items=registered,
        nominal_centers=nominal_centers,
        printed_centers=printed_centers,
        width=width,
        height=height,
        expected_shift=(shift_x, shift_y),
        fallback=False,
    )


def test_register_item_geometry_refines_nonuniform_option_centers() -> None:
    item, nominal_centers, width, height = _create_synthetic_test_item()
    printed_centers = [
        (nominal_centers[0][0] + 4, nominal_centers[0][1] - 3),
        (nominal_centers[1][0] + 3, nominal_centers[1][1] - 1),
        (nominal_centers[2][0] + 6, nominal_centers[2][1] - 4),
        (nominal_centers[3][0] + 2, nominal_centers[3][1] + 1),
    ]
    image = _render_synthetic_rings_image(
        width,
        height,
        printed_centers,
        fill_index=2,
    )

    registered = detect.register_item_geometry(image, [item])[0]

    for option, printed_center in zip(registered["options"], printed_centers):
        region = option["region"]
        center_x = (region["x"] + region["w"] / 2.0) * width
        center_y = (region["y"] + region["h"] / 2.0) * height
        assert np.hypot(
            center_x - printed_center[0],
            center_y - printed_center[1],
        ) <= 1.0


def test_register_item_geometry_weak_or_no_ring_fallback_retains_nominal() -> None:
    item, nominal_centers, width, height = _create_synthetic_test_item()
    # Blank canvas with no printed bubble rings: edge evidence is absent/weak
    image = np.full((height, width), 255, dtype=np.uint8)

    items = [item]
    original_items = copy.deepcopy(items)

    registered = detect.register_item_geometry(image, items)

    _assert_registration_invariants(
        original_items=original_items,
        items_arg=items,
        registered_items=registered,
        nominal_centers=nominal_centers,
        printed_centers=nominal_centers,
        width=width,
        height=height,
        expected_shift=(0, 0),
        fallback=True,
    )


def test_register_item_geometry_ignores_option_labels_above_circles() -> None:
    item, centers, width, height = _create_synthetic_test_item()
    centers = centers + [(300, 100), (350, 100)]
    for index, (center_x, center_y) in enumerate(centers[4:], start=4):
        item["options"].append({
            "code": chr(ord("A") + index),
            "display_order": index + 1,
            "region": {
                "x": (center_x - 12) / width,
                "y": (center_y - 12) / height,
                "w": 24 / width,
                "h": 24 / height,
            },
        })
    image = _render_synthetic_rings_image(width, height, centers)
    labels = ("a-b", "a-c", "a-d", "b-c", "b-d", "c-d")
    for center, label in zip(centers, labels):
        cv2.putText(
            image,
            label,
            (center[0] - 12, center[1] - 11),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.32,
            0,
            1,
            cv2.LINE_AA,
        )

    registered = detect.register_item_geometry(image, [item])[0]

    for option, (center_x, center_y) in zip(registered["options"], centers):
        region = option["region"]
        snapped_x = (region["x"] + region["w"] / 2) * width
        snapped_y = (region["y"] + region["h"] / 2) * height
        assert np.hypot(snapped_x - center_x, snapped_y - center_y) <= 1.0


def test_register_item_geometry_regularizes_column_drift_to_minus_18_plus_6() -> None:
    width, height = 400, 360
    base_item, _, _, _ = _create_synthetic_test_item()
    nominal_centers = [(100, 70), (150, 70), (200, 70), (250, 70)]
    items = []
    printed_centers = []
    for row_y in (70, 160, 250):
        item = copy.deepcopy(base_item)
        row_centers = [(x, row_y) for x, _ in nominal_centers]
        for option, (center_x, center_y) in zip(item["options"], row_centers):
            option["region"] = {
                "x": (center_x - 17.5) / width,
                "y": (center_y - 17.5) / height,
                "w": 35 / width,
                "h": 35 / height,
            }
        items.append(item)
        printed_centers.extend((x - 18, row_y + 6) for x, _ in nominal_centers)

    image = _render_synthetic_rings_image(width, height, printed_centers, radius=14)
    registered = detect.register_item_geometry(image, items)

    for item, row_y in zip(registered, (70, 160, 250)):
        for option, center_x in zip(item["options"], (100, 150, 200, 250)):
            region = option["region"]
            snapped_x = (region["x"] + region["w"] / 2) * width
            snapped_y = (region["y"] + region["h"] / 2) * height
            assert np.hypot(snapped_x - (center_x - 18), snapped_y - (row_y + 6)) <= 1.0


def test_register_item_geometry_missing_ring_inherits_sibling_shift() -> None:
    width, height = 400, 200
    centers = [(55 + index * 55, 100) for index in range(6)]
    options = [
        {
            "code": chr(ord("A") + index),
            "region": {
                "x": (center_x - 12) / width,
                "y": (center_y - 12) / height,
                "w": 24 / width,
                "h": 24 / height,
            },
        }
        for index, (center_x, center_y) in enumerate(centers)
    ]
    item = {
        "native_label": "Q_6",
        "region": {"x": 0.1, "y": 0.4, "w": 0.8, "h": 0.2},
        "options": options,
    }
    printed_centers = [(x + 5, y - 3) for x, y in centers]
    image = _render_synthetic_rings_image(width, height, printed_centers, radius=10)
    cv2.circle(image, printed_centers[2], 10, 255, -1)

    registered = detect.register_item_geometry(image, [item])[0]

    for option, (center_x, center_y) in zip(registered["options"], printed_centers):
        region = option["region"]
        snapped_x = (region["x"] + region["w"] / 2) * width
        snapped_y = (region["y"] + region["h"] / 2) * height
        assert np.hypot(snapped_x - center_x, snapped_y - center_y) <= 1.0


def test_register_item_geometry_competitive_constellation_claims_and_researches() -> None:
    width, height = 1200, 300
    row_x = [200, 300, 400, 500, 600, 700]

    def make_item(label: str, center_y: int) -> dict:
        options = []
        for index, center_x in enumerate(row_x):
            size = 48
            options.append({
                "code": chr(ord("A") + index),
                "display_order": index + 1,
                "region": {
                    "x": (center_x - size / 2) / width,
                    "y": (center_y - size / 2) / height,
                    "w": size / width,
                    "h": size / height,
                },
            })
        return {
            "native_label": label,
            "region": {"x": 0.1, "y": center_y / height, "w": 0.5, "h": 0.16},
            "options": options,
        }

    question_one = make_item("Q_1", 100)
    question_two = make_item("Q_2", 110)
    image = _render_synthetic_rings_image(
        width,
        height,
        [(center_x, 100) for center_x in row_x] + [(center_x, 150) for center_x in row_x],
        radius=20,
    )

    registered = detect.register_item_geometry(image, [question_one, question_two], search_radius_mm=12.0)

    for option, center_x in zip(registered[0]["options"], row_x):
        region = option["region"]
        snapped_x = (region["x"] + region["w"] / 2) * width
        snapped_y = (region["y"] + region["h"] / 2) * height
        assert np.hypot(snapped_x - center_x, snapped_y - 100) <= 1.0
    for option, center_x in zip(registered[1]["options"], row_x):
        region = option["region"]
        snapped_x = (region["x"] + region["w"] / 2) * width
        snapped_y = (region["y"] + region["h"] / 2) * height
        assert np.hypot(snapped_x - center_x, snapped_y - 150) <= 1.0


def test_register_item_geometry_preserves_nominal_shaded_s_d_rows_in_adjacent_columns() -> None:
    width, height = 1200, 360
    bubble_size = 42
    column_starts = (320, 410)
    row_y_values = (80, 145, 210)
    items = []
    printed_centers = []
    shaded_centers = []

    for column_index, column_x in enumerate(column_starts):
        for row_index, center_y in enumerate(row_y_values):
            centers = [(column_x, center_y), (column_x + 50, center_y)]
            options = [
                {
                    "code": code,
                    "display_order": option_index + 1,
                    "region": {
                        "x": (center_x - bubble_size / 2) / width,
                        "y": (center_y - bubble_size / 2) / height,
                        "w": bubble_size / width,
                        "h": bubble_size / height,
                    },
                }
                for option_index, (code, (center_x, center_y)) in enumerate(zip(("S", "D"), centers))
            ]
            items.append({
                "native_label": f"WORD_COMP_C{column_index}_Q{row_index}",
                "region": {
                    "x": (centers[0][0] - bubble_size / 2) / width,
                    "y": (center_y - bubble_size / 2) / height,
                    "w": (centers[1][0] - centers[0][0] + bubble_size) / width,
                    "h": bubble_size / height,
                },
                "options": options,
            })
            printed_centers.extend(centers)
            shaded_centers.append(centers[(row_index + column_index) % 2])

    assert column_starts[1] - column_starts[0] < 0.09 * width
    image = np.full((height, width), 255, dtype=np.uint8)
    for center in printed_centers:
        cv2.circle(image, center, 16, 0, 2, lineType=cv2.LINE_AA)
    for center in shaded_centers:
        cv2.circle(image, center, 10, 30, -1, lineType=cv2.LINE_AA)

    registered = detect.register_item_geometry(image, items)

    for original, aligned in zip(items, registered):
        for original_option, aligned_option in zip(original["options"], aligned["options"]):
            original_region = original_option["region"]
            aligned_region = aligned_option["region"]
            expected_x = (original_region["x"] + original_region["w"] / 2) * width
            expected_y = (original_region["y"] + original_region["h"] / 2) * height
            actual_x = (aligned_region["x"] + aligned_region["w"] / 2) * width
            actual_y = (aligned_region["y"] + aligned_region["h"] / 2) * height
            assert np.hypot(actual_x - expected_x, actual_y - expected_y) <= 0.01


def test_bright_unshaded_background_is_not_classified_by_relative_winner() -> None:
    width, height = 200, 100
    image = np.full((height, width), 230, dtype=np.uint8)
    cv2.circle(image, (70, 50), 10, 219, -1)
    cv2.circle(image, (130, 50), 10, 230, -1)
    item = {
        "_geometry_registered": True,
        "options": [
            {"code": code, "region": {"x": (center_x - 20) / width, "y": 0.3, "w": 40 / width, "h": 40 / height}}
            for code, center_x in (("A", 70), ("B", 130))
        ],
    }

    evidence = detect.option_mark_evidence(image, item)

    assert evidence["A"]["mean"] < evidence["B"]["mean"]
    assert evidence["A"]["median"] < evidence["B"]["median"]
    assert detect.locally_supported_option(image, item) is None
    assert detect.classify_item_options(item, evidence, 200.0) == (
        "unreadable", None, ["A"], ["candidate_evidence_incomplete"]
    )


def test_faint_candidate_is_retained_beside_a_definite_mark() -> None:
    item = {
        "_geometry_registered": True,
        "options": [{"code": code} for code in ("A", "B", "C", "D")],
    }
    evidence = {code: {"mean": 255.0, "median": 255.0} for code in ("A", "B", "C", "D")}
    evidence["A"] = {"mean": 220.0, "median": 220.0}
    assert detect.classify_item_options(item, evidence, 200.0) == (
        "unreadable", None, ["A"], ["candidate_evidence_incomplete"]
    )
    evidence["D"] = {"mean": 100.0, "median": 100.0}
    assert detect.classify_item_options(item, evidence, 200.0) == (
        "ambiguous", None, ["A", "D"], ["ambiguous_mark"]
    )
    evidence["A"] = {"mean": 245.0, "median": 245.0}
    assert detect.classify_item_options(item, evidence, 200.0) == ("marked", "D", ["D"], [])
    evidence["D"] = {"mean": 200.0, "median": 199.0}
    assert detect.classify_item_options(item, evidence, 200.0) == (
        "unreadable", None, ["D"], ["candidate_evidence_incomplete"]
    )
    evidence["D"] = {"mean": 245.0, "median": 245.0}
    assert detect.classify_item_options(item, evidence, 200.0) == ("blank", None, [], [])


def test_apply_registered_items_updates_template_bubbles_and_resets_shift() -> None:
    class Point:
        def __init__(self, x: int, y: int, label: str, value: str) -> None:
            self.x = x
            self.y = y
            self.field_label = label
            self.field_value = value

    class Block:
        def __init__(self) -> None:
            self.bubble_dimensions = (24, 24)
            self.shift = 9
            self.traverse_bubbles = [[Point(1, 2, "Q_1", code) for code in ("A", "B", "C", "D")]]

    class Template:
        def __init__(self) -> None:
            self.field_blocks = [Block()]

    item, centers, width, height = _create_synthetic_test_item()
    registered = copy.deepcopy([item])
    for option, (center_x, center_y) in zip(registered[0]["options"], centers):
        option["region"]["x"] = (center_x - 12 + 4) / width
        option["region"]["y"] = (center_y - 12 - 3) / height
    template = Template()

    detect.apply_registered_items_to_template(template, registered, width, height)

    points = template.field_blocks[0].traverse_bubbles[0]
    for point, (center_x, center_y) in zip(points, centers):
        assert (point.x, point.y) == (center_x - 12 + 4, center_y - 12 - 3)
    assert template.field_blocks[0].shift == 0
