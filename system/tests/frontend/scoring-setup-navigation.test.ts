// @vitest-environment jsdom

import { cleanup, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ScoringSetupNavigation from '../../resources/js/Components/Assessment/ScoringSetupNavigation.svelte';

afterEach(() => {
    cleanup();
});

describe('Scoring setup navigation', () => {
    it('renders the authorized setup destination and delegates an ordinary click', async () => {
        const onNavigate = vi.fn();
        const view = render(ScoringSetupNavigation, {
            props: {
                can_manage_catalog: true,
                setup_links: { test_kits: '/setup/exam-materials/test-kits' },
                onNavigate,
            },
        });
        const link = view.getByRole('link', { name: 'Test Setup' }) as HTMLAnchorElement;
        expect(link.getAttribute('href')).toBe('/setup/exam-materials/test-kits');

        const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(onNavigate).toHaveBeenCalledTimes(1);
        expect(onNavigate).toHaveBeenCalledWith({
            url: '/setup/exam-materials/test-kits',
            label: 'Test Setup',
        });
    });

    it('hides the setup destination when catalog management is denied', () => {
        const view = render(ScoringSetupNavigation, {
            props: {
                can_manage_catalog: false,
                setup_links: { test_kits: '/setup/exam-materials/test-kits' },
                onNavigate: vi.fn(),
            },
        });

        expect(view.queryByRole('link', { name: 'Test Setup' })).toBeNull();
    });

    it.each([
        ['ctrl', { ctrlKey: true }],
        ['meta', { metaKey: true }],
        ['shift', { shiftKey: true }],
        ['alt', { altKey: true }],
        ['non-left', { button: 1 }],
    ])('does not guard a %s click', async (_kind, options) => {
        const onNavigate = vi.fn();
        const view = render(ScoringSetupNavigation, {
            props: {
                can_manage_catalog: true,
                setup_links: { test_kits: '/setup/exam-materials/test-kits' },
                onNavigate,
            },
        });
        const link = view.getByRole('link', { name: 'Test Setup' });
        const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...options });
        link.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(onNavigate).not.toHaveBeenCalled();
    });
});
