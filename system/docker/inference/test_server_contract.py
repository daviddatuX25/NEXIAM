"""Server-level envelope contract test (no HTTP fakes).

Boots the REAL worker BaseHTTPRequestHandler on a local port and POSTs
hand-built multipart requests through the same code path the app uses:
strict HMAC verification, header checks, multipart parsing, and typed
child-error responses. Guards against regressions where a RequestFailure
escapes the handler (connection dies with an empty reply instead of a
typed JSON error) and against signature/contract drift between the PHP
client and the worker.

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
from pathlib import Path

# The module reads the signing secret + engine identity at import time; the
# test uses a deterministic secret so signatures are reproducible.
os.environ["OMR_WORKER_SIGNING_SECRET"] = "server-contract-test-secret-0123456789abcdef"
os.environ.setdefault("OMRCHECKER_COMMIT", "de3f6982822895491be760325fd43e65e90fa293")
os.environ.setdefault("NEXIAM_PATCH_IDENTITY", "nexiam-headless-overlay-v1")

import server  # noqa: E402

CONTRACT = "nexiam.omr.detection.v2"
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


def signed_request(path: str, manifest_bytes: bytes, image_bytes: bytes, secret: bytes, tamper: bool = False):
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
        "X-Nexiam-Contract": CONTRACT,
        "X-Nexiam-Run-Id": RUN_ID,
        "X-Nexiam-Timestamp": timestamp,
        "X-Nexiam-Signature": signature,
    }


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
    httpd = server.ThreadingHTTPServer(("127.0.0.1", 0), server.Handler)
    thread = threading.Thread(target=httpd.serve_forever, daemon=True)
    thread.start()
    global PORT
    PORT = httpd.server_address[1]

    image_bytes = b"\x89PNG\r\n\x1a\n" + b"0" * 32
    # A contract-consistent manifest so the request passes auth and multipart
    # parsing, then fails in the CHILD with a typed 422 (image_decode_failed).
    native_bundle_metadata = {"files": {}, "engine": {"name": "OMRChecker", "commit": os.environ["OMRCHECKER_COMMIT"], "patch_identity": os.environ["NEXIAM_PATCH_IDENTITY"]}, "bundle_version": "nexiam.omr.native-bundle.v1"}
    canonical = lambda value: json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode()
    manifest_bytes = json.dumps({
        "contract_version": CONTRACT,
        "run_id": RUN_ID,
        "correlation_id": "contract-test",
        "input_hash": hashlib.sha256(image_bytes).hexdigest(),
        "image_mime": "image/png",
        "image_width": 1,
        "image_height": 1,
        "native_bundle_hash": hashlib.sha256(canonical(native_bundle_metadata)).hexdigest(),
        "manifest_hash": hashlib.sha256(canonical({})).hexdigest(),
        "config_hash": hashlib.sha256(canonical({})).hexdigest(),
        "booklet_hash": "",
        "engine": {"name": "OMRChecker", "commit": os.environ["OMRCHECKER_COMMIT"], "patch_identity": os.environ["NEXIAM_PATCH_IDENTITY"]},
        "native_bundle_metadata": native_bundle_metadata,
        "native_template": {"pageDimensions": [1596, 2680], "bubbleDimensions": [40, 40], "customLabels": {}, "preProcessors": [{"name": "CropOnMarkers", "options": {"relativePath": "fiducial.png"}}], "fieldBlocks": {}},
        "native_config": {},
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

    # 3. Same envelope contract for /v1/preflight.
    body, headers = signed_request("/v1/preflight", manifest_bytes, image_bytes, server.SIGNING_SECRET)
    status, payload = post("/v1/preflight", body, headers)
    check("preflight shares the envelope", status == 422 and payload.get("code") == "image_decode_failed", (status, payload))

    # 4. Detection endpoint keeps its strict contract (child errors typed).
    body, headers = signed_request("/v1/detections", manifest_bytes, image_bytes, server.SIGNING_SECRET)
    status, payload = post("/v1/detections", body, headers)
    check("detection rejects an inconsistent child manifest typed", status == 422 and payload.get("code") == "image_decode_failed", (status, payload))

    # 5. Unknown paths stay 404, GET health stays live.
    status, payload = post("/v1/bogus", body, headers)
    check("unknown path -> 404", status == 404, status)
    with urllib.request.urlopen(f"http://127.0.0.1:{PORT}/health/live", timeout=10) as response:
        check("health/live stays live", response.status == 200, response.status)

    httpd.shutdown()
    print(json.dumps({"status": "ok", "port": PORT}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
