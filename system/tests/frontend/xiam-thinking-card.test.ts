// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XiamThinkingCard from '../../resources/js/Components/kit/Xiam/XiamThinkingCard.svelte';

afterEach(() => cleanup());

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('XiamThinkingCard component', () => {
    it('updates the current task and elapsed time as streaming steps complete', async () => {
        const view = render(XiamThinkingCard, {
            steps: [
                {
                    event_type: 'tool.result',
                    capability_id: 'knowledge.search@1',
                    ok: true,
                },
                {
                    event_type: 'tool.call',
                    capability_id: 'exams.rooms.available@1',
                },
            ],
            streaming: true,
        });

        expect(view.getByText('Thinking (0.0s)')).not.toBeNull();
        expect(view.getByText('Checking room availability…')).not.toBeNull();
        expect(view.container.textContent?.match(/Checking room availability…/g)).toHaveLength(1);
        await fireEvent.click(view.container.querySelector('button')!);
        expect(view.container.querySelector('svg:not(.text-emerald-600):not(.text-amber-600)')).not.toBeNull();

        vi.advanceTimersByTime(1200);
        await view.rerender({
            steps: [
                {
                    event_type: 'tool.result',
                    capability_id: 'knowledge.search@1',
                    ok: true,
                },
                {
                    event_type: 'tool.result',
                    capability_id: 'exams.rooms.available@1',
                    ok: true,
                    detail: 'Available rooms found',
                },
            ],
            streaming: false,
        });

        expect(view.getByText('Thought for 1.2s')).not.toBeNull();
        expect(view.container.querySelectorAll('.animate-bounce')).toHaveLength(0);
        expect(view.container.textContent?.match(/Checking room availability…/g)).toHaveLength(1);

        await fireEvent.click(view.container.querySelector('button')!);
        expect(view.queryByText('Checking approved policies…')).toBeNull();
        await fireEvent.click(view.container.querySelector('button')!);
        expect(view.getByText('Checking approved policies…')).not.toBeNull();
        expect(view.container.querySelector('svg.text-emerald-600')).not.toBeNull();
    });

    it('shows neutral unknown history as Finished without inventing elapsed time', async () => {
        const view = render(XiamThinkingCard, {
            steps: [
                {
                    event_type: 'tool.call',
                    capability_id: 'unmapped.internal.action@9',
                },
            ],
            streaming: false,
        });

        expect(view.getByText('Finished')).not.toBeNull();
        expect(view.queryByText('Checking details…')).toBeNull();
        expect(view.container.textContent).not.toContain('unmapped.internal.action');
        expect(view.container.textContent).not.toContain('0.0s');

        await fireEvent.click(view.container.querySelector('button')!);
        expect(view.container.querySelector('svg.text-emerald-600')).toBeNull();
        expect(view.container.querySelector('svg.text-amber-600')).toBeNull();
        expect(view.getAllByText('Checking details…')).toHaveLength(1);
    });
});
