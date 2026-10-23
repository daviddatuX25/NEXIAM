// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mockRouterPost = vi.fn();
const mockRouterOn = vi.fn(() => () => {});
const mockPage = { props: { flash: {}, errors: {} }, url: '/consultation/applicants/attempt-1' };

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    const actualRouter = (actual.router as Record<string, unknown>) ?? {};
    return {
        ...actual,
        usePage: () => mockPage,
        router: {
            ...actualRouter,
            on: (...args: unknown[]) => mockRouterOn(...args),
            post: (...args: unknown[]) => mockRouterPost(...args),
        },
    };
});

import ApplicantAdmissionSupport from '../../resources/js/Pages/Admission/ApplicantAdmissionSupport.svelte';
import ConsultationPolicy from '../../resources/js/Pages/Setup/ConsultationPolicy.svelte';

afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
    mockRouterOn.mockClear();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    mockPage.url = '/consultation/applicants/attempt-1';
});

function renderGuidanceSupport(
    availablePrograms = [
        { code: 'BSN', name: 'Bachelor of Science in Nursing' },
        { code: 'BSIT', name: 'Bachelor of Science in Information Technology' },
    ],
    resultReady = true,
) {
    return render(ApplicantAdmissionSupport, {
        page_state: 'ready',
        role: 'guidance',
        attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
        support: {
            readiness: { grading_finalized: false, result_ready: resultReady },
            preferences: ['BSN'],
            program_pool: { default: 'BSN' },
            analysis: { status: 'evaluated' },
            models: [{
                slot: 'tabfm_pairwise',
                status: 'evaluated',
                evaluated: true,
                source_version: 'v7',
                inference_backend: 'tabpfn_pytorch',
                fallback_reason: null,
                engine_version: '9.1.0',
                modality_coverage: {
                    inference_provenance: {
                        package: 'tabpfn',
                        package_version: '9.1.0',
                        checkpoint_identity: 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
                        checkpoint_sha256: '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
                    },
                },
                recommendations: [{
                    rank: 1,
                    program_code: 'BSIT',
                    program_name: 'Bachelor of Science in Information Technology',
                    compatibility_index: 84,
                    band: 'high_match',
                    passed_cutoff: true,
                    drivers: [],
                }],
                applicant_evidence: { gwa: 89.4 },
            }],
        },
        available_programs: availablePrograms,
    });
}

describe('Advising-backed Result released journey status', () => {
    it.each([
        [false, false],
        [true, false],
        [false, true],
        [true, true],
    ])('uses advising completion when release is %s and advising is %s', (released, advised) => {
        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
            support: { readiness: { grading_finalized: false, result_ready: true, result_released: released, advised } },
        });
        for (const label of view.getAllByText('Result released', { exact: true })) {
            const milestone = label.closest('li')!;
            expect(within(milestone).getByText(advised ? 'Complete' : 'Pending')).toBeTruthy();
            expect(within(milestone).queryByText(advised ? 'Pending' : 'Complete')).toBeNull();
        }
        expect(view.queryByText('Release of Results', { exact: true })).toBeNull();
        expect(view.queryByText('Release Day advising', { exact: true })).toBeNull();
        expect(view.queryByText('Result release', { exact: true })).toBeNull();
    });
});

describe('Online consultation applicant detail action', () => {
    it('starts an optional online exception without randomUUID and retains its key across request failures', async () => {
        const requests: Array<{ body: string; url: string }> = [];
        const getRandomValues = crypto.getRandomValues.bind(crypto);
        vi.stubGlobal('crypto', { getRandomValues });
        vi.stubGlobal('route', undefined);
        vi.stubGlobal('fetch', vi.fn(async (url: string, options: RequestInit) => {
            if (!String(url).endsWith('/online/start')) return new Response('{}', { status: 200 });
            requests.push({ url, body: String(options.body) });
            return new Response(JSON.stringify({ message: `Service unavailable ${requests.length}` }), { status: 503 });
        }));

        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
            support: { readiness: { grading_finalized: false, result_ready: true } },
            online_consultation: {
                visible: true,
                can_start: true,
                reason: null,
                exception_mode: true,
                encounter_url: null,
            },
        });

        const action = view.getByRole('button', { name: 'Start online exception' });

        await fireEvent.click(action);
        expect((await view.findByRole('alert')).textContent).toContain('Service unavailable 1');
        await fireEvent.click(action);
        await waitFor(() => expect(view.getByRole('alert').textContent).toContain('Service unavailable 2'));
        expect(requests).toHaveLength(2);
        expect(requests[0].url).toContain('/consultation/applicants/attempt-1/online/start');
        expect(JSON.parse(requests[0].body).idempotency_key).toBe(JSON.parse(requests[1].body).idempotency_key);
        expect(JSON.parse(requests[0].body).idempotency_key).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    });

    it('renders an existing encounter as an open link instead of a second start action', () => {
        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
            support: { readiness: { grading_finalized: false, result_ready: true } },
            online_consultation: {
                visible: true,
                can_start: false,
                reason: null,
                exception_mode: false,
                encounter_url: '/consultation/encounters/enc-1',
            },
        });

        expect(view.getByRole('link', { name: 'Open online consultation' }).getAttribute('href')).toBe('/consultation/encounters/enc-1');
        expect(view.queryByRole('button', { name: /Start online/ })).toBeNull();
    });

    it('keeps normal consultation available and shows only a beta note when media is unverified', () => {
        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
            support: { readiness: { grading_finalized: false, result_ready: true } },
            online_consultation: {
                visible: true,
                can_start: false,
                reason: 'Online consultation is unavailable: live Jitsi admission/control health was not positively verified.',
                exception_mode: true,
                encounter_url: null,
            },
        });
        expect(view.queryByRole('button', { name: /Start online/ })).toBeNull();
        expect((view.getAllByRole('button', { name: /Record Decision/i })[0] as HTMLButtonElement).disabled).toBe(false);
    });

    it('initializes the ordinary decision form before selecting a recommended program', async () => {
        const view = renderGuidanceSupport();

        await fireEvent.click(view.getAllByRole('button', { name: /Record Decision/i })[0]);
        expect(view.getByText('Record Advising Decision')).toBeTruthy();
        const notes = view.getByRole('textbox', { name: /notes.*findings/i });
        await fireEvent.input(notes, { target: { value: 'Earlier draft note' } });
        await fireEvent.click(view.getByRole('button', { name: 'Cancel' }));

        await fireEvent.click(view.getByRole('tab', { name: /Recommendations & Analysis/ }));
        await fireEvent.click(view.getByRole('button', { name: /Add BSIT to the advised pool/ }));

        expect((view.getByRole('textbox', { name: /notes.*findings/i }) as HTMLTextAreaElement).value).toBe('');
    });

    it('does not open the decision form for a recommendation outside the active program list', async () => {
        const view = renderGuidanceSupport([{ code: 'BSN', name: 'Bachelor of Science in Nursing' }]);

        await fireEvent.click(view.getByRole('tab', { name: /Recommendations & Analysis/ }));
        await fireEvent.click(view.getByRole('button', { name: /Add BSIT to the advised pool/ }));

        expect(view.queryByText('Record Advising Decision')).toBeNull();
    });

    it('does not expose the advised-pool action before Guidance consultation is ready', async () => {
        const view = renderGuidanceSupport(undefined, false);

        await fireEvent.click(view.getByRole('tab', { name: /Recommendations & Analysis/ }));

        expect(view.queryByRole('button', { name: /Add BSIT to the advised pool/ })).toBeNull();
    });

    it('renders the saved mode and optional F2F exception control', () => {
        const view = render(ConsultationPolicy, {
            academic_cycles: [{ id: 1, label: '2026–2027', is_active: true }],
            selected_cycle_id: 1,
            policy: {
                normal_method: 'f2f',
                allow_online_exceptions: true,
                version: 3,
                media_ready: true,
                media_unavailable_reason: null,
            },
        });

        expect((view.getByRole('radio', { name: /Face-to-face/ }) as HTMLInputElement).checked).toBe(true);
        expect((view.getByRole('checkbox', { name: /Allow online exceptions/ }) as HTMLInputElement).checked).toBe(true);
    });
    it('uses Release Day breadcrumbs when advising from a session roster', () => {
        mockPage.url = '/consultation/applicants/attempt-1?session_id=41';
        const view = renderGuidanceSupport();
        const breadcrumb = view.getByRole('navigation', { name: 'Breadcrumb' });
        const links = within(breadcrumb);

        expect(
            new URL(
                links
                    .getByRole('link', { name: 'Release Day' })
                    .getAttribute('href') ?? '',
                window.location.origin,
            ).pathname,
        ).toBe('/consultation');
        expect(
            new URL(
                links
                    .getByRole('link', { name: 'Release Day session' })
                    .getAttribute('href') ?? '',
                window.location.origin,
            ).pathname,
        ).toBe('/consultation/sessions/41');
        expect(breadcrumb.textContent).toContain('A-1');
        expect(breadcrumb.textContent).not.toMatch(/Consultation|Counselling/i);
    });
    it('keeps the direct advising breadcrumb on Release Day', () => {
        mockPage.url = '/consultation/applicants/attempt-1';
        const view = renderGuidanceSupport();
        const breadcrumb = view.getByRole('navigation', { name: 'Breadcrumb' });
        const links = within(breadcrumb);

        expect(links.getByRole('link', { name: 'Release Day' })).toBeTruthy();
        expect(links.queryByText('Release Day session')).toBeNull();
        expect(breadcrumb.textContent).toContain('A-1');
        expect(breadcrumb.textContent).not.toMatch(/Consultation|Counselling/i);
    });

    it('preserves My Programs breadcrumbs for Program Head', () => {
        mockPage.url = '/consultation/applicants/attempt-1?session_id=41';
        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'program_head',
            attempt: {
                id: 'attempt-1',
                name: 'A. Applicant',
                reference_code: 'A-1',
            },
            ph_record: {},
        });
        const breadcrumb = view.getByRole('navigation', { name: 'Breadcrumb' });

        expect(within(breadcrumb).getByText('My Programs')).toBeTruthy();
        expect(breadcrumb.textContent).toContain('A-1');
        expect(breadcrumb.textContent).not.toMatch(
            /Consultation|Counselling|Release Day/i,
        );
    });
    it('shows direct and derived assessment results with truthful frozen formula details', () => {
        const view = render(ApplicantAdmissionSupport, {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
            support: {
                readiness: { grading_finalized: false, result_ready: true },
                assessment_detail: {
                    status: 'finalized',
                    subtests: [
                        {
                            code: 'A_RAW',
                            name: 'Reasoning items',
                            metric_name: 'Reasoning items',
                            construct_name: 'Logic',
                            metric_kind: 'direct',
                            raw_score: '8.000000',
                            raw_max_score: '10.000000',
                            raw_min_score: null,
                            percentile: 74,
                            norm_label: 'Synthetic norm',
                            capture_source: 'Manually entered',
                            derivation: '74 PR',
                        },
                        {
                            code: 'WEIGHTED_RAW',
                            name: 'Weighted total',
                            metric_name: 'Weighted total',
                            construct_name: 'Language',
                            metric_kind: 'derived',
                            raw_score: '14.000000',
                            raw_min_score: '0.000000',
                            raw_max_score: '20.000000',
                            percentile: 81,
                            norm_label: 'Synthetic norm',
                            capture_source: 'Derived from raw metrics',
                            formula_expression: '0.20*A_RAW+0.80*B_RAW',
                            formula_substitution: '0.20*10.000000+0.80*15.000000',
                            components: [
                                { code: 'A_RAW', name: 'Reasoning items', raw_value: '10.000000', source: 'Manually entered' },
                                { code: 'B_RAW', name: 'Language items', raw_value: '15.000000', source: 'Imported' },
                            ],
                            derivation: 'Raw: 14.000000 points ➔ Synthetic norm ➔ 81 PR, Stanine 5, Upper band',
                        },
                        {
                            code: 'SUM_RAW',
                            name: 'Summed aptitude metric',
                            metric_name: 'Summed aptitude metric',
                            metric_kind: 'derived',
                            raw_score: '40.000000',
                            raw_min_score: null,
                            raw_max_score: '70.000000',
                            percentile: 84,
                            stanine: 6,
                            converted_label: 'Upper band',
                            norm_label: 'Synthetic norm',
                            capture_source: 'Derived from raw metrics',
                            formula_expression: 'A_RAW+B_RAW',
                            formula_substitution: '20.000000+20.000000',
                            components: [
                                { code: 'A_RAW', name: 'Reasoning items', raw_value: '20.000000', source: 'Manually entered' },
                                { code: 'B_RAW', name: 'Language items', raw_value: '20.000000', source: 'Imported' },
                            ],
                            derivation: 'Raw: 40 ➔ Synthetic norm ➔ 84 PR, Stanine 6, Upper band',
                        },
                        {
                            code: 'EXTERNAL_FINAL',
                            name: 'External aptitude result',
                            metric_name: 'External aptitude result',
                            metric_kind: 'direct',
                            raw_score: null,
                            raw_min_score: null,
                            raw_max_score: null,
                            percentile: 63,
                            capture_source: 'External final (not calculated locally)',
                            derivation: 'Recorded percentile: 63 PR',
                        },
                    ],
                },
            },
        });

        expect(view.getAllByText('Direct measures').length).toBeGreaterThan(0);
        expect(view.getAllByText('Derived measures').length).toBeGreaterThan(0);
        expect(view.getAllByText('Reasoning items').length).toBeGreaterThan(0);
        expect(view.getAllByText('Weighted total').length).toBeGreaterThan(0);
        expect(view.getAllByText('74 PR').length).toBeGreaterThan(0);
        expect(view.queryByText('74%')).toBeNull();
        expect(view.getAllByText(/allowed 0–20/).length).toBeGreaterThan(0);
        expect(view.getAllByText('Formula details').length).toBeGreaterThan(0);
        expect(view.getAllByText(/10\.000000/).length).toBeGreaterThan(0);
        expect(view.getAllByText(/Imported/).length).toBeGreaterThan(0);
        expect(view.getAllByText(/40\s*\/\s*70/).length).toBeGreaterThan(0);
        expect(view.getAllByText(/84 PR, Stanine 6, Upper band/).length).toBeGreaterThan(0);
    });

});


it('keeps decision actions disabled without an individual prepared result even when the batch is finalized', () => {
    const view = render(ApplicantAdmissionSupport, {
        page_state: 'ready',
        role: 'guidance',
        attempt: { id: 'attempt-1', name: 'A. Applicant', reference_code: 'A-1' },
        support: { readiness: { grading_finalized: true, result_ready: false } },
    });

    for (const button of view.getAllByRole('button', { name: /Record Decision/i })) {
        expect((button as HTMLButtonElement).disabled).toBe(true);
    }
});
