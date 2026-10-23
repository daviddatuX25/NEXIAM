// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrCaptureInput from '../../resources/js/Components/Omr/Diagnostics/OmrCaptureInput.svelte';
import {
  acceptedCopy,
  cameraUnsupportedMessage,
  DEFAULT_ACCEPT,
  DEFAULT_MAX_BYTES,
  formatBytes,
  initialCaptureMode,
  preflightTone,
  snapshotFileName,
  supportsGetUserMedia,
  validateFile,
} from '../../resources/js/lib/omrCaptureInput.js';

describe('OmrCaptureInput contract', () => {
  it('accepts JPEG/PNG/WebP files within the size cap', () => {
    const jpeg = { name: 'sheet.jpg', size: 1024 * 1024, type: 'image/jpeg' };
    const png = { name: 'sheet.png', size: 1024 * 1024, type: 'image/png' };
    const webp = { name: 'sheet.webp', size: 1024 * 1024, type: 'image/webp' };
    expect(validateFile(jpeg, {})).toEqual({ ok: true, code: null, message: '' });
    expect(validateFile(png, {})).toEqual({ ok: true, code: null, message: '' });
    expect(validateFile(webp, {})).toEqual({ ok: true, code: null, message: '' });
    expect(validateFile(null, {})).toMatchObject({ ok: false, code: 'missing' });
  });

  it('rejects unsupported MIME types with a typed message', () => {
    const result = validateFile({ name: 'sheet.pdf', size: 100, type: 'application/pdf' }, { accept: ['image/jpeg', 'image/png', 'image/webp'] });
    expect(result).toMatchObject({ ok: false, code: 'type' });
    expect(result.message).toContain('sheet.pdf');
    // Scanned PDF is allowed when the host accepts it.
    expect(validateFile({ name: 'scan.pdf', size: 100, type: 'application/pdf' }, { accept: ['image/jpeg', 'application/pdf'] }).ok).toBe(true);
  });

  it('rejects oversized captures with the 25 MB cap in the copy', () => {
    const result = validateFile({ name: 'huge.png', size: DEFAULT_MAX_BYTES + 1, type: 'image/png' }, {});
    expect(result).toMatchObject({ ok: false, code: 'size' });
    expect(result.message).toContain('25 MB');
    expect(validateFile({ name: 'ok.png', size: DEFAULT_MAX_BYTES, type: 'image/png' }, {}).ok).toBe(true);
  });

  it('falls back to upload when camera support is unavailable or disabled', () => {
    expect(supportsGetUserMedia({ getUserMedia: () => Promise.resolve({}) })).toBe(true);
    expect(supportsGetUserMedia({})).toBe(false);
    expect(supportsGetUserMedia(null)).toBe(false);
    expect(initialCaptureMode({ cameraEnabled: true, supports: true })).toBe('camera');
    expect(initialCaptureMode({ cameraEnabled: true, supports: false })).toBe('upload');
    expect(initialCaptureMode({ cameraEnabled: false, supports: true })).toBe('upload');
    expect(cameraUnsupportedMessage()).toMatchObject({ code: 'camera_unsupported' });
  });

  it('renders the accepted-MIME/size copy for hosts with and without PDF', () => {
    expect(acceptedCopy(['image/jpeg', 'image/png', 'image/webp'], DEFAULT_MAX_BYTES)).toBe('Accepts JPG, PNG, WebP · up to 25 MB');
    expect(acceptedCopy(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], DEFAULT_MAX_BYTES)).toBe('Accepts JPG, PNG, WebP, PDF · up to 25 MB');
    expect(acceptedCopy(DEFAULT_ACCEPT, DEFAULT_MAX_BYTES)).toBe('Accepts JPG, PNG, WebP · up to 25 MB');
    expect(formatBytes(DEFAULT_MAX_BYTES)).toBe('25 MB');
    expect(formatBytes(512 * 1024)).toBe('0.5 MB');
    expect(formatBytes(0)).toBe('0 MB');
  });

  it('classifies server preflight display tones', () => {
    expect(preflightTone({ mode: 'scan' })).toBe('scan');
    expect(preflightTone({ outcome: 'validated', submit_allowed: true })).toBe('validated');
    expect(preflightTone({ outcome: 'validated', submit_allowed: false })).toBe('rejected');
    expect(preflightTone({ outcome: 'rejected', code: 'marker_missing' })).toBe('rejected');
    expect(preflightTone(null)).toBeNull();
    expect(preflightTone({})).toBeNull();
  });

  it('names camera snapshots deterministically per timestamp', () => {
    expect(snapshotFileName(1712345678901)).toBe('camera-snapshot-1712345678901.jpg');
  });
});


afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('OmrCaptureInput presentation contract', () => {
  it('keeps file selection local until one explicit submit callback and supports cancel', async () => {
    const submit = vi.fn();
    const file = new File(['capture'], 'sheet.png', { type: 'image/png' });
    const { container, getByRole } = render(OmrCaptureInput, { submit });
    const input = container.querySelector('input[type=file]');
    expect(input?.getAttribute('accept')).toBe('image/jpeg,image/png,image/webp');
    expect(getByRole('button', { name: /Choose Photo or Drag & Drop/ })).not.toBeNull();

    await fireEvent.change(input!, { target: { files: [file] } });
    expect(submit).not.toHaveBeenCalled();
    expect(container.textContent).toContain('sheet.png');
    await fireEvent.click(getByRole('button', { name: 'Run Test Capture' }));
    expect(submit).toHaveBeenCalledTimes(1);
    expect(submit).toHaveBeenCalledWith(file);

    await fireEvent.click(getByRole('button', { name: 'Clear' }));
    expect(container.textContent).not.toContain('sheet.png');
    expect(submit).toHaveBeenCalledTimes(1);
  });

  it('handles drop, typed MIME/size errors, disabled state, and preflight feedback', async () => {
    const submit = vi.fn();
    const { container, getByRole } = render(OmrCaptureInput, {
      submit,
      preflight: { outcome: 'validated', submit_allowed: true },
    });
    const dropzone = getByRole('button', { name: /Choose Photo or Drag & Drop/ });
    const invalid = new File(['not image'], 'sheet.pdf', { type: 'application/pdf' });
    await fireEvent.drop(dropzone, { dataTransfer: { files: [invalid] } });
    expect(getByRole('alert').textContent).toContain('sheet.pdf');
    expect(submit).not.toHaveBeenCalled();
    expect(getByRole('status').textContent).toContain('Preflight validated');

    cleanup();
    const disabled = render(OmrCaptureInput, { disabled: true, disabledReason: 'Print the test sheet first.' });
    expect(disabled.getByRole('note').textContent).toContain('Print the test sheet first.');
    expect((disabled.getByRole('button', { name: /Choose Photo or Drag & Drop/ }) as HTMLButtonElement).disabled).toBe(true);
    expect(disabled.container.innerHTML).not.toContain('data:');
  });

  it('owns camera start, switch, stop, and upload fallback without exposing a second submit path', async () => {
    const track = { stop: vi.fn() };
    const stream = { getTracks: () => [track] };
    const getUserMedia = vi.fn(async () => stream);
    Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia } });
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
    const submit = vi.fn();
    const { getByRole, unmount } = render(OmrCaptureInput, { submit });

    await fireEvent.click(getByRole('button', { name: 'Device Camera' }));
    await waitFor(() => expect((getByRole('button', { name: 'Switch Camera' }) as HTMLButtonElement).disabled).toBe(false));
    expect(getUserMedia).toHaveBeenCalledWith(expect.objectContaining({ video: expect.objectContaining({ facingMode: 'environment' }) }));
    await fireEvent.click(getByRole('button', { name: 'Switch Camera' }));
    await waitFor(() => expect(getUserMedia).toHaveBeenCalledTimes(2));
    expect(getUserMedia.mock.calls[1][0]).toEqual(expect.objectContaining({ video: expect.objectContaining({ facingMode: 'user' }) }));
    expect(submit).not.toHaveBeenCalled();
    unmount();
    expect(track.stop).toHaveBeenCalled();

    cleanup();
    Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: undefined });
    const fallback = render(OmrCaptureInput, { submit });
    await fireEvent.click(fallback.getByRole('button', { name: 'Device Camera' }));
    expect(fallback.getByRole('alert').textContent).toContain('Camera unavailable');
    await fireEvent.click(fallback.getByRole('button', { name: 'Use file upload instead' }));
    expect(fallback.getByRole('button', { name: /Choose Photo or Drag & Drop/ })).not.toBeNull();
  });
});
