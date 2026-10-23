// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import FormulaExpressionInput from '../../resources/js/Components/Setup/FormulaExpressionInput.svelte';

afterEach(() => cleanup());

describe('FormulaExpressionInput', () => {
    it('inserts a function pill at the caret and leaves the caret inside its parentheses', async () => {
        const onValueChange = vi.fn();
        const view = render(FormulaExpressionInput, {
            props: {
                id: 'formula-expression',
                value: '',
                variableCodes: ['A_RAW'],
                onValueChange,
            },
        });
        const textarea = view.getByRole('combobox') as HTMLTextAreaElement;
        textarea.setSelectionRange(0, 0);
        await fireEvent.click(
            view.getByRole('button', { name: 'Insert ROUND function' }),
        );

        expect(textarea.value).toBe('ROUND(, 0)');
        expect(textarea.selectionStart).toBe(6);
        expect(textarea.selectionEnd).toBe(6);
    });

    it('supports bounded autocomplete with arrow navigation, Enter, and Escape', async () => {
        const onValueChange = vi.fn();
        const view = render(FormulaExpressionInput, {
            props: {
                id: 'formula-expression',
                value: '',
                variableCodes: ['A_RAW', 'B_RAW'],
                onValueChange,
            },
        });
        const textarea = view.getByRole('combobox') as HTMLTextAreaElement;
        await fireEvent.keyDown(textarea, { key: ' ', ctrlKey: true });
        await fireEvent.keyUp(textarea, { key: ' ', ctrlKey: true });
        await fireEvent.keyUp(textarea, { key: 'Control' });
        expect(
            view.getByRole('listbox', {
                name: 'Formula autocomplete suggestions',
            }),
        ).toBeTruthy();
        await fireEvent.keyDown(textarea, { key: 'ArrowDown' });
        await fireEvent.keyUp(textarea, { key: 'ArrowDown' });
        await fireEvent.keyDown(textarea, { key: 'Enter' });
        await fireEvent.keyUp(textarea, { key: 'Enter' });
        expect(onValueChange).toHaveBeenLastCalledWith('B_RAW');
        expect(
            view.queryByRole('listbox', {
                name: 'Formula autocomplete suggestions',
            }),
        ).toBeNull();

        await fireEvent.keyDown(textarea, { key: ' ', ctrlKey: true });
        await fireEvent.keyDown(textarea, { key: 'Escape' });
        await fireEvent.keyUp(textarea, { key: 'Escape' });
        expect(
            view.queryByRole('listbox', {
                name: 'Formula autocomplete suggestions',
            }),
        ).toBeNull();
    });

    it('completes a typed function prefix with Enter', async () => {
        const onValueChange = vi.fn();
        const view = render(FormulaExpressionInput, {
            props: {
                id: 'formula-expression',
                value: 'RO',
                variableCodes: ['A_RAW'],
                onValueChange,
            },
        });
        const textarea = view.getByRole('combobox') as HTMLTextAreaElement;
        textarea.setSelectionRange(2, 2);
        await fireEvent.input(textarea, { target: { value: 'RO' } });
        expect(view.getByRole('option', { name: /ROUND\(\)/ })).toBeTruthy();
        await fireEvent.keyDown(textarea, { key: 'Enter' });
        expect(textarea.value).toBe('ROUND(, 0)');
    });
});
