/**
 * Browser Web Push subscription and lifecycle helper.
 */

export function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

export function isPushSupported(): boolean {
  return typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;
}

export function isStandalonePwa(): boolean {
  const isMediaStandalone = typeof window.matchMedia === 'function' && window.matchMedia('(display-mode: standalone)').matches;
  const nav = window.navigator;
  const isNavigatorStandalone = 'standalone' in nav && Boolean((nav as { standalone?: boolean }).standalone);
  return isMediaStandalone || isNavigatorStandalone;
}

export function isIosSafari(): boolean {
  if (typeof window === 'undefined') return false;
  const ua = window.navigator.userAgent;
  const isIos = /iPad|iPhone|iPod/.test(ua);
  const isWebkit = /WebKit/.test(ua);
  return isIos && isWebkit && !isStandalonePwa();
}

export function getNotificationPermission(): NotificationPermission {
  if (typeof window === 'undefined' || !('Notification' in window)) {
    return 'default';
  }
  return Notification.permission;
}

async function getOrRegisterServiceWorker(): Promise<ServiceWorkerRegistration | null> {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
    return null;
  }
  try {
    let reg = await navigator.serviceWorker.getRegistration();
    if (!reg) {
      reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    }
    if (reg.active) {
      return reg;
    }
    const { promise, resolve } = typeof Promise.withResolvers === 'function'
      ? Promise.withResolvers<ServiceWorkerRegistration>()
      : (() => {
          let res!: (val: ServiceWorkerRegistration) => void;
          const p = new Promise<ServiceWorkerRegistration>((r) => { res = r; });
          return { promise: p, resolve: res };
        })();
    setTimeout(() => resolve(reg as ServiceWorkerRegistration), 2000);
    return await Promise.race([navigator.serviceWorker.ready, promise]);
  } catch {
    return null;
  }
}

export async function getCurrentSubscription(): Promise<PushSubscription | null> {
  if (!isPushSupported()) return null;
  try {
    const reg = await getOrRegisterServiceWorker();
    if (!reg) return null;
    return await reg.pushManager.getSubscription();
  } catch {
    return null;
  }
}

export async function subscribeToPush(): Promise<{ success: boolean; id?: string; error?: string }> {
  if (!isPushSupported()) {
    return { success: false, error: 'Push notifications are not supported on this browser.' };
  }

  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      return { success: false, error: 'Notification permission was not granted.' };
    }

    const vapidRes = await fetch('/notifications/push-subscriptions/vapid-key');
    if (!vapidRes.ok) {
      return { success: false, error: 'Failed to retrieve VAPID application key.' };
    }
    const { public_key } = await vapidRes.json();

    const reg = await getOrRegisterServiceWorker();
    if (!reg) {
      return { success: false, error: 'Could not register background service worker.' };
    }
    let subscription = await reg.pushManager.getSubscription();
    if (!subscription) {
      subscription = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(public_key),
      });
    }

    const subJson = subscription.toJSON();
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const token = csrfMeta instanceof HTMLMetaElement ? csrfMeta.content : '';

    const storeRes = await fetch('/notifications/push-subscriptions', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        endpoint: subscription.endpoint,
        p256dh: subJson.keys?.p256dh,
        auth: subJson.keys?.auth,
      }),
    });

    if (!storeRes.ok) {
      let serverError = 'Failed to register subscription with the server.';
      try {
        const errorJson = await storeRes.json();
        if (typeof errorJson?.message === 'string') {
          serverError = errorJson.message;
        }
      } catch {}
      return { success: false, error: serverError };
    }

    const data = await storeRes.json();
    return { success: true, id: data.id };
  } catch (err: unknown) {
    const message = err instanceof Error ? err.message : String(err);
    return { success: false, error: message };
  }
}

export async function unsubscribeFromPush(subscriptionId?: string): Promise<{ success: boolean }> {
  try {
    if (subscriptionId) {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const token = csrfMeta instanceof HTMLMetaElement ? csrfMeta.content : '';
      await fetch(`/notifications/push-subscriptions/${subscriptionId}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
    }

    if (isPushSupported()) {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        await sub.unsubscribe();
      }
    }

    return { success: true };
  } catch {
    return { success: false };
  }
}
