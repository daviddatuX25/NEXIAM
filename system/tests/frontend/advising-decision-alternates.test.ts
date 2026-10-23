// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    page: { props: { flash: {} as Record<string, unknown> }, url: '/consultation/applicants/42' },
    posts: [] as Array<{ url: string; payload: Record<string, unknown> }>,
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        usePage: () => mocks.page,
        useForm(initial: Record<string, unknown>) {
            const form = {
                ...initial,
                errors: {} as Record<string, string>,
                processing: false,
                clearErrors: vi.fn(),
                transform(callback: () => Record<string, unknown>) {
                    this.payload = callback();
                    return this;
                },
                payload: initial,
                post(url: string, options: { onSuccess?: () => void }) {
                    mocks.posts.push({ url, payload: this.payload });
                    options.onSuccess?.();
                },
            };

            return form;
        },
    };
});

vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));
vi.mock('@/Pages/Admission/E2PageState.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/TabFmConsultationCard.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/YuKeeConsultationCard.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/ApplicantEvidenceBar.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/SupplementalEvidenceModal.svelte', () => ({ default: () => null }));
vi.mock('@/Components/consultation/ConsultationMobileDock.svelte', () => ({ default: () => null }));
vi.mock('@/Components/consultation/ApplicantLookupDialog.svelte', () => ({ default: () => null }));
vi.mock('@/Components/admission-support/UnknownEngineCard.svelte', () => ({ default: () => null }));
vi.mock('@/Components/Admission/CaseConversation.svelte', () => ({ default: () => null }));
vi.mock('@/Components/DocumentTemplate/PrintDocumentsSlot.svelte', () => ({ default: () => null }));

import ApplicantAdmissionSupport from '../../resources/js/Pages/Admission/ApplicantAdmissionSupport.svelte';

const programs = [
    { code: 'BEEd', name: 'Bachelor of Elementary Education', configured: true, remaining: 8, committed: 2, capacity: 10 },
    { code: 'BSIT', name: 'Information Technology', configured: true, remaining: 5, committed: 5, capacity: 10 },
    { code: 'BSBA', name: 'Business Administration', configured: false, remaining: null, committed: 0, capacity: 0 },
];

function renderSupport(programPool = { default: null, options: [] }) {
    return render(ApplicantAdmissionSupport, {
        props: {
            page_state: 'ready',
            role: 'guidance',
            attempt: { id: 42, name: 'Test Applicant', reference_code: 'APP-42' },
            support: {
                preferences: ['BEEd'],
                readiness: { grading_finalized: false, result_ready: true },
                program_pool: programPool,
                score_profile: { results: [] },
            },
            available_programs: programs,
        },
    });
}

async function openDecision() {
    await fireEvent.click(screen.getByRole('button', { name: 'Record Decision' }));
    return screen.findByRole('dialog');
}


afterEach(async () => {
    cleanup();
    await waitFor(() => expect(document.body.style.overflow).not.toBe('hidden'));
    mocks.posts.length = 0;
    vi.unstubAllGlobals();
});

describe('Record Advising Decision alternate options', () => {
    it('keeps alternate controls reachable before the full catalog and notes', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        renderSupport();
        const dialog = await openDecision();
        const alternateButton = within(dialog).getByRole('button', { name: 'Add Alternate Degree Option' });

        expect(alternateButton.compareDocumentPosition(within(dialog).getByRole('heading', { name: 'Programs and quota' })) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(alternateButton.compareDocumentPosition(within(dialog).getByLabelText('Guidance Notes & Findings')) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });

    it('requires a deliberate valid alternate, then posts the selected primary and applicant choice exactly', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        renderSupport();
        const dialog = await openDecision();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Add Alternate Degree Option' }));

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));
        expect(await within(dialog).findByRole('alert')).toBeTruthy();
        expect(mocks.posts).toHaveLength(0);

        await fireEvent.change(within(dialog).getByRole('combobox', { name: 'Alternate degree option 1' }), { target: { value: 'BSIT' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));

        expect(mocks.posts).toHaveLength(1);
        expect(mocks.posts[0].payload).toMatchObject({
            decision_type: 'approve',
            default_program_code: 'BEEd',
            pool_options: [
                { program_code: 'BEEd', display_order: 1, basis: 'preference' },
                { program_code: 'BSIT', display_order: 2, basis: 'guidance_option' },
            ],
            pool_reason: 'Advising decision',
        });
        expect(Object.keys(mocks.posts[0].payload).sort()).toEqual([
            'decision_type', 'default_program_code', 'idempotency_key', 'notes', 'pool_options', 'pool_reason',
        ]);
    });
    it('submits the primary alone when no alternate is intentionally added', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        renderSupport();
        const dialog = await openDecision();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));

        expect(mocks.posts[0].payload).toMatchObject({
            default_program_code: 'BEEd',
            pool_options: [{ program_code: 'BEEd', display_order: 1, basis: 'preference' }],
        });
    });

    it('allows changing an existing saved alternate deliberately', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        renderSupport({
            default: 'BEEd',
            options: [
                { program_code: 'BEEd', display_order: 1, basis: 'preference' },
                { program_code: 'BSBA', display_order: 2, basis: 'guidance_option' },
            ],
        });
        const dialog = await openDecision();
        await fireEvent.change(within(dialog).getByRole('combobox', { name: 'Alternate degree option 1' }), { target: { value: 'BSIT' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));

        expect(mocks.posts[0].payload).toMatchObject({
            default_program_code: 'BEEd',
            pool_options: [
                { program_code: 'BEEd', display_order: 1, basis: 'preference' },
                { program_code: 'BSIT', display_order: 2, basis: 'guidance_option' },
            ],
        });
    });
    it('requires an explicit primary and retains saved options when no counselor default exists', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        renderSupport({
            default: null,
            options: [
                { program_code: 'BEEd', display_order: 1, basis: 'preference' },
                { program_code: 'BSIT', display_order: 2, basis: 'guidance_option' },
            ],
        });
        const dialog = await openDecision();
        const primary = within(dialog).getByRole('combobox', { name: 'Primary recommended program' }) as HTMLSelectElement;
        expect(primary.value).toBe('');
        expect(within(dialog).getByText(/no recorded primary counselor recommendation/i)).toBeTruthy();
        expect((within(dialog).getByRole('combobox', { name: 'Alternate degree option 1' }) as HTMLSelectElement).value).toBe('BEEd');
        expect((within(dialog).getByRole('combobox', { name: 'Alternate degree option 2' }) as HTMLSelectElement).value).toBe('BSIT');

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));
        expect(mocks.posts).toHaveLength(0);
        await fireEvent.change(primary, { target: { value: 'BEEd' } });
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));

        expect(mocks.posts[0].payload).toMatchObject({
            default_program_code: 'BEEd',
            pool_options: [
                { program_code: 'BEEd', display_order: 1, basis: 'preference' },
                { program_code: 'BSIT', display_order: 2, basis: 'guidance_option' },
            ],
        });
    });

    it('submits one primary without adding an option and preserves existing pool choices when reopened', async () => {
        vi.stubGlobal('route', (name: string) => `/${name}`);
        const savedPool = {
            default: 'BSIT',
            options: [
                { program_code: 'BSIT', display_order: 1, basis: 'guidance_option' },
                { program_code: 'BSBA', display_order: 2, basis: 'guidance_option' },
            ],
        };
        renderSupport(savedPool);
        let dialog = await openDecision();
        expect((screen.getByRole('combobox', { name: 'Primary recommended program' }) as HTMLSelectElement).value).toBe('BSIT');
        expect((screen.getByRole('combobox', { name: 'Alternate degree option 1' }) as HTMLSelectElement).value).toBe('BSBA');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Endorse Program' }));
        expect((within(dialog).getByRole('combobox', { name: 'Primary recommended program' }) as HTMLSelectElement).value).toBe('BSIT');
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Remove alternate program 1' }));
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Save Decision' }));
        expect(mocks.posts[0].payload).toMatchObject({
            default_program_code: 'BSIT',
            pool_options: [{ program_code: 'BSIT', display_order: 1, basis: 'guidance_option' }],
        });

        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        await fireEvent.click(screen.getByRole('button', { name: 'Record Decision' }));
        dialog = await screen.findByRole('dialog');
        expect((screen.getByRole('combobox', { name: 'Primary recommended program' }) as HTMLSelectElement).value).toBe('BSIT');
        expect((screen.getByRole('combobox', { name: 'Alternate degree option 1' }) as HTMLSelectElement).value).toBe('BSBA');
        expect(mocks.posts).toHaveLength(1);
    });
});
