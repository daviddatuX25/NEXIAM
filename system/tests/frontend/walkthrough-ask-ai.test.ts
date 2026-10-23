// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { resolveTour } from '../../resources/js/lib/walkthrough/resolver';
import { getTour } from '../../resources/js/lib/walkthrough/tours';
import type { TourDefinition } from '../../resources/js/lib/walkthrough/types';

const mocks = vi.hoisted(() => ({
    page: {
        props: {
            auth: { user: null as Record<string, unknown> | null },
            xiam: null as Record<string, unknown> | null,
        },
    },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => mocks.page,
    };
});

import XiamNpcDialog from '../../resources/js/Components/kit/Walkthrough/XiamNpcDialog.svelte';

describe('Walkthrough "Ask Xiam AI" conditional display', () => {
    afterEach(() => {
        cleanup();
    });

    beforeEach(() => {
        window.matchMedia = vi.fn().mockReturnValue({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });
        mocks.page.props.auth.user = null;
        mocks.page.props.xiam = null;
    });
    describe('Tour and Step definitions', () => {
        it('resolves allowAskAi from tour definition to resolved tour and steps', () => {
            const def: TourDefinition = {
                id: 'test-public-tour',
                revision: 1,
                title: 'Test Public Tour',
                start: 'step-1',
                allowAskAi: false,
                steps: [
                    {
                        id: 'step-1',
                        title: 'Step 1',
                        text: 'Hello world',
                        advance: {
                            kind: 'next',
                            destination: { kind: 'complete' },
                        },
                    },
                    {
                        id: 'step-2',
                        title: 'Step 2 with override',
                        text: 'Hello again',
                        allowAskAi: true,
                        advance: {
                            kind: 'next',
                            destination: { kind: 'complete' },
                        },
                    },
                ],
            };

            const resolved = resolveTour(def);
            expect(resolved.allowAskAi).toBe(false);
            expect(resolved.steps[0].allowAskAi).toBe(false);
            expect(resolved.steps[1].allowAskAi).toBe(true);
        });

        it('marks public application tours with allowAskAi = false', () => {
            const applyTour = getTour('applicant-apply-guide');
            expect(applyTour).not.toBeNull();
            expect(applyTour!.allowAskAi).toBe(false);

            const safetyTour = getTour('applicant-access-key-safety');
            expect(safetyTour).not.toBeNull();
            expect(safetyTour!.allowAskAi).toBe(false);
        });
    });

    describe('XiamNpcDialog component', () => {
        it('does NOT render "Ask Xiam AI" button on public/guest sites when user is unauthenticated', () => {
            mocks.page.props.auth.user = null;
            mocks.page.props.xiam = null;

            render(XiamNpcDialog, {
                tourKey: 'staff-operations-dashboard',
                open: true,
            });

            expect(screen.getByText('Skip Tour')).toBeTruthy();
            expect(screen.queryByText('Ask Xiam AI')).toBeNull();
        });

        it('does NOT render "Ask Xiam AI" button on public tours even if authenticated', () => {
            mocks.page.props.auth.user = { id: 1, name: 'Staff User' };
            mocks.page.props.xiam = {
                contract_version: '2',
                availability: 'assistant_active',
            };

            render(XiamNpcDialog, {
                tourKey: 'applicant-apply-guide',
                open: true,
            });

            expect(screen.getByText('Skip Tour')).toBeTruthy();
            expect(screen.queryByText('Ask Xiam AI')).toBeNull();
        });

        it('renders "Ask Xiam AI" button on authenticated staff pages with active Xiam session', () => {
            mocks.page.props.auth.user = { id: 1, name: 'Staff User' };
            mocks.page.props.xiam = {
                contract_version: '2',
                availability: 'assistant_active',
            };

            render(XiamNpcDialog, {
                tourKey: 'staff-operations-dashboard',
                open: true,
            });

            expect(screen.getByText('Skip Tour')).toBeTruthy();
            expect(screen.getByText('Ask Xiam AI')).toBeTruthy();
        });

        it('respects explicit canAskAi={false} prop override even if authenticated with active Xiam', () => {
            mocks.page.props.auth.user = { id: 1, name: 'Staff User' };
            mocks.page.props.xiam = {
                contract_version: '2',
                availability: 'assistant_active',
            };

            render(XiamNpcDialog, {
                tourKey: 'staff-operations-dashboard',
                open: true,
                canAskAi: false,
            });

            expect(screen.getByText('Skip Tour')).toBeTruthy();
            expect(screen.queryByText('Ask Xiam AI')).toBeNull();
        });

        it('respects explicit canAskAi={true} prop override even if unauthenticated', () => {
            mocks.page.props.auth.user = null;
            mocks.page.props.xiam = null;

            render(XiamNpcDialog, {
                tourKey: 'staff-operations-dashboard',
                open: true,
                canAskAi: true,
            });

            expect(screen.getByText('Skip Tour')).toBeTruthy();
            expect(screen.getByText('Ask Xiam AI')).toBeTruthy();
        });
    });
});
