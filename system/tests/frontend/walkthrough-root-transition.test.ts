// @vitest-environment jsdom
import { getContext } from 'svelte';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', () => ({
    Link: () => null,
    http: { onResponse: () => () => undefined },
    router: {},
    usePage: () => getContext('walkthrough-test-page'),
}));
vi.mock('@/Components/kit/Xiam/XiamDock.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));
vi.mock('@/Components/Wayfinding/WayfindingEventBridge.svelte', () => ({ default: () => null }));
vi.mock('@/lib/staff-account-generation', () => ({
    createStaffGenerationLifecycle: () => ({
        update: () => undefined,
        updateFromInertiaResponse: () => undefined,
        dispose: () => undefined,
    }),
    installStaffGenerationGuard: () => () => undefined,
}));

import WalkthroughRootTransitionHarness from './WalkthroughRootTransitionHarness.svelte';
import WalkthroughTransitionProbe from './WalkthroughTransitionProbe.svelte';

afterEach(() => cleanup());

it('defers safety reactions out of a reactive page transition effect', async () => {
    const errors: ErrorEvent[] = [];
    const handleError = (event: ErrorEvent): void => {
        errors.push(event);
    };
    window.addEventListener('error', handleError);

    try {
        render(WalkthroughRootTransitionHarness, {
            props: { Page: WalkthroughTransitionProbe },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Navigate to next page' }));
        await waitFor(() => {
            expect(screen.getByTestId('transition-probe').textContent)
                .toContain('/walkthrough-next-transition-probe');
        });
        await new Promise<void>((resolve) => queueMicrotask(resolve));

        expect(errors.map((event) => event.error?.message ?? event.message).join('\n'))
            .not.toMatch(/state_unsafe_mutation|unsafe mutation/i);
    } finally {
        window.removeEventListener('error', handleError);
    }
});
