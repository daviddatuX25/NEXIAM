// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import XiamNpcDialog from '../../resources/js/Components/kit/Walkthrough/XiamNpcDialog.svelte';
import type { TourDefinition } from '../../resources/js/lib/walkthrough/types';

const mockedPage = vi.hoisted(() => ({
    component: 'Applicant/Home',
    url: '/applicant/home',
    props: {},
}));

vi.mock('@inertiajs/svelte', () => ({
    usePage: () => mockedPage,
}));

describe('Xiam NPC Dialog typewriter effect', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        sessionStorage.clear();
        localStorage.clear();
        window.matchMedia = vi.fn().mockReturnValue({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        });
    });
    afterEach(() => {
        cleanup();
        vi.useRealTimers();
    });

    it('does not stop midway through typing when a reactive prop or context update occurs', async () => {
        const sampleDefinition: TourDefinition = {
            id: 'test-tour',
            title: 'Test Tour',
            revision: 1,
            steps: [
                {
                    id: 'step-1',
                    title: 'Welcome',
                    text: 'Welcome to NEXIAM admissions system. This text should finish typing completely.',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ],
        };

        const { component, rerender } = render(XiamNpcDialog, {
            props: {
                tourKey: 'test-tour',
                definition: sampleDefinition,
                open: true,
            },
        });

        // Advance 100ms: at 25ms per char, ~4 characters should be typed
        await vi.advanceTimersByTimeAsync(100);

        const dialog = screen.getByRole('dialog');
        const textElement = dialog.querySelector('.text-foreground\\/90');
        const textAt100ms = (textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim();
        expect(textAt100ms.length).toBeGreaterThan(0);
        expect(textAt100ms.length).toBeLessThan(sampleDefinition.steps[0].text.length);
        // Now simulate a reactive update (e.g. definition object re-passed or context update)
        // With current bug, this triggers $effect cleanup, which clears the timer.
        // Then $effect runs, sees activeTypingStepId === stepKey, returns, and typing halts midway!
        await rerender({
            definition: { ...sampleDefinition },
        });

        // Advance enough time for the full text to finish typing (e.g. 3000ms)
        await vi.advanceTimersByTimeAsync(3000);

        const finalText = (textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim();
        // It must finish the entire text!
        expect(finalText).toBe(sampleDefinition.steps[0].text);
    });

    it('transitions cleanly to the next step and types out the new step to completion', async () => {
        const multiStepDefinition: TourDefinition = {
            id: 'multi-tour',
            title: 'Multi Tour',
            revision: 1,
            steps: [
                {
                    id: 'step-1',
                    title: 'Step One',
                    text: 'First step text typing out.',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'step', stepId: 'step-2' } },
                },
                {
                    id: 'step-2',
                    title: 'Step Two',
                    text: 'Second step text typing out completely.',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ],
        };

        render(XiamNpcDialog, {
            props: {
                tourKey: 'multi-tour',
                definition: multiStepDefinition,
                open: true,
            },
        });

        // Finish step 1
        await vi.advanceTimersByTimeAsync(2000);
        const dialog = screen.getByRole('dialog');
        const textElement = dialog.querySelector('.text-foreground\\/90');
        expect((textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim()).toBe(multiStepDefinition.steps[0].text);

        // Click next button
        const nextBtn = screen.getByRole('button', { name: /Next/i });
        nextBtn.click();

        // Midway through step 2, simulate a reactive update
        await vi.advanceTimersByTimeAsync(100);
        const step2MidText = (textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim();
        expect(step2MidText.length).toBeGreaterThan(0);
        expect(step2MidText.length).toBeLessThan(multiStepDefinition.steps[1].text.length);

        // Let step 2 finish typing
        await vi.advanceTimersByTimeAsync(2000);
        expect((textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim()).toBe(multiStepDefinition.steps[1].text);
    });

    it('reveals text immediately when user clicks the speech bubble', async () => {
        const sampleDefinition: TourDefinition = {
            id: 'reveal-tour',
            title: 'Reveal Tour',
            revision: 1,
            steps: [
                {
                    id: 'step-1',
                    title: 'Quick Reveal',
                    text: 'This is a long sentence that the user clicks to reveal immediately without waiting.',
                    speechMode: 'typewriter',
                    advance: { kind: 'next', destination: { kind: 'complete' } },
                },
            ],
        };

        render(XiamNpcDialog, {
            props: {
                tourKey: 'reveal-tour',
                definition: sampleDefinition,
                open: true,
            },
        });

        await vi.advanceTimersByTimeAsync(50);
        const dialog = screen.getByRole('dialog');
        const bubbleButton = dialog.querySelector('button[aria-label*="reveal all text"]') as HTMLButtonElement;
        expect(bubbleButton).not.toBeNull();
        await fireEvent.click(bubbleButton);
        await tick();

        const textElement = dialog.querySelector('.text-foreground\\/90');
        expect((textElement?.textContent?.trim() ?? '').replace(/▍$/, '').trim()).toBe(sampleDefinition.steps[0].text);
    });
});
