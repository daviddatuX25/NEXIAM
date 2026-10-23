// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import {
  closeStaffIdleAlert,
  disableStaffIdleAlerts,
  enableStaffIdleAlertsFromUserGesture,
  getStaffIdleAlertStatus,
  isStaffIdleAlertEnabled,
  showStaffIdleAlert,
} from '../../resources/js/lib/staff-idle-alerts.svelte';

const preferenceKey = 'nexiam.staff.idle.browser_alerts_enabled';
const notificationTag = 'nexiam-staff-session-inactivity-warning';
let notificationPermission: NotificationPermission = 'default';
let requestPermission: Mock<() => Promise<NotificationPermission>>;
let notificationInstances: MockNotification[] = [];

class MockNotification {
  static get permission(): NotificationPermission {
    return notificationPermission;
  }

  static requestPermission = vi.fn<() => Promise<NotificationPermission>>();
  onclick: ((this: Notification, event: Event) => unknown) | null = null;
  close = vi.fn();

  constructor(public title: string, public options: NotificationOptions) {
    notificationInstances.push(this);
  }
}

const generation = () => `staff-session-${crypto.randomUUID()}`;

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
  notificationPermission = 'default';
  notificationInstances = [];
  requestPermission = vi.fn(async () => {
    notificationPermission = 'granted';
    return notificationPermission;
  });
  MockNotification.requestPermission = requestPermission;
  vi.stubGlobal('Notification', MockNotification);
  Object.defineProperty(window, 'isSecureContext', { configurable: true, value: true });
  Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'visible' });
  vi.spyOn(window, 'focus').mockImplementation(() => undefined);
});

afterEach(() => {
  closeStaffIdleAlert();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('staff idle browser alerts', () => {
  it('defaults off and does not request permission until the explicit enable method', async () => {
    expect(isStaffIdleAlertEnabled()).toBe(false);
    expect(getStaffIdleAlertStatus()).toBe('permission-required');
    expect(requestPermission).not.toHaveBeenCalled();
    expect(localStorage.getItem(preferenceKey)).toBeNull();

    await enableStaffIdleAlertsFromUserGesture();

    expect(requestPermission).toHaveBeenCalledOnce();
    expect(isStaffIdleAlertEnabled()).toBe(true);
    expect(getStaffIdleAlertStatus()).toBe('enabled');
    expect(localStorage.getItem(preferenceKey)).toBe('true');
  });

  it('reports unsupported for insecure contexts and missing Notification API', () => {
    Object.defineProperty(window, 'isSecureContext', { configurable: true, value: false });
    expect(getStaffIdleAlertStatus()).toBe('unsupported');
    expect(isStaffIdleAlertEnabled()).toBe(false);

    Object.defineProperty(window, 'isSecureContext', { configurable: true, value: true });
    vi.stubGlobal('Notification', undefined);
    expect(getStaffIdleAlertStatus()).toBe('unsupported');
  });

  it('does not opt in when permission is denied', async () => {
    notificationPermission = 'denied';
    expect(getStaffIdleAlertStatus()).toBe('permission-denied');

    expect(await enableStaffIdleAlertsFromUserGesture()).toBe('permission-denied');
    expect(requestPermission).not.toHaveBeenCalled();
    expect(isStaffIdleAlertEnabled()).toBe(false);
    expect(localStorage.getItem(preferenceKey)).toBeNull();
  });

  it('only notifies while hidden and includes generic content with a stable tag', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    const currentGeneration = generation();

    showStaffIdleAlert(currentGeneration, 'episode-1');
    expect(notificationInstances).toHaveLength(0);

    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    showStaffIdleAlert(currentGeneration, 'episode-1');

    expect(notificationInstances).toHaveLength(1);
    expect(notificationInstances[0].title).toBe('Session inactivity warning');
    expect(notificationInstances[0].options).toEqual({
      body: 'Your NEXIAM staff session will expire soon. Return to the tab to stay signed in.',
      tag: notificationTag,
    });
  });

  it('deduplicates notifications per warning episode and allows a renewed episode', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    const currentGeneration = generation();

    showStaffIdleAlert(currentGeneration, 'episode-1');
    showStaffIdleAlert(currentGeneration, 'episode-1');
    expect(notificationInstances).toHaveLength(1);

    closeStaffIdleAlert();
    showStaffIdleAlert(currentGeneration, 'episode-2');
    expect(notificationInstances).toHaveLength(2);

    showStaffIdleAlert(currentGeneration, 'episode-1');
    expect(notificationInstances).toHaveLength(2);
  });

  it('does not replay a dismissed warning after a same-generation module reload', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    const currentGeneration = generation();
    showStaffIdleAlert(currentGeneration, 'episode-reload');
    closeStaffIdleAlert();
    vi.resetModules();
    // A static import cannot exercise a fresh module's state after a page reload.
    const reloadedAlerts = await import('../../resources/js/lib/staff-idle-alerts.svelte');
    reloadedAlerts.showStaffIdleAlert(currentGeneration, 'episode-reload');
    expect(notificationInstances).toHaveLength(1);
    reloadedAlerts.showStaffIdleAlert(currentGeneration, 'episode-renewed');
    expect(notificationInstances).toHaveLength(2);
    reloadedAlerts.closeStaffIdleAlert();
  });

  it('keeps warning episode deduplication scoped to its authentication generation', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });

    showStaffIdleAlert('generation-a', 'episode-1');
    showStaffIdleAlert('generation-b', 'episode-1');

    expect(notificationInstances).toHaveLength(2);
  });

  it('ignores missing generation or warning episode identifiers', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });

    showStaffIdleAlert('', 'episode-1');
    showStaffIdleAlert('generation-a', '');

    expect(notificationInstances).toHaveLength(0);
  });

  it('notification click requests focus without keepalive or session renewal', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    const currentGeneration = generation();
    showStaffIdleAlert(currentGeneration, 'episode-1');

    notificationInstances[0].onclick?.call(notificationInstances[0] as unknown as Notification, new Event('click'));

    expect(notificationInstances[0].close).toHaveBeenCalledOnce();
    expect(window.focus).toHaveBeenCalledOnce();
    expect(fetch).not.toHaveBeenCalled();
    expect(localStorage.getItem(preferenceKey)).toBe('true');
  });

  it('explicitly closes the active notification and disables future alerts', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    showStaffIdleAlert(generation(), 'episode-1');
    const notification = notificationInstances[0];

    closeStaffIdleAlert();
    closeStaffIdleAlert();
    expect(notification.close).toHaveBeenCalledOnce();

    disableStaffIdleAlerts();
    expect(isStaffIdleAlertEnabled()).toBe(false);
    expect(localStorage.getItem(preferenceKey)).toBeNull();
  });

  it('treats blocked localStorage as disabled without throwing', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('Blocked');
    });
    expect(isStaffIdleAlertEnabled()).toBe(false);
    expect(getStaffIdleAlertStatus()).toBe('permission-required');
  });

  it('surfaces browsers that reject non-persistent notification construction as unsupported', async () => {
    await enableStaffIdleAlertsFromUserGesture();
    Object.defineProperty(document, 'visibilityState', { configurable: true, value: 'hidden' });
    vi.stubGlobal('Notification', class {
      static permission = 'granted';
      static requestPermission = vi.fn();

      constructor() {
        throw new TypeError('Persistent notifications required');
      }
    });

    showStaffIdleAlert(generation(), 'episode-1');

    expect(getStaffIdleAlertStatus()).toBe('unsupported');
    expect(isStaffIdleAlertEnabled()).toBe(false);
  });
});
