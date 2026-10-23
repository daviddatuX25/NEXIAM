// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrSettingsModal from '../../resources/js/Components/Omr/Workbench/OmrSettingsModal.svelte';
import OmrShortcutsModal from '../../resources/js/Components/Omr/Workbench/OmrShortcutsModal.svelte';

afterEach(() => {
    cleanup();
    document.body.replaceChildren();
});

describe('OMR console dialogs', () => {
    it('contains focus while shortcuts are open and restores it when closed', async () => {
        const trigger = document.createElement('button');
        trigger.textContent = 'Open shortcuts';
        document.body.append(trigger);
        trigger.focus();
        const view = render(OmrShortcutsModal, {
            open: true,
            onClose: vi.fn(),
        });

        const dialog = await view.findByRole('dialog');
        await waitFor(() => {
            expect(dialog.contains(document.activeElement)).toBe(true);
        });
        const close = view.getByRole('button', { name: 'Close' });
        await fireEvent.keyDown(close, { key: 'Tab' });
        expect(dialog.contains(document.activeElement)).toBe(true);
        await fireEvent.click(close);
        await view.rerender({ open: false, onClose: vi.fn() });
        await waitFor(() => expect(document.activeElement).toBe(trigger));
    });

    it('does not allow applying settings while a replacement run is busy', async () => {
        const onApply = vi.fn();
        const view = render(OmrSettingsModal, {
            open: true,
            busy: true,
            answer_sheets: [{ id: 1, display_name: 'Version 1' }],
            booklets: [
                { id: 2, template_version_id: 1, name: 'Booklet', sets: ['A'] },
            ],
            selectedVersionId: 1,
            selectedBookletId: 2,
            selectedSetCode: 'A',
            onClose: vi.fn(),
            onApply,
        });

        const apply = await view.findByRole('button', {
            name: 'Apply & Re-calibrate',
        });
        expect((apply as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.click(apply);
        expect(onApply).not.toHaveBeenCalled();
        expect(
            view.getByRole('radio', { name: /Version 1/ }).closest('fieldset')
                ?.disabled,
        ).toBe(true);
    });
});
