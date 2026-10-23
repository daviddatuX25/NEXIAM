# NEXIAM Modular QR & Camera Intake Subsystem (`@/Components/qr`)

## 1. Purpose & Architectural Principles
The `@/Components/qr` module provides a single, authoritative, hardware-safe QR intake and camera scanning infrastructure across NEXIAM. Any surface requiring QR scanning, examinee lookup, or camera intake must adopt these components rather than writing custom `getUserMedia` or detector loops.

### Inviolable Invariants:
1. **Hardware Stream Lifecycle & Zero Leaks:**
   - Every `getUserMedia` call is bound to a generation token (`sessionToken`). If a modal is dismissed or switched to manual input while hardware spin-up is in flight, the resulting stream tracks are **immediately terminated synchronously** (`stream.getTracks().forEach(t => t.stop())`). The physical camera indicator LED must never stay illuminated after close.
   - The `<video>` element is **permanently mounted** in the DOM to avoid null-reference race conditions during stream attachment.
   - The background tab event (`visibilitychange`) automatically releases the camera when the browser tab is hidden and safely re-acquires it when visible.
2. **True Centering (0.0px Offset):**
   - The camera container must strictly use `w-full max-w-[440px] mx-auto`. Avoid unconstrained aspect-ratio wrappers that cause horizontal drift or asymmetric layout skews.
3. **Stable Height (Zero Layout Jitter):**
   - Modal bodies must enforce `min-h-[380px] max-h-[520px]` with `overflow-y-auto`. Toggling between `Camera Scanner` and `Manual Code` must never cause the dialog height to jump or jitter.
4. **Decoupled Segmented Controls:**
   - Mode toggles (`Camera Scanner` vs `Manual Search/Code`) must live in a dedicated tactile segmented control bar (`grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1`) placed **beneath** the dialog title/description, reserving `pr-8` or `pr-10` clearance so it never collides with the top-right `[X]` close button.
5. **Deduplicated Fallback Entry:**
   - When in camera mode, never render a redundant full-text search bar above the viewfinder. Provide only the camera feed, accompanied by a single fallback card beneath it: `"Can't scan QR? Enter Applicant Number"`.

---

## 2. Component Inventory

### A. Headless/Viewfinder Component: `QrScanner.svelte`
Mounts the camera stream, runs hardware-accelerated `BarcodeDetector`, renders the alignment reticle, and handles haptic/visual feedback.

```svelte
<script>
    import { QrScanner } from '@/Components/qr';
</script>

<div class="w-full max-w-[440px] mx-auto">
    <QrScanner
        active={isOpen && currentMode === 'camera'}
        paused={isProcessing}
        onScan={(code) => handleScannedCode(code)}
        onError={(err) => handleError(err)}
    />
</div>
```

**Props:**
- `active` (`boolean`): Master stream toggle. Setting to `false` halts the camera immediately and frees device tracks.
- `paused` (`boolean`): Keeps the camera running but temporarily suppresses barcode frame detection while an async action or network request resolves.
- `facingMode` (`'environment' | 'user'`, default `'environment'`).
- `onScan` (`(code: string) => void`): Invoked upon reliable QR detection (debounced by 1500ms for duplicate frames).
- `onError` (`(error: string) => void`): Invoked on permission denial or unreadable device hardware.

---

### B. High-Level Modular Dialog: `UniversalQrModal.svelte`
Pre-packaged modal with locked centering, tactile segmented controls, stable height, error recovery, and dual-mode input.

```svelte
<script>
    import { UniversalQrModal } from '@/Components/qr';
    let scanModalOpen = $state(false);
</script>

<UniversalQrModal
    open={scanModalOpen}
    onOpenChange={(v) => (scanModalOpen = v)}
    mode="callback"
    title="Scan Door Pass"
    description="Align the examinee QR pass within the viewfinder."
    onScan={(code, { close, reset }) => {
        // Handle code
        close();
    }}
/>
```

**Operating Modes:**
1. **`mode="callback"` (Recommended for Actions & Forms):**
   Fires `onScan(code, { close, reset })`. Ideal for proctor door check-ins, custom verification flows, or populating form inputs without automatic navigation.
2. **`mode="redirect"`:**
   Posts the scanned code to `resolveUrl` (`/staff/scan/resolve`), verifies identity, and redirects to the target workspace or destination URL.
3. **`continuous={true}` (Batch Attendance):**
   Prevents auto-closing on successful scan; flashes green reticle feedback, pulses haptics, and resets for rapid consecutive scanning.

---

### C. Shared Staff Kit Modal: `ApplicantScanModal.svelte`
Pre-configured for cross-office staff intake (`openScanModal()`). Incorporates the server-authorized destination chooser for resolved candidates with an explicit `[Scan another applicant]` reset button.

---

## 3. Adapter Guide for Future Extenders

When implementing QR or camera intake in a new module:
1. **Never call `navigator.mediaDevices.getUserMedia` directly.** Always import `QrScanner` or `UniversalQrModal`.
2. **Do not create ad-hoc aspect-ratio boxes.** Always nest the scanner inside a container with `max-w-[440px] mx-auto`.
3. **Handle both Camera and Manual entry.** Real-world campus conditions include scratched printed sheets, low-light corridors, or broken device cameras. Every scanner surface must provide a manual code entry fallback.
