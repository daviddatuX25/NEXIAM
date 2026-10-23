// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import NewBookletDialog from '../../resources/js/Components/Setup/NewBookletDialog.svelte';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = actual.router;
    const routerBase = actualRouter && typeof actualRouter === 'object' ? actualRouter : {};

    return {
        ...actual,
        router: {
            ...routerBase,
            post: (...args: unknown[]) => post(...args),
        },
    };
});

afterEach(async () => {
    cleanup();
    await vi.runAllTimersAsync();
    vi.useRealTimers();
    post.mockReset();
});

async function closeCreatedDialog(options: { onSuccess: () => void }, unmount: () => void) {
    options.onSuccess();
    await tick();
    await vi.runAllTimersAsync();
    unmount();
    await vi.runAllTimersAsync();
}

describe('New Booklet dialog', () => {
    it('leaves an institutional Answer Sheet unselected for a synthetic preparation and posts no sheet', async () => {
        vi.useFakeTimers();
        const view = render(NewBookletDialog, {
            props: {
                answer_sheets: [{ id: 91, label: 'Institutional Answer Sheet v1' }],
                can_view_question_content: false,
                kit_context: {
                    test_kit_id: 6,
                    preparation_id: 14,
                    test_kit_version_id: null,
                    template_version_id: null,
                    authoring_booklet_version_id: null,
                    is_synthetic: true,
                    read_only: false,
                },
            },
        });

        await fireEvent.click(view.getByRole('button', { name: 'New Booklet' }));

        const answerSheet = view.getByLabelText('Matching Answer Sheet') as HTMLSelectElement;
        expect(answerSheet.value).toBe('');
        expect(view.getByRole('option', { name: 'No Answer Sheet selected (optional)' })).toBeTruthy();

        await fireEvent.click(view.getByRole('button', { name: 'Create Booklet' }));

        expect(post).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets?test_kit_id=6&preparation_id=14',
            {
                name: expect.any(String),
                authoring_mode: 'answer_keys_only',
                template_version_id: null,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
        const [, , options] = post.mock.calls.at(0) as [
            string,
            unknown,
            { onSuccess: () => void },
        ];
        await closeCreatedDialog(options, view.unmount);
    });

    it('retains the standalone single-choice Answer Sheet default', async () => {
        vi.useFakeTimers();
        const view = render(NewBookletDialog, {
            props: {
                answer_sheets: [{ id: 91, label: 'Institutional Answer Sheet v1' }],
                can_view_question_content: false,
            },
        });

        await fireEvent.click(view.getByRole('button', { name: 'New Booklet' }));
        expect(view.queryByRole('combobox', { name: 'Matching Answer Sheet' })).toBeNull();
        expect(view.getByText('Institutional Answer Sheet v1')).toBeTruthy();

        await fireEvent.click(view.getByRole('button', { name: 'Create Booklet' }));
        expect(post).toHaveBeenCalledWith(
            '/setup/exam-materials/booklets',
            expect.objectContaining({ template_version_id: 91 }),
            expect.any(Object),
        );
        const [, , options] = post.mock.calls.at(0) as [
            string,
            unknown,
            { onSuccess: () => void },
        ];
        await closeCreatedDialog(options, view.unmount);
    });
});
