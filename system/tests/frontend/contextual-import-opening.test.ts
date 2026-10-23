// @vitest-environment jsdom
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';
import { tick } from 'svelte';
import ContextualImportModal from '../../resources/js/Components/Ingestion/ContextualImportModal.svelte';
vi.mock('@/Components/Ingestion/ImportWalkthrough.svelte', () => ({
    default: () => null,
}));

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.unstubAllGlobals();
});
function jsonResponse(body: unknown) {
    return new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    });
}

it.each(['scores_only', 'applicants_only'])(
    'opens %s before analysis with review disabled',
    (importType) => {
        render(ContextualImportModal, { props: { open: true, importType } });

        const review = screen.getByRole('button', { name: 'Review rows' });
        expect(review.hasAttribute('disabled')).toBe(true);
        expect(
            screen
                .getByRole('button', { name: 'Cancel' })
                .hasAttribute('disabled'),
        ).toBe(false);
        expect(screen.getByRole('dialog')).toBeTruthy();
    },
);
it('returns from template preparation with the selected file and analysis intact', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(
            jsonResponse({
                file_name: 'intake.csv',
                total_rows: 1,
                checks: [],
                missing_required: [],
                sheets: [],
                column_analysis: [],
            }),
        )
        .mockResolvedValueOnce(
            jsonResponse({
                type: 'applicants_only',
                academic_cycle: { id: 8, label: '2026–2027 · First semester' },
                test_kit_version_id: null,
                revision: 'policy-1',
                fields: [
                    {
                        header: 'first_name',
                        label: 'First name',
                        group: 'Applicant identity',
                        required: true,
                        requirement_reason:
                            'Required by the current import contract.',
                        description: 'First name meaning',
                        source: 'First name source',
                        format: 'Text',
                        example: 'Mina',
                        aliases: [],
                    },
                ],
                default_headers: ['first_name'],
            }),
        );
    vi.stubGlobal('fetch', fetchMock);

    render(ContextualImportModal, {
        props: {
            open: true,
            importType: 'applicants_only',
            academicCycle: { id: 8, label: '2026–2027 · First semester' },
        },
    });

    const file = new File(['first_name\\nMina'], 'intake.csv', {
        type: 'text/csv',
    });
    await fireEvent.change(screen.getByLabelText(/Select spreadsheet file/), {
        target: { files: [file] },
    });
    await screen.findByText('intake.csv');
    await waitFor(() =>
        expect(
            screen
                .getByRole('button', { name: 'Review 1 rows' })
                .hasAttribute('disabled'),
        ).toBe(false),
    );

    await fireEvent.click(
        screen.getByRole('button', { name: 'Build import template' }),
    );
    await screen.findByRole('heading', { name: 'Build import template' });
    expect(await screen.findByText('First name meaning')).toBeTruthy();
    expect(fetchMock).toHaveBeenCalledTimes(2);
    const applicantAnalysisBody = fetchMock.mock.calls[0][1].body as FormData;
    expect(applicantAnalysisBody.get('import_type')).toBe('applicants_only');
    expect(applicantAnalysisBody.get('test_kit_version_id')).toBeNull();

    vi.useFakeTimers();
    await fireEvent.click(screen.getByRole('button', { name: 'Done' }));
    await vi.advanceTimersByTimeAsync(180);
    await tick();
    expect(screen.getByText('intake.csv')).toBeTruthy();
    expect(
        screen
            .getByRole('button', { name: 'Review 1 rows' })
            .hasAttribute('disabled'),
    ).toBe(false);
    await vi.advanceTimersByTimeAsync(250);
    await tick();
    expect(document.activeElement).toBe(
        screen.getByRole('button', { name: 'Build import template' }),
    );
    expect(fetchMock).toHaveBeenCalledTimes(2);
});
it('analyzes score files against the exact kit and completed target session', async () => {
    const fetchMock = vi.fn().mockResolvedValueOnce(
        jsonResponse({
            file_name: 'scores.csv',
            total_rows: 1,
            checks: [],
            missing_required: [],
            sheets: [],
            column_analysis: [],
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    render(ContextualImportModal, {
        props: {
            open: true,
            importType: 'scores_only',
            academicCycle: { id: 8, label: '2026–2027 · First semester' },
            scoreImportTargets: [
                {
                    id: 91,
                    label: 'Morning session',
                    kit_label: 'Cognitive Kit',
                    test_kit_version_id: 5,
                },
            ],
            kitVersions: [
                {
                    id: 5,
                    display_name: 'Cognitive Kit',
                    kit_code: 'CK',
                    version_number: 2,
                },
            ],
        },
    });

    await fireEvent.change(
        screen.getByRole('combobox', { name: 'Completed target session' }),
        {
            target: { value: '91' },
        },
    );
    await fireEvent.change(
        screen.getByRole('combobox', { name: 'Exact locked Test Kit version' }),
        {
            target: { value: '5' },
        },
    );
    const file = new File(
        ['applicant_number,VERBAL\\nAY26-00001,42'],
        'scores.csv',
        { type: 'text/csv' },
    );
    await fireEvent.change(screen.getByLabelText(/Select spreadsheet file/), {
        target: { files: [file] },
    });
    await screen.findByText('scores.csv');
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

    const analysisBody = fetchMock.mock.calls[0][1].body as FormData;
    expect(analysisBody.get('import_type')).toBe('scores_only');
    expect(analysisBody.get('academic_cycle_id')).toBe('8');
    expect(analysisBody.get('test_kit_version_id')).toBe('5');
    expect(analysisBody.get('target_exam_session_id')).toBe('91');
});
