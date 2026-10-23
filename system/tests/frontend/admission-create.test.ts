// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, within } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import AdmissionCreate from '../../resources/js/Pages/Admission/Create.svelte';

const programs = [
    { code: 'BSIT', name: 'BS Information Technology' },
    { code: 'BSCS', name: 'BS Computer Science' },
];

const requirements = [
    {
        id: 101,
        code: 'FORM_138',
        label: 'High School Report Card (Form 138 / SF9 - Original)',
        applicant_track: 'freshman',
        item_kind: 'document',
        is_mandatory: true,
    },
    {
        id: 102,
        code: 'TOR',
        label: 'Official Transcript of Records (TOR)',
        applicant_track: 'transferee',
        item_kind: 'document',
        is_mandatory: true,
    },
    {
        id: 103,
        code: 'PSA_BIRTH',
        label: 'PSA Birth Certificate (SECPA Original)',
        applicant_track: 'all',
        item_kind: 'document',
        is_mandatory: true,
    },
    {
        id: 104,
        code: 'BROWN_ENV',
        label: 'Long Brown Envelope',
        applicant_track: 'all',
        item_kind: 'container',
        is_mandatory: false,
    },
];

beforeEach(() => {
    window.sessionStorage.clear();
});

afterEach(() => {
    cleanup();
});

describe('Admission/Create.svelte', () => {
    it('renders Application wording, /apply-aligned controls, and defaults to Immediate Walk-in with Complete documents ON', () => {
        render(AdmissionCreate, {
            page_state: 'ready',
            programs,
            requirements,
            can_verify_documents: true,
            cycle: {
                id: 1,
                name: 'AY 2026-2027 1st Semester',
                code: '2026-1',
                school_year: '2026-2027',
                semester: '1st Semester',
            },
        });

        expect(screen.getByRole('heading', { name: 'New Application' })).toBeTruthy();
        expect(screen.getByText('Application Stage & Initial Status')).toBeTruthy();
        expect(screen.queryByText(/Intake Stage/i)).toBeNull();
        expect(screen.queryByText(/Punch in applicant/i)).toBeNull();

        // Birthdate uses MM-DD-YYYY ApplicantBirthdateInput
        const birthdateInput = screen.getByLabelText(/Birthdate/i) as HTMLInputElement;
        expect(birthdateInput.getAttribute('placeholder')).toBe('MM-DD-YYYY');

        // Academic background suggestion inputs match /apply
        expect(screen.getByLabelText(/Last school enrolled/i)).toBeTruthy();
        expect(
            screen.getByLabelText(/SHS strand \/ track/i),
        ).toBeTruthy();

        // Single Email field (no duplicate Email (identity))
        expect(screen.queryByLabelText(/Email \(identity\)/i)).toBeNull();
        expect(screen.getAllByLabelText(/^Email/i)).toHaveLength(1);
        // Standard address selector (manual toggle hidden, matching /apply)
        expect(screen.getByText('Address')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /enter address manually/i })).toBeNull();
        // Immediate Walk-in Acceptance is selected by default
        const acceptedRadio = screen.getByRole('radio', {
            name: /Immediate Walk-in Acceptance/i,
        }) as HTMLInputElement;
        expect(acceptedRadio.checked).toBe(true);

        // Complete documents toggle is ON by default
        const completeToggle = screen.getByRole('switch', { name: 'Complete documents' });
        expect(completeToggle.getAttribute('aria-checked')).toBe('true');
        expect(screen.queryByTestId('walkin-individual-requirements')).toBeNull();
    });

    it('reveals track-filtered requirement checkboxes when Complete documents is toggled OFF', async () => {
        render(AdmissionCreate, {
            page_state: 'ready',
            programs,
            requirements,
            can_verify_documents: true,
            cycle: {
                id: 1,
                name: 'AY 2026-2027 1st Semester',
                code: '2026-1',
                school_year: '2026-2027',
                semester: '1st Semester',
            },
        });

        const completeToggle = screen.getByRole('switch', { name: 'Complete documents' });
        await fireEvent.click(completeToggle);

        expect(completeToggle.getAttribute('aria-checked')).toBe('false');

        const checklistBox = screen.getByTestId('walkin-individual-requirements');
        expect(checklistBox).toBeTruthy();

        // Default classification is 'new' (freshman track): shows FORM_138, PSA_BIRTH, BROWN_ENV; hides TOR
        expect(
            within(checklistBox).getByText('High School Report Card (Form 138 / SF9 - Original)'),
        ).toBeTruthy();
        expect(
            within(checklistBox).getByText('PSA Birth Certificate (SECPA Original)'),
        ).toBeTruthy();
        expect(within(checklistBox).getByText('Long Brown Envelope')).toBeTruthy();
        expect(
            within(checklistBox).queryByText('Official Transcript of Records (TOR)'),
        ).toBeNull();

        // Check individual requirement
        const form138Checkbox = within(checklistBox).getByRole('checkbox', {
            name: /High School Report Card/i,
        }) as HTMLInputElement;
        expect(form138Checkbox.checked).toBe(false);

        await fireEvent.click(form138Checkbox);
        expect(form138Checkbox.checked).toBe(true);
        expect(
            within(checklistBox).getByText('Select verified document requirements (1 of 3)'),
        ).toBeTruthy();

        // Switch classification to Transferee: swaps FORM_138 for TOR and prunes freshman-only selection
        const classificationSelect = screen.getByLabelText(
            /Applicant classification/i,
        ) as HTMLSelectElement;
        await fireEvent.change(classificationSelect, { target: { value: 'transferee' } });

        expect(
            within(checklistBox).queryByText('High School Report Card (Form 138 / SF9 - Original)'),
        ).toBeNull();
        expect(
            within(checklistBox).getByText('Official Transcript of Records (TOR)'),
        ).toBeTruthy();
        expect(
            within(checklistBox).getByText('Select verified document requirements (0 of 3)'),
        ).toBeTruthy();
    });
});
