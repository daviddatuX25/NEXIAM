#!/usr/bin/env python3
"""Bounded same-origin HTTP bridge into the isolated Compose recovery runtime."""

import argparse
import json
import os
import select
import socket
import subprocess
import sys
import threading
import time

BUFFER_SIZE = 65536
MAX_CLIENTS = 8
MAX_HEADER_BYTES = 65536
MAX_BODY_BYTES = 64 * 1024 * 1024
MAX_RESPONSE_BYTES = 128 * 1024 * 1024
TOTAL_SECONDS = 120
IDLE_SECONDS = 15
COMPOSE_COMMAND = [
    "docker", "compose", "-p", "nexiam-document-recovery",
    "-f", "compose.yaml", "-f", "compose.document-recovery.yaml",
    "--profile", "document-recovery", "exec", "-T",
    "document-recovery", "php", "bin/document-recovery-relay.php",
]


class BadRequest(Exception):
    pass


def recv_until(sock, marker, limit, deadline):
    data = bytearray()
    while marker not in data:
        if time.monotonic() >= deadline or len(data) >= limit:
            raise BadRequest("header limit or deadline exceeded")
        sock.settimeout(min(IDLE_SECONDS, max(0.01, deadline - time.monotonic())))
        chunk = sock.recv(min(BUFFER_SIZE, limit - len(data)))
        if not chunk:
            raise BadRequest("incomplete HTTP headers")
        data.extend(chunk)
    index = data.index(marker) + len(marker)
    return bytes(data[:index]), bytes(data[index:])


def read_request(sock, deadline, expected_host):
    headers, extra = recv_until(sock, b"\r\n\r\n", MAX_HEADER_BYTES, deadline)
    lines = headers[:-4].split(b"\r\n")
    try:
        method, target, version = lines[0].decode("ascii").split(" ")
    except (UnicodeDecodeError, ValueError) as exc:
        raise BadRequest("malformed request line") from exc
    if method.upper() == "CONNECT" or version not in ("HTTP/1.0", "HTTP/1.1"):
        raise BadRequest("unsupported request method or version")
    if not target.startswith("/") or target.startswith("//") or "#" in target:
        raise BadRequest("absolute-form or invalid target rejected")
    fields = {}
    raw_headers = []
    for line in lines[1:]:
        if not line or line[:1] in (b" ", b"\t") or b":" not in line:
            raise BadRequest("malformed or folded header")
        name, value = line.split(b":", 1)
        try:
            key = name.decode("ascii").lower()
            text = value.strip().decode("ascii")
        except UnicodeDecodeError as exc:
            raise BadRequest("non-ASCII header") from exc
        if not key or any(c not in "!#$%&'*+-.^_`|~0123456789abcdefghijklmnopqrstuvwxyz" for c in key):
            raise BadRequest("invalid header name")
        fields.setdefault(key, []).append(text)
        raw_headers.append(line)
    hosts = fields.get("host", [])
    if len(hosts) != 1 or hosts[0].lower() != expected_host.lower():
        raise BadRequest("Host does not match recovery origin")
    origins = fields.get("origin", [])
    if len(origins) > 1 or (origins and origins[0].lower() != ("http://" + expected_host).lower()):
        raise BadRequest("Origin does not match recovery origin")
    if "transfer-encoding" in fields and "content-length" in fields:
        raise BadRequest("contradictory HTTP framing")
    if len(fields.get("content-length", [])) > 1 or len(fields.get("transfer-encoding", [])) > 1:
        raise BadRequest("ambiguous HTTP framing")
    if "transfer-encoding" in fields:
        if fields["transfer-encoding"][-1].lower() != "chunked":
            raise BadRequest("unsupported transfer encoding")
        body = bytearray(extra)
        while True:
            complete_length = chunked_length(body)
            if complete_length is not None:
                if complete_length != len(body):
                    raise BadRequest("bytes after framed request body rejected")
                break
            if len(body) >= MAX_BODY_BYTES or time.monotonic() >= deadline:
                raise BadRequest("chunked request exceeds bounds")
            sock.settimeout(min(IDLE_SECONDS, max(0.01, deadline-time.monotonic())))
            chunk = sock.recv(min(BUFFER_SIZE, MAX_BODY_BYTES-len(body)))
            if not chunk:
                raise BadRequest("incomplete chunked request")
            body.extend(chunk)
        body_bytes = bytes(body)
    else:
        lengths = fields.get("content-length", ["0"])
        if not lengths[0].isdigit():
            raise BadRequest("invalid content length")
        length = int(lengths[0])
        if length > MAX_BODY_BYTES:
            raise BadRequest("request body exceeds limit")
        body = bytearray(extra)
        while len(body) < length:
            if time.monotonic() >= deadline:
                raise BadRequest("request body deadline exceeded")
            sock.settimeout(min(IDLE_SECONDS, max(0.01, deadline-time.monotonic())))
            chunk = sock.recv(min(BUFFER_SIZE, length-len(body)))
            if not chunk:
                raise BadRequest("incomplete request body")
            body.extend(chunk)
        if len(body) != length:
            raise BadRequest("bytes after framed request body rejected")
        body_bytes = bytes(body)
    # One request per relay; close semantics avoid silently reusing it for a second request.
    forwarded_headers = [line for line in raw_headers if not line.lower().startswith(b"connection:")]
    request = lines[0] + b"\r\n" + b"\r\n".join(forwarded_headers) + b"\r\nConnection: close\r\n\r\n"
    return request + body_bytes


def chunked_length(data):
    """Return complete framed chunk length, or None while more bytes are needed."""
    pos = 0
    while True:
        end = data.find(b"\r\n", pos)
        if end < 0:
            return None
        size_text = data[pos:end].split(b";", 1)[0]
        if not size_text or any(byte not in b"0123456789abcdefABCDEF" for byte in size_text):
            raise BadRequest("invalid chunk size")
        size = int(size_text, 16)
        pos = end + 2
        if size == 0:
            while True:
                end = data.find(b"\r\n", pos)
                if end < 0:
                    return None
                if end == pos:
                    return end + 2
                trailer = data[pos:end]
                if b":" not in trailer or trailer[:1] in (b" ", b"\t"):
                    raise BadRequest("invalid chunk trailer")
                pos = end + 2
        if pos + size + 2 > len(data):
            return None
        pos += size
        if data[pos:pos+2] != b"\r\n":
            raise BadRequest("invalid chunk data terminator")
        pos += 2


def validate_chunks(data):
    complete_length = chunked_length(data)
    if complete_length is None or complete_length != len(data):
        raise BadRequest("incomplete or trailing chunked data")

def valid_response(response):
    sep = response.find(b"\r\n\r\n")
    if sep < 0:
        return False
    header = response[:sep].lower()
    body = response[sep+4:]
    if b"\r\ntransfer-encoding: chunked" in header:
        try:
            validate_chunks(body)
            return True
        except BadRequest:
            return False
    for line in header.split(b"\r\n"):
        if line.startswith(b"content-length:"):
            try:
                return len(body) == int(line.split(b":", 1)[1].strip())
            except ValueError:
                return False
    return True


def send_error(client, status, message):
    payload = (message + "\n").encode()
    try:
        client.sendall(f"HTTP/1.1 {status}\r\nConnection: close\r\nContent-Type: text/plain\r\nContent-Length: {len(payload)}\r\n\r\n".encode() + payload)
    except OSError:
        pass


def handle_client(client, address, workdir, expected_host, compose_command=None):
    if compose_command is None:
        compose_command = COMPOSE_COMMAND
    deadline = time.monotonic() + TOTAL_SECONDS
    process = None
    try:
        request = read_request(client, deadline, expected_host)
        process = subprocess.Popen(compose_command, cwd=workdir, stdin=subprocess.PIPE,
                                   stdout=subprocess.PIPE, stderr=None, bufsize=0)
        stdin_fd = process.stdin.fileno()
        os.set_blocking(stdin_fd, False)
        request_view = memoryview(request)
        while request_view:
            remaining = deadline - time.monotonic()
            if remaining <= 0:
                raise TimeoutError("request forwarding deadline exceeded")
            _, writable, _ = select.select([], [stdin_fd], [], remaining)
            if not writable:
                raise TimeoutError("request forwarding deadline exceeded")
            written = os.write(stdin_fd, request_view[:BUFFER_SIZE])
            if written <= 0:
                raise OSError("relay request pipe closed")
            request_view = request_view[written:]
        process.stdin.close()
        response = bytearray()
        fd = process.stdout.fileno()
        os.set_blocking(fd, False)
        last_activity = time.monotonic()
        while True:
            remaining = min(deadline - time.monotonic(), IDLE_SECONDS - (time.monotonic() - last_activity))
            if remaining <= 0 or len(response) >= MAX_RESPONSE_BYTES:
                raise TimeoutError("response deadline or byte limit exceeded")
            ready, _, _ = select.select([fd, client], [], [], remaining)
            if not ready:
                raise TimeoutError("response idle deadline exceeded")
            if client in ready:
                extra = client.recv(1, socket.MSG_PEEK)
                if not extra:
                    raise ConnectionError("client disconnected")
                raise BadRequest("pipelined requests are not supported")
            chunk = os.read(fd, min(BUFFER_SIZE, MAX_RESPONSE_BYTES - len(response)))
            if not chunk:
                break
            response.extend(chunk)
            last_activity = time.monotonic()
        process.stdout.close()
        process.wait(timeout=max(.01, deadline-time.monotonic()))
        if process.returncode or not valid_response(response):
            raise BadRequest("relay failed or response framing incomplete")
        client.settimeout(min(IDLE_SECONDS, max(.01, deadline-time.monotonic())))
        client.sendall(response)
    except (BadRequest, TimeoutError, OSError, subprocess.SubprocessError, ValueError) as error:
        print(f"document-recovery-proxy: {address}: {error}", file=sys.stderr)
        send_error(client, "400 Bad Request" if isinstance(error, BadRequest) else "504 Gateway Timeout", "Recovery request failed")
    finally:
        if process is not None and process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=1)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
        try:
            client.shutdown(socket.SHUT_RDWR)
        except OSError:
            pass
        client.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--run-id", required=True)
    parser.add_argument("--manifest", default="/evidence/fixture.json")
    parser.add_argument("--compose-project",
                        default=os.environ.get("COMPOSE_PROJECT_NAME", "nexiam-document-recovery"),
                        help="Docker Compose project name")
    args = parser.parse_args()
    if not 1 <= args.port <= 65535:
        parser.error("--port must be between 1 and 65535")
    try:
        with open(args.manifest, encoding="utf-8") as manifest_file:
            manifest = json.load(manifest_file)
    except (OSError, ValueError) as error:
        parser.error(f"cannot read recovery manifest: {error}")
    if (manifest.get("run_id") != args.run_id or manifest.get("public_port") != args.port
            or manifest.get("service") != "document-recovery"
            or not manifest.get("initial_source_digest") or not manifest.get("build_digest")
            or not isinstance(manifest.get("cases"), list)):
        parser.error("proxy identity does not match recovery manifest")
    if os.environ.get("DOCUMENT_RECOVERY_PUBLIC_PORT") != str(args.port):
        parser.error("--port does not match DOCUMENT_RECOVERY_PUBLIC_PORT")
    if os.environ.get("DOCUMENT_RECOVERY_RUN_ID") != args.run_id:
        parser.error("--run-id does not match DOCUMENT_RECOVERY_RUN_ID")
    global COMPOSE_COMMAND
    COMPOSE_COMMAND = [
        "docker", "compose", "-p", args.compose_project,
        "-f", "compose.yaml", "-f", "compose.document-recovery.yaml",
        "--profile", "document-recovery", "exec", "-T",
        "document-recovery", "php", "bin/document-recovery-relay.php",
    ]
    host = f"127.0.0.1:{args.port}"
    workdir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    listener = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    gate = threading.BoundedSemaphore(MAX_CLIENTS)
    try:
        listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        listener.bind(("127.0.0.1", args.port))
        listener.listen(MAX_CLIENTS)
        print(f"document-recovery-proxy listening on {host}", flush=True)
        while True:
            client, address = listener.accept()
            if not gate.acquire(blocking=False):
                send_error(client, "503 Service Unavailable", "Recovery proxy busy")
                client.close()
                continue
            def run(sock=client, peer=address):
                try:
                    handle_client(sock, peer, workdir, host, COMPOSE_COMMAND)
                finally:
                    gate.release()
            threading.Thread(target=run, daemon=True).start()
    except KeyboardInterrupt:
        return 0
    except OSError as error:
        print(f"document-recovery-proxy: listener failed: {error}", file=sys.stderr)
        return 1
    finally:
        listener.close()


if __name__ == "__main__":
    raise SystemExit(main())
