"""Typed marker-failure classification for the worker (pure, no cv2).

Maps a failed `CropOnMarkers` chain to a typed preflight state instead of
letting a marker-bearing page degrade into the brittle Hough row-clustering
path. Evidence is conservative: a marker failure is NEVER classified as
success; uncertain geometry reports the most actionable typed state.

States (design §Registration and Capture Preflight):
- marker_missing:      no marker matched anywhere and no sheet-like quad present
- marker_mismatch:     wrong form / rotated sheet / inconsistent marker evidence
- sheet_partial:       some corners matched, others out of frame or covered
- preprocessing_failed: any other chain failure (never marker-specific)
"""

import re

MIN_MATCHING_THRESHOLD = 0.3
# Calibration datum (never a qualified threshold): the best scale's global
# match across the whole frame. Real marker matches score well above 0.4;
# texture/background noise can spuriously clear the per-quadrant 0.3 floor, so
# a weak global peak is treated as "no reliable marker evidence" instead of a
# partial sheet.
MIN_STRONG_MATCH = 0.4

QUAD_FAILURE_RE = re.compile(
    r"Error: No circle found in Quad\s*(\d)"
    r"[\s\S]*?max_t\s+([\d.]+)[\s\S]*?all_max_t\s+([\d.]+)",
    re.MULTILINE,
)
QUARTER_SCORE_RE = re.compile(r"Quarter(\d):\s*([\d.]+)")


def parse_quad_failures(engine_log: str) -> list[dict]:
    """Each failing quadrant logs its own line; extract (quadrant, max_t)."""
    failures = []
    for match in QUAD_FAILURE_RE.finditer(engine_log):
        failures.append(
            {
                "quadrant": int(match.group(1)),
                "max_t": float(match.group(2)),
                "all_max_t": float(match.group(3)),
            }
        )
    return failures


def parse_match_scores(engine_log: str) -> dict[str, float]:
    """Per-quadrant TM_CCOEFF_NORMED scores from the successful match log."""
    scores = {}
    for match in QUARTER_SCORE_RE.finditer(engine_log):
        scores[f"quarter_{match.group(1)}"] = float(match.group(2))
    return scores

def parse_raw_marker_telemetry(engine_log: str) -> dict[str, float | None]:
    """Extract all 4 quarter scores for forensic telemetry.

    Merges successful Quarter match scores with failing quadrant max_t values,
    ensuring all four canonical quarter keys ('quarter_1'..'quarter_4') are present,
    with None for any quadrant with no match evidence.
    """
    telemetry: dict[str, float | None] = {f"quarter_{i}": None for i in range(1, 5)}
    for match in QUARTER_SCORE_RE.finditer(engine_log):
        telemetry[f"quarter_{match.group(1)}"] = float(match.group(2))
    for entry in parse_quad_failures(engine_log):
        q = entry.get("quadrant")
        if q is not None and 1 <= q <= 4:
            key = f"quarter_{q}"
            if telemetry[key] is None:
                telemetry[key] = float(entry["max_t"])
    return telemetry

def classify(engine_log: str, sheet_present: bool) -> dict:
    """Conservative typed classification of a marker-chain failure.

    Returns {"code", "orientation_hint", "message"}.
    """
    failures = parse_quad_failures(engine_log)
    if not failures:
        # No quadrant failure logged: the chain broke elsewhere (or the log
        # was empty) — not a marker diagnosis.
        return {
            "code": "preprocessing_failed",
            "orientation_hint": "unknown",
            "message": "Image preprocessing failed before registration.",
        }

    failed = {entry["quadrant"] for entry in failures}
    passed = 4 - len(failed)

    # Weak global evidence: texture noise can clear the per-quadrant floor
    # spuriously. A genuine registration needs a strong peak somewhere.
    global_strength = max(entry["all_max_t"] for entry in failures)
    if global_strength < MIN_STRONG_MATCH:
        if sheet_present:
            return {
                "code": "marker_mismatch",
                "orientation_hint": "upright_required",
                "message": (
                    "No corner markers matched this answer sheet — the wrong "
                    "form may be photographed or the sheet is rotated. Hold "
                    "the sheet upright (portrait, header at the top) and retake."
                ),
            }
        return {
            "code": "marker_missing",
            "orientation_hint": "unknown",
            "message": (
                "No corner markers were detected in the photo. Hold the entire "
                "sheet inside the frame with even lighting and retake."
            ),
        }

    if passed <= 0:
        # Zero quadrants matched but the global peak is strong: inconsistent
        # marker evidence — wrong form or rotated sheet.
        return {
            "code": "marker_mismatch",
            "orientation_hint": "upright_required",
            "message": (
                "No corner markers matched this answer sheet — the wrong "
                "form may be photographed or the sheet is rotated. Hold "
                "the sheet upright (portrait, header at the top) and retake."
            ),
        }

    if passed == 3:
        return {
            "code": "sheet_partial",
            "orientation_hint": "reposition",
            "message": (
                "Three of four corner markers were found — part of the sheet "
                "is out of frame or covered. Move the sheet fully into the "
                "frame and retake."
            ),
        }

    if passed == 2:
        # Adjacent failed pair (top or bottom row) = the sheet slid out along
        # one edge (partial). Diagonal failed pair = consistent with a 180°
        # rotated sheet.
        if failed in ({1, 2}, {3, 4}):
            return {
                "code": "sheet_partial",
                "orientation_hint": "reposition",
                "message": (
                    "Two corner markers are missing — the sheet is partially "
                    "out of frame. Center the sheet and retake."
                ),
            }
        return {
            "code": "marker_mismatch",
            "orientation_hint": "rotated_180",
            "message": (
                "The matched corner markers are inconsistent — the sheet may "
                "be rotated 180°. Hold the sheet upright (header at the top) "
                "and retake."
            ),
        }

    # Single surviving quadrant: skewed/rotated sheet or almost entirely out
    # of frame. Fail closed with upright + reframe guidance.
    return {
        "code": "marker_mismatch",
        "orientation_hint": "upright_required",
        "message": (
            "Only one corner marker matched — the sheet is rotated or badly "
            "positioned. Hold the sheet upright, fill the frame, and retake."
        ),
    }
