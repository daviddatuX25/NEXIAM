"""Corner-pattern corpus must never be interpreted as registration success."""

import marker_states


def failed_log(global_peak: float = 0.2) -> str:
    return "\n".join(
        f"Error: No circle found in Quad {quad}\nmax_t 0.12 all_max_t {global_peak}"
        for quad in range(1, 5)
    )


def test_decorative_corners_and_imported_borders_fail_closed() -> None:
    cases = {
        "decorative_corners": (failed_log(0.18), False, "marker_missing"),
        "partial_marker_clipped": (failed_log(0.45), True, "marker_mismatch"),
        "border_like_pattern": (failed_log(0.22), True, "marker_mismatch"),
        "imported_supplier_border": (failed_log(0.28), True, "marker_mismatch"),
        "dark_corner_shadow": (failed_log(0.16), False, "marker_missing"),
    }
    for name, (engine_log, sheet_present, expected) in cases.items():
        result = marker_states.classify(engine_log, sheet_present)
        assert result["code"] == expected, name
        assert result["code"] not in {"succeeded", "validated"}, name
