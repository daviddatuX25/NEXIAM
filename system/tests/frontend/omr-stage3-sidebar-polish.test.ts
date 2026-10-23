// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';
import OmrPhysicalTestMethodCard from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrPhysicalTestMethodCard.svelte';

describe('Stage 3 physical-test consolidation and checkpoint navigation', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders three checkpoints with physical preparation and capture combined', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                code: 'CANONICAL-TEST',
                stage: 'mapping_complete',
            },
            urls: { digital_verification: '/verify' },
        });

        const rail = container.querySelector(
            'ol[aria-label="Verification progress"]',
        );
        expect(rail).not.toBeNull();
        const items = rail?.querySelectorAll('li');
        expect(items?.length).toBe(3);

        const labels = Array.from(items ?? []).map(
            (li) => li.textContent?.trim() ?? '',
        );
        expect(labels[0]).toContain('Digital engine check');
        expect(labels[1]).toContain('Physical tests');
        expect(labels[2]).toContain('Evidence review');
        expect(labels.join(' ')).not.toContain('Capture printed challenge');
        expect(labels.join(' ')).not.toContain('Qualification');
    });

    it('marks active checkpoint with aria-current="step" and shows blocked prerequisites', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                digital_verified: true,
                digital_verification_current: true,
            },
            verification_run: {
                run_id: 101,
                mode: 'quick_rehearsal',
                status: 'awaiting_print',
                target_current: true,
                digital_verification_current: true,
                methods: [
                    { method: 'pre_shaded', state: 'not_started' },
                    { method: 'hand_shaded', state: 'not_started' },
                ],
            },
        });

        const items = container.querySelectorAll(
            'ol[aria-label="Verification progress"] > li',
        );
        expect(items.length).toBe(3);

        // Checkpoint 1 (Digital engine check) is Done
        expect(items[0].textContent).toContain('Done');

        // Checkpoint 2 (Physical tests) is current/active with aria-current="step"
        expect(items[1].getAttribute('aria-current')).toBe('step');
        expect(items[1].textContent).toContain('Physical tests');

        // Blocked steps show prerequisites clearly
        const blockedStep = items[2]; // Evidence review
        expect(blockedStep.textContent).toContain('Blocked');
        expect(blockedStep.textContent).toContain('Requires:');
    });

    it('renders both ordered methods once inside the unified physical checkpoint', () => {
        const { container } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                digital_verified: true,
                digital_verification_current: true,
            },
            verification_run: {
                run_id: 102,
                mode: 'quick_rehearsal',
                status: 'awaiting_print',
                target_current: true,
                digital_verification_current: true,
                methods: [
                    { method: 'pre_shaded', state: 'not_started' },
                    { method: 'hand_shaded', state: 'not_started' },
                ],
            },
        });

        const physicalGrid = container.querySelector(
            'div[aria-label="Physical tests"]',
        );
        expect(physicalGrid).not.toBeNull();
        expect(physicalGrid?.className).not.toContain('grid-cols-2');
        expect(physicalGrid?.className).toContain('flex-col');

        const methodCards = container.querySelectorAll(
            'article[data-omr-method]',
        );
        expect(methodCards.length).toBe(2);
        expect(methodCards[0].getAttribute('data-omr-method')).toBe(
            'pre_shaded',
        );
        expect(methodCards[0].textContent).toContain('1. Pre-shaded test');
        expect(methodCards[1].getAttribute('data-omr-method')).toBe(
            'hand_shaded',
        );
        expect(methodCards[1].textContent).toContain('2. Hand-shaded test');
        expect(
            container.querySelectorAll('div[aria-label="Physical tests"]'),
        ).toHaveLength(1);
        const orderGuide = container.querySelector(
            '[aria-label="Physical test order"]',
        );
        expect(orderGuide?.textContent?.replace(/\s+/g, ' ')).toContain(
            'There is no separate upload step',
        );
    });

    it('renders OmrPhysicalTestMethodCard with vertical attempts stack and accessible buttons', () => {
        const { container, getByRole } = render(OmrPhysicalTestMethodCard, {
            method: 'pre_shaded',
            methodData: {
                state: 'challenge_ready',
                challenge_id: 55,
                print_url: '/print-pdf',
                pages: [
                    {
                        page_no: 1,
                        state: 'not_started',
                        selected_attempt: null,
                    },
                ],
            },
            requiredPageCount: 1,
        });

        const attemptsContainer = container.querySelector(
            'div[aria-label="Selected attempts"]',
        );
        expect(attemptsContainer).not.toBeNull();
        // Must NOT use multi-column grid for attempts inside sidebar
        expect(attemptsContainer?.className).not.toContain('grid-cols-2');
        expect(attemptsContainer?.className).toContain('flex-col');

        const printBtn = getByRole('button', {
            name: /Open print PDF|Print at actual size/i,
        });
        expect(printBtn).not.toBeNull();
    });

    it('renders clean sticky bottom footer without negative margin breakout', () => {
        const onBack = vi.fn();
        const { getByRole } = render(StudioExportVerifyPanel, {
            draft: { id: 13, stage: 'mapping_complete' },
            onBack,
        });

        const backBtn = getByRole('button', {
            name: /Return to Recognition Mapping/i,
        });
        expect(backBtn).not.toBeNull();

        const footer = backBtn.closest('div');
        expect(footer?.className).not.toContain('-mx-6');
        expect(footer?.className).not.toContain('-mx-4');
        expect(footer?.className).toContain('sticky');

        fireEvent.click(backBtn);
        expect(onBack).toHaveBeenCalledTimes(1);
    });

    it('allows expanding and collapsing checkpoints via accordion buttons', async () => {
        const { container, getByRole } = render(StudioExportVerifyPanel, {
            draft: {
                id: 13,
                stage: 'digital_verified',
                digital_verified: true,
                digital_verification_current: true,
            },
            verification_run: {
                run_id: 103,
                mode: 'quick_rehearsal',
                status: 'awaiting_print',
                target_current: true,
                digital_verification_current: true,
                methods: [
                    { method: 'pre_shaded', state: 'not_started' },
                    { method: 'hand_shaded', state: 'not_started' },
                ],
            },
        });

        // Step 2 is active by default
        const step2Heading = container.querySelector('#current-step-heading');
        expect(step2Heading?.textContent).toContain('Physical tests');

        // Click collapse all button
        const collapseAllBtn = getByRole('button', { name: /Collapse all/i });
        await fireEvent.click(collapseAllBtn);

        // Now no step should be expanded
        expect(container.querySelector('#current-step-heading')).toBeNull();
    });
});
