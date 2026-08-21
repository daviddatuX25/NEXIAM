# Canonical template authoring handoff

## Implementation status

Unified Template Studio, Atomic Save Protocol, and Dual-Input Engine Integration complete and verified.
- **Single Atomic Studio Save (`PUT /setup/omr/templates/canonical/drafts/{draft}/studio`)**: FormRequest `SaveOmrStudioStateRequest` + Action `SaveOmrCanonicalStudioState` persisting mapping, custom overlays, and studio state (`authoring_json.studio`) in a single DB transaction without mutual deletion or concurrency deadlocks.
- **Expanded Overlay Spec & Content Persistence**: `OmrOverlaySpec` supports `header_title`, `instructions_box`, `signature_box`, `metadata_box`, `custom_text`, and `blank_instance_qr` with sanitized `content`, `name`, and `font_size`.
- **Parametric Blank Canvas Mode**: `OmrCanonicalComposer.php` generates clean white 300 PPI rasters with 4 corner fiducials directly from paper dimensions and marker insets without requiring ingested documents.
- **Dynamic Blank Print QR Placement**: `OmrBlankPrintOrchestrator.php` positions cryptographic QR codes dynamically from overlay box reservations with safe top-right fallback.
- **Dual-Input Test Capture (Live Camera + Upload)**: Stage 5 in `OmrTemplateStudio.svelte` provides live viewfinder snapshot capture via `getUserMedia` and drag-and-drop file upload against OMRChecker diagnostic verification.
- **Clean Cutover & Parity**: 100% vector print PDF and frame-anchored `template.json` export cleanly with zero coordinate drift. All 22 backend test suites pass green.
## Pinned dependencies and security decisions

### Server-side QR renderer
- Package: `endroid/qr-code`
- Locked version: `6.1.3` (Composer lock)
- License: MIT
- Runtime: PHP 8.4; SVG and PNG writers are used in queue-safe PHP code.
- Fixed output contract: UTF-8, ECC `Medium`, quiet-zone margin `4`, `RoundBlockSizeMode::Margin`, no logo, no external/network calls.
- Determinism: identical payload and options produce identical renderer output; repeated canonical runs hash the bytes.
- Package source: https://github.com/endroid/qr-code

### HTML sanitizer
- Package: `ezyang/htmlpurifier`
- Locked version: `4.19.0` (Composer lock)
- License: LGPL-2.1-or-later
- Adapter policy: server-side allowlist, CSS filtering, remote resources disabled, scripts/events/iframes rejected, and deterministic configuration; unknown dynamic tokens rejected unless fixed `data-omr-overlay` slots.
- Package source: https://github.com/ezyang/htmlpurifier

### ICC conversion
- Images with no ICC profile or an sRGB profile are accepted; non-sRGB ICC input is rejected with typed `image_icc_unsupported`.
- Alpha is flattened onto white during image normalization.

## Runtime and reset

Use the Compose runtime, not Sail:

```bash
docker compose -f system/compose.yaml up -d
```

To reset and seed demo data:

```bash
docker compose -f system/compose.yaml exec app php artisan migrate:fresh --seed --force
```

## Demo credentials and URLs

- Staff Login: `guidance.counselor@nexiam.test` / `password`
- Canonical Templates Index: `http://localhost:18000/setup/omr/templates/canonical`
- Seeded Drafts:
  - Draft 1 (`CANONICAL-HTML`): HTML source adapter
  - Draft 2 (`CANONICAL-DOCX`): Word DOCX source adapter
  - Draft 3 (`CANONICAL-PDF`): PDF document adapter
  - Draft 4 (`CANONICAL-IMAGE`): Image / photo scan adapter (Pathway A overlay)
  - Draft 5 (`CANONICAL-PRESET`): Synthetic preset (Pathway B parametric studio)
- Active Draft Studio URL: `http://localhost:18000/setup/omr/templates/canonical/drafts/5`

Expected initial state: Every canonical draft is uncompiled; physical qualification and activation remain blocked until genuine marker/template evidence is recorded.

## Verification evidence

- OpenSpec: `openspec validate omr-canonical-template-authoring --strict` → **Valid**.
- Canonical focused Laravel suite: **56 passed, 1 skipped, 261 assertions (100% green)**.
- Parity command: `php artisan omr:canonical-parity` → **PASSED for all 7 seeded versions**.
- Worker scenarios: Rectification and layout verification passing in the pinned inference worker image.
- Headless Browser: Confirmed live 3-pane `OmrTemplateStudio` workspace on Draft 5 with interactive toolbox, SVG canvas, property inspector, and live geometry validation.

## Qualification truth

- Both seeded marker profiles remain `candidate`.
- All supplier/canonical demo drafts remain unqualified and inactive.
- No seeder fabricates `physical_qualified`; UI qualification requires explicit printer/device identity, measured marker-center distances, and operator confirmation that the physical matrix passed.
- Real physical printer/device/paper acceptance remains open and is the production activation blocker.
