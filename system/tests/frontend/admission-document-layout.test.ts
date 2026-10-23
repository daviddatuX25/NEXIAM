// @vitest-environment jsdom

import { cleanup, render, screen, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    page: { props: { flash: {} as Record<string, unknown> } },
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        usePage: () => mocks.page,
        useForm(initial: Record<string, unknown>) {
            return {
                ...initial,
                errors: {},
                processing: false,
                post: vi.fn(),
                put: vi.fn(),
            };
        },
    };
});

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import AdmissionShow from '../../resources/js/Pages/Admission/Show.svelte';

const attempt = {
    id: 44,
    version: 5,
    reference_code: 'ADM-0044',
    applicant_name: 'Sample Applicant',
    status: 'pending',
    is_clearance_confirmed: true,
    applicant_type: 'new',
    identity: {
        first_name: 'Sample',
        last_name: 'Applicant',
        birthdate: '2008-01-02',
        sex: 'female',
    },
    program_preferences: ['BSCS'],
    cycle: { name: '2026-2027', code: '2026-27' },
    submitted_at: '2026-01-01T00:00:00Z',
};

const documentChecklist = {
    set: {
        id: 8,
        applicant_track: 'freshman',
        catalog_version: 2,
    },
    status: 'current',
    expected_version: 5,
    all_mandatory_verified: false,
    mandatory_pending_count: 1,
    flags: {
        documents_view: true,
        documents_initialize: true,
        documents_verify: true,
        documents_request: true,
    },
    items: [
        {
            id: 13,
            label: 'Transcript of Records',
            code: 'TOR',
            item_kind: 'document',
            is_mandatory: true,
            latest_status: 'missing',
            latest_verification_id: 21,
        },
    ],
};

afterEach(() => cleanup());

describe('admission dossier document layout', () => {
    it('renders the canonical header and status badge without the legacy status bar', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt,
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: false, dismiss: false, correct: false },
            document_checklist: documentChecklist,
        });

        expect(screen.getByText('Pending')).toBeTruthy();
        expect(
            screen.getByRole('heading', { level: 1, name: 'Sample Applicant' }),
        ).toBeTruthy();
        expect(screen.queryByText('Status')).toBeNull();
    });

    it('shows the Accept action when a pending application can be accepted', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt,
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: true, dismiss: false, correct: false },
            document_checklist: documentChecklist,
        });

        expect(
            screen.getAllByRole('button', { name: 'Accept' }).length,
        ).toBeGreaterThanOrEqual(1);
    });

    it('shows the canonical Edit application link when correction capability is granted', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt,
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: false, dismiss: false, correct: true },
            document_checklist: documentChecklist,
        });

        const editLink = screen.getByRole('link', {
            name: /Edit application/i,
        });
        expect(editLink.getAttribute('href')).toBe('/admissions/44/edit');
    });

    it('keeps the three dossier sections and document checklist in a 50/50 split and honors confirmed clearance', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt,
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: false, dismiss: false, correct: false },
            document_checklist: documentChecklist,
        });

        const split = screen.getByTestId('admission-dossier-split');
        expect(split.className).toContain('lg:grid-cols-12');
        expect(
            split.querySelectorAll(
                ':scope > [data-testid="admission-dossier-left"]',
            ),
        ).toHaveLength(1);
        expect(
            split.querySelectorAll(
                ':scope > [data-testid="admission-dossier-right"]',
            ),
        ).toHaveLength(1);

        const left = within(screen.getByTestId('admission-dossier-left'));
        expect(left.getByText('Personal details')).toBeTruthy();
        expect(left.getByText('Program preferences')).toBeTruthy();
        expect(left.getByText('Timeline')).toBeTruthy();
        expect(
            screen.getByTestId('admission-dossier-left').className,
        ).toContain('lg:col-span-6');

        const right = screen.getByTestId('admission-dossier-right');
        expect(right.className).toContain('lg:col-span-6');
        expect(within(right).getByText('Document checklist')).toBeTruthy();
        expect(
            within(right).getByText(
                /locked while admission clearance is confirmed/,
            ),
        ).toBeTruthy();
        expect(
            within(right).queryByRole('button', { name: 'Mark complete' }),
        ).toBeNull();
        expect(
            within(right).queryByRole('button', {
                name: 'Request document correction',
            }),
        ).toBeNull();
    });

    it('renders a single canonical Edit application link without duplicate inline edit modals when pending or needs_correction', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt: {
                ...attempt,
                status: 'pending',
                is_clearance_confirmed: false,
            },
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: true, dismiss: true, correct: true },
            document_checklist: {
                ...documentChecklist,
                is_clearance_confirmed: false,
            },
        });

        const editLink = screen.getByRole('link', {
            name: /Edit application/i,
        });
        expect(editLink.getAttribute('href')).toBe('/admissions/44/edit');
        expect(
            screen.queryByRole('button', { name: /Update for applicant/i }),
        ).toBeNull();
    });

    it('renders a single canonical Authorize amendment link when accepted and not clearance-fenced', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt: {
                ...attempt,
                status: 'accepted',
                is_clearance_confirmed: false,
            },
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: true, dismiss: true, correct: true },
            document_checklist: {
                ...documentChecklist,
                is_clearance_confirmed: false,
            },
        });

        const amendLinks = screen.getAllByRole('link', {
            name: /Authorize amendment/i,
        });
        expect(amendLinks).toHaveLength(1);
        expect(amendLinks[0].getAttribute('href')).toBe('/admissions/44/edit');
        expect(
            screen.queryByRole('button', { name: /Authorize amendment/i }),
        ).toBeNull();
        expect(
            screen.queryByRole('link', { name: /Edit application/i }),
        ).toBeNull();
    });

    it('shows Accept & verify all documents action when unverified documents exist and staff has verify capability', () => {
        render(AdmissionShow, {
            page_state: 'ready',
            attempt: { ...attempt, is_clearance_confirmed: false },
            programs: [{ code: 'BSCS', name: 'Computer Science' }],
            capabilities: { accept: true, dismiss: false, correct: false },
            document_checklist: {
                ...documentChecklist,
                is_clearance_confirmed: false,
                flags: {
                    ...documentChecklist.flags,
                    documents_verify: true,
                },
                items: [
                    {
                        id: 13,
                        label: 'Transcript of Records',
                        code: 'TOR',
                        latest_status: 'missing',
                    },
                ],
            },
        });

        expect(
            screen.getAllByRole('button', { name: 'Accept' }).length,
        ).toBeGreaterThanOrEqual(1);
        expect(
            screen.getAllByRole('button', {
                name: 'Accept & verify all documents',
            }).length,
        ).toBeGreaterThanOrEqual(1);
    });
});
