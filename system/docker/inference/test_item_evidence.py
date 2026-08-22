import numpy as np

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
