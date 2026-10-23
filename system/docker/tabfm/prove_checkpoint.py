#!/usr/bin/env python3
"""Run a non-hosted, synthetic-only TabPFN checkpoint proof through the worker."""

from __future__ import annotations

import argparse
import copy
import faulthandler
import importlib.metadata
import json
import math
import os
import platform
import resource
import subprocess
import sys
import threading
import time
from pathlib import Path
from typing import Any

_DIAGNOSTIC_PHASE = "startup"
_DIAGNOSTIC_STOP = threading.Event()
_DIAGNOSTIC_THREAD: threading.Thread | None = None
_PROFILE_TARGETS = {
    ("worker", "_verified_checkpoint"),
    ("worker", "_construct_classifier"),
    ("worker", "_warm_classifier"),
    ("worker", "_tabpfn_scores"),
    ("tabpfn.classifier", "TabPFNClassifier.fit"),
    ("tabpfn.classifier", "TabPFNClassifier.predict_proba"),
    ("tabpfn.model_loading", "load_model"),
    ("tabpfn.model_loading", "_build_model"),
}


def _diagnostic_event(event: str, **details: Any) -> None:
    record = {
        "event": event,
        "phase": _DIAGNOSTIC_PHASE,
        "monotonic_seconds": time.monotonic(),
        "wall_time": time.time(),
        "process_time_seconds": time.process_time(),
        "ru_maxrss": _peak_rss(),
        **details,
    }
    print(json.dumps(record, sort_keys=True, allow_nan=False), file=sys.stderr, flush=True)


def _read_diagnostic_file(path: str) -> dict[str, Any]:
    try:
        with open(path, encoding="ascii") as stream:
            value = stream.read().strip()
        return {"value": value}
    except OSError as exc:
        return {"error": f"{exc.__class__.__name__}: {exc}"}


def _diagnostic_snapshot() -> dict[str, Any]:
    paths = {
        "memory.current": ("/sys/fs/cgroup/memory.current", "/sys/fs/cgroup/memory/memory.usage_in_bytes"),
        "memory.peak": ("/sys/fs/cgroup/memory.peak", "/sys/fs/cgroup/memory/memory.max_usage_in_bytes"),
        "memory.events": ("/sys/fs/cgroup/memory.events",),
        "cpu.stat": ("/sys/fs/cgroup/cpu.stat",),
        "cpu.max": ("/sys/fs/cgroup/cpu.max",),
        "memory.max": ("/sys/fs/cgroup/memory.max", "/sys/fs/cgroup/memory/memory.limit_in_bytes"),
        "proc.status": ("/proc/self/status",),
    }
    snapshot: dict[str, Any] = {}
    for name, candidates in paths.items():
        for path in candidates:
            result = _read_diagnostic_file(path)
            if "value" in result:
                snapshot[name] = {"path": path, **result}
                break
        else:
            snapshot[name] = {"error": "; ".join(
                f"{path}: { _read_diagnostic_file(path).get('error', 'unavailable') }"
                for path in candidates
            )}
    return snapshot


def _diagnostic_heartbeat() -> None:
    started = time.monotonic()
    while not _DIAGNOSTIC_STOP.wait(10):
        _diagnostic_event(
            "heartbeat",
            elapsed_seconds=time.monotonic() - started,
            resources=_diagnostic_snapshot(),
        )


def _diagnostic_profile(frame: Any, event: str, arg: Any) -> None:
    if event not in ("call", "return"):
        return
    module = frame.f_globals.get("__name__", "")
    qualname = frame.f_code.co_qualname
    if (module, qualname) in _PROFILE_TARGETS:
        _diagnostic_event("profile", call_event=event.upper(), module=module, qualname=qualname)


def _start_diagnostics() -> None:
    global _DIAGNOSTIC_THREAD
    faulthandler.enable(file=sys.stderr, all_threads=True)
    faulthandler.dump_traceback_later(30, repeat=True, file=sys.stderr)
    sys.setprofile(_diagnostic_profile)
    _DIAGNOSTIC_THREAD = threading.Thread(target=_diagnostic_heartbeat, name="checkpoint-diagnostics", daemon=True)
    _DIAGNOSTIC_THREAD.start()
    _diagnostic_event("diagnostics_started", resources=_diagnostic_snapshot())


def _stop_diagnostics() -> None:
    sys.setprofile(None)
    faulthandler.cancel_dump_traceback_later()
    _DIAGNOSTIC_STOP.set()
    if _DIAGNOSTIC_THREAD is not None:
        _DIAGNOSTIC_THREAD.join()
    _diagnostic_event("diagnostics_stopped")


def _set_diagnostic_phase(phase: str) -> None:
    global _DIAGNOSTIC_PHASE
    _DIAGNOSTIC_PHASE = phase
    _diagnostic_event("phase")

EXPECTED_PACKAGE_VERSION = "9.1.0"
EXPECTED_CHECKPOINT_ID = (
    "Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt"
)
EXPECTED_CHECKPOINT_SHA256 = (
    "1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88"
)


def _fail(message: str) -> None:
    raise RuntimeError(message)


def _underlying_worker_error(error: BaseException) -> str:
    cause = error.__cause__
    classifier_error = getattr(sys.modules.get("worker"), "_CLASSIFIER_ERROR", None)
    if classifier_error:
        return str(classifier_error)
    if cause is not None:
        return f"{cause.__class__.__name__}: {cause}"
    return f"{error.__class__.__name__}: {error}"


def _check_provenance(provenance: Any) -> None:
    if not isinstance(provenance, dict):
        _fail("worker response omitted provenance")
    expected = {
        "package": "tabpfn",
        "package_version": EXPECTED_PACKAGE_VERSION,
        "checkpoint_identity": EXPECTED_CHECKPOINT_ID,
        "checkpoint_sha256": EXPECTED_CHECKPOINT_SHA256,
    }
    for key, value in expected.items():
        if provenance.get(key) != value:
            _fail(f"worker provenance mismatch for {key}")


def _check_response(response: Any) -> None:
    if not isinstance(response, dict) or response.get("status") != "evaluated":
        _fail("worker did not return an evaluated result")
    if response.get("inference_backend") != "tabpfn_pytorch":
        _fail("worker did not identify the TabPFN PyTorch backend")
    if response.get("fallback_reason") is not None:
        _fail("worker reported an inference fallback")
    if response.get("engine_version") != EXPECTED_PACKAGE_VERSION:
        _fail("worker engine version did not match the pinned package")
    _check_provenance(response.get("provenance"))

    recommendations = response.get("recommendations")
    expected_programs = {"SYN-PROGRAM-A", "SYN-PROGRAM-B"}
    if not isinstance(recommendations, list) or len(recommendations) != 2:
        _fail("worker did not return exactly two program recommendations")
    program_codes = [row.get("program_code") for row in recommendations]
    if set(program_codes) != expected_programs or len(set(program_codes)) != 2:
        _fail("worker returned unexpected or duplicate synthetic programs")
    ranks = [row.get("rank") for row in recommendations]
    if ranks != [1, 2]:
        _fail("recommendation ranks are not unique and sequential")
    for row in recommendations:
        index = row.get("compatibility_index")
        if isinstance(index, bool) or not isinstance(index, (int, float)):
            _fail("compatibility index is not numeric")
        if not math.isfinite(float(index)) or not 0 <= index <= 100:
            _fail("compatibility index is not finite and within 0..100")

    coverage = response.get("modality_coverage")
    if not isinstance(coverage, dict):
        _fail("worker response omitted feature coverage")
    if "intake_biology" not in coverage.get("missing_optional_features", []):
        _fail("null optional biology value was not preserved as missing")
    if "intake_biology" not in coverage.get("missing_features", []):
        _fail("null biology value was not retained in missing-feature coverage")
    feature_coverage = coverage.get("feature_coverage")
    if not isinstance(feature_coverage, dict):
        _fail("worker response omitted per-feature coverage")
    biology_coverage = feature_coverage.get("intake_biology")
    if not isinstance(biology_coverage, dict) or biology_coverage.get("available") is not False:
        _fail("optional biology missingness was not preserved in per-feature coverage")


def _negative_checks() -> int:
    """Exercise worker checkpoint validation in a separate, non-model-loading process."""
    import worker

    original = dict(os.environ)
    try:
        for key in ("TABPFN_CHECKPOINT_PATH", "TABPFN_CHECKPOINT_ID", "TABPFN_CHECKPOINT_SHA256"):
            os.environ.pop(key, None)
        try:
            worker._verified_checkpoint()
        except (ValueError, OSError):
            pass
        else:
            _fail("missing checkpoint configuration unexpectedly verified")

        checkpoint_path = original.get("TABPFN_CHECKPOINT_PATH")
        checkpoint_identity = original.get("TABPFN_CHECKPOINT_ID")
        if not checkpoint_path or not checkpoint_identity:
            _fail("negative checksum check requires configured checkpoint path and identity")
        os.environ["TABPFN_CHECKPOINT_PATH"] = checkpoint_path
        os.environ["TABPFN_CHECKPOINT_ID"] = checkpoint_identity
        expected = original.get("TABPFN_CHECKPOINT_SHA256", "")
        os.environ["TABPFN_CHECKPOINT_SHA256"] = "0" * 64 if expected.lower() != "0" * 64 else "1" * 64
        try:
            worker._verified_checkpoint()
        except (ValueError, OSError):
            pass
        else:
            _fail("deliberately wrong checkpoint hash unexpectedly verified")
    finally:
        os.environ.clear()
        os.environ.update(original)
    print("SAFE_NEGATIVE_CHECKS=PASS (missing configuration; wrong hash; no file mutation)")
    return 0


def _run_negative_checks() -> None:
    completed = subprocess.run(
        [sys.executable, str(Path(__file__).resolve()), "--negative-checks"],
        check=False,
        capture_output=True,
        text=True,
        env=os.environ.copy(),
    )
    if completed.returncode != 0:
        detail = (completed.stderr or completed.stdout).strip()
        _fail(f"isolated checkpoint negative checks failed: {detail}")
    print(completed.stdout.strip())


def _peak_rss() -> dict[str, Any]:
    import resource

    raw = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss
    if sys.platform == "darwin":
        return {"value": int(raw), "unit": "bytes", "source_unit": "bytes"}
    if sys.platform.startswith("linux"):
        return {"value": int(raw * 1024), "unit": "bytes", "source_unit": "KiB"}
    return {
        "value": int(raw),
        "unit": f"platform ru_maxrss units ({platform.system()})",
        "source_unit": "platform-defined ru_maxrss",
    }


def _run_proof() -> dict[str, Any]:
    import worker

    package_version = importlib.metadata.version("tabpfn")
    if package_version != EXPECTED_PACKAGE_VERSION:
        _fail(f"installed tabpfn version mismatch: expected {EXPECTED_PACKAGE_VERSION}, got {package_version}")
    if os.environ.get("TABPFN_CHECKPOINT_ID") != EXPECTED_CHECKPOINT_ID:
        _fail("configured checkpoint identity does not match the verified artifact")
    if os.environ.get("TABPFN_CHECKPOINT_SHA256", "").lower() != EXPECTED_CHECKPOINT_SHA256:
        _fail("configured checkpoint SHA-256 does not match the verified artifact")

    payload_path = Path(__file__).with_name("synthetic_model_probe.json")
    payload = json.loads(payload_path.read_text(encoding="utf-8"))
    if "EXPLICIT SYNTHETIC RESEARCH PROBE ONLY" not in payload.get("notice", ""):
        _fail("synthetic-only notice is missing")

    _set_diagnostic_phase("worker_ready")
    started = time.perf_counter()
    try:
        with worker._INFERENCE_LOCK:
            classifier, provenance = worker._ready_classifier()
    except Exception as exc:
        _fail(f"actual worker classifier warmup failed: {_underlying_worker_error(exc)}")
    warm_duration_ms = (time.perf_counter() - started) * 1000
    _check_provenance(provenance)
    if classifier is not worker._WARMED_CLASSIFIER:
        _fail("worker classifier was not marked warm after its real fit/predict")

    requests: list[dict[str, Any]] = []
    for label, query in (
        ("initial", payload["query_applicant"]),
        ("changed-evidence", {**payload["query_applicant"], "gwa": 95.2, "shs_strand": "SYNTH-HUMANITIES"}),
    ):
        request_payload = copy.deepcopy(payload)
        request_payload["query_applicant"] = query
        _set_diagnostic_phase(f"evaluate_{label}")
        started = time.perf_counter()
        try:
            response = worker.evaluate_pairwise_tabpfn(request_payload)
        except Exception as exc:
            _fail(f"actual worker inference failed for {label} query: {_underlying_worker_error(exc)}")
        duration_ms = (time.perf_counter() - started) * 1000
        _check_response(response)
        requests.append({
            "case": label,
            "wall_request_duration_ms": round(duration_ms, 2),
            "worker_execution_time_ms": response.get("execution_time_ms"),
            "result": response,
        })

    _set_diagnostic_phase("proof_complete")
    return {
        "notice": "Synthetic research-only direct worker proof; no HTTP server/API or application data used.",
        "warmup_duration_ms": round(warm_duration_ms, 2),
        "requests": requests,
        "peak_rss": _peak_rss(),
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--negative-checks", action="store_true")
    parser.add_argument("--diagnose", action="store_true", help="emit runtime diagnostics to stderr")
    args = parser.parse_args()
    if args.negative_checks:
        if args.diagnose:
            parser.error("--diagnose applies only to the actual proof")
        return _negative_checks()
    diagnosed = args.diagnose
    try:
        _run_negative_checks()
        if diagnosed:
            _start_diagnostics()
        proof = _run_proof()
    except Exception as exc:
        print(f"CHECKPOINT_PROOF_FAILED: {exc}", file=sys.stderr)
        return 1
    finally:
        if diagnosed:
            _stop_diagnostics()
    print(json.dumps(proof, indent=2, sort_keys=True, allow_nan=False))
    print("CHECKPOINT_PROOF=PASS")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
