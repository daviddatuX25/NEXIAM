#!/usr/bin/env python3
"""Prepare a private immutable source/evidence/build workspace for document recovery."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import sys
from pathlib import Path

EXCLUSIONS = {".git", "bootstrap/cache", "docker/jitsi/config", "node_modules", "public/build", "storage", "vendor/pestphp/pest/.temp"}
OMITTED_FILES = {"auth.json"}
OVERLAY = b"DOCUMENT_RECOVERY_SYNTHETIC_ENV=1\n"


def fail(message: str) -> None:
    raise RuntimeError(message)


def digest(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def exclusion_reason(relative: str) -> str | None:
    if any(relative == item or relative.startswith(item + "/") for item in EXCLUSIONS):
        return "excluded directory"
    name = Path(relative).name
    if name == ".env" or (name.startswith(".env.") and name not in {".env.example", ".env.example.write"}):
        if relative != ".env":
            return "private environment file"
    if name in OMITTED_FILES:
        return "private credential file"
    if relative == "docker/jitsi/.env-provision.lock":
        return "private provisioning lock"
    if relative.startswith("database/") and "/" not in relative[len("database/"):] and re.fullmatch(r".*\.sqlite.*", name):
        return "private database file"
    return None


def source_map(root: Path, omissions: dict[str, str]) -> dict[str, dict[str, object]]:
    result = {}
    for directory, dirs, files in os.walk(root, topdown=True, followlinks=False):
        base = Path(directory)
        kept_dirs = []
        for name in sorted(dirs):
            path = base / name
            relative = path.relative_to(root).as_posix()
            if relative == ".env":
                continue
            reason = exclusion_reason(relative)
            if path.is_symlink():
                omissions[relative] = "symbolic link"
            elif reason is not None:
                omissions[relative] = reason
            else:
                kept_dirs.append(name)
        dirs[:] = kept_dirs
        for name in sorted(files):
            path = base / name
            rel = path.relative_to(root).as_posix()
            reason = exclusion_reason(rel)
            if reason is not None:
                omissions[rel] = reason
                continue
            if path.is_symlink():
                omissions[rel] = "symbolic link"
                continue
            info = path.stat(follow_symlinks=False)
            if not stat.S_ISREG(info.st_mode):
                omissions[rel] = "not a regular file"
                continue
            data = OVERLAY if rel == ".env" else path.read_bytes()
            result[rel] = {"sha256": digest(data), "size": len(data)}
    result[".env"] = {"sha256": digest(OVERLAY), "size": len(OVERLAY)}
    omissions.pop(".env", None)
    return dict(sorted(result.items()))


def global_digest(records: dict[str, dict[str, object]]) -> str:
    hashes = {path: str(record["sha256"]) for path, record in sorted(records.items())}
    encoded = json.dumps(hashes, sort_keys=True, separators=(",", ":"), ensure_ascii=True).encode("ascii")
    return digest(encoded)


def dependency_map(root: Path) -> dict[str, str]:
    resolved_root = root.resolve(strict=True)
    records = {}
    for directory, dirs, files in os.walk(root, topdown=True, followlinks=False):
        base = Path(directory)
        kept = []
        for name in sorted(dirs):
            if name in {".vite", ".vite-temp"}:
                continue
            path = base / name
            relative = path.relative_to(root).as_posix()
            if path.is_symlink():
                link = os.readlink(path)
                if os.path.isabs(link):
                    fail(f"Absolute dependency symlink is not allowed: {relative}")
                target = path.resolve(strict=True)
                if target != resolved_root and resolved_root not in target.parents:
                    fail(f"Dependency symlink escapes node_modules: {relative}")
                if any(part in {".vite", ".vite-temp"} for part in target.relative_to(resolved_root).parts):
                    fail(f"Dependency symlink targets excluded scratch: {relative}")
                records[relative] = "symlink:" + link
            else:
                kept.append(name)
        dirs[:] = kept
        for name in sorted(files):
            path = base / name
            relative = path.relative_to(root).as_posix()
            if path.is_symlink():
                link = os.readlink(path)
                if os.path.isabs(link):
                    fail(f"Absolute dependency symlink is not allowed: {relative}")
                target = path.resolve(strict=True)
                if target != resolved_root and resolved_root not in target.parents:
                    fail(f"Dependency symlink escapes node_modules: {relative}")
                if any(part in {".vite", ".vite-temp"} for part in target.relative_to(resolved_root).parts):
                    fail(f"Dependency symlink targets excluded scratch: {relative}")
                records[relative] = "symlink:" + link
            elif path.is_file():
                records[relative] = digest(path.read_bytes())
    return dict(sorted(records.items()))


def copy_dependencies(source: Path, destination: Path) -> dict[str, str]:
    if not source.is_dir() or source.is_symlink():
        fail("Existing system/node_modules directory is required for an isolated build.")
    before = dependency_map(source)
    root = source.resolve(strict=True)
    directory_modes = []
    for directory, dirs, files in os.walk(source, topdown=True, followlinks=False):
        base = Path(directory)
        dirs[:] = sorted(name for name in dirs if name not in {".vite", ".vite-temp"})
        relative_dir = base.relative_to(source)
        out_dir = destination / relative_dir
        out_dir.mkdir(parents=True, exist_ok=True)
        directory_modes.append((base, out_dir))
        for name in sorted(dirs):
            child = base / name
            if child.is_symlink():
                dirs.remove(name)
                target = child.resolve(strict=True)
                if target != root and root not in target.parents:
                    fail(f"Dependency symlink escapes node_modules: {child.relative_to(source)}")
                (out_dir / name).symlink_to(os.readlink(child), target_is_directory=True)
        for name in sorted(files):
            child = base / name
            relative = child.relative_to(source).as_posix()
            out = destination / relative
            if child.is_symlink():
                link = os.readlink(child)
                target = child.resolve(strict=True)
                if os.path.isabs(link) or (target != root and root not in target.parents):
                    fail(f"Dependency symlink escapes node_modules: {relative}")
                out.parent.mkdir(parents=True, exist_ok=True)
                out.symlink_to(link)
                continue
            if child.is_file():
                out.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(child, out, follow_symlinks=False)
                if digest(out.read_bytes()) != before.get(relative):
                    fail(f"Dependency changed while copying: {relative}")
        shutil.copystat(base, out_dir, follow_symlinks=False)
        os.chmod(out_dir, stat.S_IMODE(base.stat(follow_symlinks=False).st_mode) | 0o700)
    after = dependency_map(source)
    copied = dependency_map(destination)
    if before != after or copied != before:
        fail("Installed dependencies changed during owned copy.")
    return copied


def write_json(path: Path, value: object) -> None:
    with path.open("x", encoding="utf-8") as stream:
        json.dump(value, stream, indent=2, sort_keys=True)
        stream.write("\n")


def reject_symlink_path(path: Path) -> None:
    current = Path(path.anchor)
    for part in path.parts[1:]:
        current = current / part
        if current.is_symlink():
            fail(f"Refusing symbolic link in recovery path: {current}")


def repair_mountpoints(args: argparse.Namespace) -> int:
    expected_hash = "25744d5cd19bfc8c6712c5a485db5b44151ee68cff47e0a3351746d2b549a8aa"
    if (args.run_id, args.port) != ("immutable-preview-20261008-1405", 18102):
        fail("Mountpoint repair is restricted to the reviewed immutable preview run identity.")

    snapshot = args.snapshot_root.expanduser().absolute()
    evidence = args.evidence_dir.expanduser().absolute()
    build = args.build_dir.expanduser().absolute()
    for path in (snapshot, evidence, build):
        reject_symlink_path(path)
    summary_path = snapshot / "provenance" / "summary.json"
    summary = json.loads(summary_path.read_text(encoding="utf-8"))
    if (
        summary.get("run_id") != args.run_id
        or summary.get("port") != args.port
        or summary.get("snapshot_root") != str(snapshot)
        or summary.get("runtime_cwd") != str(snapshot / "system")
        or summary.get("evidence_dir") != str(evidence)
        or summary.get("build_dir") != str(build)
        or summary.get("source_sha256") != expected_hash
        or summary.get("snapshot_sha256") != expected_hash
    ):
        fail("Existing snapshot summary does not match the reviewed immutable run identity.")

    target = snapshot / "system"
    actual_map = source_map(target, {})
    if actual_map != summary.get("snapshot_files") or global_digest(actual_map) != expected_hash:
        fail("Included snapshot map differs from the reviewed summary.")

    destinations = [target / item for item in ("storage", "bootstrap/cache", "public/build")]
    created: list[Path] = []
    changed_modes: dict[Path, int] = {}
    try:
        for destination in destinations:
            reject_symlink_path(destination)
            if destination.exists():
                if not destination.is_dir() or any(destination.iterdir()):
                    fail(f"Existing excluded mountpoint is not empty: {destination}")
                continue
            missing = []
            parent = destination.parent
            while not parent.exists():
                missing.append(parent)
                parent = parent.parent
            for directory in reversed(missing):
                reject_symlink_path(directory)
                original = stat.S_IMODE(directory.parent.stat(follow_symlinks=False).st_mode)
                if not original & stat.S_IWUSR:
                    changed_modes.setdefault(directory.parent, original)
                    os.chmod(directory.parent, original | stat.S_IWUSR | stat.S_IXUSR)
                directory.mkdir(mode=0o700)
                created.append(directory)
            original = stat.S_IMODE(parent.stat(follow_symlinks=False).st_mode)
            if not original & stat.S_IWUSR:
                changed_modes.setdefault(parent, original)
                os.chmod(parent, original | stat.S_IWUSR | stat.S_IXUSR)
            destination.mkdir(mode=0o700)
            created.append(destination)
        for path in reversed(created):
            os.chmod(path, 0o555)
    finally:
        for path, mode in reversed(list(changed_modes.items())):
            os.chmod(path, mode)

    if source_map(target, {}) != summary["snapshot_files"]:
        fail("Included snapshot map changed during mountpoint repair.")
    print(json.dumps({"run_id": args.run_id, "mountpoints": [str(path) for path in destinations], "ready": True}, indent=2))
    return 0




def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--run-id", required=True)
    parser.add_argument("--snapshot-root", required=True, type=Path)
    parser.add_argument("--evidence-dir", required=True, type=Path)
    parser.add_argument("--build-dir", required=True, type=Path)
    parser.add_argument("--port", required=True, type=int)
    parser.add_argument("--repair-mountpoints", action="store_true")
    args = parser.parse_args()
    if args.repair_mountpoints:
        return repair_mountpoints(args)
    if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._-]{0,63}", args.run_id) or ".." in args.run_id:
        fail("Run ID must be a short safe identifier.")
    if not 1024 <= args.port <= 65535 or args.port == 18000:
        fail("Port must be 1024..65535 and must not be the shared port 18000.")

    repo = Path(__file__).resolve().parents[2]
    system = repo / "system"

    def physical_output(argument: Path) -> Path:
        requested = argument.expanduser()
        if requested.exists() or requested.is_symlink():
            fail(f"Refusing to overwrite existing claimed output path: {requested}")
        parent = requested.parent.resolve(strict=True)
        if not parent.is_dir():
            fail(f"Output parent must already be a directory: {parent}")
        return (parent / requested.name).resolve(strict=False)

    snapshot = physical_output(args.snapshot_root)
    evidence = physical_output(args.evidence_dir)
    build = physical_output(args.build_dir)
    for path in (snapshot, evidence, build):
        if path == repo or repo in path.parents:
            fail(f"Owned output must be outside the repository: {path}")
        if path.exists() or path.is_symlink():
            fail(f"Refusing to overwrite existing claimed output path: {path}")
    paths = (snapshot, evidence, build)
    if len(set(paths)) != len(paths) or any(a in b.parents or b in a.parents for index, a in enumerate(paths) for b in paths[index + 1:]):
        fail("Snapshot, evidence, and build directories must be distinct, non-nested physical paths.")

    claimed = []
    provenance = None
    copied = {}
    try:
        for path in paths:
            path.mkdir(mode=0o700)
            os.chmod(path, 0o700)
            claimed.append(path)
        provenance = snapshot / "provenance"
        provenance.mkdir(mode=0o700)
        omissions: dict[str, str] = {}
        before = source_map(system, omissions)
        write_json(provenance / "source-before.json", before)
        write_json(provenance / "omissions.json", dict(sorted(omissions.items())))
        target = snapshot / "system"
        target.mkdir(mode=0o700)
        for relative, record in sorted(before.items()):
            source = system / relative
            destination = target / relative
            destination.parent.mkdir(parents=True, exist_ok=True)
            data = OVERLAY if relative == ".env" else source.read_bytes()
            actual = {"sha256": digest(data), "size": len(data)}
            if actual != record:
                fail(f"SOURCE_CHANGED while copying {relative}")
            with destination.open("xb") as stream:
                stream.write(data)
            source_mode = 0o444 if relative == ".env" else (stat.S_IMODE(source.stat(follow_symlinks=False).st_mode) & 0o111) | 0o444
            os.chmod(destination, source_mode)
            copied[relative] = actual

        after_omissions: dict[str, str] = {}
        after = source_map(system, after_omissions)
        write_json(provenance / "source-after.json", after)
        write_json(provenance / "omissions-after.json", dict(sorted(after_omissions.items())))
        if before != after or omissions != after_omissions or copied != before:
            fail("SOURCE_CHANGED during snapshot; partial evidence retained")
        snapshot_omissions: dict[str, str] = {}
        snapshot_map = source_map(target, snapshot_omissions)
        write_json(provenance / "snapshot.json", snapshot_map)
        if snapshot_map != before:
            fail("Snapshot map differs from source map")
        dependency_map = copy_dependencies(system / "node_modules", target / "node_modules")
        for required in (target / "node_modules/.bin/vite", target / "node_modules/.bin/playwright"):
            if not required.is_file() or not os.access(required, os.X_OK):
                fail(f"Owned dependencies lack an executable {required.name} launcher.")
        for relative in ("storage", "bootstrap/cache", "public/build"):
            (target / relative).mkdir(parents=True, exist_ok=True, mode=0o700)
        source_directories = []
        for directory, dirs, _files in os.walk(target, topdown=True):
            dirs[:] = sorted(name for name in dirs if name != "node_modules")
            source_directories.append(Path(directory))
        for directory in reversed(source_directories):
            os.chmod(directory, 0o555)
        write_json(provenance / "dependencies.json", dependency_map)
        summary = {
            "run_id": args.run_id,
            "port": args.port,
            "snapshot_root": str(snapshot),
            "runtime_cwd": str(target),
            "evidence_dir": str(evidence),
            "build_dir": str(build),
            "source_sha256": global_digest(before),
            "snapshot_sha256": global_digest(snapshot_map),
            "source_files": before,
            "snapshot_files": snapshot_map,
            "included_files": len(before),
            "omissions": dict(sorted(omissions.items())),
            "exclusions": sorted(EXCLUSIONS),
            "owned_dependency_files": len(dependency_map),
            "overlay": ".env is synthetic recovery marker only",
        }
        write_json(provenance / "summary.json", summary)
        print(json.dumps({
            "run_id": args.run_id,
            "snapshot_root": str(snapshot),
            "runtime_cwd": str(target),
            "evidence_dir": str(evidence),
            "build_dir": str(build),
            "port": args.port,
            "compose": "docker compose -p nexiam-document-recovery -f compose.yaml -f compose.document-recovery.yaml --profile document-recovery",
            "ready": True,
        }, indent=2))
        return 0
    except Exception as error:
        failure = {"run_id": args.run_id, "status": "PREPARATION_FAILED", "error": str(error)}
        if provenance is not None and provenance.is_dir():
            failure["copied_files"] = copied
            try:
                write_json(provenance / "summary.json", failure)
            except Exception:
                pass
        elif snapshot in claimed:
            try:
                (snapshot / "provenance").mkdir(mode=0o700)
                write_json(snapshot / "provenance" / "summary.json", failure)
            except Exception:
                pass
        raise

if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exception:
        print(f"prepare-document-recovery-inputs: {exception}", file=sys.stderr)
        raise SystemExit(1)
