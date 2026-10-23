// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import NewAnswerSheetDialog from '../../resources/js/Components/Setup/NewAnswerSheetDialog.svelte';

afterEach(() => cleanup());

describe('NewAnswerSheetDialog', () => {
    it('opens with the recommended explicit paper policy and one page', async () => {
        const view = render(NewAnswerSheetDialog, {
            create_url: '/answer-sheets',
        });

        await fireEvent.click(
            view.getByRole('button', { name: 'New Answer Sheet' }),
        );
        await waitFor(() => expect(view.getByRole('dialog')).not.toBeNull());
        expect(
            (
                view.getByRole('radio', {
                    name: /Standard Paper \/ Document Boundary/,
                }) as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (
                view.getByRole('radio', {
                    name: /No production QR/,
                }) as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (view.getByRole('radio', { name: '1 Page' }) as HTMLInputElement)
                .checked,
        ).toBe(true);
        expect(
            (
                view.getByRole('button', {
                    name: 'Create and open Studio',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(false);
    });

    it('lets staff choose anchored registration, signed QR, and four pages', async () => {
        const view = render(NewAnswerSheetDialog, {
            create_url: '/answer-sheets',
        });

        await fireEvent.click(
            view.getByRole('button', { name: 'New Answer Sheet' }),
        );
        await fireEvent.click(
            view.getByRole('radio', { name: /High-Precision Corner Markers/ }),
        );
        await fireEvent.click(
            view.getByRole('radio', { name: /Signed production QR/ }),
        );
        await fireEvent.click(view.getByRole('radio', { name: '4 Pages' }));
        expect(
            (
                view.getByRole('radio', {
                    name: /High-Precision Corner Markers/,
                }) as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (
                view.getByRole('radio', {
                    name: /Signed production QR/,
                }) as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (view.getByRole('radio', { name: '4 Pages' }) as HTMLInputElement)
                .checked,
        ).toBe(true);
    });
});
