// @vitest-environment jsdom
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ImportTemplateBuilder from '../../resources/js/Components/Ingestion/ImportTemplateBuilder.svelte';
import { tick } from 'svelte';

vi.mock('@/Components/Ingestion/ImportWalkthrough.svelte', () => ({
    default: () => null,
}));
type PendingResponse = { resolve(response: Response): void };
const originalCreateObjectURL = URL.createObjectURL;
const originalClipboard = navigator.clipboard;

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    if (originalCreateObjectURL) {
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: originalCreateObjectURL,
        });
    } else {
        Reflect.deleteProperty(URL, 'createObjectURL');
    }
    if (originalClipboard) {
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: originalClipboard,
        });
    } else {
        Reflect.deleteProperty(navigator, 'clipboard');
    }
});

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
});

function jsonResponse(body: unknown, status = 200) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function field(header: string, label: string, group: string, required = false) {
    return {
        header,
        label,
        group,
        required,
        requirement_reason: required
            ? 'Required by the current import contract.'
            : null,
        description: `${label} meaning`,
        source: `${label} source`,
        format: required ? 'Text' : 'Text or blank',
        example: required ? 'Mina' : 'Sample value',
        aliases: required ? ['given name'] : ['alternate spelling'],
    };
}

function applicantSchema(revision = 'policy-3') {
    const fields = [
        field('first_name', 'First name', 'Applicant identity', true),
        field('birthdate', 'Date of birth', 'Applicant identity', true),
        field('email', 'Email address', 'Contact details'),
        field('phone', 'Phone number', 'Contact details'),
    ];
    return {
        type: 'applicants_only',
        academic_cycle: { id: 8, label: '2026–2027 · First semester' },
        test_kit_version_id: null,
        revision,
        fields,
        default_headers: ['first_name', 'birthdate', 'email', 'phone'],
    };
}

function scoreSchema(revision = 'kit-5-r4') {
    return {
        type: 'scores_only',
        academic_cycle: { id: 8, label: '2026–2027 · First semester' },
        test_kit_version_id: 5,
        revision,
        fields: [
            field(
                'applicant_number',
                'Applicant number',
                'Applicant matching',
                true,
            ),
            field('VERBAL', 'Verbal reasoning', 'Accepted score inputs'),
            field('PROFILE_VERBAL', 'Profile verbal', 'Accepted score inputs'),
        ],
        default_headers: ['applicant_number', 'VERBAL', 'PROFILE_VERBAL'],
    };
}

function renderBuilder(props: Record<string, unknown> = {}) {
    return render(ImportTemplateBuilder, {
        props: {
            open: true,
            mode: 'applicants_only',
            allowedModes: ['applicants_only'],
            academicCycle: { id: 8, label: '2026–2027 · First semester' },
            ...props,
        },
    });
}

describe('import template builder', () => {
    it('keeps searched selections, resets to current defaults, and reports clipboard outcomes', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock.mockResolvedValueOnce(jsonResponse(applicantSchema()));
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: { writeText },
        });

        renderBuilder();
        await screen.findByRole('button', { name: 'Reset' });

        const preview = screen.getByLabelText(
            'Scrollable ordered header preview',
        );
        expect((preview as HTMLTextAreaElement).value).toBe(
            'first_name\tbirthdate\temail\tphone',
        );
        expect(screen.getByText('First name meaning')).toBeTruthy();
        expect(screen.getByText('First name source')).toBeTruthy();
        expect(screen.getByText('given name')).toBeTruthy();

        await fireEvent.input(
            screen.getByRole('searchbox', { name: 'Search supported fields' }),
            {
                target: { value: 'no matching field' },
            },
        );
        expect(
            screen.queryByRole('checkbox', { name: /Email address/ }),
        ).toBeNull();
        expect(
            screen.getByRole('button', { name: 'Remove email' }),
        ).toBeTruthy();
        expect((preview as HTMLTextAreaElement).value).toBe(
            'first_name\tbirthdate\temail\tphone',
        );

        await fireEvent.click(
            screen.getByRole('button', { name: 'Remove email' }),
        );
        expect((preview as HTMLTextAreaElement).value).toBe(
            'first_name\tbirthdate\tphone',
        );
        await fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
        expect((preview as HTMLTextAreaElement).value).toBe(
            'first_name\tbirthdate\temail\tphone',
        );

        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy header' }),
        );
        await waitFor(() =>
            expect(writeText).toHaveBeenCalledWith('first_name'),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy all headers' }),
        );
        await waitFor(() =>
            expect(writeText).toHaveBeenLastCalledWith(
                'first_name\tbirthdate\temail\tphone',
            ),
        );
        expect(screen.getByRole('status').textContent).toContain(
            'Headers copied.',
        );

        writeText.mockRejectedValueOnce(new Error('clipboard denied'));
        await fireEvent.click(
            screen.getByRole('button', { name: 'Copy header' }),
        );
        expect(await screen.findByText(/Could not copy header/)).toBeTruthy();
    });

    it('binds exact kit/session context and refreshes a stale contract before CSV download', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock
            .mockResolvedValueOnce(jsonResponse(scoreSchema()))
            .mockResolvedValueOnce(jsonResponse(scoreSchema()))
            .mockResolvedValueOnce(
                jsonResponse(
                    {
                        message: 'Revision changed.',
                        errors: { revision: ['Refresh the schema.'] },
                    },
                    409,
                ),
            )
            .mockResolvedValueOnce(jsonResponse(scoreSchema('kit-5-r5')))
            .mockResolvedValueOnce(
                new Response('applicant_number,VERBAL,PROFILE_VERBAL\n', {
                    status: 200,
                    headers: { 'Content-Type': 'text/csv; charset=UTF-8' },
                }),
            );

        const createObjectURL = vi.fn().mockReturnValue('blob:import-template');
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: createObjectURL,
        });
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(
            () => {},
        );

        renderBuilder({
            mode: 'scores_only',
            allowedModes: ['scores_only'],
            testKitVersions: [
                {
                    id: 5,
                    display_name: 'Cognitive Kit',
                    kit_code: 'CK',
                    version_number: 2,
                    status: 'active',
                },
            ],
            targetExamSessions: [
                {
                    id: 91,
                    label: 'Morning session',
                    status: 'completed',
                    test_kit_version_id: 5,
                },
            ],
        });

        await fireEvent.change(
            screen.getByRole('combobox', {
                name: 'Exact locked Test Kit version',
            }),
            {
                target: { value: '5' },
            },
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        expect(fetchMock.mock.calls[0][1]?.headers).toEqual(
            expect.objectContaining({ Accept: 'application/json' }),
        );
        const firstSchemaUrl = new URL(
            String(fetchMock.mock.calls[0][0]),
            'http://localhost',
        );
        expect(firstSchemaUrl.searchParams.get('test_kit_version_id')).toBe(
            '5',
        );
        expect(firstSchemaUrl.searchParams.get('academic_cycle_id')).toBe('8');

        await fireEvent.change(
            screen.getByRole('combobox', {
                name: 'Target exam session (optional)',
            }),
            {
                target: { value: '91' },
            },
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        await waitFor(() =>
            expect(
                screen
                    .getByRole('button', { name: 'Download CSV' })
                    .hasAttribute('disabled'),
            ).toBe(false),
        );
        expect(fetchMock.mock.calls[1][1]?.headers).toEqual(
            expect.objectContaining({ Accept: 'application/json' }),
        );
        const contextualSchemaUrl = new URL(
            String(fetchMock.mock.calls[1][0]),
            'http://localhost',
        );
        expect(
            contextualSchemaUrl.searchParams.get('target_exam_session_id'),
        ).toBe('91');

        await fireEvent.click(
            screen.getByRole('button', { name: 'Download CSV' }),
        );
        await screen.findByText(/The template contract changed/);
        expect(fetchMock.mock.calls[2][1]?.headers).toEqual(
            expect.objectContaining({ Accept: 'application/json' }),
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(4));
        expect(screen.getByText(/Review the refreshed headers/)).toBeTruthy();
        expect(
            screen
                .getByRole('button', { name: 'Download CSV' })
                .hasAttribute('disabled'),
        ).toBe(true);
        await fireEvent.click(
            screen.getByRole('button', {
                name: 'I reviewed the refreshed headers',
            }),
        );

        vi.spyOn(window, 'setTimeout').mockReturnValue(0);
        await fireEvent.click(
            screen.getByRole('button', { name: 'Download CSV' }),
        );
        await screen.findByText('CSV template downloaded.');
        const exportUrl = new URL(
            String(fetchMock.mock.calls[4][0]),
            'http://localhost',
        );
        expect(exportUrl.pathname).toBe('/imports/templates/scores_only');
        expect(exportUrl.searchParams.get('revision')).toBe('kit-5-r5');
        expect(exportUrl.searchParams.getAll('headers[]')).toEqual([
            'applicant_number',
            'VERBAL',
            'PROFILE_VERBAL',
        ]);
    });
    it.each([403, 409])(
        'ignores a late HTTP %s export error after context changes',
        async (status) => {
            const exportResponse = Promise.withResolvers<Response>();
            const fetchMock = vi.fn((input: RequestInfo | URL) => {
                const url = String(input);
                if (url.includes('/schema')) {
                    return Promise.resolve(
                        url.includes('/scores_only/schema')
                            ? jsonResponse(scoreSchema())
                            : jsonResponse(applicantSchema()),
                    );
                }
                return exportResponse.promise;
            });
            vi.stubGlobal('fetch', fetchMock);
            const initialProps = {
                open: true,
                mode: 'applicants_only',
                allowedModes: ['applicants_only', 'scores_only'],
                academicCycle: { id: 8, label: '2026–2027 · First semester' },
                testKitVersions: [
                    {
                        id: 5,
                        display_name: 'Cognitive Kit',
                        kit_code: 'CK',
                        version_number: 2,
                        status: 'active',
                    },
                ],
            };
            const view = render(ImportTemplateBuilder, { props: initialProps });
            await screen.findByRole('button', { name: 'Copy all headers' });
            await waitFor(() =>
                expect(
                    screen
                        .getByRole('button', { name: 'Download CSV' })
                        .hasAttribute('disabled'),
                ).toBe(false),
            );

            await fireEvent.click(
                screen.getByRole('button', { name: 'Download CSV' }),
            );
            await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
            await view.rerender({
                ...initialProps,
                mode: 'scores_only',
                testKitVersionId: '5',
            });
            await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));
            await waitFor(() =>
                expect(
                    screen.getAllByText('applicant_number').length,
                ).toBeGreaterThan(0),
            );

            const lateJson = vi
                .fn()
                .mockResolvedValue({
                    message: 'Late stale or denied response.',
                });
            exportResponse.resolve({
                ok: false,
                status,
                json: lateJson,
            } as Response);
            await exportResponse.promise;
            await tick();
            expect(lateJson).not.toHaveBeenCalled();
            expect(
                screen
                    .getByRole('button', { name: 'Download CSV' })
                    .hasAttribute('disabled'),
            ).toBe(false);
            expect(screen.queryByRole('alert')).toBeNull();
            expect(screen.queryByText(/template contract changed/i)).toBeNull();
        },
    );

    it('does not let a late schema response replace a newly selected mode context', async () => {
        const fetchMock = vi.mocked(fetch);
        const pending: PendingResponse[] = [];
        fetchMock.mockImplementation(() => {
            const deferred = Promise.withResolvers<Response>();
            pending.push({ resolve: deferred.resolve });
            return deferred.promise;
        });

        renderBuilder({
            allowedModes: ['applicants_only', 'scores_only'],
            testKitVersions: [
                {
                    id: 5,
                    display_name: 'Cognitive Kit',
                    kit_code: 'CK',
                    version_number: 2,
                    status: 'active',
                },
            ],
        });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
        const mode = screen.getByRole('combobox', { name: 'Template type' });
        await fireEvent.change(mode, { target: { value: 'scores_only' } });
        await fireEvent.change(
            await screen.findByRole('combobox', {
                name: 'Exact locked Test Kit version',
            }),
            { target: { value: '5' } },
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

        pending[1].resolve(jsonResponse(scoreSchema()));
        await waitFor(() =>
            expect(
                screen.getAllByText('applicant_number').length,
            ).toBeGreaterThan(0),
        );
        pending[0].resolve(jsonResponse(applicantSchema()));
        await Promise.resolve();
        await Promise.resolve();
        expect(screen.getAllByText('applicant_number').length).toBeGreaterThan(
            0,
        );
        expect(screen.queryByText('first_name')).toBeNull();
    });

    it('keeps output disabled after a schema error and permits an explicit retry', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock
            .mockResolvedValueOnce(
                jsonResponse({ message: 'Schema unavailable.' }, 503),
            )
            .mockResolvedValueOnce(jsonResponse(applicantSchema()));

        renderBuilder();
        expect(await screen.findByRole('alert')).toBeTruthy();
        expect(
            screen
                .getByRole('button', { name: 'Copy all headers' })
                .hasAttribute('disabled'),
        ).toBe(true);
        expect(
            screen
                .getByRole('button', { name: 'Download CSV' })
                .hasAttribute('disabled'),
        ).toBe(true);

        await fireEvent.click(
            screen.getByRole('button', { name: 'Retry schema' }),
        );
        expect(
            await screen.findByRole('checkbox', { name: /First name/ }),
        ).toBeTruthy();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
    it('keeps score output disabled when no locked kit is available', async () => {
        const fetchMock = vi.mocked(fetch);
        renderBuilder({
            mode: 'scores_only',
            allowedModes: ['scores_only'],
            testKitVersions: [],
        });

        expect((await screen.findByRole('status')).textContent).toContain(
            'No authorized locked Test Kit version',
        );
        expect(
            screen
                .getByRole('button', { name: 'Copy all headers' })
                .hasAttribute('disabled'),
        ).toBe(true);
        expect(
            screen
                .getByRole('button', { name: 'Download CSV' })
                .hasAttribute('disabled'),
        ).toBe(true);
        expect(fetchMock).not.toHaveBeenCalled();
    });
    it('offers only active kits and no target session for combined templates', async () => {
        const fetchMock = vi.mocked(fetch);
        fetchMock.mockResolvedValueOnce(jsonResponse(scoreSchema()));
        renderBuilder({
            mode: 'all_in_one',
            allowedModes: ['all_in_one'],
            testKitVersions: [
                {
                    id: 5,
                    display_name: 'Active Kit',
                    kit_code: 'AK',
                    version_number: 2,
                    status: 'active',
                },
                {
                    id: 6,
                    display_name: 'Retired Kit',
                    kit_code: 'RK',
                    version_number: 1,
                    status: 'retired',
                },
            ],
            targetExamSessions: [
                {
                    id: 91,
                    label: 'Morning session',
                    status: 'completed',
                    test_kit_version_id: 5,
                },
            ],
        });

        const kitSelector = screen.getByRole('combobox', {
            name: 'Exact locked Test Kit version',
        });
        expect(
            Array.from((kitSelector as HTMLSelectElement).options).map(
                (option) => option.value,
            ),
        ).toEqual(['', '5']);
        await fireEvent.change(kitSelector, { target: { value: '5' } });
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

        const schemaUrl = new URL(
            String(fetchMock.mock.calls[0][0]),
            'http://localhost',
        );
        expect(schemaUrl.pathname).toBe('/imports/templates/all_in_one/schema');
        expect(schemaUrl.searchParams.get('test_kit_version_id')).toBe('5');
        expect(schemaUrl.searchParams.get('target_exam_session_id')).toBeNull();
    });
});
