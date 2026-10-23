/**
 * Centralized Theme Preference Service for NEXIAM
 *
 * Implemented using Svelte 5 runes for reactive state across all layouts and components.
 * Manages user preference ('light' | 'dark' | 'system') and resolved appearance ('light' | 'dark').
 * Synchronizes with DOM documentElement (.dark), localStorage ('theme'), cross-tab storage events,
 * and operating system prefers-color-scheme media queries.
 */

export type ThemePreference = 'light' | 'dark' | 'system';
export type ResolvedTheme = 'light' | 'dark';

const STORAGE_KEY = 'theme';

let preference = $state<ThemePreference>('system');
let override = $state<ThemePreference | null>(null);
let resolvedTheme = $state<ResolvedTheme>('light');

let mediaQueryList: MediaQueryList | null = null;
let mediaQueryListener: ((e: MediaQueryListEvent) => void) | null = null;
let storageListener: ((e: StorageEvent) => void) | null = null;
let isInitialized = false;

function normalizePreference(raw: string | null | undefined): ThemePreference {
    if (raw === 'dark' || raw === 'light' || raw === 'system') {
        return raw;
    }
    return 'system';
}

function getStoredPreference(): ThemePreference {
    if (typeof window === 'undefined') return 'system';
    try {
        return normalizePreference(window.localStorage?.getItem(STORAGE_KEY));
    } catch {
        return 'system';
    }
}

function getSystemDark(): boolean {
    if (typeof window === 'undefined') return false;
    try {
        return Boolean(
            window.matchMedia?.('(prefers-color-scheme: dark)')?.matches,
        );
    } catch {
        return false;
    }
}

function commitTheme(pref: ThemePreference, persist = false): void {
    const systemDark = getSystemDark();
    const effectivePreference = override ?? pref;
    const appearance: ResolvedTheme =
        effectivePreference === 'dark'
            ? 'dark'
            : effectivePreference === 'light'
              ? 'light'
              : systemDark
                ? 'dark'
                : 'light';

    preference = pref;
    resolvedTheme = appearance;

    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle(
            'dark',
            appearance === 'dark',
        );
    }

    if (persist && typeof window !== 'undefined') {
        try {
            window.localStorage?.setItem(STORAGE_KEY, pref);
        } catch {}
    }
}

function handleStorageEvent(e: StorageEvent): void {
    if (e.key === STORAGE_KEY || e.key === null) {
        const freshPref = getStoredPreference();
        commitTheme(freshPref, false);
    }
}

function handleMediaChange(e: MediaQueryListEvent): void {
    if ((override ?? preference) === 'system') {
        const appearance = e.matches ? 'dark' : 'light';
        resolvedTheme = appearance;
        if (typeof document !== 'undefined') {
            document.documentElement.classList.toggle(
                'dark',
                appearance === 'dark',
            );
        }
    }
}

export function initializeTheme(): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    if (isInitialized) {
        return;
    }

    const initialPref = getStoredPreference();
    commitTheme(initialPref, false);

    storageListener = handleStorageEvent;
    window.addEventListener('storage', storageListener);

    try {
        mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQueryListener = handleMediaChange;
        if (typeof mediaQueryList.addEventListener === 'function') {
            mediaQueryList.addEventListener('change', mediaQueryListener);
        } else if (typeof mediaQueryList.addListener === 'function') {
            mediaQueryList.addListener(mediaQueryListener);
        }
    } catch {}

    isInitialized = true;
}

export function disposeTheme(): void {
    if (typeof window !== 'undefined' && storageListener) {
        window.removeEventListener('storage', storageListener);
        storageListener = null;
    }

    if (mediaQueryList && mediaQueryListener) {
        try {
            if (typeof mediaQueryList.removeEventListener === 'function') {
                mediaQueryList.removeEventListener(
                    'change',
                    mediaQueryListener,
                );
            } else if (typeof mediaQueryList.removeListener === 'function') {
                mediaQueryList.removeListener(mediaQueryListener);
            }
        } catch {}
        mediaQueryListener = null;
        mediaQueryList = null;
    }

    isInitialized = false;
}

export function getThemePreference(): ThemePreference {
    return override ?? preference;
}

export function getResolvedTheme(): ResolvedTheme {
    return resolvedTheme;
}

export function isDarkMode(): boolean {
    return resolvedTheme === 'dark';
}

export function setThemePreference(pref: ThemePreference): void {
    override = null;
    commitTheme(normalizePreference(pref), true);
}

/** Apply a layout-local appearance without replacing the saved preference. */
export function setThemeOverride(pref: ThemePreference | null): void {
    override = pref;
    commitTheme(preference);
}

export function toggleTheme(): void {
    const nextAppearance: ThemePreference =
        resolvedTheme === 'dark' ? 'light' : 'dark';
    setThemePreference(nextAppearance);
}
