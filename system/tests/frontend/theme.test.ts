// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    disposeTheme,
    getResolvedTheme,
    getThemePreference,
    initializeTheme,
    isDarkMode,
    setThemePreference,
    setThemeOverride,
    toggleTheme,
} from '../../resources/js/lib/theme.svelte';

describe('theme service (system/resources/js/lib/theme.svelte.ts)', () => {
    let mediaQueryListeners: Array<(e: MediaQueryListEvent) => void> = [];
    let matchesDark = false;

    beforeEach(() => {
        mediaQueryListeners = [];
        matchesDark = false;
        localStorage.clear();
        document.documentElement.className = '';

        // Mock window.matchMedia
        window.matchMedia = vi.fn().mockImplementation((query: string) => {
            return {
                matches: query.includes('prefers-color-scheme: dark')
                    ? matchesDark
                    : false,
                media: query,
                onchange: null,
                addListener: vi.fn(),
                removeListener: vi.fn(),
                addEventListener: vi.fn(
                    (
                        event: string,
                        callback: (e: MediaQueryListEvent) => void,
                    ) => {
                        if (event === 'change') {
                            mediaQueryListeners.push(callback);
                        }
                    },
                ),
                removeEventListener: vi.fn(
                    (
                        event: string,
                        callback: (e: MediaQueryListEvent) => void,
                    ) => {
                        if (event === 'change') {
                            mediaQueryListeners = mediaQueryListeners.filter(
                                (cb) => cb !== callback,
                            );
                        }
                    },
                ),
                dispatchEvent: vi.fn(),
            };
        });
    });

    afterEach(() => {
        setThemeOverride(null);
        disposeTheme();
        localStorage.clear();
        document.documentElement.className = '';
        vi.restoreAllMocks();
    });

    it('initializes to dark when stored preference is "dark"', () => {
        localStorage.setItem('theme', 'dark');
        initializeTheme();

        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('initializes to light when stored preference is "light"', () => {
        localStorage.setItem('theme', 'light');
        initializeTheme();

        expect(getThemePreference()).toBe('light');
        expect(getResolvedTheme()).toBe('light');
        expect(isDarkMode()).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('initializes to system mode and resolves OS dark when stored preference is unset', () => {
        matchesDark = true;
        initializeTheme();

        expect(getThemePreference()).toBe('system');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('initializes to system mode and resolves OS light when stored preference is unset', () => {
        matchesDark = false;
        initializeTheme();

        expect(getThemePreference()).toBe('system');
        expect(getResolvedTheme()).toBe('light');
        expect(isDarkMode()).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('normalizes corrupt or arbitrary storage values to "system"', () => {
        localStorage.setItem('theme', 'arbitrary_hostile_payload');
        matchesDark = true;
        initializeTheme();

        expect(getThemePreference()).toBe('system');
        expect(getResolvedTheme()).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('setThemePreference updates state, DOM, and persists to localStorage', () => {
        initializeTheme();

        setThemePreference('dark');
        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');

        setThemePreference('light');
        expect(getThemePreference()).toBe('light');
        expect(getResolvedTheme()).toBe('light');
        expect(isDarkMode()).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('theme')).toBe('light');
    });

    it('toggleTheme switches resolved appearance and sets explicit preference', () => {
        // Start in light mode
        matchesDark = false;
        initializeTheme();
        expect(getResolvedTheme()).toBe('light');

        // Toggle to dark
        toggleTheme();
        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');

        // Toggle back to light
        toggleTheme();
        expect(getThemePreference()).toBe('light');
        expect(getResolvedTheme()).toBe('light');
        expect(isDarkMode()).toBe(false);
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(localStorage.getItem('theme')).toBe('light');
    });

    it('reacts to OS media-query changes when in system mode without writing back to localStorage', () => {
        matchesDark = false;
        initializeTheme();
        expect(getResolvedTheme()).toBe('light');
        expect(localStorage.getItem('theme')).toBeNull();

        // Simulate OS switching to dark
        matchesDark = true;
        for (const listener of mediaQueryListeners) {
            listener({ matches: true } as MediaQueryListEvent);
        }

        expect(getThemePreference()).toBe('system');
        expect(getResolvedTheme()).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        // CRITICAL: Must not persist OS state as a hard override in localStorage
        expect(localStorage.getItem('theme')).toBeNull();
    });

    it('ignores OS media-query changes when an explicit preference is active', () => {
        initializeTheme();
        setThemePreference('light');
        expect(localStorage.getItem('theme')).toBe('light');

        // OS switches to dark
        matchesDark = true;
        for (const listener of mediaQueryListeners) {
            listener({ matches: true } as MediaQueryListEvent);
        }

        // Must remain light
        expect(getThemePreference()).toBe('light');
        expect(getResolvedTheme()).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('synchronizes cross-tab storage events without re-persisting', () => {
        initializeTheme();
        expect(getResolvedTheme()).toBe('light');
        const setItemSpy = vi.spyOn(Storage.prototype, 'setItem');

        // Another tab set theme to dark
        localStorage.setItem('theme', 'dark');
        setItemSpy.mockClear();

        window.dispatchEvent(
            new StorageEvent('storage', {
                key: 'theme',
                newValue: 'dark',
                storageArea: localStorage,
            }),
        );
        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        // setItem should not have been called by the receiving tab's listener
        expect(setItemSpy).not.toHaveBeenCalled();
    });

    it('follows OS theme during a homepage override and restores the latest saved staff preference', () => {
        localStorage.setItem('theme', 'dark');
        initializeTheme();
        setThemeOverride('system');
        expect(getResolvedTheme()).toBe('light');
        expect(localStorage.getItem('theme')).toBe('dark');

        matchesDark = true;
        for (const listener of mediaQueryListeners) {
            listener({ matches: true } as MediaQueryListEvent);
        }
        expect(getResolvedTheme()).toBe('dark');

        localStorage.setItem('theme', 'light');
        window.dispatchEvent(new StorageEvent('storage', { key: 'theme' }));
        expect(getResolvedTheme()).toBe('dark');
        expect(getThemePreference()).toBe('system');

        setThemeOverride(null);
        expect(getResolvedTheme()).toBe('light');
        expect(getThemePreference()).toBe('light');
        expect(localStorage.getItem('theme')).toBe('light');
    });
    it('clears override and applies preference when setThemePreference is called', () => {
        initializeTheme();
        setThemeOverride('system');
        expect(getThemePreference()).toBe('system');

        setThemePreference('dark');
        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');
    });

    it('clears override and toggles appearance when toggleTheme is called', () => {
        matchesDark = false;
        initializeTheme();
        setThemeOverride('system');
        expect(getResolvedTheme()).toBe('light');

        toggleTheme();
        expect(getThemePreference()).toBe('dark');
        expect(getResolvedTheme()).toBe('dark');
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(localStorage.getItem('theme')).toBe('dark');
    });

    it('gracefully handles localStorage exceptions (e.g. private browsing)', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('Access denied in private browsing');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError or security block');
        });

        // Does not throw during initialization
        expect(() => initializeTheme()).not.toThrow();
        expect(getThemePreference()).toBe('system');

        // Does not throw during toggle
        expect(() => toggleTheme()).not.toThrow();
        expect(isDarkMode()).toBe(true);
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('disposeTheme removes listeners and prevents further reactions', () => {
        matchesDark = false;
        initializeTheme();
        disposeTheme();

        // Simulate storage event after disposal
        localStorage.setItem('theme', 'dark');
        window.dispatchEvent(
            new StorageEvent('storage', {
                key: 'theme',
                newValue: 'dark',
                storageArea: localStorage,
            }),
        );

        // State remains unchanged because listeners were removed
        expect(getResolvedTheme()).toBe('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
