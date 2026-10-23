/**
 * Platform-neutral keyboard shortcut dispatcher with input-focus isolation
 * and command routing for document studio editors.
 * Zero domain, framework, or rendering dependencies.
 */

import type {
  KeyBinding,
  StudioCommand,
  StudioKeyHandlers,
  StudioKeymapConfig,
} from './types.js';

/**
 * Detects if keyboard focus is currently inside a text input, textarea, select,
 * or contenteditable element. When true, canvas shortcuts must not intercept keystrokes.
 */
export function isInputFocused(event: Event | { target: unknown }): boolean {
  const target = event.target as HTMLElement | null;
  if (!target || typeof target !== 'object') {
    return false;
  }

  const tagName = target.tagName ? target.tagName.toUpperCase() : '';
  if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
    return true;
  }

  if (target.isContentEditable) {
    return true;
  }

  if (typeof target.getAttribute === 'function') {
    const contentEditable = target.getAttribute('contenteditable');
    if (contentEditable === 'true' || contentEditable === '') {
      return true;
    }

    const role = target.getAttribute('role');
    if (role === 'textbox' || role === 'searchbox') {
      return true;
    }
  }

  if (typeof target.closest === 'function') {
    if (target.closest('[contenteditable="true"], [role="textbox"], [role="searchbox"]')) {
      return true;
    }
  }

  return false;
}

/**
 * Checks if the platform modifier key is pressed (Ctrl on Windows/Linux, Cmd on macOS).
 */
export function isMod(event: KeyboardEvent): boolean {
  return event.ctrlKey || event.metaKey;
}

/**
 * Default keyboard shortcuts for studio document editors.
 */
export const DEFAULT_KEY_BINDINGS: readonly KeyBinding[] = [
  // History
  { key: 'z', mod: true, shift: false, command: 'undo' },
  { key: 'z', mod: true, shift: true, command: 'redo' },
  { key: 'y', mod: true, shift: false, command: 'redo' },

  // Selection & Lifecycle
  { key: 'a', mod: true, shift: false, command: 'selectAll' },
  { key: 'Escape', command: 'escape' },
  { key: 'Escape', command: 'deselect' },
  { key: 'd', mod: true, shift: false, command: 'duplicate' },
  { key: 'Delete', command: 'delete' },
  { key: 'Backspace', command: 'delete' },

  // Nudge (1mm) - Safe with key repeats
  { key: 'ArrowUp', shift: false, mod: false, command: 'nudgeUp', allowRepeat: true },
  { key: 'ArrowDown', shift: false, mod: false, command: 'nudgeDown', allowRepeat: true },
  { key: 'ArrowLeft', shift: false, mod: false, command: 'nudgeLeft', allowRepeat: true },
  { key: 'ArrowRight', shift: false, mod: false, command: 'nudgeRight', allowRepeat: true },

  // Large Nudge (10mm with Shift) - Safe with key repeats
  { key: 'ArrowUp', shift: true, mod: false, command: 'nudgeUpLarge', allowRepeat: true },
  { key: 'ArrowDown', shift: true, mod: false, command: 'nudgeDownLarge', allowRepeat: true },
  { key: 'ArrowLeft', shift: true, mod: false, command: 'nudgeLeftLarge', allowRepeat: true },
  { key: 'ArrowRight', shift: true, mod: false, command: 'nudgeRightLarge', allowRepeat: true },

  // Layer Ordering
  { key: ']', mod: true, shift: false, command: 'bringForward' },
  { key: '[', mod: true, shift: false, command: 'sendBackward' },
  { key: 'ArrowUp', mod: true, shift: false, command: 'bringForward' },
  { key: 'ArrowDown', mod: true, shift: false, command: 'sendBackward' },
  { key: ']', mod: true, shift: true, command: 'bringToFront' },
  { key: '[', mod: true, shift: true, command: 'sendToBack' },
  { key: 'ArrowUp', mod: true, shift: true, command: 'bringToFront' },
  { key: 'ArrowDown', mod: true, shift: true, command: 'sendToBack' },

  // Lock
  { key: 'l', mod: true, shift: false, command: 'toggleLock' },
  { key: 'l', mod: true, shift: true, command: 'unlock' },

  // Help
  { key: '?', shift: true, command: 'help' },
  { key: '/', mod: true, command: 'help' },
  { key: 'F1', command: 'help' },
];

/**
 * Matches a KeyboardEvent against a list of key bindings.
 */
export function matchKeyBinding(
  event: KeyboardEvent,
  bindings: readonly KeyBinding[] = DEFAULT_KEY_BINDINGS
): KeyBinding | null {
  const modPressed = isMod(event);
  const shiftPressed = Boolean(event.shiftKey);
  const altPressed = Boolean(event.altKey);
  const eventKey = event.key;

  for (const binding of bindings) {
    // Check modifier requirements
    if (binding.mod !== undefined && binding.mod !== modPressed) {
      continue;
    }
    if (binding.shift !== undefined && binding.shift !== shiftPressed) {
      continue;
    }
    if (binding.alt !== undefined && binding.alt !== altPressed) {
      continue;
    }

    // Key match: case-insensitive for single alpha letters when Mod is held,
    // exact match for named keys (Escape, ArrowUp, Delete, etc.)
    const keyMatches =
      binding.key.toLowerCase() === eventKey.toLowerCase() ||
      binding.key === eventKey;

    if (keyMatches) {
      return binding;
    }
  }

  return null;
}

/**
 * Handles a keydown event, verifying input isolation and invoking matching command handlers.
 * Returns true if a command was handled and default prevented.
 */
export function handleKeyDown(
  event: KeyboardEvent,
  handlers: StudioKeyHandlers,
  options: {
    bindings?: readonly KeyBinding[];
    stopPropagation?: boolean;
  } = {}
): boolean {
  // Never intercept keys when typing in form inputs or during IME composition
  if (isInputFocused(event) || event.isComposing || event.keyCode === 229) {
    return false;
  }

  const bindings = options.bindings ?? DEFAULT_KEY_BINDINGS;
  const binding = matchKeyBinding(event, bindings);

  if (!binding) {
    return false;
  }

  // Guard against unsafe repeats on destructive / action commands
  if (event.repeat && !binding.allowRepeat) {
    return false;
  }

  const handler = handlers[binding.command];
  if (typeof handler === 'function') {
    const result = handler(event);
    // If handler explicitly returned false, do not prevent default
    if (result !== false) {
      event.preventDefault();
      if (options.stopPropagation) {
        event.stopPropagation();
      }
      return true;
    }
  }

  return false;
}

/**
 * Class wrapper for configurable keyboard shortcut dispatching.
 */
export class StudioKeymap {
  private bindings: KeyBinding[];
  private handlers: StudioKeyHandlers;

  constructor(config: StudioKeymapConfig = {}) {
    this.bindings = config.bindings ? config.bindings.slice() : DEFAULT_KEY_BINDINGS.slice();
    this.handlers = config.handlers ? { ...config.handlers } : {};
  }

  public getBindings(): KeyBinding[] {
    return this.bindings.slice();
  }

  public setBindings(bindings: readonly KeyBinding[]): this {
    this.bindings = bindings.slice();
    return this;
  }

  public register(binding: KeyBinding): this {
    this.bindings.unshift(binding);
    return this;
  }

  public unregister(command: StudioCommand): this {
    this.bindings = this.bindings.filter((b) => b.command !== command);
    return this;
  }

  public setHandlers(handlers: StudioKeyHandlers): this {
    this.handlers = { ...handlers };
    return this;
  }

  public updateHandlers(handlers: StudioKeyHandlers): this {
    this.handlers = { ...this.handlers, ...handlers };
    return this;
  }

  public handle(
    event: KeyboardEvent,
    overrideHandlers?: StudioKeyHandlers,
    stopPropagation = false
  ): boolean {
    const activeHandlers = overrideHandlers
      ? { ...this.handlers, ...overrideHandlers }
      : this.handlers;

    return handleKeyDown(event, activeHandlers, {
      bindings: this.bindings,
      stopPropagation,
    });
  }
}

/**
 * Factory helper for creating StudioKeymap instances.
 */
export function createStudioKeymap(config?: StudioKeymapConfig): StudioKeymap {
  return new StudioKeymap(config);
}
