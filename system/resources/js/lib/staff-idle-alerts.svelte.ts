export type StaffIdleAlertStatus = 'enabled' | 'disabled' | 'permission-required' | 'permission-denied' | 'unsupported';

const preferenceKey = 'nexiam.staff.idle.browser_alerts_enabled';
const notificationTitle = 'Session inactivity warning';
const notificationBody = 'Your NEXIAM staff session will expire soon. Return to the tab to stay signed in.';
const notificationTag = 'nexiam-staff-session-inactivity-warning';
const notifiedEpisodeKey = 'nexiam.staff.idle.last_notified_episode';

let activeNotification: Notification | null = null;
let deliveryUnavailable = false;
const notifiedWarningEpisodes = new Set<string>();

function browserApisAvailable(): boolean {
  try {
    return typeof window !== 'undefined'
      && window.isSecureContext === true
      && typeof Notification !== 'undefined';
  } catch {
    return false;
  }
}

function hasOptIn(): boolean {
  try {
    return window.localStorage.getItem(preferenceKey) === 'true';
  } catch {
    return false;
  }
}

function storeOptIn(enabled: boolean): boolean {
  try {
    if (enabled) {
      window.localStorage.setItem(preferenceKey, 'true');
    } else {
      window.localStorage.removeItem(preferenceKey);
    }
    return true;
  } catch {
    return false;
  }
}

export function isStaffIdleAlertEnabled(): boolean {
  if (deliveryUnavailable || !browserApisAvailable() || !hasOptIn()) {
    return false;
  }

  try {
    return Notification.permission === 'granted';
  } catch {
    return false;
  }
}

export function getStaffIdleAlertStatus(): StaffIdleAlertStatus {
  if (deliveryUnavailable || !browserApisAvailable()) {
    return 'unsupported';
  }

  try {
    if (Notification.permission === 'denied') {
      return 'permission-denied';
    }
    if (Notification.permission === 'default') {
      return 'permission-required';
    }
    return hasOptIn() ? 'enabled' : 'disabled';
  } catch {
    return 'unsupported';
  }
}

export async function enableStaffIdleAlertsFromUserGesture(): Promise<StaffIdleAlertStatus> {
  if (!browserApisAvailable()) {
    return 'unsupported';
  }

  try {
    let permission = Notification.permission;
    if (permission === 'default') {
      permission = await Notification.requestPermission();
    }

    if (permission === 'denied') {
      storeOptIn(false);
      return 'permission-denied';
    }
    if (permission !== 'granted') {
      storeOptIn(false);
      return 'permission-required';
    }

    return storeOptIn(true) ? 'enabled' : 'disabled';
  } catch {
    storeOptIn(false);
    return getStaffIdleAlertStatus();
  }
}

export function disableStaffIdleAlerts(): void {
  storeOptIn(false);
  closeStaffIdleAlert();
}

export function showStaffIdleAlert(generation: string, warningEpisodeId: string): void {
  if (!generation || !warningEpisodeId || !isStaffIdleAlertEnabled()) {
    return;
  }

  const notificationId = JSON.stringify([generation, warningEpisodeId]);
  if (notifiedWarningEpisodes.has(notificationId)) {
    return;
  }
  try {
    if (window.sessionStorage.getItem(notifiedEpisodeKey) === notificationId) {
      return;
    }
  } catch {
    // In-memory deduplication remains available when session storage is blocked.
  }

  try {
    if (document.visibilityState !== 'hidden') {
      return;
    }

    if (activeNotification) {
      closeStaffIdleAlert();
    }

    const notification = new Notification(notificationTitle, {
      body: notificationBody,
      tag: notificationTag,
    });
    activeNotification = notification;
    notifiedWarningEpisodes.add(notificationId);
    try {
      window.sessionStorage.setItem(notifiedEpisodeKey, notificationId);
    } catch {
      // Storage availability must not block a best-effort alert.
    }
    notification.onclick = () => {
      try {
        notification.close();
      } catch {
        // Browser notification lifecycle can be unavailable or already closed.
      }
      if (activeNotification === notification) {
        activeNotification = null;
      }
      try {
        window.focus();
      } catch {
        // Focus is only a best-effort request.
      }
    };
  } catch {
    deliveryUnavailable = true;
    // Some browsers expose the API but reject non-persistent notifications.
  }
}

export function closeStaffIdleAlert(): void {
  const notification = activeNotification;
  activeNotification = null;

  if (notification) {
    try {
      notification.close();
    } catch {
      // Closing an already-dismissed notification is harmless.
    }
  }
}
