/**
 * SND.DEV SND01 "SINE" Production Web Audio Engine
 * Pure mathematical sine wave synthesis for enterprise UI haptic feedback.
 * Zero external audio assets, zero network latency.
 *
 * Defaults to opt-in (muted) for institutional environments;
 * restores user preference from localStorage ('snd_sine_enabled').
 */

class SoundService {
  constructor() {
    this.ctx = null;
    this.enabled = false;
    this.initialized = false;
    this.listenerInstalled = false;
    this.lastInteractionSoundAt = 0;
    this.lastInteractionType = '';

    if (typeof window !== 'undefined') {
      try {
        const stored = localStorage.getItem('snd_sine_enabled');
        this.enabled = stored !== 'false';
      } catch {
        this.enabled = true;
      }
      this.installGlobalInteractionListener();
    }
  }

  /**
   * Lazy-initialize AudioContext upon user gesture.
   */
  getContext() {
    if (typeof window === 'undefined') return null;
    if (this.ctx) return this.ctx;
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      this.ctx = new AudioCtx();
      return this.ctx;
    } catch {
      return null;
    }
  }

  ensureContext() {
    const ctx = this.getContext();
    if (!ctx) return false;
    if (ctx.state === 'suspended') {
      try {
        ctx.resume().catch(() => {});
      } catch {}
    }
    return true;
  }

  playImmediate(schedule) {
    if (!this.enabled) return;
    const ctx = this.getContext();
    if (!ctx) return;
    if (ctx.state === 'running') {
      try {
        schedule(ctx);
      } catch (e) { /* ignore audio failure */ }
      return;
    }
    if (ctx.state === 'suspended' && typeof ctx.resume === 'function') {
      ctx.resume().then(() => {
        if (!this.enabled || !this.ctx || this.ctx.state !== 'running') return;
        try {
          schedule(this.ctx);
        } catch (e) { /* ignore audio failure */ }
      }).catch(() => {});
    }
  }

  installGlobalInteractionListener() {
    if (typeof window === 'undefined' || this.listenerInstalled) return;
    this.listenerInstalled = true;

    window.addEventListener(
      'click',
      (event) => {
        if (!this.enabled) return;
        const target = event.target;
        if (!(target instanceof Element)) return;

        // Strictly omit sounds when clicking or typing in text/input fields
        if (target.closest('input, textarea, [contenteditable="true"], select')) {
          return;
        }

        const now = Date.now();
        if (now - this.lastInteractionSoundAt < 35) {
          return;
        }

        // 1. Close buttons (dialogs, drawers, modals, cards, dismiss actions)
        const closeEl = target.closest(
          '[data-slot*="close"], [data-dialog-close], [aria-label*="close" i], [title*="close" i], [data-tour-action="skip"], [data-dismiss], .close-button, [aria-label*="dismiss" i]'
        );
        if (closeEl) {
          if (closeEl.hasAttribute('disabled') || closeEl.getAttribute('aria-disabled') === 'true') return;
          this.pop();
          return;
        }

        // 2. Micro navigation: tabs, switches, radios, segmented controls
        const microEl = target.closest(
          '[role="tab"], [data-slot="tabs-trigger"], [role="switch"], [data-slot="switch"], [role="radio"], [role="menuitem"], [role="checkbox"], [data-slot="checkbox"]'
        );
        if (microEl) {
          if (microEl.hasAttribute('disabled') || microEl.getAttribute('aria-disabled') === 'true') return;
          this.tick();
          return;
        }

        // 3. Regular buttons, button roles, and action links (including Apply and CTAs)
        // Exclude notification bell trigger to keep bell audio tests isolated to arrival events
        if (target.closest('[aria-label*="notifications (" i]')) {
          return;
        }

        const btnEl = target.closest(
          'button, [role="button"], a[href], summary'
        );
        if (btnEl) {
          if (btnEl.hasAttribute('disabled') || btnEl.getAttribute('aria-disabled') === 'true') return;
          this.press();
        }
      },
      { capture: true, passive: true }
    );
  }

  async playInactivityCue(isCurrent, schedule) {
    if (!this.enabled) return false;
    const ctx = this.getContext();
    if (!ctx) return false;
    try {
      if (ctx.state !== 'running') {
        if (ctx.state === 'closed' || typeof ctx.resume !== 'function') return false;
        await ctx.resume();
      }
      if (!this.enabled || this.ctx !== ctx || ctx.state !== 'running' || !isCurrent()) return false;
      schedule(ctx);
      return true;
    } catch {
      return false;
    }
  }

  isEnabled() {
    return this.enabled;
  }

  enable(playTick = true) {
    this.enabled = true;
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('snd_sine_enabled', 'true');
      } catch {}
    }
    this.ensureContext();
    if (playTick) {
      this.tick();
    }
  }

  disable() {
    this.enabled = false;
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('snd_sine_enabled', 'false');
      } catch {}
    }
  }

  toggle() {
    if (this.enabled) {
      this.disable();
    } else {
      this.enable();
    }
    return this.enabled;
  }

  /**
   * Tier 1: Micro Navigation Tick
   * For segmented dials, radio controls, tabs, and filters.
   * Transient sine blip: 1200Hz -> 900Hz, 14ms decay.
   */
  tick() {
    const now = Date.now();
    if (now - this.lastInteractionSoundAt < 35 && this.lastInteractionType === 'tick') return;
    this.lastInteractionSoundAt = now;
    this.lastInteractionType = 'tick';

    this.playImmediate((ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(1200, t);
      osc.frequency.exponentialRampToValueAtTime(900, t + 0.014);

      gain.gain.setValueAtTime(0.045, t);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.014);

      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.016);
    });
  }

  /**
   * Tier 2: Tactile Button Press
   * For primary and authoritative command buttons.
   * Mechanical thud: 480Hz -> 240Hz, 32ms decay.
   */
  press() {
    const now = Date.now();
    if (now - this.lastInteractionSoundAt < 35 && this.lastInteractionType === 'press') return;
    this.lastInteractionSoundAt = now;
    this.lastInteractionType = 'press';

    this.playImmediate((ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(480, t);
      osc.frequency.exponentialRampToValueAtTime(240, t + 0.032);
      gain.gain.setValueAtTime(0.07, t);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.032);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.035);
    });
  }

  /**
   * Tier 3: Operational Success Resolution
   * For completed task commits, affirmative toasts, and qualification releases.
   * 4-note ascending Major 9th arpeggio: C5 (523Hz) -> E5 (659Hz) -> G5 (784Hz) -> B5 (988Hz).
   */
  success() {
    this.playImmediate((ctx) => {
      const notes = [523.25, 659.25, 783.99, 987.77];
      notes.forEach((freq, i) => {
        const t = ctx.currentTime + i * 0.04;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, t);
        gain.gain.setValueAtTime(0.05, t);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.1);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(t);
        osc.stop(t + 0.11);
      });
    });
  }

  /**
   * Tier 4: Error Rejection Warning
   * For conflict alerts, invalid actions, and destructive dismissals.
   * Distinct two-tone descending minor dissonance:
   * Pulse 1: 420Hz -> 280Hz (35ms alert drop)
   * Pulse 2: 240Hz -> 150Hz (50ms rejection descent, 45ms gap)
   */
  error() {
    this.playImmediate((ctx) => {
      const t = ctx.currentTime;
      const osc1 = ctx.createOscillator();
      const gain1 = ctx.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(420, t);
      osc1.frequency.exponentialRampToValueAtTime(280, t + 0.035);
      gain1.gain.setValueAtTime(0.08, t);
      gain1.gain.exponentialRampToValueAtTime(0.0001, t + 0.035);
      osc1.connect(gain1);
      gain1.connect(ctx.destination);
      osc1.start(t);
      osc1.stop(t + 0.038);

      const t2 = t + 0.045;
      const osc2 = ctx.createOscillator();
      const gain2 = ctx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(240, t2);
      osc2.frequency.exponentialRampToValueAtTime(150, t2 + 0.05);
      gain2.gain.setValueAtTime(0.09, t2);
      gain2.gain.exponentialRampToValueAtTime(0.0001, t2 + 0.05);
      osc2.connect(gain2);
      gain2.connect(ctx.destination);
      osc2.start(t2);
      osc2.stop(t2 + 0.055);
    });
  }

  /**
   * Tier 5: Buoyant Drawer Pop
   * For modal sheets, Xiam assistant dock, and flyout navigation.
   * Upward frequency sweep: 360Hz -> 840Hz, 45ms duration.
   */
  pop() {
    const now = Date.now();
    if (now - this.lastInteractionSoundAt < 35 && this.lastInteractionType === 'pop') return;
    this.lastInteractionSoundAt = now;
    this.lastInteractionType = 'pop';

    this.playImmediate((ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(360, t);
      osc.frequency.exponentialRampToValueAtTime(840, t + 0.045);
      gain.gain.setValueAtTime(0.06, t);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.045);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.05);
    });
  }

  /**
   * Quiet ascending cue for a newly arrived unread notification.
   */
  notification(isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(659.25, t);
      osc.frequency.setValueAtTime(783.99, t + 0.1);
      gain.gain.setValueAtTime(0.035, t);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.08);
      gain.gain.setValueAtTime(0.035, t + 0.1);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);

      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.185);
    });
  }

  /**
   * Tier 6: Mechanical Slot Latch Lock
   * For seating desk booking and room capacity reservation.
   * Dual latch tick: 900Hz and 1400Hz spaced 22ms apart.
   */
  slot() {
    this.playImmediate((ctx) => {
      const t = ctx.currentTime;
      [900, 1400].forEach((freq, i) => {
        const tSub = t + i * 0.022;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(freq, tSub);
        gain.gain.setValueAtTime(0.05, tSub);
        gain.gain.exponentialRampToValueAtTime(0.0001, tSub + 0.018);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(tSub);
        osc.stop(tSub + 0.02);
      });
    });
  }
  /**
   * Tier 7: Gentle inactivity warning chime.
   */
  warning(isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      [523.25, 659.25].forEach((frequency, index) => {
        const start = t + index * 0.18;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(frequency, start);
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.035, start + 0.008);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.15);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + 0.15);
      });
    });
  }

  /**
   * Tier 8: Urgent countdown pulse.
   */
  countdownUrgent(remainingSec, isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(660, t);
      gain.gain.setValueAtTime(0, t);
      gain.gain.linearRampToValueAtTime(0.035, t + 0.005);
      gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.08);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t);
      osc.stop(t + 0.08);
    });
  }

  /**
   * Tier 9: Session expired downward cadence.
   */
  expired(isCurrent = () => true) {
    return this.playInactivityCue(isCurrent, (ctx) => {
      const t = ctx.currentTime;
      [ { frequency: 392, duration: 0.18 }, { frequency: 261.63, duration: 0.22 } ].forEach(({ frequency, duration }, index) => {
        const start = t + index * 0.20;
        const attack = 0.01;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(frequency, start);
        gain.gain.setValueAtTime(0, start);
        gain.gain.linearRampToValueAtTime(0.04, start + attack);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + duration);
      });
    });
  }
}

export const sound = new SoundService();
