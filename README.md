# NEXIAM encrypted source backup — 2026-10-23

This branch is a recoverable backup, not an application release or a plaintext source branch. It contains the current `system/` source, including uncommitted application dependencies, migrations, 74 seeder files and their included datasets/assets, tests, lockfiles and build configuration. `manifest.json` records scope, checks, limits and the source HEAD. The application working tree, local master, GitHub master and live deployment were not changed to create this backup.

The source archive is encrypted because the current application contains an active hardcoded Web Push private key. The decryption passphrase is deliberately **not** in this repository. Keep a separate, secure copy of the generated passphrase; losing it makes this backup unrecoverable. Do not paste it into GitHub, issue comments, shell commands, or chat. Decrypted files must be kept private until credentials have been externalized.

## Restore on a private machine

Install Git, GnuPG and tar. Clone this backup branch, then verify and reassemble the parts:

```sh
sha256sum --check SHA256SUMS
cat source.tar.gz.gpg.part-* > source.tar.gz.gpg
sha256sum source.tar.gz.gpg
```

Compare the last checksum with `archive_sha256` in `manifest.json`. Decrypt with GnuPG's secure passphrase prompt, then extract into a **new empty private directory**, not an existing deployment:

```sh
umask 077
gpg --output source.tar.gz --decrypt source.tar.gz.gpg
mkdir restored
chmod 700 restored
tar --extract --gzip --file source.tar.gz --directory restored
```

The application source will be under `restored/system/`. On the backup host, decryption and `tar --compare` matched all 4,956 archived files against the working source before upload. Each part is below GitHub's 100 MiB per-file limit.

## Dependencies and omitted state

- PHP/Composer and Node dependencies must be installed from the included lockfiles in a new isolated environment. This backup does not include `vendor/` or `node_modules/`.
- Runtime `.env`, databases, storage volumes, logs, and the selectively published production frontend are **not** included. This is not a full disaster-recovery backup of live data. Preserve the existing application key and obtain deployment configuration through a separate private process; never regenerate keys or run demo seeds against an existing deployment.
- The TabPFN model checkpoint is intentionally omitted. It is an externally supplied artifact, not reconstructible by compiling this application. The included Compose/environment contract identifies `Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt`, SHA-256 `1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88`. Obtain the exact licensed artifact from an authorized source, verify its hash, and supply it at `TABPFN_CHECKPOINT_HOST_PATH` before using the inference profile. This backup does not establish model redistribution or institutional-use approval. A separate private Git-history backup on the original host preserves the existing checkpoint.
- Redundant source-art ZIPs, development-only Word trials, local QA outputs and editor artifacts are omitted. Runtime source assets and document templates are included. Unrelated manuscript/planning edits are not part of this source archive.

## Verification and known defects

Compose default and HTTPS-overlay configuration validation, runtime route registration, public health HTTP 200 and login HTTP 200 were exercised. After stopping resource-heavy review checks, host load dropped from approximately 61 to 3.6 and one public login request completed in 0.327 seconds. These observations are not a load-test or comprehensive release acceptance.

The focused suggestion-input regression had 2 failures out of 12 tests. Source review also found a missing academic-history form binding and missing suggestion-option roles. Full type checks, tests and build were stopped to protect the resource-constrained host; browser acceptance did not pass. No fixes, schema changes, seeds, resets, service recreation, or frontend activation were performed.

Do not deploy this snapshot merely because its backup succeeded. Externalize the active private key, resolve the recorded defects and authority gaps, and complete isolated verification before preparing a coordinated release.
