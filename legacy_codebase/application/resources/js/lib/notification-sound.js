// Notification sound utility — delegates to notifStore (file-based Audio)
// Two tiers: 'background' (soft poll alert) and 'action' (direct user action)

import { playNotif } from '@/lib/notifStore.js';

/**
 * Play a context-aware notification sound.
 * @param {'background' | 'action'} tier
 *   - 'background': softer volume for poll-based notifications
 *   - 'action': full volume for direct user actions
 */
export function playChime(tier = 'background') {
  if (tier === 'background') {
    playNotif('background', 0.35);
  } else {
    playNotif('action', 0.85);
  }
}

/** @deprecated Use playChime('action') instead. */
export function playNotificationSound() {
  playChime('action');
}

/** @deprecated Use playChime('action') or playChime('background') instead. */
export function playSound() {
  playChime('action');
}