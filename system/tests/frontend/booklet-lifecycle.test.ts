// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mockRouterDelete = vi.fn();
const mockRouterPost = vi.fn();

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
            post: (...args: unknown[]) => mockRouterPost(...args),
        },
    };
});

import Booklets from '../../resources/js/Pages/Setup/Booklets.svelte';
import BookletsIndex from '../../resources/js/Pages/Setup/BookletsIndex.svelte';

describe('Booklet lifecycle actions', () => {
    beforeEach(() => {
        mockRouterDelete.mockClear();
        mockRouterPost.mockClear();
    });

    afterEach(() => {
        cleanup();
    });

    it('renders Discard button on draft booklet cards in BookletsIndex and opens confirmation dialog', async () => {
        const { getByRole, getByText } = render(BookletsIndex, {
            booklets: [{
                id: 10,
                name: 'Draft Booklet',
                lifecycle_status: 'draft',
                mode: 'answer_keys_only',
                can_discard: true,
                destroy_url: '/setup/exam-materials/booklets/10',
                url: '/setup/exam-materials/booklets/10',
                aptitude_count: 0,
                sets: [],
            }],
        });

        await fireEvent.click(getByRole('button', { name: 'Discard' }));
        expect(getByText('Discard draft for Draft Booklet?')).toBeTruthy();
        expect(getByRole('button', { name: 'Cancel' })).toBeTruthy();
        await fireEvent.click(getByRole('button', { name: 'Discard draft' }));

        expect(mockRouterDelete).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets/10',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('renders Archive button on official booklet cards in BookletsIndex and opens confirmation dialog', async () => {
        const { getByRole, getByText } = render(BookletsIndex, {
            booklets: [{
                id: 5,
                name: 'Official Booklet',
                lifecycle_status: 'official',
                mode: 'answer_keys_only',
                can_archive: true,
                archive_url: '/setup/exam-materials/booklets/5/archive',
                url: '/setup/exam-materials/booklets/5',
                aptitude_count: 0,
                sets: [],
            }],
        });

        await fireEvent.click(getByRole('button', { name: 'Archive' }));
        expect(getByText('Archive Official Booklet?')).toBeTruthy();
        expect(getByRole('button', { name: 'Cancel' })).toBeTruthy();
        await fireEvent.click(getByRole('button', { name: 'Archive Booklet' }));

        expect(mockRouterPost).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets/5/archive',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('renders Archived Booklets section with Restore button', async () => {
        const { getByRole, getByText } = render(BookletsIndex, {
            booklets: [{
                id: 5,
                name: 'Archived Booklet',
                lifecycle_status: 'archived',
                mode: 'answer_keys_only',
                can_restore: true,
                restore_url: '/setup/exam-materials/booklets/5/restore',
                url: '/setup/exam-materials/booklets/5',
                aptitude_count: 0,
                sets: [],
            }],
        });

        expect(getByText(/Archived Booklets/)).toBeTruthy();
        await fireEvent.click(getByRole('button', { name: 'Restore to Official' }));

        expect(mockRouterPost).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets/5/restore',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('renders Discard Draft button in Booklets.svelte workspace when in draft mode', () => {
        const { getByRole } = render(Booklets, {
            selected_booklet_id: 10,
            booklets: [{
                id: 10,
                name: 'Draft Booklet',
                lifecycle_status: 'draft',
                workspace: {
                    version_id: 10,
                    version: 1,
                    mode: 'answer_keys_only',
                    lifecycle_status: 'draft',
                    can_discard: true,
                    destroy_url: '/setup/exam-materials/booklets/10',
                    areas: [],
                    sets: [],
                    items: [],
                },
            }],
        });

        expect(getByRole('button', { name: 'Discard Draft' })).toBeTruthy();
    });
});
