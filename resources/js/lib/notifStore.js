// Simple Svelte 5 audio store for notification sounds
// Preloads Audio objects keyed by name, plays them on demand.

const sounds = new Map();

/**
 * Register a sound key → file path and preload it.
 * Call this on app mount so files are cached for instant playback.
 *
 * @param {Record<string, string>} map  e.g. { action: '/sounds/action.wav', background: '/sounds/background.mp3' }
 */
export function registerSounds(map) {
  for (const [key, src] of Object.entries(map)) {
    const audio = new Audio(src);
    audio.preload = 'auto';
    sounds.set(key, audio);
  }
}

/**
 * Play a registered sound by key.
 * Restarts from the beginning if already playing.
 *
 * @param {string} key  Sound key registered via registerSounds()
 * @param {number} [volume=1]  0..1
 */
export function playNotif(key, volume = 1) {
  const audio = sounds.get(key);
  if (!audio) return;

  audio.volume = Math.min(1, Math.max(0, volume));
  audio.currentTime = 0;
  audio.play().catch(() => {
    // Autoplay blocked until user gesture — silence is acceptable
  });
}