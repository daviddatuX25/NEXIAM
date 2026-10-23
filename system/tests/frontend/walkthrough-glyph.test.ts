// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import {
    walkthroughCoordinator,
    type WalkthroughProgressEnvelope,
} from '../../resources/js/lib/walkthrough/coordinator';

const mockedPage = vi.hoisted(() => ({
    component: 'Applicant/ReleaseDay',
    url: '/applicant/release-day',
    props: {
        walkthroughProgress: null as WalkthroughProgressEnvelope | null,
    },
}));

vi.mock('@inertiajs/svelte', () => ({
    usePage: () => mockedPage,
}));

import TourTriggerGlyph from '../../resources/js/Components/kit/Walkthrough/TourTriggerGlyph.svelte';

afterEach(() => {
    cleanup();
    mockedPage.props.walkthroughProgress = null;
});

it('registers one reachable contextual trigger despite synchronous coordinator notifications', () => {
    mockedPage.props.walkthroughProgress = {
        scope: {
            accountId: 'glyph-account',
            institutionId: 'glyph-institution',
            workspaceId: null,
            surface: 'applicant',
            key: 'glyph-registration-scope',
        },
        records: [],
        persistenceAvailable: false,
    };

    const bounds = vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
        x: 0,
        y: 0,
        width: 80,
        height: 32,
        top: 0,
        right: 80,
        bottom: 32,
        left: 0,
        toJSON: () => ({}),
    } as DOMRect);
    const openGuide = vi.fn();

    try {
        render(TourTriggerGlyph, {
            props: {
                tourKey: 'page:Applicant/ReleaseDay',
                onclick: openGuide,
            },
        });

        const button = screen.getByRole('button', { name: 'Xiam Guide & Tour' });
        const trigger = walkthroughCoordinator.matchingTrigger(
            'page:Applicant/ReleaseDay',
            'Applicant/ReleaseDay',
            '/applicant/release-day',
        );

        expect(trigger).not.toBeNull();
        expect(trigger?.element).toBe(button);
        trigger?.open();
        expect(openGuide).toHaveBeenCalledTimes(1);
    } finally {
        bounds.mockRestore();
    }
});
