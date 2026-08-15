import hashlib
import hmac
import json
import os
import secrets
import shutil
import subprocess
import sys
import tempfile
import threading
import time
from email.parser import BytesParser
from email.policy import default
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


CONTRACT_VERSION = "nexiam.omr.detection.v2"
SIGNING_SECRET = os.environ.get("OMR_WORKER_SIGNING_SECRET", "").encode()
MAX_REQUEST_BYTES = int(os.environ.get("OMR_WORKER_MAX_REQUEST_BYTES", str(12 * 1024 * 1024)))
MAX_MANIFEST_BYTES = int(os.environ.get("OMR_WORKER_MAX_MANIFEST_BYTES", str(2 * 1024 * 1024)))
MAX_IMAGE_BYTES = int(os.environ.get("OMR_WORKER_MAX_IMAGE_BYTES", str(10 * 1024 * 1024)))
MAX_RESULT_BYTES = int(os.environ.get("OMR_WORKER_MAX_RESULT_BYTES", str(2 * 1024 * 1024)))
MAX_ALIGNED_BYTES = int(os.environ.get("OMR_WORKER_MAX_ALIGNED_BYTES", str(15 * 1024 * 1024)))
MAX_CLOCK_SKEW_SECONDS = int(os.environ.get("OMR_WORKER_MAX_CLOCK_SKEW_SECONDS", "30"))
CHILD_TIMEOUT_SECONDS = float(os.environ.get("OMR_WORKER_CHILD_TIMEOUT_SECONDS", "30"))
MAX_CONCURRENCY = int(os.environ.get("OMR_WORKER_MAX_CONCURRENCY", "2"))
TEMP_ROOT = Path(os.environ.get("OMR_WORKER_TEMP_ROOT", "/tmp"))
CHILD = Path(__file__).with_name("detect.py")
CAPACITY = threading.BoundedSemaphore(MAX_CONCURRENCY)


def canonical_request_signature(timestamp: str, run_id: str, manifest_hash: str, image_hash: str) -> bytes:
    return f"POST\n/v1/detections\n{timestamp}\n{run_id}\n{manifest_hash}\n{image_hash}".encode()


def canonical_response_signature(timestamp: str, run_id: str, result_hash: str, aligned_hash: str) -> bytes:
    return f"{timestamp}\n{run_id}\n{result_hash}\n{aligned_hash}".encode()


def sign(payload: bytes) -> str:
    return hmac.new(SIGNING_SECRET, payload, hashlib.sha256).hexdigest()


def cleanup_stale_directories() -> None:
    TEMP_ROOT.mkdir(parents=True, exist_ok=True)
    for path in TEMP_ROOT.glob("nexiam-omr-request-*"):
        if path.is_dir():
            shutil.rmtree(path, ignore_errors=True)


def readiness() -> tuple[bool, dict]:
    if len(SIGNING_SECRET) < 32:
        return False, {"status": "not_ready", "code": "signing_secret_invalid"}
    try:
        completed = subprocess.run(
            [sys.executable, str(CHILD), "--self-test"],
            check=False,
            capture_output=True,
            timeout=15,
        )
    except (OSError, subprocess.TimeoutExpired):
        return False, {"status": "not_ready", "code": "engine_self_test_failed"}
    if completed.returncode != 0:
        return False, {"status": "not_ready", "code": "engine_self_test_failed"}
    return True, {"status": "ready", "authority": "proposal-only", "contract_version": CONTRACT_VERSION}


READY, READY_BODY = readiness()
cleanup_stale_directories()


class RequestFailure(Exception):
    def __init__(self, status: int, code: str):
        super().__init__(code)
        self.status = status
        self.code = code


class Handler(BaseHTTPRequestHandler):
    server_version = "NexiamOmr/1"

    def do_GET(self):
        if self.path == "/health/live":
            self.send_json(200, {"status": "live"})
            return
        if self.path == "/health/ready":
            self.send_json(200 if READY else 503, READY_BODY)
            return
        self.send_json(404, {"code": "not_found"})

    def do_POST(self):
        if self.path != "/v1/detections":
            self.send_json(404, {"code": "not_found"})
            return
        if not READY:
            self.send_json(503, {"code": "worker_not_ready"})
            return
        if not CAPACITY.acquire(blocking=False):
            self.send_json(429, {"code": "worker_busy"})
            return
        try:
            self.handle_detection()
        except RequestFailure as error:
            self.send_json(error.status, {"code": error.code})
        except Exception:
            self.send_json(500, {"code": "worker_internal_failure"})
        finally:
            CAPACITY.release()

    def handle_detection(self) -> None:
        content_length_text = self.headers.get("Content-Length", "")
        if not content_length_text.isdigit():
            raise RequestFailure(411, "content_length_required")
        content_length = int(content_length_text)
        if content_length <= 0 or content_length > MAX_REQUEST_BYTES:
            raise RequestFailure(413, "request_too_large")

        timestamp = self.headers.get("X-Nexiam-Timestamp", "")
        run_id = self.headers.get("X-Nexiam-Run-Id", "")
        contract = self.headers.get("X-Nexiam-Contract", "")
        signature = self.headers.get("X-Nexiam-Signature", "")
        if not timestamp.isdigit() or not run_id or contract != CONTRACT_VERSION or len(signature) != 64:
            raise RequestFailure(401, "request_auth_invalid")
        if abs(int(time.time()) - int(timestamp)) > MAX_CLOCK_SKEW_SECONDS:
            raise RequestFailure(401, "request_timestamp_invalid")

        body = self.rfile.read(content_length)
        manifest_bytes, image_bytes, image_mime = self.parse_multipart(body)
        manifest_hash = hashlib.sha256(manifest_bytes).hexdigest()
        image_hash = hashlib.sha256(image_bytes).hexdigest()
        expected_signature = sign(canonical_request_signature(timestamp, run_id, manifest_hash, image_hash))
        if not hmac.compare_digest(signature, expected_signature):
            raise RequestFailure(401, "request_signature_invalid")

        try:
            manifest = json.loads(manifest_bytes)
        except (UnicodeDecodeError, json.JSONDecodeError):
            raise RequestFailure(422, "manifest_invalid") from None
        if not isinstance(manifest, dict) or manifest.get("run_id") != run_id or manifest.get("image_mime") != image_mime:
            raise RequestFailure(422, "manifest_invalid")
        if manifest.get("input_hash") != image_hash:
            raise RequestFailure(422, "input_hash_mismatch")

        directory = Path(tempfile.mkdtemp(prefix="nexiam-omr-request-", dir=TEMP_ROOT))
        try:
            manifest_path = directory / "manifest.json"
            image_path = directory / "input"
            manifest_path.write_bytes(manifest_bytes)
            image_path.write_bytes(image_bytes)
            try:
                completed = subprocess.run(
                    [sys.executable, str(CHILD), str(manifest_path), str(image_path), str(directory)],
                    check=False,
                    capture_output=True,
                    timeout=CHILD_TIMEOUT_SECONDS,
                )
            except subprocess.TimeoutExpired:
                raise RequestFailure(504, "child_timeout") from None
            if completed.returncode != 0:
                code = self.safe_child_code(completed.stderr)
                status = 422 if completed.returncode == 2 else 500
                raise RequestFailure(status, code)

            result_path = directory / "result.json"
            aligned_path = directory / "aligned.png"
            if not result_path.is_file() or not aligned_path.is_file():
                raise RequestFailure(502, "child_output_invalid")
            result_bytes = result_path.read_bytes()
            aligned_bytes = aligned_path.read_bytes()
            if not result_bytes or len(result_bytes) > MAX_RESULT_BYTES or not aligned_bytes or len(aligned_bytes) > MAX_ALIGNED_BYTES:
                raise RequestFailure(502, "child_output_invalid")
            try:
                result = json.loads(result_bytes)
            except (UnicodeDecodeError, json.JSONDecodeError):
                raise RequestFailure(502, "child_output_invalid") from None
            if not isinstance(result, dict) or result.get("run_id") != run_id:
                raise RequestFailure(502, "child_output_invalid")

            aligned_hash = hashlib.sha256(aligned_bytes).hexdigest()
            result["aligned_image"]["hash"] = aligned_hash
            result_bytes = json.dumps(result, separators=(",", ":"), sort_keys=True).encode()
            self.send_multipart(run_id, result_bytes, aligned_bytes)
        finally:
            shutil.rmtree(directory, ignore_errors=True)

    def parse_multipart(self, body: bytes) -> tuple[bytes, bytes, str]:
        content_type = self.headers.get("Content-Type", "")
        if not content_type.lower().startswith("multipart/form-data;"):
            raise RequestFailure(415, "multipart_required")
        message = BytesParser(policy=default).parsebytes(
            b"Content-Type: " + content_type.encode("ascii", "strict") + b"\r\nMIME-Version: 1.0\r\n\r\n" + body
        )
        if not message.is_multipart():
            raise RequestFailure(422, "multipart_invalid")
        parts: dict[str, tuple[bytes, str]] = {}
        for part in message.iter_parts():
            name = part.get_param("name", header="content-disposition")
            if name not in {"manifest", "image"} or name in parts:
                raise RequestFailure(422, "multipart_invalid")
            payload = part.get_payload(decode=True)
            if not isinstance(payload, bytes):
                raise RequestFailure(422, "multipart_invalid")
            parts[name] = (payload, part.get_content_type())
        if set(parts) != {"manifest", "image"}:
            raise RequestFailure(422, "multipart_invalid")
        manifest_bytes, manifest_mime = parts["manifest"]
        image_bytes, image_mime = parts["image"]
        if manifest_mime != "application/json" or len(manifest_bytes) > MAX_MANIFEST_BYTES or len(image_bytes) > MAX_IMAGE_BYTES:
            raise RequestFailure(413, "multipart_part_too_large")
        return manifest_bytes, image_bytes, image_mime

    def send_multipart(self, run_id: str, result: bytes, aligned: bytes) -> None:
        boundary = "nexiam-" + secrets.token_hex(16)
        body = b"".join(
            [
                f"--{boundary}\r\nContent-Disposition: form-data; name=\"result\"\r\nContent-Type: application/json\r\n\r\n".encode(),
                result,
                b"\r\n",
                f"--{boundary}\r\nContent-Disposition: form-data; name=\"aligned_image\"; filename=\"aligned.png\"\r\nContent-Type: image/png\r\n\r\n".encode(),
                aligned,
                b"\r\n",
                f"--{boundary}--\r\n".encode(),
            ]
        )
        timestamp = str(int(time.time()))
        result_hash = hashlib.sha256(result).hexdigest()
        aligned_hash = hashlib.sha256(aligned).hexdigest()
        signature = sign(canonical_response_signature(timestamp, run_id, result_hash, aligned_hash))
        self.send_response(200)
        self.send_header("Content-Type", f"multipart/form-data; boundary={boundary}")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("X-Nexiam-Contract", CONTRACT_VERSION)
        self.send_header("X-Nexiam-Run-Id", run_id)
        self.send_header("X-Nexiam-Timestamp", timestamp)
        self.send_header("X-Nexiam-Result-SHA256", result_hash)
        self.send_header("X-Nexiam-Aligned-SHA256", aligned_hash)
        self.send_header("X-Nexiam-Signature", signature)
        self.end_headers()
        self.wfile.write(body)

    @staticmethod
    def safe_child_code(stderr: bytes) -> str:
        try:
            line = stderr.decode("utf-8", "replace").splitlines()[-1]
            value = json.loads(line)
            code = value.get("error")
            if isinstance(code, str) and code.replace("_", "").isalnum() and len(code) <= 64:
                return code
        except (IndexError, json.JSONDecodeError):
            pass
        return "child_failed"

    def send_json(self, status: int, payload: dict) -> None:
        body = json.dumps(payload, separators=(",", ":")).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        return


if __name__ == "__main__":
    ThreadingHTTPServer(("0.0.0.0", 8100), Handler).serve_forever()
