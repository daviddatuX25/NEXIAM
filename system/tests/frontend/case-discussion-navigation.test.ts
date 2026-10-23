// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    page: { props: { flash: {} }, url: '' },
    get: vi.fn(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => mocks.page,
    useForm: (initial: Record<string, unknown>) => ({ ...initial, errors: {}, processing: false }),
    router: { get: mocks.get, on: vi.fn(() => () => {}) },
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Pages/Admission/E2PageState.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/YuKeeConsultationCard.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/ApplicantEvidenceBar.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/SupplementalEvidenceModal.svelte', () => ({ default: () => null }));
vi.mock('@/Components/consultation/ConsultationMobileDock.svelte', () => ({ default: () => null }));
vi.mock('@/Components/consultation/ApplicantLookupDialog.svelte', () => ({ default: () => null }));
vi.mock('@/Components/Admission/CaseConversation.svelte', () => ({ default: () => null }));
vi.mock('@/Components/DocumentTemplate/PrintDocumentsSlot.svelte', () => ({ default: () => null }));

import ApplicantAdmissionSupport from '../../resources/js/Pages/Admission/ApplicantAdmissionSupport.svelte';

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('Case discussion audience navigation', () => {
    it.each([
        {
            label: 'Guidance session entry without academic context',
            url: '/consultation/applicants/20?session_id=4&tab=threads',
            role: 'guidance',
            ph_program: null,
            expected: '/consultation/applicants/20?session_id=4&tab=threads',
        },
        {
            label: 'academic entry with an exact program',
            url: '/consultation/applicants/20?program_code=BSIT&tab=threads&thread_type=applicant_support',
            role: 'program_head',
            ph_program: { code: 'BSIT', name: 'Information Technology' },
            expected: '/consultation/applicants/20?program_code=BSIT&tab=threads&cycle_code=SYN-CYCLE-2026',
        },
        {
            label: 'Guidance entry with validated academic context',
            url: '/consultation/applicants/20?session_id=4&program_code=BSIT&cycle_code=SYN-CYCLE-2026&tab=threads',
            role: 'guidance',
            ph_program: null,
            expected: '/consultation/applicants/20?session_id=4&program_code=BSIT&cycle_code=SYN-CYCLE-2026&tab=threads',
        },
    ])('keeps a valid return URL for $label', async ({ url, role, ph_program, expected }) => {
        mocks.page.url = url;
        render(ApplicantAdmissionSupport, {
            props: {
                page_state: 'ready', role, ph_program,
                attempt: { id: 20, name: 'Test Applicant', reference_code: 'APP-20', cycle_code: 'SYN-CYCLE-2026' },
                support: { preferences: [], readiness: {}, score_profile: { results: [] } },
                ph_record: { tier: 'A' },
            },
        });

        await fireEvent.click(await screen.findByRole('button', { name: 'Staff discussion', exact: true }));

        expect(mocks.get).toHaveBeenCalledWith(expected, {}, { preserveState: true, preserveScroll: true });
    });

    it('shows the scoped TABFM result and approved background on academic review', () => {
        mocks.page.url = '/consultation/applicants/20?program_code=BSIT&tab=record';
        render(ApplicantAdmissionSupport, {
            props: {
                page_state: 'ready',
                role: 'program_head',
                ph_program: { code: 'BSIT', name: 'Information Technology' },
                attempt: { id: 20, name: 'Test Applicant', reference_code: 'APP-20', cycle_code: 'SYN-CYCLE-2026' },
                ph_record: {
                    tier: 'B',
                    score_profile: { series: [] },
                    analysis: {
                        status: 'no_analysis',
                        evaluated: false,
                        recommendations: [],
                        message: 'No analysis has been produced.',
                    },
                    background: {
                        applicant_type: 'First year',
                        strand: 'STEM',
                        gwa: 88.5,
                        vocational_profile: {
                            status: 'reviewed',
                            is_reviewed: true,
                            scores: { R: 60, I: 80, A: 40, S: 55, E: 50, C: 70 },
                            emergent_code: 'IC',
                            emergent_themes: ['Investigative', 'Conventional'],
                            program_targets: {
                                BSIT: { priority_strands: ['STEM'], cmo_reference: 'CHED CMO 25' },
                            },
                            },
                    },
                    follow_ups: [],
                },
            },
        });

        expect(screen.getByText('TABFM — BSIT')).toBeTruthy();
        expect(screen.getByText('Applicant background')).toBeTruthy();
        expect(screen.getByText('First year')).toBeTruthy();
        expect(screen.getByText('Academic strand').nextElementSibling?.textContent).toBe('STEM');
        expect(screen.getByText('88.5')).toBeTruthy();
        expect(screen.getByText('Investigative · Conventional')).toBeTruthy();
        expect(screen.getByText('Directly Aligned')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Add .*Advised Pool|Compare on Radar|View advising notes|Record (?:Consultation )?Notes/ })).toBeNull();
        expect(screen.getByText('TABFM — BSIT').compareDocumentPosition(screen.getByText('Applicant background')) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it.each([false, true])('keeps referred assessment evidence visible with applicant issuance %s', (resultReleased) => {
        mocks.page.url = '/consultation/applicants/20?program_code=BSIT&tab=record';
        render(ApplicantAdmissionSupport, {
            props: {
                page_state: 'ready',
                role: 'program_head',
                ph_program: { code: 'BSIT', name: 'Information Technology' },
                attempt: { id: 20, name: 'Test Applicant', reference_code: 'APP-20', cycle_code: 'SYN-CYCLE-2026' },
                ph_record: {
                    tier: 'B',
                    result_released: resultReleased,
                    access_reason: 'targeted_follow_up',
                    score_profile: { series: [{ area_code: 'VR', area_name: 'Verbal reasoning', score: 82, scale: 'percentile' }] },
                    analysis: { status: 'no_analysis', evaluated: false, recommendations: [], message: 'No saved analysis is available.' },
                    follow_ups: [{ id: 7, status: 'open', reason: 'Review program suitability.' }],
                },
            },
        });

        expect(screen.getByText('Verbal reasoning')).toBeTruthy();
        expect(screen.getByText('82')).toBeTruthy();
        expect(screen.getByText(resultReleased ? 'Results issued to applicant' : 'Results not yet issued to applicant')).toBeTruthy();
        expect(screen.getByText('Review program suitability.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Record Decision|Release results/i })).toBeNull();
    });
});
