// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PlaceholderPicker from '../../resources/js/Components/ResultSheet/PlaceholderPicker.svelte';
import ResultSheetComponentPreview from '../../resources/js/Components/ResultSheet/ResultSheetComponentPreview.svelte';

afterEach(() => cleanup());

describe('template short-output placeholder picker', () => {
    const placeholderGroups = {
        applicant: [
            {
                placeholder: 'last_school_enrolled',
                label: 'Last school — full name',
                description: 'Native applicant-entered text',
            },
            {
                placeholder: 'last_school_short',
                label: 'Last school — abbreviation',
                description: 'Known abbreviation, otherwise generated initials.',
                example: 'Ilocos Sur College → ISC',
                aliases: ['school_short'],
            },
            {
                placeholder: 'strand_or_prev_course_short',
                label: 'Strand / previous course — abbreviation',
                description: 'Known abbreviation, otherwise generated initials.',
                example: 'Humanities and Social Sciences → HUMSS',
                aliases: ['history_short'],
            },
        ],
    };

    it('distinguishes native and abbreviated values and inserts the selected alias macro', async () => {
        const oninsert = vi.fn();
        const oncopy = vi.fn();
        render(PlaceholderPicker, {
            props: { open: true, mode: 'insert', placeholderGroups, oninsert, oncopy },
        });

        expect(screen.getByRole('button', { name: '{{school_short}}' })).toBeTruthy();
        expect(screen.getByRole('button', { name: '{{history_short}}' })).toBeTruthy();

        const search = screen.getByPlaceholderText('Search applicant, score, exam date…');
        await fireEvent.input(search, { target: { value: 'last school' } });
        expect(screen.getByRole('button', { name: '{{school_short}}' })).toBeTruthy();
        await fireEvent.input(search, { target: { value: 'last_school_enrolled' } });
        expect(screen.queryByRole('button', { name: '{{school_short}}' })).toBeNull();
        await fireEvent.input(search, { target: { value: 'last school' } });

        await fireEvent.click(screen.getByRole('button', { name: '{{school_short}}' }));

        expect(oninsert).toHaveBeenCalledWith({
            key: 'school_short',
            macro: '{{school_short}}',
            label: 'Last school — abbreviation',
        });
        expect(oncopy).not.toHaveBeenCalled();
    });

    it('copies a selected alias in Word copy mode', async () => {
        const oninsert = vi.fn();
        const oncopy = vi.fn();
        render(PlaceholderPicker, {
            props: { open: true, mode: 'copy', placeholderGroups, oninsert, oncopy },
        });

        await fireEvent.click(screen.getByRole('button', { name: '{{history_short}}' }));

        expect(oncopy).toHaveBeenCalledWith({
            key: 'history_short',
            macro: '{{history_short}}',
            label: 'Strand / previous course — abbreviation',
        });
        expect(oninsert).not.toHaveBeenCalled();
    });

    it.each(['', '<p>{{slot:text}}</p>'])('renders native and short macros from supplied context in the canvas template %s', (componentTemplate) => {
        const { container } = render(ResultSheetComponentPreview, {
            props: {
                componentTemplate,
                component: {
                    type: 'text',
                    props: { text: '{{last_school_enrolled}} / {{school_short}} — {{strand}} / {{history_short}}' },
                },
                sampleContext: {
                    last_school_enrolled: 'Ilocos Sur College',
                    school_short: 'ISC',
                    strand: 'Humanities and Social Sciences',
                    history_short: 'HUMSS',
                },
            },
        });

        expect(container.textContent).toContain('Ilocos Sur College / ISC — Humanities and Social Sciences / HUMSS');
    });
});
