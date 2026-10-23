// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import ApplicantBirthdateInput from '../../resources/js/Components/ApplicantBirthdateInput.svelte';

afterEach(() => {
    cleanup();
});

describe('ApplicantBirthdateInput', () => {
    it('shows the requested format and converts an ISO browser autofill value for display', async () => {
        const view = render(ApplicantBirthdateInput, {
            props: { id: 'birthdate', value: '' },
        });
        const input = view.getByRole('textbox', { name: /birthdate/i });

        expect(input.getAttribute('placeholder')).toBe('MM-DD-YYYY');
        expect(input.getAttribute('inputmode')).toBe('numeric');
        expect(view.getByText('Enter as MM-DD-YYYY.')).not.toBeNull();

        await fireEvent.input(input, { target: { value: '2003-09-25' } });

        expect(input).toHaveProperty('value', '09-25-2003');
    });

    it('inserts separators when a mobile numeric keyboard supplies only digits', async () => {
        const view = render(ApplicantBirthdateInput, {
            props: { id: 'birthdate', value: '' },
        });
        const input = view.getByRole('textbox', { name: /birthdate/i });

        await fireEvent.input(input, { target: { value: '09252003' } });

        expect(input).toHaveProperty('value', '09-25-2003');
    });

    it('associates format and validation feedback with the required input', () => {
        const view = render(ApplicantBirthdateInput, {
            props: {
                id: 'birthdate',
                value: '02-29-2001',
                error: 'Enter a valid birthdate as MM-DD-YYYY.',
            },
        });
        const input = view.getByRole('textbox', { name: /birthdate/i });

        expect(input.hasAttribute('required')).toBe(true);
        expect(input.getAttribute('aria-invalid')).toBe('true');
        expect(input.getAttribute('aria-describedby')).toContain(
            'birthdate-format',
        );
        expect(input.getAttribute('aria-describedby')).toContain(
            'birthdate-error',
        );
        expect(view.getByRole('alert').textContent).toContain(
            'valid birthdate',
        );
    });
});
