// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, within } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ApplicantEvidenceBar from '../../resources/js/Components/admission-support/ApplicantEvidenceBar.svelte';
import SupplementalEvidenceModal from '../../resources/js/Components/admission-support/SupplementalEvidenceModal.svelte';
import TabFmBenchmarkCalibrator from '../../resources/js/Components/admission-support/TabFmBenchmarkCalibrator.svelte';

const formMock = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/svelte', () => ({
    useForm(initial: Record<string, unknown>) {
        let transform = (data: Record<string, unknown>) => data;
        const form: Record<string, any> = {
            ...initial,
            errors: {},
            processing: false,
            clearErrors: vi.fn(),
            setStore(data: Record<string, unknown>) {
                Object.assign(form, data);
                return form;
            },
            transform(callback: (data: Record<string, unknown>) => Record<string, unknown>) {
                transform = callback;
                return form;
            },
            post(url: string, options: Record<string, unknown>) {
                const data = Object.fromEntries(Object.entries(form).filter(([key]) => !['errors', 'processing', 'clearErrors', 'setStore', 'transform', 'post'].includes(key)));
                formMock.post(url, transform(data), options);
            },
        };
        return form;
    },
}));

vi.mock('../../resources/js/lib/toast.js', () => ({ toastSuccess: vi.fn() }));

const inputSchema = {
    subjects: [
        {
            key: 'biology_lab',
            label: 'Biology Laboratory',
            logical_type: 'numeric',
            source_kind: 'approved_intake_binding',
            source_id: 17,
            source_code: 'BIO-LAB',
            source_version: '3',
            unit: 'points',
            min_value: 0,
            max_value: 100,
            is_required: false,
            position: 4,
        },
    ],
    activities: [
        { id: 'hardware_tech', label: 'Hardware and systems', parent: 'riasec_r' },
        { id: 'software_algorithms', label: 'Algorithms and software', parent: 'riasec_i' },
        { id: 'literary_writing', label: 'Literary expression', parent: 'riasec_a' },
        { id: 'teaching_pedagogy', label: 'Classroom pedagogy', parent: 'riasec_s' },
        { id: 'business_leadership', label: 'Business leadership', parent: 'riasec_e' },
        { id: 'records_clerical', label: 'Office and records administration', parent: 'riasec_c' },
    ],
};

const modalProps = {
    open: true,
    onClose: vi.fn(),
    attemptId: 91,
    inputSchema,
    benchmarkActivationId: 12,
    schemaDigest: 'schema-digest-12',
    baselineEvidence: {
        subject_grades: { biology_lab: 84 },
        vocational_activities: ['software_algorithms'],
        activities_reviewed: true,
        evidence_source: 'physical_form_138',
        counselor_notes: 'Reviewed with the applicant.',
    },
};

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

beforeEach(() => {
    formMock.post.mockReset();
});

describe('supplemental consultation evidence', () => {
    it('records GWA and counselor notes without retired RIASEC career activity controls or subject-grade fields', async () => {
        render(SupplementalEvidenceModal, { props: modalProps });
        expect(screen.queryByLabelText('Biology Laboratory')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Algorithms and software' })).toBeNull();
        expect(screen.queryByLabelText(/I reviewed the applicant’s activities/)).toBeNull();

        await fireEvent.input(screen.getByLabelText(/High School \/ Prior GWA/i), { target: { value: '88.5' } });
        await fireEvent.input(screen.getByLabelText('Counselor notes'), { target: { value: 'Updated GWA and advising notes.' } });
        await fireEvent.submit(screen.getByRole('dialog').querySelector('form')!);

        const payload = formMock.post.mock.calls[0][1];
        expect(payload).not.toHaveProperty('subject_grades');
        expect(payload).not.toHaveProperty('vocational_activities');
        expect(payload.gwa).toBe(88.5);
        expect(payload.counselor_notes).toBe('Updated GWA and advising notes.');
        expect(payload.evidence_source).toBe('physical_form_138');
    });

    it('allows recording counselor notes even when benchmark activation binding is absent', async () => {
        render(SupplementalEvidenceModal, {
            props: {
                ...modalProps,
                benchmarkActivationId: null,
                schemaDigest: null,
            },
        });
        expect(screen.getByRole('alert').textContent).toContain('Notes and GWA can still be recorded');
        expect((screen.getByRole('button', { name: 'Record Notes' }) as HTMLButtonElement).disabled).toBe(false);
        await fireEvent.input(screen.getByLabelText('Counselor notes'), { target: { value: 'Notes recorded without active benchmark.' } });
        await fireEvent.submit(screen.getByRole('dialog').querySelector('form')!);
        expect(formMock.post).toHaveBeenCalledOnce();
    });

    it('renders GWA, SHS strand, counselor notes, and the subtle inline unit toggle chip when converted', async () => {
        render(ApplicantEvidenceBar, {
            props: {
                evidence: {
                    applicant_type: 'transferee',
                    gwa: 95.13,
                    gwa_raw: 4.75,
                    gwa_scale: 'gpa_5_highest',
                    gwa_scale_label: '5.00 best',
                    gwa_is_converted: true,
                    strand: 'Bachelor of Early Childhood Education',
                    counselor_notes: 'Verified against official TOR.',
                },
            },
        });
        expect(screen.getByText('Prior College / TOR GWA')).toBeTruthy();
        expect(screen.getByText('95.13')).toBeTruthy();
        const switcher = screen.getByRole('button', { name: /4\.75/i });
        await fireEvent.click(switcher);
        expect(screen.getByText('4.75')).toBeTruthy();
        expect(screen.getByText('(5.00 best)')).toBeTruthy();
        expect(screen.getByRole('button', { name: /95\.13/i })).toBeTruthy();
    });
});
describe('benchmark authoring controls', () => {
    it('keeps blank source ranges honest, preserves unsaved tab values, and separates prepare, apply, and save', async () => {
        const features = [
            {
                key: 'gwa',
                label: 'GWA',
                logical_type: 'numeric',
                is_required: true,
                unit_unverified: true,
                source_definition: { source_kind: 'frozen_legacy', min_value: null, max_value: null },
            },
            {
                key: 'biology_lab',
                label: 'Biology Laboratory',
                logical_type: 'numeric',
                is_required: false,
                source_definition: { unit: 'points', min_value: 0, max_value: 100 },
            },
        ];
        const content = {
            tiers: {
                '1': { values: { gwa: 90, biology_lab: 90 } },
                '2': { values: { gwa: 85, biology_lab: 85 } },
                '3': { values: { gwa: 80, biology_lab: 80 } },
                '4': { values: {} },
                '5': { values: {} },
            },
        };
        const onContentChange = vi.fn();
        const onSave = vi.fn(async () => true);

        const props = {
                program: { code: 'GOV-DEMO-A' },
                cycle: { code: 'SYN-CYCLE-2026' },
                features,
                inputSchema: { subjects: [inputSchema.subjects[0]], activities: inputSchema.activities },
                content,
                version: 1,
                canEdit: true,
                dirty: true,
                onContentChange,
                onSave,
                onReset: vi.fn(),
        };
        const { rerender } = render(TabFmBenchmarkCalibrator, { props });

        const corePanel = screen.getByRole('region', { name: 'Programme benchmark editor' });
        const coreFrom = within(corePanel).getByLabelText('Common range · From') as HTMLInputElement;
        const coreTo = within(corePanel).getByLabelText('Common range · To') as HTMLInputElement;
        expect(coreFrom.value).toBe('');
        expect(coreTo.value).toBe('');
        expect(coreFrom.hasAttribute('min')).toBe(false);
        expect(coreFrom.hasAttribute('max')).toBe(false);
        expect(screen.queryAllByRole('slider')).toHaveLength(0);

        await fireEvent.input(coreFrom, { target: { value: '70' } });
        await fireEvent.input(coreTo, { target: { value: '92' } });
        await rerender({ ...props, view: 'profile' });
        await rerender({ ...props, view: 'core' });
        expect((screen.getByLabelText('Common range · From') as HTMLInputElement).value).toBe('70');
        expect((screen.getByLabelText('Common range · To') as HTMLInputElement).value).toBe('92');
        expect(onContentChange).not.toHaveBeenCalled();
        expect(onSave).not.toHaveBeenCalled();

        await fireEvent.click(screen.getByRole('button', { name: 'Prepare examples' }));
        expect(screen.getByText('Review before applying · local only')).toBeTruthy();
        expect(onContentChange).not.toHaveBeenCalled();
        expect(onSave).not.toHaveBeenCalled();

        await fireEvent.click(screen.getByRole('button', { name: 'Apply reviewed values to this draft' }));
        expect(onContentChange).toHaveBeenCalledOnce();
        const appliedContent = onContentChange.mock.calls[0][0];
        expect(appliedContent.tiers['4'].values).toEqual({});
        expect(appliedContent.tiers['5'].values).toEqual({});
        expect(onSave).not.toHaveBeenCalled();

        await fireEvent.click(screen.getByRole('button', { name: 'Save changes' }));
        expect(onSave).not.toHaveBeenCalled();
        await fireEvent.input(screen.getByLabelText('Reason for this save'), { target: { value: 'Reviewed sample values' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Confirm save' }));
        expect(onSave).toHaveBeenCalledOnce();
    });
});
