// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ActionDropdown from '../../resources/js/Components/kit/ActionDropdown.svelte';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe('ActionDropdown', () => {
    it('portals row actions outside its local container and runs a selected action once', async () => {
        const onclick = vi.fn();
        const { container } = render(ActionDropdown, {
            items: [{ label: 'Run action', onclick }],
        });
        const localContainer = container.firstElementChild;

        await fireEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );

        const menu = await screen.findByRole('menu');
        expect(localContainer?.contains(menu)).toBe(false);

        await fireEvent.click(
            screen.getByRole('menuitem', { name: 'Run action' }),
        );

        expect(onclick).toHaveBeenCalledOnce();
        await waitFor(() => expect(screen.queryByRole('menu')).toBeNull());
    });

    it('keeps a delete menu open when confirmation is cancelled', async () => {
        const onclick = vi.fn();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        render(ActionDropdown, {
            confirmDelete: true,
            items: [{ label: 'Delete item', method: 'delete', onclick }],
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await screen.findByRole('menu');
        await fireEvent.click(
            screen.getByRole('menuitem', { name: 'Delete item' }),
        );

        expect(confirm).toHaveBeenCalledWith(
            'Are you sure you want to delete "Delete item"? This action cannot be undone.',
        );
        expect(onclick).not.toHaveBeenCalled();
        expect(screen.getByRole('menu')).toBeTruthy();
    });

    it('submits non-GET actions through their native form', async () => {
        render(ActionDropdown, {
            items: [
                {
                    label: 'Deactivate item',
                    href: '/items/1/deactivate',
                    method: 'post',
                },
            ],
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'More actions' }),
        );
        await screen.findByRole('menu');
        const menuItem = screen.getByRole('menuitem', {
            name: 'Deactivate item',
        });
        const form = menuItem.closest('form');
        const submit = vi.fn((event: Event) => event.preventDefault());
        form?.addEventListener('submit', submit);

        await fireEvent.click(menuItem);

        expect(submit).toHaveBeenCalledOnce();
    });
});
