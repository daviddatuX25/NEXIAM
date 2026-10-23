// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ReasonSuggestionChips from '../../resources/js/Components/kit/ReasonSuggestionChips.svelte';

afterEach(cleanup);

describe('ReasonSuggestionChips', () => {
    const suggestions = [{ code: 'one', label: 'First option', text: 'First sentence.' }];

    it('replaces the bound value and calls onSelect with the chosen record', async () => {
        const onSelect = vi.fn();
        const view = render(ReasonSuggestionChips, {
            props: { suggestions, value: 'Old value', onSelect },
        });
        await fireEvent.click(view.getByRole('button', { name: 'First option' }));
        expect(onSelect).toHaveBeenCalledWith('First sentence.', suggestions[0]);
    });

    it('appends once with the requested separator and ignores duplicate text', async () => {
        const onSelect = vi.fn();
        const view = render(ReasonSuggestionChips, {
            props: { suggestions, value: 'Existing note', mode: 'append', separator: '\n', onSelect },
        });
        const button = view.getByRole('button', { name: /First option/ });
        await fireEvent.click(button);
        expect(onSelect).toHaveBeenCalledWith('Existing note\nFirst sentence.', suggestions[0]);
        await fireEvent.click(button);
        expect(onSelect).toHaveBeenCalledTimes(1);
    });

    it('does not append duplicate text or exceed maxlength', async () => {
        const onSelect = vi.fn();
        const duplicate = render(ReasonSuggestionChips, {
            props: { suggestions, value: 'First sentence.', mode: 'append', onSelect },
        });
        await fireEvent.click(duplicate.getByRole('button', { name: /First option/ }));
        expect(onSelect).not.toHaveBeenCalled();
        duplicate.unmount();

        const limited = render(ReasonSuggestionChips, {
            props: { suggestions, value: 'Keep this', maxlength: 5, onSelect },
        });
        await fireEvent.click(limited.getByRole('button', { name: 'First option' }));
        expect(onSelect).not.toHaveBeenCalled();
    });
});
