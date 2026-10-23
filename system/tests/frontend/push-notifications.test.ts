// @vitest-environment jsdom
import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import {
  isPushSupported,
  isStandalonePwa,
  isIosSafari,
  getNotificationPermission,
  subscribeToPush,
  unsubscribeFromPush,
  urlBase64ToUint8Array,
} from '../../resources/js/lib/push-notifications';

describe('push-notifications helper', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('detects push support correctly', () => {
    // jsdom does not define serviceWorker or PushManager by default
    expect(isPushSupported()).toBe(false);
  });

  it('detects standalone PWA mode', () => {
    expect(isStandalonePwa()).toBe(false);
  });

  it('reads notification permission', () => {
    expect(getNotificationPermission()).toBe('default');
  });

  it('unsubscribes and calls DELETE endpoint when id provided', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ status: 'revoked' }) });
    global.fetch = fetchMock;

    const result = await unsubscribeFromPush('sub-uuid-123');
    expect(result.success).toBe(true);
    expect(fetchMock).toHaveBeenCalledWith('/notifications/push-subscriptions/sub-uuid-123', expect.objectContaining({
      method: 'DELETE',
    }));
  });

  it('converts 65-byte base64url key to valid 65-byte Uint8Array starting with 0x04', () => {
    const pubKey = 'BIncNZ6qP0jel1VDAxF1uDyQvnqII0Q1cl-tAyusfZLU0DBBSC-Jh_Wfs4GJEtbmluhsECnbG2-baWpYalkPdxk';
    const bytes = urlBase64ToUint8Array(pubKey);
    expect(bytes.length).toBe(65);
    expect(bytes[0]).toBe(4);
  });
});
