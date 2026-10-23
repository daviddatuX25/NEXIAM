// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';

const builderDocument = {
    schema_version: '1.0.0',
    page: {
        paper_size: 'a4',
        orientation: 'portrait',
        width_mm: 210,
        height_mm: 297,
        margin_mm: 12,
    },
    components: [
        {
            id: 'candidate-photo',
            type: 'dynamic_image',
            x: 10,
            y: 10,
            w: 40,
            h: 40,
            locked: false,
            props: {
                binding: 'applicant_photo_2x2',
                fit: 'cover',
                fallback: 'affix_box',
            },
        },
        {
            id: 'verification-code',
            type: 'dynamic_qr',
            x: 60,
            y: 10,
            w: 30,
            h: 30,
            locked: false,
            props: { binding: 'verification_qr', error_correction: 'M' },
        },
    ],
};

describe('ResultSheetStudio dynamic assets', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('renders photo and QR badges and exposes each selected binding in the inspector', async () => {
        const { container, getByText, getByLabelText } = render(
            ResultSheetStudio,
            {
                builderDocument,
                builderCatalog: {
                    dynamic_image: {
                        label: 'Dynamic image / photo',
                        description: 'Applicant photo',
                    },
                    dynamic_qr: {
                        label: 'Verification QR',
                        description: 'Verification code',
                    },
                },
            },
        );

        const photo = container.querySelector(
            '[data-result-sheet-component="candidate-photo"]',
        ) as HTMLElement;
        const qr = container.querySelector(
            '[data-result-sheet-component="verification-code"]',
        ) as HTMLElement;

        expect(getByText('2x2 Photo')).not.toBeNull();
        expect(getByText('applicant_photo_2x2')).not.toBeNull();
        expect(getByText('QR')).not.toBeNull();
        expect(getByText('verification_qr')).not.toBeNull();

        await fireEvent.pointerDown(photo, { button: 0 });
        expect(photo.getAttribute('aria-pressed')).toBe('true');
        let bindingInput = getByLabelText('Data binding') as HTMLInputElement;
        expect(bindingInput.value).toBe('applicant_photo_2x2');

        await fireEvent.pointerDown(qr, { button: 0 });
        expect(qr.getAttribute('aria-pressed')).toBe('true');
        bindingInput = getByLabelText('Data binding') as HTMLInputElement;
        expect(bindingInput.value).toBe('verification_qr');
    });
});
