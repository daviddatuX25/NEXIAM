// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mockRouterDelete = vi.fn();

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};
    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: {
                    user: { id: 1, name: 'Guidance Counselor', email: 'guidance.counselor@nexiam.test' },
                },
            },
        }),
        router: {
            ...actualRouter,
            delete: (...args: unknown[]) => mockRouterDelete(...args),
        },
    };
});

import Index from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/Index.svelte';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';

describe('Answer Sheet Draft Discard Flow', () => {
    beforeEach(() => {
        mockRouterDelete.mockClear();
    });

    afterEach(() => {
        cleanup();
    });

    it('renders Discard button on Index page only for eligible drafts and handles confirmation', async () => {
        const drafts = [
            {
                id: 101,
                code: 'ELIGIBLE-1',
                name: 'Eligible Draft',
                stage: 'draft',
                can_edit: true,
                can_discard: true,
                destroy_url: '/setup/exam-materials/answer-sheets/101',
                url: '/setup/exam-materials/answer-sheets/101/edit',
            },
            {
                id: 102,
                code: 'INELIGIBLE-1',
                name: 'Verified Draft',
                stage: 'digital_verified',
                can_edit: true,
                can_discard: false,
                destroy_url: null,
                url: '/setup/exam-materials/answer-sheets/102/edit',
            },
        ];

        const { getByRole, queryByRole, getByText, queryByText } = render(Index, {
            drafts,
            create_url: '/setup/exam-materials/answer-sheets',
        });

        // Eligible draft has Discard button
        const discardBtn = getByRole('button', { name: 'Discard Eligible Draft draft' });
        expect(discardBtn).toBeDefined();

        // Ineligible draft does NOT have Discard button
        expect(queryByRole('button', { name: 'Discard Verified Draft draft' })).toBeNull();

        // Clicking Discard opens the modal
        await fireEvent.click(discardBtn);
        expect(getByText('Discard Answer Sheet Draft?')).toBeDefined();
        expect(getByText('ELIGIBLE-1 · Eligible Draft')).toBeDefined();

        // Clicking Cancel closes modal without deleting
        const cancelBtn = getByRole('button', { name: 'Cancel' });
        await fireEvent.click(cancelBtn);
        expect(mockRouterDelete).not.toHaveBeenCalled();

        // Re-open and confirm discard
        await fireEvent.click(discardBtn);
        const confirmBtn = getByRole('button', { name: 'Yes, Discard Draft' });
        await fireEvent.click(confirmBtn);

        expect(mockRouterDelete).toHaveBeenCalledWith(
            '/setup/exam-materials/answer-sheets/101',
            expect.objectContaining({ preserveScroll: true })
        );
    });

    it('renders Discard button in Studio header and confirms deletion', async () => {
        const draft = {
            id: 201,
            code: 'STUDIO-DRAFT',
            name: 'Studio Test Sheet',
            stage: 'draft',
            can_discard: true,
            source_kind: 'html',
            authoring: {
                studio: {
                    current_stage: 1,
                    stage1Mode: 'html',
                    components: [],
                    sections: [],
                },
            },
        };

        const { getByRole, getByText, queryByText } = render(OmrTemplateStudio, {
            draft,
            marker_profiles: [],
            aptitude_areas: [],
            urls: {
                studio: '/setup/exam-materials/answer-sheets/201',
                destroy: '/setup/exam-materials/answer-sheets/201',
            },
        });

        // Discard button in header
        const discardHeaderBtn = getByRole('button', { name: 'Discard Studio Test Sheet draft' });
        expect(discardHeaderBtn).toBeDefined();

        // Clicking opens studio confirmation modal
        await fireEvent.click(discardHeaderBtn);
        expect(getByText('Permanently discard this draft and exit the studio?')).toBeDefined();

        // Clicking Cancel keeps draft
        const cancelBtn = getByRole('button', { name: 'Cancel & Keep Draft' });
        await fireEvent.click(cancelBtn);
        expect(mockRouterDelete).not.toHaveBeenCalled();

        // Re-open and confirm
        await fireEvent.click(discardHeaderBtn);
        const confirmBtn = getByRole('button', { name: 'Yes, Discard Draft' });
        await fireEvent.click(confirmBtn);

        expect(mockRouterDelete).toHaveBeenCalledWith(
            '/setup/exam-materials/answer-sheets/201',
            expect.any(Object)
        );
    });
});
