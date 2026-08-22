"""Alignment-fiducial asset provider (Python mirror of OmrFiducial.php).

The marker contract is OMRChecker's OWN pinned bullseye asset
(`samples/sample5/omr_marker.jpg`): a plain concentric-bullseye correlation
fiducial matched by CropOnMarkers at the four frame corners. This module
ships the byte-faithful copy (sha256
61d39aab56d6c5ead514c0769528af56ca7f2b849ca26ebd6e9346f4ce704059) for
fixture generation; the printed sheet, the bundle asset, and the engine
match all use the same bytes.

The bullseye is an ALIGNMENT fiducial, NOT a template ID: it carries no
version/form identity and wrong-form registration is not detected by it.
"""

import hashlib
from pathlib import Path

ASSET_NAME = "omr_marker.jpg"
SHEET_TO_MARKER_WIDTH_RATIO = 17
ASSET_PATH = Path(__file__).with_name(ASSET_NAME)


def asset_bytes() -> bytes:
    if not ASSET_PATH.is_file():
        raise FileNotFoundError(f"The pinned OMRChecker marker asset is missing: {ASSET_PATH}")
    return ASSET_PATH.read_bytes()


def asset_hash() -> str:
    return hashlib.sha256(asset_bytes()).hexdigest()
