// @vitest-environment jsdom
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Facilitator from '../../resources/js/Pages/Foundation/ResearchFacilitatorGuide.svelte';

const mermaid = vi.hoisted(() => ({ initialize: vi.fn(), render: vi.fn() }));
vi.mock('mermaid', () => ({ default: mermaid }));

beforeEach(() => { mermaid.render.mockReset(); });
afterEach(() => { cleanup(); });

it('prepares while collapsed and retains the completed diagram through hide and reopen during rendering', async () => {
    const { promise, resolve: finish } = Promise.withResolvers<{ svg: string }>();
    mermaid.render.mockImplementation((_id, _code, host: HTMLElement) => {
        expect(host.isConnected).toBe(true);
        expect(host.style.display).not.toBe('none');
        return promise;
    });
    const view = render(Facilitator);
    await waitFor(() => expect(mermaid.render).toHaveBeenCalledTimes(1));
    expect(view.queryByRole('region', { name: 'Scrollable coordination diagram' })).toBeNull();
    await fireEvent.click(view.getByRole('button', { name: 'View Swimlane Diagram' }));
    await fireEvent.click(view.getByRole('button', { name: 'Hide Swimlane Diagram' }));
    finish({ svg: '<svg role="img" aria-label="Coordination sequence"><text>Intake to clearance</text></svg>' });
    await waitFor(() => expect(mermaid.render.mock.calls[0][2].isConnected).toBe(false));
    await fireEvent.click(view.getByRole('button', { name: 'View Swimlane Diagram' }));
    expect(view.getByRole('img', { name: 'Coordination sequence' })).toBeTruthy();
    expect(view.getByRole('button', { name: 'Hide Swimlane Diagram' }).getAttribute('aria-expanded')).toBe('true');
    expect(mermaid.render).toHaveBeenCalledTimes(1);
});

it('removes the scratch surface after failure and recovers on explicit retry', async () => {
    mermaid.render.mockRejectedValueOnce(new Error('Unable to render sequence'));
    const view = render(Facilitator);
    await waitFor(() => expect(mermaid.render).toHaveBeenCalledTimes(1));
    await fireEvent.click(view.getByRole('button', { name: 'View Swimlane Diagram' }));
    expect((await view.findByRole('alert')).textContent).toContain('Unable to render sequence');
    expect(mermaid.render.mock.calls[0][2].isConnected).toBe(false);
    mermaid.render.mockResolvedValueOnce({ svg: '<svg role="img" aria-label="Recovered sequence"></svg>' });
    await fireEvent.click(view.getByRole('button', { name: 'Retry diagram' }));
    await view.findByRole('img', { name: 'Recovered sequence' });
    expect(view.queryByRole('alert')).toBeNull();
});

it('does not publish a late render after navigation unmounts the guide', async () => {
    const { promise, resolve: finish } = Promise.withResolvers<{ svg: string }>();
    mermaid.render.mockReturnValue(promise);
    const view = render(Facilitator);
    await waitFor(() => expect(mermaid.render).toHaveBeenCalledTimes(1));
    const host = mermaid.render.mock.calls[0][2] as HTMLElement;
    view.unmount();
    finish({ svg: '<svg role="img" aria-label="Stale sequence"></svg>' });
    await waitFor(() => expect(host.isConnected).toBe(false));
    expect(document.querySelector('[aria-label="Stale sequence"]')).toBeNull();
});
