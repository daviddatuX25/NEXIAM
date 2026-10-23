import { describe, expect, it } from 'vitest';
import {
    disposeTheme,
    getResolvedTheme,
    getThemePreference,
    initializeTheme,
    isDarkMode,
    setThemePreference,
    toggleTheme,
} from '../../resources/js/lib/theme.svelte';

describe('theme service server/headless safety (node environment)', () => {
    it('safely imports and runs getters without window or document', () => {
        expect(typeof window).toBe('undefined');
        expect(typeof document).toBe('undefined');

        expect(getThemePreference()).toBe('system');
        expect(getResolvedTheme()).toBe('light');
        expect(isDarkMode()).toBe(false);
    });

    it('safely handles initializeTheme, setThemePreference, toggleTheme, and disposeTheme without throwing', () => {
        expect(() => initializeTheme()).not.toThrow();
        expect(() => setThemePreference('dark')).not.toThrow();
        expect(() => toggleTheme()).not.toThrow();
        expect(() => disposeTheme()).not.toThrow();
    });
});
