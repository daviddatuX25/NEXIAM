// Shared camera/upload capture input contract (OpenSpec 35.34).
// Pure module: no DOM, no network, no run/grading/qualification/applicant
// knowledge. OmrCaptureInput.svelte only submits a validated File upward;
// every acceptance/fallback decision lives here so it is testable in Node.

export const DEFAULT_ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];

/** Backend upload cap: `max:25600` KB (= 25 MB) on the test-capture route. */
export const DEFAULT_MAX_BYTES = 25 * 1024 * 1024;

export interface CaptureInputLabels {
  cameraLabel?: string;
  uploadLabel?: string;
  switchCameraLabel?: string;
  captureLabel?: string;
  runLabel?: string;
  retryLabel?: string;
  chooseLabel?: string;
  changeLabel?: string;
  dropHint?: string;
  acceptCopy?: string;
  disabledHint?: string;
}

export interface FileLike {
  name: string;
  size: number;
  type: string;
}

export interface FileValidation {
  ok: boolean;
  code: 'missing' | 'type' | 'size' | null;
  message: string;
}

/** Camera availability is a capability question, not a runtime one. */
export function supportsGetUserMedia(mediaDevices: unknown): boolean {
  if (!mediaDevices || typeof mediaDevices !== 'object') return false;
  return 'getUserMedia' in mediaDevices && typeof mediaDevices.getUserMedia === 'function';
}

/**
 * Camera-unavailable fallback decision: when camera support is missing or
 * disabled, the capture input must start (and remain usable) in upload mode.
 */
export function initialCaptureMode(options: { cameraEnabled: boolean; supports: boolean }): 'camera' | 'upload' {
  return options.cameraEnabled && options.supports ? 'camera' : 'upload';
}

export function cameraUnsupportedMessage(): { code: string; detail: string } {
  return {
    code: 'camera_unsupported',
    detail: 'Camera access is not supported on this browser/device — use the upload fallback.',
  };
}

export function validateFile(file: FileLike | null, options: { accept?: string[]; maxBytes?: number } = {}): FileValidation {
  if (!file) {
    return { ok: false, code: 'missing', message: 'No file was selected.' };
  }
  const accept = options.accept ?? DEFAULT_ACCEPT;
  if (accept.length > 0 && !accept.includes(file.type)) {
    return { ok: false, code: 'type', message: `${file.name} is not an accepted capture type.` };
  }
  const maxBytes = options.maxBytes ?? DEFAULT_MAX_BYTES;
  if (file.size > maxBytes) {
    return {
      ok: false,
      code: 'size',
      message: `${file.name} is ${formatBytes(file.size)} — the limit is ${formatBytes(maxBytes)}.`,
    };
  }
  return { ok: true, code: null, message: '' };
}

/** Human copy shown on the dropzone, e.g. "Accepts JPG, PNG, WebP · up to 25 MB". */
export function acceptedCopy(accept: string[], maxBytes: number): string {
  const DISPLAY_NAMES = { jpeg: 'JPG', jpg: 'JPG', png: 'PNG', webp: 'WebP', pdf: 'PDF', tiff: 'TIFF' };
  const kinds = accept.length
    ? accept
        .map((mime) => {
          const extension = mime.split('/')[1] ?? '';
          return DISPLAY_NAMES[extension] ?? extension.toUpperCase();
        })
        .join(', ')
    : 'Any image';
  return `Accepts ${kinds} · up to ${formatBytes(maxBytes)}`;
}

export function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 MB';
  const mb = bytes / (1024 * 1024);
  return Number.isInteger(mb) ? `${mb} MB` : `${mb.toFixed(1)} MB`;
}

export function snapshotFileName(timestamp: number): string {
  return `camera-snapshot-${timestamp}.jpg`;
}

/** Preflight display tone: validated | rejected | scan (not applicable) | null. */
export function preflightTone(preflight: unknown): 'validated' | 'rejected' | 'scan' | null {
  if (!preflight || typeof preflight !== 'object') return null;
  if ('mode' in preflight && preflight.mode === 'scan') return 'scan';
  if ('outcome' in preflight && preflight.outcome === 'validated') {
    return 'submit_allowed' in preflight && preflight.submit_allowed === true ? 'validated' : 'rejected';
  }
  if ('outcome' in preflight && typeof preflight.outcome === 'string') return 'rejected';
  return null;
}
