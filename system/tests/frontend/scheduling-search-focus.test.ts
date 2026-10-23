// @vitest-environment jsdom
import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ExaminationShow from '../../resources/js/Pages/Examination/Show.svelte';
const originalMatchMedia = Object.getOwnPropertyDescriptor(
    window,
    'matchMedia',
);

const session = {
    id: 31,
    code: 'EXAM-S31',
    status: 'draft',
    version: 1,
    booking_mode: 'staff_only',
    date: '2026-10-08',
    start_time: null,
    end_time: null,
    room: { name: 'Science Hall', capacity: 24 },
    academic_cycle: { label: '2026–2027' },
};

function assignments(count: number) {
    return Array.from({ length: count }, (_, index) => ({
        id: index + 1,
        name: `Applicant ${index + 1}`,
        reference_number: `REF-${index + 1}`,
        source: 'Assigned',
    }));
}

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    if (originalMatchMedia) {
        Object.defineProperty(window, 'matchMedia', originalMatchMedia);
    } else {
        delete (window as Partial<Window>).matchMedia;
    }
});

describe('scheduling search destination focus', () => {
    it('pages to the actual row ID before focusing a page-two target', async () => {
        Object.defineProperty(window, 'matchMedia', {
            configurable: true,
            value: vi.fn().mockReturnValue({ matches: true }),
        });
        const scrollIntoView = vi.fn();
        Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
            configurable: true,
            value: scrollIntoView,
        });
        const view = render(ExaminationShow, {
            props: {
                session,
                assigned_applicants: assignments(12),
                focus_assignment_id: 12,
            },
        });

        const row = await view.findByText('Applicant 12');
        await waitFor(() =>
            expect(row.closest('tr')?.id).toBe('scheduling-assignment-12'),
        );
        await waitFor(() => expect(scrollIntoView).toHaveBeenCalled());
        expect(view.getByText('Showing 11–12 of 12')).toBeTruthy();
        expect(row.closest('tr')?.classList.contains('ring-2')).toBe(true);
        const initialFocusCount = scrollIntoView.mock.calls.length;
        await fireEvent.click(view.getByRole('button', { name: 'Previous' }));
        expect(view.getByText('Showing 1–10 of 12')).toBeTruthy();
        expect(scrollIntoView).toHaveBeenCalledTimes(initialFocusCount);
    });

    it('does not focus or page when the validated target is not rendered in the roster', async () => {
        const scrollIntoView = vi.fn();
        Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
            configurable: true,
            value: scrollIntoView,
        });
        const view = render(ExaminationShow, {
            props: {
                session,
                assigned_applicants: assignments(3),
                focus_assignment_id: 999,
            },
        });

        expect(
            view.container.querySelector('#scheduling-assignment-1'),
        ).toBeTruthy();
        expect(
            view.container.querySelector('#scheduling-assignment-2'),
        ).toBeTruthy();
        expect(
            view.container.querySelector('#scheduling-assignment-3'),
        ).toBeTruthy();
        expect(
            view.container.querySelector('#scheduling-assignment-4'),
        ).toBeNull();
        expect(view.queryByText('Applicant 999')).toBeNull();
        expect(view.queryByRole('button', { name: 'Next' })).toBeNull();
        await Promise.resolve();
        expect(scrollIntoView).not.toHaveBeenCalled();
    });
});
