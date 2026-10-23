// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Xiam/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));

import MyApplication from '../../resources/js/Pages/Applicant/MyApplication.svelte';

afterEach(() => cleanup());

const application = {
    reference_code: 'APP-2026-000003',
    status: 'accepted',
    needs_correction: false,
    can_amend: false,
    amend_disabled_reason: 'This application has already been accepted.',
    correction_notes: null,
    correction_href: null,
    personal: {
        name: 'Applicant Example',
        birthdate: '2001-01-01',
        sex: 'female',
        contact_phone: null,
        contact_email: null,
        address: null,
        last_school: null,
        strand: null,
    },
    preferences: [],
    program_preferences: [],
    submitted_at: null,
};

describe('applicant application reference', () => {
    it('shows the reference once as Applicant No, not as an NOA number', () => {
        render(MyApplication, {
            props: { application, document_checklist: null },
        });

        expect(screen.getByText('Applicant No. APP-2026-000003')).toBeTruthy();
        expect(screen.queryByText(/NOA No\./i)).toBeNull();
        expect(screen.getAllByText(/APP-2026-000003/)).toHaveLength(1);
    });
});
