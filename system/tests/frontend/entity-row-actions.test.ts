// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import EntityRowActions from '../../resources/js/Components/kit/EntityRowActions.svelte';

afterEach(() => cleanup());

describe('EntityRowActions', () => {
    it('keeps its action cell from wrapping', () => {
        const { container } = render(EntityRowActions);

        const cell = container.querySelector('[data-action-cell]');
        expect(cell?.classList.contains('flex-nowrap')).toBe(true);
        expect(cell?.classList.contains('whitespace-nowrap')).toBe(true);
        expect(cell?.classList.contains('shrink-0')).toBe(true);
    });

    it('calls the primary action and disables it when requested', async () => {
        const onclick = vi.fn();
        const { rerender } = render(EntityRowActions, {
            primaryAction: { label: 'Open record', onclick },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'Open record' }),
        );
        expect(onclick).toHaveBeenCalledOnce();

        await rerender({
            primaryAction: { label: 'Open record', onclick, disabled: true },
        });
        expect(
            screen
                .getByRole('button', { name: 'Open record' })
                .getAttribute('disabled'),
        ).not.toBeNull();
    });

    it('portals overflow actions outside the cell and preserves keyboard navigation and Escape focus return', async () => {
        const { container } = render(EntityRowActions, {
            triggerLabel: 'Requirement actions',
            overflowItems: [
                { label: 'Edit requirement', onclick: vi.fn() },
                { label: 'Deactivate requirement', onclick: vi.fn() },
            ],
        });

        const trigger = screen.getByRole('button', {
            name: 'Requirement actions',
        });
        const actionCell = container.querySelector('[data-action-cell]');
        expect(trigger.getAttribute('aria-haspopup')).toBe('menu');
        trigger.focus();
        await fireEvent.click(trigger);

        const menu = await screen.findByRole('menu');
        const menuItems = screen.getAllByRole('menuitem');
        expect(trigger.getAttribute('aria-expanded')).toBe('true');
        expect(menuItems).toHaveLength(2);
        expect(actionCell?.contains(menu)).toBe(false);

        await fireEvent.keyDown(menu, { key: 'ArrowDown' });
        await waitFor(() => expect(document.activeElement).toBe(menuItems[0]));

        await fireEvent.keyDown(menuItems[0], { key: 'ArrowDown' });
        await waitFor(() => expect(document.activeElement).toBe(menuItems[1]));

        await fireEvent.keyDown(menuItems[1], { key: 'ArrowUp' });
        await waitFor(() => expect(document.activeElement).toBe(menuItems[0]));

        await fireEvent.keyDown(menuItems[0], { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
        expect(trigger.getAttribute('aria-expanded')).toBe('false');
        await waitFor(() => expect(document.activeElement).toBe(trigger));
    });

    it('disables the overflow trigger when every visible overflow item is disabled', () => {
        render(EntityRowActions, {
            triggerLabel: 'Requirement actions',
            overflowItems: [
                { label: 'Activate', disabled: true },
                { label: 'Deactivate', disabled: true },
            ],
        });

        expect(
            (
                screen.getByRole('button', {
                    name: 'Requirement actions',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });
});
