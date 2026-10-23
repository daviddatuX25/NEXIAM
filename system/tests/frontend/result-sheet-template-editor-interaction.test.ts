// @vitest-environment jsdom

import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { get } from 'svelte/store';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/svelte';
import { toasts } from '../../resources/js/lib/toast.js';
import ResultSheetTemplateEditor from '../../resources/js/Components/ResultSheet/ResultSheetTemplateEditor.svelte';

beforeEach(() => {
    toasts.clear();
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({
            ok: true,
            json: async () => ({ draft: { id: 1, lock_version: 1 } }),
        })),
    );
});

afterEach(() => {
    cleanup();
    window.history.replaceState({}, '', '/');
    vi.useRealTimers();
    toasts.clear();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

it('does not expose any Source & Prompt surface to staff across stages', async () => {
    const builderDocument = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                locked: false,
                props: { title: 'State University' },
            },
        ],
    };
    const builderCatalog = {
        header: { label: 'Header', description: 'Institution header' },
    };
    const { getByRole, queryByText } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: {
                name: 'Clean Surface Template',
                mode: 'html',
                builder_json: builderDocument,
            },
            builderDefaults: builderDocument,
            builderCatalog,
        },
    });

    expect(queryByText(/Source & Prompt/i)).toBeNull();

    await fireEvent.click(getByRole('button', { name: /Continue to Design/ }));
    await tick();
    await tick();

    expect(queryByText(/Source & Prompt/i)).toBeNull();
    expect(queryByText(/Builder JSON/i)).toBeNull();
    expect(queryByText(/Suite Prompt/i)).toBeNull();
});

it('exposes lifecycle controls in create mode and hides them in edit mode', () => {
    const builderDocument = {
        schema_version: '1.0.0',
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [],
    };

    const createView = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Draft Lifecycle Create', mode: 'html' },
            builderDefaults: builderDocument,
        },
    });

    const draftActions = createView.container.querySelector(
        '[data-result-sheet-draft-actions]',
    );
    expect(draftActions).not.toBeNull();
    expect(
        createView.getByRole('button', { name: 'Continue later' }),
    ).toBeTruthy();
    expect(createView.getByRole('button', { name: 'Start over' })).toBeTruthy();
    expect(
        createView.getByRole('button', { name: 'Discard draft' }),
    ).toBeTruthy();
    createView.unmount();

    const editView = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'edit',
            template: { id: 10, name: 'Existing Template', mode: 'html' },
            builderDefaults: builderDocument,
        },
    });

    expect(
        editView.container.querySelector('[data-result-sheet-draft-actions]'),
    ).toBeNull();
    expect(
        editView.queryByRole('button', { name: 'Continue later' }),
    ).toBeNull();
    expect(editView.queryByRole('button', { name: 'Start over' })).toBeNull();
    expect(
        editView.queryByRole('button', { name: 'Discard draft' }),
    ).toBeNull();
});

it('flushes draft save before navigating away when Continue later is selected', async () => {
    const visitSpy = vi.spyOn(router, 'visit').mockImplementation(() => {});
    const fetchMock = vi.fn(
        async (input: RequestInfo | URL, init?: RequestInit) => ({
            ok: true,
            json: async () => ({ draft: { id: 88, lock_version: 2 } }),
        }),
    );
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [],
    };

    const { getByRole } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Unsaved draft template', mode: 'html' },
            builderDefaults: builderDocument,
        },
    });

    const continueLaterButton = getByRole('button', { name: 'Continue later' });
    await fireEvent.click(continueLaterButton);
    await tick();
    await tick();

    const draftCalls = fetchMock.mock.calls.filter(([input]) =>
        String(input).includes('/drafts'),
    );
    expect(draftCalls.length).toBeGreaterThanOrEqual(1);
    const [url, init] = draftCalls[0];
    expect(String(url)).toContain('/setup/result-templates/drafts');
    const body = JSON.parse(String(init?.body));
    expect(body.state.name).toBe('Unsaved draft template');

    expect(visitSpy).toHaveBeenCalledWith('/setup/result-templates');
});

it('presents a retryable visible error when preview returns neither a PDF nor a background job', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
        if (String(input).includes('/preview')) {
            return {
                ok: true,
                json: async () => ({ success: true }),
            };
        }
        return {
            ok: true,
            json: async () => ({ draft: { id: 1, lock_version: 1 } }),
        };
    });
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                props: { title: 'State University' },
            },
        ],
    };

    const { container, getByRole } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Preview Error Template', mode: 'html' },
            draft: {
                id: 42,
                stage: 3,
                lock_version: 4,
                source_template_id: null,
                state: {
                    name: 'Preview Error Template',
                    mode: 'html',
                    source_kind: 'builder',
                    paper_size: 'a4',
                    orientation: 'portrait',
                    logical_unit: 'full',
                    content: '',
                    builder_json: builderDocument,
                    watermark_text: '',
                    is_active: false,
                },
            },
            builderDefaults: builderDocument,
        },
    });
    await vi.advanceTimersByTimeAsync(400);
    await tick();
    await tick();
    const alert = container.querySelector('[role="alert"]');
    expect(alert).not.toBeNull();
    expect(alert?.textContent).toContain(
        'Preview generation returned no PDF or background job. Try again.',
    );
    expect(getByRole('button', { name: 'Retry preview' })).toBeTruthy();
});

function wordTemplateProps(
    documentAnalysis = {
        valid: true,
        found: ['{{ applicant.name }}'],
        missing: [],
        extra: [],
        unresolved: [],
        source_checksum: 'a'.repeat(64),
    },
) {
    const templateId = 77;

    return {
        mode: 'edit',
        basePath: '/setup/result-templates',
        template: {
            id: templateId,
            name: 'Word Preview Template',
            mode: 'docx',
            source_kind: 'docx',
            document_path: 'private/result-templates/word-template.docx',
            document_extension: 'docx',
        },
        documentAnalysis: {
            ...documentAnalysis,
            document_path: 'private/result-templates/word-template.docx',
            template_id: templateId,
        },
    };
}

function wordFetchMock(statusForJob) {
    return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);
        if (url.endsWith('/preview')) {
            const body = init?.body as FormData;
            expect(body.get('mode')).toBe('docx');
            expect(body.get('template_id')).toBe('77');

            return {
                ok: true,
                json: async () => ({
                    preview_job_id: 'word-preview-job',
                    preview_url: '/printing/jobs/word-preview-job/download',
                }),
            };
        }
        if (url.includes('/printing/jobs/word-preview-job')) {
            return statusForJob();
        }
        return {
            ok: true,
            json: async () => ({ draft: { id: 1, lock_version: 1 } }),
        };
    });
}

async function advanceWordEditorToPreview(view) {
    await fireEvent.click(
        view.getByRole('button', { name: 'Continue to Upload' }),
    );
    await vi.advanceTimersByTimeAsync(50);
    await tick();
    await fireEvent.click(
        view.getByRole('button', { name: 'Continue to Preview' }),
    );
    await vi.advanceTimersByTimeAsync(50);
    await tick();
}

it('automatically initiates Word preview when advancing to Stage 3 without manual Refresh click', async () => {
    vi.useFakeTimers();
    let statusCalls = 0;
    const fetchMock = wordFetchMock(async () => {
        statusCalls++;
        return {
            ok: true,
            json: async () => ({ status: 'rendering' }),
        };
    });
    vi.stubGlobal('fetch', fetchMock);

    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });

    await fireEvent.click(
        view.getByRole('button', { name: 'Continue to Upload' }),
    );
    await vi.advanceTimersByTimeAsync(50);
    await tick();

    await fireEvent.click(
        view.getByRole('button', { name: 'Continue to Preview' }),
    );
    await vi.advanceTimersByTimeAsync(50);
    await tick();

    expect(view.container.textContent).toContain('Rendering production PDF');

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    expect(statusCalls).toBeGreaterThan(0);
    vi.useRealTimers();
    view.unmount();
});

it('polls a queued Word preview through rendering to a PDF and enables Save only when ready', async () => {
    vi.useFakeTimers();
    let statusIndex = 0;
    const statuses = ['queued', 'rendering', 'ready'];
    const fetchMock = wordFetchMock(async () => ({
        ok: true,
        json: async () => ({ status: statuses[statusIndex++] }),
    }));
    vi.stubGlobal('fetch', fetchMock);

    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);

    const saveButtons = view.getAllByRole('button', { name: 'Save changes' });
    expect(
        saveButtons.every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    expect(view.container.textContent).toContain('Rendering production PDF');
    expect(
        view.container.querySelector('iframe[title="Template PDF preview"]'),
    ).toBeNull();
    expect(
        saveButtons.every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    expect(
        view.container.querySelector(
            'iframe[src*="/printing/jobs/word-preview-job/download"]',
        ),
    ).toBeNull();
    expect(
        saveButtons.every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    expect(
        view.container.querySelector(
            'iframe[src*="/printing/jobs/word-preview-job/download"]',
        ),
    ).not.toBeNull();
    expect(
        saveButtons.every((button) => !(button as HTMLButtonElement).disabled),
    ).toBe(true);
});

it('shows the worker safe error and keeps Save disabled when a Word preview fails', async () => {
    vi.useFakeTimers();
    let pollCount = 0;
    const fetchMock = wordFetchMock(async () => ({
        ok: true,
        json: async () =>
            pollCount++ === 0
                ? { status: 'rendering' }
                : {
                      status: 'failed',
                      safe_error: 'document_conversion_failed',
                  },
    }));
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    await vi.advanceTimersByTimeAsync(1200);
    await tick();

    expect(
        view.container.querySelector('[role="alert"]')?.textContent,
    ).toContain('document_conversion_failed');
    expect(
        view
            .getAllByRole('button', { name: 'Save changes' })
            .every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);
});

it('stops Word preview polling and exposes Retry after an HTTP 500 status error', async () => {
    vi.useFakeTimers();
    let statusRequestCount = 0;
    const fetchMock = wordFetchMock(async () => {
        statusRequestCount++;

        return statusRequestCount === 1
            ? {
                  ok: true,
                  json: async () => ({ status: 'rendering' }),
              }
            : {
                  ok: false,
                  status: 500,
                  json: async () => ({}),
              };
    });
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    await vi.advanceTimersByTimeAsync(1200);
    await tick();

    expect(view.container.querySelector('[role="alert"] p')?.textContent).toBe(
        'Preview status request failed (500). Retry to start a new preview.',
    );
    expect(view.getByRole('button', { name: 'Retry preview' })).toBeTruthy();
    expect(
        view
            .getAllByRole('button', { name: 'Save changes' })
            .every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);
    expect(
        view.container.querySelector('iframe[title="Template PDF preview"]'),
    ).toBeNull();

    const statusRequestCountAtFailure = fetchMock.mock.calls.filter(([input]) =>
        String(input).includes('/printing/jobs/word-preview-job'),
    ).length;
    expect(statusRequestCountAtFailure).toBe(2);
    await vi.advanceTimersByTimeAsync(5000);
    expect(
        fetchMock.mock.calls.filter(([input]) =>
            String(input).includes('/printing/jobs/word-preview-job'),
        ),
    ).toHaveLength(statusRequestCountAtFailure);
});

it('stops Word status requests at the overall preview deadline', async () => {
    vi.useFakeTimers();
    const fetchMock = wordFetchMock(async () => ({
        ok: true,
        json: async () => ({ status: 'rendering' }),
    }));
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);

    await vi.advanceTimersByTimeAsync(120000);
    await tick();

    const statusRequestCount = fetchMock.mock.calls.filter(([input]) =>
        String(input).includes('/printing/jobs/word-preview-job'),
    ).length;
    expect(statusRequestCount).toBeGreaterThan(0);
    expect(
        view.container.querySelector('[role="alert"]')?.textContent,
    ).toContain('Preview generation timed out.');
    await vi.advanceTimersByTimeAsync(5000);
    expect(
        fetchMock.mock.calls.filter(([input]) =>
            String(input).includes('/printing/jobs/word-preview-job'),
        ),
    ).toHaveLength(statusRequestCount);
});

it('fails a pending Word status request when its ten-second abort signal fires', async () => {
    vi.useFakeTimers();
    let receivedSignal: AbortSignal | undefined;
    const fetchMock = vi.fn(
        async (input: RequestInfo | URL, init?: RequestInit) => {
            if (String(input).includes('/printing/jobs/word-preview-job')) {
                receivedSignal = init?.signal as AbortSignal;
                const pending = Promise.withResolvers<{
                    ok: boolean;
                    json: () => Promise<unknown>;
                }>();
                receivedSignal.addEventListener('abort', () => {
                    pending.reject(new DOMException('Aborted', 'AbortError'));
                });
                return await pending.promise;
            }
            return await wordFetchMock(async () => ({
                ok: true,
                json: async () => ({ status: 'rendering' }),
            }))(input, init);
        },
    );
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);

    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    await vi.advanceTimersByTimeAsync(10000);
    await tick();

    expect(receivedSignal?.aborted).toBe(true);
    expect(
        view.container.querySelector('[role="alert"]')?.textContent,
    ).toContain('Preview status request timed out.');
    expect(
        view
            .getAllByRole('button', { name: 'Save changes' })
            .every((button) => (button as HTMLButtonElement).disabled),
    ).toBe(true);
});

it.each([null, {}, { status: 'unexpected' }])(
    'fails closed for malformed or unknown Word job statuses (%o)',
    async (status) => {
        vi.useFakeTimers();
        const fetchMock = wordFetchMock(async () => ({
            ok: true,
            json: async () => status,
        }));
        vi.stubGlobal('fetch', fetchMock);
        const view = render(ResultSheetTemplateEditor, {
            props: wordTemplateProps(),
        });
        await advanceWordEditorToPreview(view);
        await vi.advanceTimersByTimeAsync(1200);
        await tick();

        expect(
            view.container.querySelector('[role="alert"]')?.textContent,
        ).toContain('Preview status was not recognized.');
        expect(
            view
                .getAllByRole('button', { name: 'Save changes' })
                .every((button) => (button as HTMLButtonElement).disabled),
        ).toBe(true);
        expect(
            fetchMock.mock.calls.filter(([input]) =>
                String(input).includes('/printing/jobs/word-preview-job'),
            ),
        ).toHaveLength(1);
    },
);

it('ignores a late Word status response after invalidation and a replacement preview', async () => {
    vi.useFakeTimers();
    let resolveOldStatus:
        | ((value: { ok: boolean; json: () => Promise<unknown> }) => void)
        | undefined;
    let previewRuns = 0;
    const fetchMock = vi.fn(
        async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = String(input);
            if (url.endsWith('/preview')) {
                previewRuns += 1;
                const jobId = `word-preview-job-${previewRuns}`;
                return {
                    ok: true,
                    json: async () => ({
                        preview_job_id: jobId,
                        preview_url: `/printing/jobs/${jobId}/download`,
                    }),
                };
            }
            if (url.endsWith('/validate-document')) {
                return {
                    ok: true,
                    json: async () => wordTemplateProps().documentAnalysis,
                };
            }
            if (url.includes('/printing/jobs/word-preview-job-1')) {
                const oldStatus = Promise.withResolvers<{
                    ok: boolean;
                    json: () => Promise<unknown>;
                }>();
                resolveOldStatus = oldStatus.resolve;
                (init?.signal as AbortSignal).addEventListener('abort', () => {
                    // The server can settle an already-dispatched request after abort.
                });
                return await oldStatus.promise;
            }
            if (url.includes('/printing/jobs/word-preview-job-2')) {
                return { ok: true, json: async () => ({ status: 'ready' }) };
            }
            return {
                ok: true,
                json: async () => ({ draft: { id: 1, lock_version: 1 } }),
            };
        },
    );
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);
    await vi.advanceTimersByTimeAsync(1200);
    await tick();

    await fireEvent.click(
        within(
            view.container.querySelector(
                '[data-result-sheet-mobile-workflow]',
            )!,
        ).getByRole('button', { name: 'Previous workflow stage' }),
    );
    await tick();
    await tick();
    const wordFile = new File(['docx fixture'], 'updated-template.docx', {
        type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    });
    await fireEvent.change(
        view.container.querySelector('input[type="file"]')!,
        {
            target: { files: [wordFile] },
        },
    );
    await tick();
    await tick();
    await fireEvent.click(
        within(
            view.container.querySelector(
                '[data-result-sheet-mobile-workflow]',
            )!,
        ).getByRole('button', { name: 'Continue to Preview' }),
    );
    await tick();
    await tick();
    await vi.advanceTimersByTimeAsync(50);
    await tick();
    await vi.advanceTimersByTimeAsync(1200);
    await tick();

    expect(
        view.container.querySelector(
            'iframe[src*="word-preview-job-2/download"]',
        ),
    ).not.toBeNull();
    resolveOldStatus?.({
        ok: true,
        json: async () => ({ status: 'failed', safe_error: 'stale failure' }),
    });
    await tick();
    await tick();

    expect(view.container.querySelector('[role="alert"]')).toBeNull();
    expect(
        view.container.querySelector(
            'iframe[src*="word-preview-job-2/download"]',
        ),
    ).not.toBeNull();
    expect(
        view
            .getAllByRole('button', { name: 'Save changes' })
            .every((button) => !(button as HTMLButtonElement).disabled),
    ).toBe(true);
});

it('does not render a late Word job response after the editor unmounts', async () => {
    vi.useFakeTimers();
    let resolveStatus:
        | ((value: { ok: boolean; json: () => Promise<unknown> }) => void)
        | undefined;
    const fetchMock = wordFetchMock(() => {
        const pending = Promise.withResolvers<{
            ok: boolean;
            json: () => Promise<unknown>;
        }>();
        resolveStatus = pending.resolve;
        return pending.promise;
    });
    vi.stubGlobal('fetch', fetchMock);
    const view = render(ResultSheetTemplateEditor, {
        props: wordTemplateProps(),
    });
    await advanceWordEditorToPreview(view);
    await vi.advanceTimersByTimeAsync(1200);
    await tick();
    view.unmount();
    resolveStatus?.({
        ok: true,
        json: async () => ({ status: 'failed', safe_error: 'late failure' }),
    });
    await tick();
    await tick();

    expect(view.container.querySelector('[role="alert"]')).toBeNull();
    expect(view.container.querySelector('iframe')).toBeNull();
});

it('renders title precedence reactively, with explicit title overriding its surface', async () => {
    const view = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            surface: {
                title: 'Passing Report',
                surface_key: 'passing_report',
            },
        },
    });
    expect(view.getByText('Passing Report Studio').tagName).toBe('P');

    await view.rerender({
        mode: 'create',
        studioTitle: 'Custom Customizer',
        surface: {
            title: 'Passing Report',
            surface_key: 'passing_report',
        },
    });
    expect(view.getByText('Custom Customizer').tagName).toBe('P');
    view.unmount();

    const viewWithBasePath = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            basePath: '/setup/report-templates/passing_report',
        },
    });
    expect(viewWithBasePath.getByText('Report Template Studio').tagName).toBe(
        'P',
    );
    viewWithBasePath.unmount();

    const defaultView = render(ResultSheetTemplateEditor, {
        props: { mode: 'create' },
    });
    expect(defaultView.getByText('Result Sheet Studio').tagName).toBe('P');
    defaultView.unmount();
});

it('restores a resumed Word draft at stage 3 to the Upload stage with a truthful reselect warning', () => {
    const { container } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Resumed Word Template', mode: 'docx' },
            draft: {
                id: 99,
                stage: 3,
                lock_version: 1,
                source_template_id: null,
                state: {
                    name: 'Resumed Word Template',
                    mode: 'docx',
                    source_kind: 'docx',
                    paper_size: 'a4',
                    orientation: 'portrait',
                    logical_unit: 'full',
                    content: '',
                    watermark_text: '',
                    is_active: false,
                },
            },
        },
    });

    expect(
        container.querySelector('[data-result-sheet-stage="word-upload"]'),
    ).not.toBeNull();
    expect(
        container.querySelector('[data-result-sheet-stage="word-preview"]'),
    ).toBeNull();

    expect(get(toasts)).toEqual(
        expect.arrayContaining([
            expect.objectContaining({
                message:
                    'Draft restored. Select the Word / ODT file again before analysis or preview.',
                type: 'warning',
            }),
        ]),
    );
});

it('resets local state without navigating when Start over is confirmed', async () => {
    const visitSpy = vi.spyOn(router, 'visit').mockImplementation(() => {});
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => ({
        ok: true,
        json: async () => ({}),
    }));
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [],
    };

    const { container, getByLabelText } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'To Be Reset', mode: 'html' },
            draft: {
                id: 77,
                stage: 2,
                lock_version: 1,
                source_template_id: null,
                state: { name: 'To Be Reset', mode: 'html' },
            },
            builderDefaults: builderDocument,
        },
    });
    const draftActions = container.querySelector(
        '[data-result-sheet-draft-actions]',
    );
    expect(draftActions).not.toBeNull();
    const startOverButton = within(draftActions as HTMLElement).getByRole(
        'button',
        { name: 'Start over' },
    );
    await fireEvent.click(startOverButton);
    await tick();

    expect(fetchMock).toHaveBeenCalledWith(
        expect.stringContaining('/setup/result-templates/drafts/77'),
        expect.objectContaining({ method: 'DELETE' }),
    );
    expect(visitSpy).not.toHaveBeenCalled();
    expect((getByLabelText('Template Name') as HTMLInputElement).value).toBe(
        '',
    );
});

it('restores a stage-1 Word draft to the Upload stage with a truthful reselect warning', () => {
    const { container } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Stage 1 Word Draft', mode: 'docx' },
            draft: {
                id: 98,
                stage: 1,
                lock_version: 1,
                source_template_id: null,
                state: {
                    name: 'Stage 1 Word Draft',
                    mode: 'docx',
                    source_kind: 'docx',
                    paper_size: 'a4',
                    orientation: 'portrait',
                    logical_unit: 'full',
                    content: '',
                    watermark_text: '',
                    is_active: false,
                },
            },
        },
    });

    expect(
        container.querySelector('[data-result-sheet-stage="word-upload"]'),
    ).not.toBeNull();
    expect(
        container.querySelector('[data-result-sheet-stage="word-copy"]'),
    ).toBeNull();
    expect(get(toasts)).toEqual(
        expect.arrayContaining([
            expect.objectContaining({
                message:
                    'Draft restored. Select the Word / ODT file again before analysis or preview.',
                type: 'warning',
            }),
        ]),
    );
});

it('does not mark default create state as unsaved before an edit', () => {
    const builderDefaults = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [],
    };

    const { container } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: {},
            builderDefaults,
            builderCatalog: {},
        },
    });

    expect(
        container
            .querySelector('[data-result-sheet-save-state]')
            ?.textContent?.trim(),
    ).toBe('Draft');
});
it('uses a warning toast instead of an inline alert when Setup blocks an empty template name', async () => {
    const builderDefaults = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [],
    };

    const { container, getByRole, queryByText } = render(
        ResultSheetTemplateEditor,
        {
            props: {
                mode: 'create',
                template: {},
                builderDefaults,
                builderCatalog: {},
            },
        },
    );

    const message = 'Enter a template name in Setup before proceeding.';
    await fireEvent.click(getByRole('button', { name: /Continue to Design/ }));
    await tick();

    expect(get(toasts)).toEqual(
        expect.arrayContaining([
            expect.objectContaining({ message, type: 'warning' }),
        ]),
    );
    expect(container.querySelector('[role="alert"]')).toBeNull();
    expect(queryByText(message)).toBeNull();
    expect(
        container.querySelector('[data-result-sheet-stage="setup"]'),
    ).not.toBeNull();
});

it('reports pending and saved states during automatic draft saving', async () => {
    vi.useFakeTimers();
    const builderDefaults = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [],
    };

    const { container, getByLabelText } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: {},
            builderDefaults,
            builderCatalog: {},
        },
    });

    await fireEvent.input(getByLabelText('Template Name'), {
        target: { value: 'Autosaved template' },
    });
    expect(
        container
            .querySelector('[data-result-sheet-save-state]')
            ?.textContent?.trim(),
    ).toBe('Changes pending…');

    await vi.advanceTimersByTimeAsync(300);
    await tick();

    expect(
        container
            .querySelector('[data-result-sheet-save-state]')
            ?.textContent?.trim(),
    ).toBe('Draft saved');
    expect(window.location.search).toBe('?draft=1');
});

it('persists the current stage before leaving Setup', async () => {
    const fetchMock = vi.fn(async () => ({
        ok: true,
        json: async () => ({ draft: { id: 7, lock_version: 2 } }),
    }));
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                locked: false,
                props: { title: 'State University' },
            },
        ],
    };

    const { getByRole } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: {
                name: 'Persist stage test',
                mode: 'html',
                builder_json: builderDocument,
            },
            builderDefaults: builderDocument,
            builderCatalog: {
                header: { label: 'Header', description: 'Institution header' },
            },
        },
    });

    await fireEvent.click(getByRole('button', { name: /Continue to Design/ }));
    await tick();
    await tick();

    const [input, init] = fetchMock.mock.calls[0];
    const body = JSON.parse(String(init?.body));
    expect(input).toBe('/setup/result-templates/drafts');
    expect(body.stage).toBe(2);
    expect(body.state.name).toBe('Persist stage test');
    expect(window.location.search).toBe('?draft=7');
    expect(
        document
            .querySelector('[data-result-sheet-save-state]')
            ?.textContent?.trim(),
    ).toBe('Draft saved');
});

it('debounces PDF preview generation after a Studio change', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn(async (input: RequestInfo | URL) => ({
        ok: true,
        json: async () =>
            String(input).includes('/drafts')
                ? { draft: { id: 41, lock_version: 2 } }
                : { pdf_base64: 'cGRm' },
    }));
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                locked: false,
                props: { title: 'State University' },
            },
        ],
    };
    const builderCatalog = {
        header: { label: 'Header', description: 'Institution header' },
    };
    const { container, getByRole } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: {
                name: 'Debounce Test',
                mode: 'html',
                builder_json: builderDocument,
            },
            builderDefaults: builderDocument,
            builderCatalog,
        },
    });

    await fireEvent.click(getByRole('button', { name: /Continue to Design/ }));
    const component = container.querySelector(
        '[data-result-sheet-component="header-1"]',
    );
    const editorSurface = container.querySelector('[data-editor-surface]');
    expect(component).not.toBeNull();
    await fireEvent.pointerDown(component as HTMLElement, { button: 0 });
    await fireEvent.keyDown(editorSurface as HTMLElement, {
        key: 'ArrowRight',
    });
    await fireEvent.keyDown(editorSurface as HTMLElement, {
        key: 'ArrowRight',
    });
    await tick();

    const previewCalls = () =>
        fetchMock.mock.calls.filter(([input]) =>
            String(input).includes('/preview'),
        );

    expect(previewCalls()).toHaveLength(0);
    vi.advanceTimersByTime(399);
    await tick();
    expect(previewCalls()).toHaveLength(0);

    vi.advanceTimersByTime(1);
    await tick();
    expect(previewCalls()).toHaveLength(1);
});
it('restores the persisted design stage after a page reload', async () => {
    const builderDocument = {
        schema_version: '1.0.0',
        page: {
            paper_size: 'a4',
            orientation: 'portrait',
            width_mm: 210,
            height_mm: 297,
            margin_mm: 12,
        },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                locked: false,
                props: { title: 'State University' },
            },
        ],
    };

    const { container } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Resumable template', mode: 'html' },
            draft: {
                id: 41,
                stage: 2,
                lock_version: 3,
                source_template_id: null,
                state: {
                    name: 'Resumable template',
                    mode: 'html',
                    source_kind: 'builder',
                    paper_size: 'a4',
                    orientation: 'portrait',
                    logical_unit: 'full',
                    content: '',
                    builder_json: builderDocument,
                    watermark_text: '',
                    is_active: false,
                },
            },
            builderDefaults: builderDocument,
            builderCatalog: {
                header: { label: 'Header', description: 'Institution header' },
            },
        },
    });
    expect(
        container.querySelector('[data-result-sheet-stage="design"]'),
    ).not.toBeNull();
    expect(
        container.querySelector('[data-result-sheet-stage="setup"]'),
    ).toBeNull();
});

it('restores Verify and regenerates a stale preview after a page reload', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn(async () => ({
        ok: true,
        json: async () => ({ pdf_base64: 'cGRm' }),
    }));
    vi.stubGlobal('fetch', fetchMock);

    const builderDocument = {
        schema_version: '1.0.0',
        page: { paper_size: 'a4', orientation: 'portrait', margin_mm: 12 },
        components: [
            {
                id: 'header-1',
                type: 'header',
                x: 10,
                y: 10,
                w: 160,
                h: 24,
                props: { title: 'State University' },
            },
        ],
    };

    const { container } = render(ResultSheetTemplateEditor, {
        props: {
            mode: 'create',
            template: { name: 'Verify template', mode: 'html' },
            draft: {
                id: 42,
                stage: 3,
                lock_version: 4,
                source_template_id: null,
                state: {
                    name: 'Verify template',
                    mode: 'html',
                    source_kind: 'builder',
                    paper_size: 'a4',
                    orientation: 'portrait',
                    logical_unit: 'full',
                    content: '',
                    builder_json: builderDocument,
                    watermark_text: '',
                    is_active: false,
                },
            },
            builderDefaults: builderDocument,
        },
    });

    expect(
        container.querySelector('[data-result-sheet-stage="verify"]'),
    ).not.toBeNull();
    expect(
        container.querySelector('[data-result-sheet-stage="setup"]'),
    ).toBeNull();

    vi.advanceTimersByTime(400);
    await tick();

    expect(
        fetchMock.mock.calls.filter(([input]) =>
            String(input).includes('/preview'),
        ),
    ).toHaveLength(1);
});
