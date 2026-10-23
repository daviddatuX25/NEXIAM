"""Exact native config artifact identity at the worker validation seam."""

import hashlib
import json
from pathlib import Path

import detect
import test_document_bound_inference as fixtures


def canonical(value: object) -> bytes:
    return json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode("utf-8")


def bundle_hash(manifest: dict) -> None:
    metadata = manifest["native_bundle_metadata"]
    identity = {
        "files": metadata.get("files"),
        "engine": metadata.get("engine"),
        "bundle_version": metadata.get("bundle_version"),
    }
    manifest["native_bundle_hash"] = hashlib.sha256(canonical(identity)).hexdigest()


def valid_manifest() -> dict:
    manifest = fixtures.manifest_for()
    native_config = json.loads(manifest.pop("native_config_json"))
    config_bytes = json.dumps(native_config, ensure_ascii=False, indent=2).encode("utf-8")
    manifest["native_config_json"] = config_bytes.decode("utf-8")
    manifest["config_hash"] = hashlib.sha256(config_bytes).hexdigest()
    manifest["native_bundle_metadata"]["files"]["config.json"] = hashlib.sha256(config_bytes).hexdigest()
    bundle_hash(manifest)
    return manifest


def expect_rejected(manifest: dict, expected_code: str | None = None) -> None:
    try:
        detect._validate_manifest_artifacts(manifest)
    except detect.DetectionError as error:
        if expected_code is not None:
            assert error.code == expected_code, error.code
    else:
        raise AssertionError("invalid native config artifact was accepted")


def test_pretty_config_bytes_are_verified_and_decoded_for_execution() -> None:
    manifest = valid_manifest()
    config_bytes = manifest["native_config_json"].encode("utf-8")

    decoded_config = detect._validate_manifest_artifacts(manifest)

    assert hashlib.sha256(config_bytes).hexdigest() == manifest["config_hash"]
    assert decoded_config == json.loads(config_bytes)
    assert "native_config" not in manifest
    assert detect._validate_manifest_artifacts(manifest) == decoded_config


def test_preserved_pinned_version_two_config_bytes_validate_without_reencoding() -> None:
    manifest = fixtures.manifest_for()
    config_bytes = (Path(__file__).parent / "fixtures" / "nexiam-omr-pinned-config-v2.json").read_bytes()
    expected_hash = "bdcd5749419564476c80b6657a8a20b00e02a9bbc6e12fc504d915be323d392e"
    assert len(config_bytes) == 693
    assert hashlib.sha256(config_bytes).hexdigest() == expected_hash

    manifest["native_config_json"] = config_bytes.decode("utf-8")
    manifest["config_hash"] = expected_hash
    manifest["native_bundle_metadata"]["files"]["config.json"] = expected_hash
    bundle_hash(manifest)

    decoded_config = detect._validate_manifest_artifacts(manifest)

    assert decoded_config["dimensions"] == {
        "display_width": 2480,
        "display_height": 3508,
        "processing_width": 2480,
        "processing_height": 3508,
    }


def test_modified_config_bytes_are_rejected_even_if_the_json_value_is_unchanged() -> None:
    manifest = valid_manifest()
    manifest["native_config_json"] = manifest["native_config_json"].replace("\n", "\r\n", 1)

    expect_rejected(manifest, "config_hash_mismatch")


def test_config_bytes_must_match_bundle_file_digest() -> None:
    manifest = valid_manifest()
    manifest["native_bundle_metadata"]["files"]["config.json"] = "0" * 64
    bundle_hash(manifest)

    expect_rejected(manifest, "config_hash_mismatch")


def test_obsolete_missing_malformed_and_incompatible_configs_are_rejected() -> None:
    obsolete = valid_manifest()
    obsolete.pop("native_config_json")
    obsolete["native_config"] = {}

    missing = valid_manifest()
    missing.pop("native_config_json")

    expect_rejected(obsolete, "manifest_invalid")
    expect_rejected(missing, "manifest_invalid")

    for invalid_json in ("{not-json", "[]", "null", "NaN", "{\"overflow\":1e309}"):
        manifest = valid_manifest()
        manifest["native_config_json"] = invalid_json
        digest = hashlib.sha256(invalid_json.encode("utf-8")).hexdigest()
        manifest["config_hash"] = digest
        manifest["native_bundle_metadata"]["files"]["config.json"] = digest
        bundle_hash(manifest)
        expect_rejected(manifest, "manifest_invalid")


def main() -> int:
    test_pretty_config_bytes_are_verified_and_decoded_for_execution()
    test_preserved_pinned_version_two_config_bytes_validate_without_reencoding()
    test_modified_config_bytes_are_rejected_even_if_the_json_value_is_unchanged()
    test_config_bytes_must_match_bundle_file_digest()
    test_obsolete_missing_malformed_and_incompatible_configs_are_rejected()
    print(json.dumps({"status": "ok", "tests": 5}, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
