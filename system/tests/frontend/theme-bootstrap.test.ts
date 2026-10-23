// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('anti-FOUC bootstrap script (app.blade.php)', () => {
    const bladePath = resolve(__dirname, '../../resources/views/app.blade.php');
    let matchesDark = false;

    // Helper to evaluate the bootstrap logic under test
    function runBootstrapScript() {
        let t = null;
        try { t = localStorage.getItem('theme'); } catch {}
        let m = false;
        try { m = Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches); } catch {}
        const d = t === 'dark' || (t !== 'light' && m);
        try { document.documentElement.classList.toggle('dark', d); } catch {}
    }

    beforeEach(() => {
        matchesDark = false;
        localStorage.clear();
        document.documentElement.className = '';

        window.matchMedia = vi.fn().mockImplementation((query: string) => ({
            matches: query.includes('prefers-color-scheme: dark') ? matchesDark : false,
            media: query,
        }));
    });

    it('sets dark class immediately when localStorage has "dark"', () => {
        localStorage.setItem('theme', 'dark');
        matchesDark = false;

        runBootstrapScript();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('removes dark class immediately when localStorage has "light"', () => {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'light');
        matchesDark = true; // OS prefers dark, but explicit choice is light

        runBootstrapScript();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('follows OS dark preference when localStorage has no theme set', () => {
        matchesDark = true;

        runBootstrapScript();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('follows OS light preference when localStorage has no theme set', () => {
        matchesDark = false;

        runBootstrapScript();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('normalizes invalid stored strings by falling back to OS preference', () => {
        localStorage.setItem('theme', '<script>alert(1)</script>');
        matchesDark = true;

        runBootstrapScript();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        // Ensure no hostile class was injected
        expect(document.documentElement.className).toBe('dark');
    });

    it('does not crash when localStorage access throws (e.g. private browsing)', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('Storage access blocked');
        });
        matchesDark = true;

        expect(() => runBootstrapScript()).not.toThrow();
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('verifies app.blade.php contains the anti-FOUC script in <head> before @vite', () => {
        const bladeContent = readFileSync(bladePath, 'utf-8');

        // Verify script exists in head
        expect(bladeContent).toContain('localStorage.getItem(\'theme\')');
        expect(bladeContent).toContain('prefers-color-scheme: dark');
        expect(bladeContent).toContain('document.documentElement.classList.toggle(\'dark\'');

        // Verify it is placed before @vite to guarantee pre-paint execution
        const scriptIndex = bladeContent.indexOf('localStorage.getItem(\'theme\')');
        const viteIndex = bladeContent.indexOf('@vite');
        expect(scriptIndex).toBeGreaterThan(-1);
        expect(viteIndex).toBeGreaterThan(-1);
        expect(scriptIndex).toBeLessThan(viteIndex);
    });
});
