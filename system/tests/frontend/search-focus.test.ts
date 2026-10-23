// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { applySearchFocus } from '../../resources/js/lib/search-focus';

const originalMatchMedia = Object.getOwnPropertyDescriptor(window, 'matchMedia');

afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    if (originalMatchMedia) {
        Object.defineProperty(window, 'matchMedia', originalMatchMedia);
    } else {
        delete (window as Partial<Window>).matchMedia;
    }
});

function setReducedMotionPreference(matches: boolean): void {
    Object.defineProperty(window, 'matchMedia', {
        configurable: true,
        value: vi.fn().mockReturnValue({ matches }),
    });
}

function createSearchTarget(id: string): HTMLElement {
    const element = document.createElement('div');
    element.id = id;
    Object.defineProperty(element, 'scrollIntoView', {
        configurable: true,
        value: vi.fn(),
    });
    document.body.append(element);

    return element;
}

describe('applySearchFocus', () => {
    it('returns gracefully when the target element is not found', () => {
        setReducedMotionPreference(false);

        expect(() => applySearchFocus('missing-search-result')).not.toThrow();
        expect(window.matchMedia).not.toHaveBeenCalled();
    });

    it('scrolls to, makes focusable, focuses, and highlights the target', () => {
        setReducedMotionPreference(false);
        const element = createSearchTarget('search-result');
        const scrollIntoView = vi.mocked(element.scrollIntoView);
        const focus = vi.spyOn(element, 'focus');

        applySearchFocus('search-result');

        expect(scrollIntoView).toHaveBeenCalledWith({
            block: 'center',
            inline: 'nearest',
            behavior: 'smooth',
        });
        expect(element.getAttribute('tabindex')).toBe('-1');
        expect(focus).toHaveBeenCalledWith({ preventScroll: true });
        expect(element.classList.contains('ring-2')).toBe(true);
        expect(element.classList.contains('ring-primary')).toBe(true);
    });

    it('uses non-animated scrolling and highlighting when reduced motion is preferred', () => {
        setReducedMotionPreference(true);
        const element = createSearchTarget('search-result');
        const scrollIntoView = vi.mocked(element.scrollIntoView);

        applySearchFocus('search-result');

        expect(scrollIntoView).toHaveBeenCalledWith({
            block: 'center',
            inline: 'nearest',
            behavior: 'auto',
        });
        expect(element.classList.contains('ring-2')).toBe(true);
        expect(element.classList.contains('ring-primary')).toBe(true);
        expect(element.classList.contains('bg-primary/10')).toBe(false);
    });
});
