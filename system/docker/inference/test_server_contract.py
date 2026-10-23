"""Server-level worker envelope characterization (OpenSpec 36.3/36.8).

Boots the REAL worker BaseHTTPRequestHandler on a local port and POSTs
hand-built multipart requests through the same path the app adapter uses:
strict request HMAC verification, bounded headers/parts, typed child errors,
and a successful layout-verification response whose result/aligned/overlay
hashes are covered by the response HMAC.

Usage:  python3 test_server_contract.py   (from /app in the inference image)
"""

import hashlib
import http.client
import json
import os
import threading
import time
import urllib.error
import urllib.request
from email.parser import BytesParser
from email.policy import default
from pathlib import Path

import cv2
import numpy as np

# The module reads the signing secret + engine identity at import time; the
# test uses a deterministic secret so signatures are reproducible.
os.environ["OMR_WORKER_SIGNING_SECRET"] = "server-contract-test-secret-0123456789abcdef"
os.environ.setdefault("OMRCHECKER_COMMIT", "de3f6982822895491be760325fd43e65e90fa293")
os.environ.setdefault("NEXIAM_PATCH_IDENTITY", "nexiam-explicit-bubble-positions-v1")

import server  # noqa: E402

CONTRACT = "nexiam.omr.detection.v5"
AUXILIARY_CONTRACT = "nexiam.omr.detection.v3"
PREFLIGHT_CONTRACT = "nexiam.omr.detection.v4"
BOUNDARY = "contract-test-boundary"
RUN_ID = "contract-test-run"


def check(name: str, condition: bool, detail: str = "") -> None:
    if not condition:
        raise AssertionError(f"{name}: {detail}")
    print(f"ok - {name}")


def multipart_body(parts: list[tuple[str, bytes, str, str | None]]) -> bytes:
    body = b""
    for name, content, ctype, filename in parts:
        body += f"--{BOUNDARY}\r\n".encode()
        disposition = f'Content-Disposition: form-data; name="{name}"'
        if filename is not None:
            disposition += f'; filename="{filename}"'
        body += disposition.encode() + f"\r\nContent-Type: {ctype}\r\n\r\n".encode() + content + b"\r\n"
    body += f"--{BOUNDARY}--\r\n".encode()
    return body


def signed_request(
    path: str,
    manifest_bytes: bytes,
    image_bytes: bytes,
    secret: bytes,
    tamper: bool = False,
    contract: str = CONTRACT,
):
    timestamp = str(int(time.time()))
    manifest_hash = hashlib.sha256(manifest_bytes).hexdigest()
    image_hash = hashlib.sha256(image_bytes).hexdigest()
    payload = server.canonical_request_signature(path, timestamp, RUN_ID, manifest_hash, image_hash)
    signature = server.sign(payload)
    if tamper:
        signature = "0" * 64
    body = multipart_body([
        ("manifest", manifest_bytes, "application/json", "manifest.json"),
        ("image", image_bytes, "image/png", "sheet"),
    ])
    return body, {
        "Content-Type": f"multipart/form-data; boundary={BOUNDARY}",
        "X-Nexiam-Contract": contract,
        "X-Nexiam-Run-Id": RUN_ID,
        "X-Nexiam-Timestamp": timestamp,
        "X-Nexiam-Signature": signature,
    }

def worker_manifest(image_bytes: bytes) -> bytes:
    image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_GRAYSCALE)
    if image is None or image.size == 0:
        raise AssertionError("worker contract fixture did not decode")
    manifest = __import__("test_marker_pipeline").manifest_for(
        image,
        image_bytes,
        __import__("test_marker_pipeline").template_json(),
    )
    manifest["run_id"] = RUN_ID
    manifest["correlation_id"] = "server-contract"
    return json.dumps(manifest, separators=(",", ":"), ensure_ascii=False).encode()


def post_raw(path: str, body: bytes, headers: dict) -> tuple[int, dict, bytes]:
    request = urllib.request.Request(f"http://127.0.0.1:{PORT}{path}", data=body, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status, dict(response.headers.items()), response.read()
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers.items()), error.read()


def response_parts(content_type: str, body: bytes) -> dict[str, tuple[str, bytes]]:
    message = BytesParser(policy=default).parsebytes(
        b"Content-Type: " + content_type.encode("ascii", "strict") + b"\r\nMIME-Version: 1.0\r\n\r\n" + body
    )
    if not message.is_multipart():
        raise AssertionError("worker response was not multipart")
    parts: dict[str, tuple[str, bytes]] = {}
    for part in message.iter_parts():
        name = part.get_param("name", header="content-disposition")
        payload = part.get_payload(decode=True)
        if not isinstance(name, str) or not isinstance(payload, bytes) or name in parts:
            raise AssertionError("worker response multipart parts are malformed")
        parts[name] = (part.get_content_type(), payload)
    return parts


def check_response_signature(headers: dict, run_id: str, parts: dict[str, tuple[str, bytes]]) -> None:
    result = parts["result"][1]
    aligned = parts["aligned_image"][1]
    overlay = parts["layout_overlay"][1]
    result_hash = hashlib.sha256(result).hexdigest()
    aligned_hash = hashlib.sha256(aligned).hexdigest()
    overlay_hash = hashlib.sha256(overlay).hexdigest()
    check("response result hash is bound", headers.get("X-Nexiam-Result-SHA256") == result_hash, headers)
    check("response aligned hash is bound", headers.get("X-Nexiam-Aligned-SHA256") == aligned_hash, headers)
    check("response overlay hash is bound", headers.get("X-Nexiam-Layout-Overlay-SHA256") == overlay_hash, headers)
    timestamp = headers.get("X-Nexiam-Timestamp", "")
    expected = server.sign(server.canonical_verification_response_signature(timestamp, run_id, result_hash, aligned_hash, overlay_hash))
    check("response HMAC covers result/aligned/overlay hashes", headers.get("X-Nexiam-Signature") == expected, headers)


def post(path: str, body: bytes, headers: dict) -> tuple[int, dict]:
    request = urllib.request.Request(f"http://127.0.0.1:{PORT}{path}", data=body, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return response.status, json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        payload = error.read().decode("utf-8")
        try:
            return error.code, json.loads(payload)
        except json.JSONDecodeError:
            return error.code, {"raw": payload}


def main() -> int:
    server.READY = True
    server.SIGNING_SECRET = os.environ["OMR_WORKER_SIGNING_SECRET"].encode()
    orientation_cases = [
        ("upright", [[0, 0], [100, 0], [100, 100], [0, 100]], 0),
        ("clockwise 90", [[100, 0], [100, 100], [0, 100], [0, 0]], 270),
        ("clockwise 180", [[100, 100], [0, 100], [0, 0], [100, 0]], 180),
        ("clockwise 270", [[0, 100], [0, 0], [100, 0], [100, 100]], 90),
    ]
    for label, points, expected_correction in orientation_cases:
        orientation = server.qr_orientation(points)
        check(
            f"QR {label} maps to correction turn",
            orientation["correction_quarter_turn"] == expected_correction,
            str(orientation),
        )
    uncertain = server.qr_orientation([[0, 0], [100, 58], [42, 158], [-58, 100]])
    check(
        "QR geometry outside residual bound is not trusted",
        uncertain["correction_quarter_turn"] is None
        and uncertain["quarter_turn_residual_degrees"] > 20,
        str(uncertain),
    )
    for label, points in [
        ("duplicate corners", [[0, 0], [100, 0], [100, 0], [0, 0]]),
        ("self-intersecting corners", [[0, 0], [100, 100], [0, 100], [100, 0]]),
        ("collinear corners", [[0, 0], [100, 0], [200, 0], [300, 0]]),
    ]:
        geometry = server.qr_orientation(points)
        check(
            f"QR {label} cannot produce a trusted correction",
            geometry["correction_quarter_turn"] is None,
            str(geometry),
        )
    httpd = server.ThreadingHTTPServer(("127.0.0.1", 0), server.Handler)
    thread = threading.Thread(target=httpd.serve_forever, daemon=True)
    global PORT
    PORT = httpd.server_address[1]
    thread.start()

    image_bytes = b"\x89PNG\r\n\x1a\n" + b"0" * 32
    # A contract-consistent manifest so the request passes auth and multipart
    # parsing, then fails in the CHILD with a typed 422 (image_decode_failed).
    config_bytes = b"{}"
    native_bundle_metadata = {"files": {"config.json": hashlib.sha256(config_bytes).hexdigest()}, "engine": {"name": "OMRChecker", "commit": os.environ["OMRCHECKER_COMMIT"], "patch_identity": os.environ["NEXIAM_PATCH_IDENTITY"]}, "bundle_version": "nexiam.omr.native-bundle.v1"}
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
    manifest_bytes = json.dumps({
        "contract_version": CONTRACT,
        "registration_mode": "fiducial_anchored",
        "template_version_id": 1,
        "expected_page_no": 1,
        "source_image": {"width": 1, "height": 1, "orientation": 1, "input_hash": hashlib.sha256(image_bytes).hexdigest()},
        "run_id": RUN_ID,
        "correlation_id": "contract-test",
        "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": 1,
        "image_height": 1,
        "native_bundle_hash": hashlib.sha256(canonical(native_bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical({})).hexdigest(),
        "config_hash": hashlib.sha256(config_bytes).hexdigest(),
        "booklet_hash": "",
        "engine": {"name": "OMRChecker", "commit": os.environ["OMRCHECKER_COMMIT"], "patch_identity": os.environ["NEXIAM_PATCH_IDENTITY"]},
        "native_bundle_metadata": native_bundle_metadata,
        "native_template": {"pageDimensions": [1596, 2680], "bubbleDimensions": [40, 40], "customLabels": {}, "preProcessors": [{"name": "CropOnMarkers", "options": {"relativePath": "fiducial.png"}}], "fieldBlocks": {}},
        "native_config_json": config_bytes.decode("utf-8"),
        "review_manifest": {},
        "items": [{"position": 1, "area_code": "LOGIC", "question_no": 1, "native_label": "LOGIC_Q_1", "region": {"x": 0.1, "y": 0.1, "w": 0.2, "h": 0.02}, "options": [{"code": "A", "display_order": 1, "region": {"x": 0.1, "y": 0.1, "w": 0.04, "h": 0.02}}]}],
        "identity_fields": [{"code": "applicant_number", "native_label": "APPLICANT_NUMBER_1", "position": 1, "region": {"x": 0.1, "y": 0.1, "w": 0.02, "h": 0.02}}],
        "native_assets": {},
    }).encode()

    # 1. Tampered signature must be rejected with a typed 401 JSON response
    #    (never an empty reply / connection drop).
    body, headers = signed_request("/v1/layout-verification", manifest_bytes, image_bytes, server.SIGNING_SECRET, tamper=True)
    status, payload = post("/v1/layout-verification", body, headers)
    check("tampered signature -> 401 typed", status == 401 and payload.get("code") == "request_signature_invalid", (status, payload))

    # 2. Correct signature + real multipart headers must pass auth and reach
    #    the typed manifest/hash checks (422 with the exact code).
    body, headers = signed_request("/v1/layout-verification", manifest_bytes, image_bytes, server.SIGNING_SECRET)
    status, payload = post("/v1/layout-verification", body, headers)
    check("valid signature -> child runs and fails typed", status == 422 and payload.get("code") == "image_decode_failed", (status, payload))
    # 3. Detection endpoint keeps its strict contract (child errors typed).
    body, headers = signed_request("/v1/detections", manifest_bytes, image_bytes, server.SIGNING_SECRET)
    status, payload = post("/v1/detections", body, headers)
    check("detection rejects an inconsistent child manifest typed", status == 422 and payload.get("code") == "image_decode_failed", (status, payload))

    # 4. The auxiliary v4 preflight contract uses the shared exact-config artifact shape.
    preflight_manifest = json.loads(manifest_bytes.decode("utf-8"))
    preflight_manifest["contract_version"] = PREFLIGHT_CONTRACT
    preflight_manifest_bytes = json.dumps(preflight_manifest, separators=(",", ":"), ensure_ascii=False).encode()
    body, headers = signed_request("/v1/preflight", preflight_manifest_bytes, image_bytes, server.SIGNING_SECRET, contract=PREFLIGHT_CONTRACT)
    status, payload = post("/v1/preflight", body, headers)
    check("preflight shares the envelope", status == 422 and payload.get("code") == "image_decode_failed", (status, payload))

    # 5. A successful layout response is a bounded three-part envelope.
    portrait_bytes = Path("fixtures/characterization/run31-portrait.png").read_bytes()
    portrait_manifest = worker_manifest(portrait_bytes)
    body, headers = signed_request("/v1/layout-verification", portrait_manifest, portrait_bytes, server.SIGNING_SECRET)
    status, response_headers, response_body = post_raw("/v1/layout-verification", body, headers)
    check(
        "successful layout response preserves contract identity",
        status == 200
        and response_headers.get("X-Nexiam-Contract") == CONTRACT
        and response_headers.get("X-Nexiam-Run-Id") == RUN_ID,
        (status, response_headers),
    )
    parts = response_parts(response_headers.get("Content-Type", ""), response_body)
    check("successful layout response has ordered result/aligned/overlay parts", list(parts) == ["result", "aligned_image", "layout_overlay"], list(parts))
    check(
        "successful layout response part MIME types are pinned",
        parts["result"][0] == "application/json"
        and parts["aligned_image"][0] == "image/png"
        and parts["layout_overlay"][0] == "image/png",
        {name: value[0] for name, value in parts.items()},
    )
    response_result = json.loads(parts["result"][1].decode("utf-8"))
    check(
        "successful layout response keeps four markers and typed shifts",
        response_result.get("outcome") == "succeeded"
        and list(response_result.get("marker_matches", {})) == ["quarter_1", "quarter_2", "quarter_3", "quarter_4"]
        and all(
            isinstance(shift.get("x"), int) and isinstance(shift.get("y"), int)
            for shift in response_result.get("field_block_shifts", [])
        ),
        response_result,
    )
    check("layout response carries no score authority", not {"score", "scores", "answer_key"} & set(response_result), response_result.keys())
    check_response_signature(response_headers, RUN_ID, parts)

    # 6. A real marker failure remains typed across the signed server envelope.
    blank_frame = np.full((3000, 4000), 200, dtype=np.uint8)
    blank_ok, blank_encoded = cv2.imencode(".png", blank_frame)
    assert blank_ok
    blank_bytes = blank_encoded.tobytes()
    blank_manifest = worker_manifest(blank_bytes)
    body, headers = signed_request("/v1/layout-verification", blank_manifest, blank_bytes, server.SIGNING_SECRET)
    status, payload = post("/v1/layout-verification", body, headers)
    check("blank frame marker failure -> typed marker_missing/mismatch", status == 422 and payload.get("code") in {"marker_missing", "marker_mismatch"}, (status, payload))

    # 7. Unknown paths stay 404, GET health stays live.
    status, payload = post("/v1/bogus", body, headers)
    check("unknown path -> 404", status == 404, status)
    with urllib.request.urlopen(f"http://127.0.0.1:{PORT}/health/live", timeout=10) as response:
        check("health/live stays live", response.status == 200, response.status)
    # 8. QR extraction returns signed payload plus ordered semantic geometry.
    qr = cv2.QRCodeEncoder_create().encode("nexiam:test:orientation")
    qr = cv2.resize(qr, (400, 400), interpolation=cv2.INTER_NEAREST)
    qr_canvas = np.full((600, 600), 255, dtype=np.uint8)
    qr_canvas[100:500, 100:500] = qr
    qr_ok, qr_png = cv2.imencode(".png", qr_canvas)
    assert qr_ok
    qr_bytes = qr_png.tobytes()
    qr_manifest = json.dumps({
        "contract_version": AUXILIARY_CONTRACT,
        "run_id": RUN_ID,
        "correlation_id": "server-contract-qr",
        "input_hash": hashlib.sha256(qr_bytes).hexdigest(),
        "image_mime": "image/png",
    }, separators=(",", ":")).encode()
    body, headers = signed_request("/v1/qr-extract", qr_manifest, qr_bytes, server.SIGNING_SECRET, contract=AUXILIARY_CONTRACT)
    status, response_headers, response_body = post_raw("/v1/qr-extract", body, headers)
    qr_result = json.loads(response_body.decode("utf-8"))
    check(
        "QR extraction exposes a reliable upright correction",
        status == 200
        and response_headers.get("X-Nexiam-Contract") == AUXILIARY_CONTRACT
        and qr_result.get("contract_version") == AUXILIARY_CONTRACT
        and qr_result.get("payload") == "nexiam:test:orientation"
        and qr_result.get("correction_quarter_turn") == 0
        and len(qr_result.get("corners", [])) == 4,
        (status, qr_result),
    )
    qr_result_hash = hashlib.sha256(response_body).hexdigest()
    qr_timestamp = response_headers.get("X-Nexiam-Timestamp", "")
    qr_signature = server.sign(server.canonical_qr_extract_response_signature(qr_timestamp, RUN_ID, qr_result_hash))
    check("QR geometry response is HMAC-bound", response_headers.get("X-Nexiam-Signature") == qr_signature, response_headers)

    # 9. Source rectification returns signed multipart envelope with result and rectified rasters.
    dummy_img = np.full((100, 100, 3), 255, dtype=np.uint8)
    cv2.rectangle(dummy_img, (10, 10), (90, 90), (0, 0, 0), 2)
    ok_enc, dummy_png = cv2.imencode(".png", dummy_img)
    assert ok_enc
    dummy_bytes = dummy_png.tobytes()
    rect_manifest_dict = {
        "run_id": RUN_ID,
        "correlation_id": "server-contract-rect",
        "input_hash": hashlib.sha256(dummy_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": 100,
        "image_height": 100,
        "points": [[10, 10], [90, 10], [90, 90], [10, 90]],
        "target_width": 400,
        "target_height": 400,
    }
    rect_manifest_bytes = json.dumps(rect_manifest_dict, separators=(",", ":")).encode("utf-8")
    body_rect, headers_rect = signed_request("/v1/source-rectification", rect_manifest_bytes, dummy_bytes, server.SIGNING_SECRET, contract=AUXILIARY_CONTRACT)
    status_rect, resp_headers_rect, resp_body_rect = post_raw("/v1/source-rectification", body_rect, headers_rect)
    check("source rectification keeps the v3 producer contract", resp_headers_rect.get("X-Nexiam-Contract") == AUXILIARY_CONTRACT, resp_headers_rect)
    rect_parts = response_parts(resp_headers_rect.get("Content-Type", ""), resp_body_rect)
    check("source rectification has ordered result/rectified_image parts", list(rect_parts) == ["result", "rectified_image"], list(rect_parts))
    rect_res_json = json.loads(rect_parts["result"][1].decode("utf-8"))
    check("source rectification result keeps the v3 producer contract", rect_res_json.get("contract_version") == AUXILIARY_CONTRACT, rect_res_json)
    check("source rectification outcome is rectified", rect_res_json.get("outcome") == "rectified", rect_res_json)
    check("source rectification target dims match", rect_res_json.get("rectified", {}).get("width") == 400, rect_res_json)
    check("source rectification result hash matches header", resp_headers_rect.get("X-Nexiam-Result-SHA256") == hashlib.sha256(rect_parts["result"][1]).hexdigest())
    check("source rectification rectified hash matches header", resp_headers_rect.get("X-Nexiam-Rectified-SHA256") == hashlib.sha256(rect_parts["rectified_image"][1]).hexdigest())

    # 9. Source rectification with corrupt image bytes fails with 422 image_decode_failed (child-isolated).
    corrupt_bytes = b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x10\x00\x00\x00\x10\x08\x02\x00\x00\x00\x90\x91h6corruptdatahere"
    corrupt_manifest = dict(rect_manifest_dict)
    corrupt_manifest["input_hash"] = hashlib.sha256(corrupt_bytes).hexdigest()
    corrupt_manifest_bytes = json.dumps(corrupt_manifest, separators=(",", ":")).encode("utf-8")
    body_corrupt, headers_corrupt = signed_request("/v1/source-rectification", corrupt_manifest_bytes, corrupt_bytes, server.SIGNING_SECRET, contract=AUXILIARY_CONTRACT)
    status_corrupt, payload_corrupt = post("/v1/source-rectification", body_corrupt, headers_corrupt)
    check("source rectification corrupt image -> 422 image_decode_failed", status_corrupt == 422 and payload_corrupt.get("code") == "image_decode_failed", (status_corrupt, payload_corrupt))

    httpd.shutdown()
    print(json.dumps({"status": "ok", "port": PORT}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
