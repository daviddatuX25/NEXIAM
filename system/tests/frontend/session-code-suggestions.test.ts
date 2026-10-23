// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(anchor),
}));

import ExamCreate from '../../resources/js/Pages/Examination/Create.svelte';
import ReleaseCreate from '../../resources/js/Pages/Consultation/ConsultationSessionCreate.svelte';

const cycles = [
    { id: 1, label: 'Current year', is_active: true },
    { id: 2, label: 'Next year', is_active: false },
];

const cases = [
    {
        name: 'Exam scheduling',
        prefix: 'Batch',
        mount: () => render(ExamCreate, {
            cycles,
            default_cycle_id: 1,
            session_code_suggestions: { 1: 'Batch-04', 2: 'Batch-01' },
        }),
    },
    {
        name: 'Release Day scheduling',
        prefix: 'Release',
        mount: () => render(ReleaseCreate, {
            cycles,
            default_cycle_id: 1,
            session_code_suggestions: { 1: 'Release-04', 2: 'Release-01' },
        }),
    },
];

afterEach(cleanup);

for (const { name, prefix, mount } of cases) {
    describe(name, () => {
        it('follows the selected year until the suggested code is edited', async () => {
            mount();
            await tick();
            const code = screen.getByLabelText<HTMLInputElement>('Session code');
            const year = screen.getByLabelText<HTMLSelectElement>('Academic year');
            expect(code.value).toBe(`${prefix}-04`);

            await fireEvent.change(year, { target: { value: '2' } });
            expect(code.value).toBe(`${prefix}-01`);

            await fireEvent.change(year, { target: { value: '' } });
            expect(code.value).toBe('');

            await fireEvent.change(year, { target: { value: '1' } });
            expect(code.value).toBe(`${prefix}-04`);
        });

        it.each(['Special morning session', ''])('preserves a manual code or deliberate clear (%j) across year changes', async (value) => {
            mount();
            await tick();
            const code = screen.getByLabelText<HTMLInputElement>('Session code');
            const year = screen.getByLabelText<HTMLSelectElement>('Academic year');
            await fireEvent.input(code, { target: { value } });

            await fireEvent.change(year, { target: { value: '2' } });
            expect(code.value).toBe(value);

            await fireEvent.change(year, { target: { value: '1' } });
            expect(code.value).toBe(value);
        });
    });
}
