// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SearchSuggestionDropdown from '../../resources/js/Components/kit/SearchSuggestionDropdown.svelte';

type DropdownHandle = {
    handleInput(value: string): void;
    handleFocus(): void;
    handleBlur(event: FocusEvent): void;
    dismiss(): void;
    handleKeydown(event: KeyboardEvent): void;
    isExpanded(): boolean;
    listboxIdentifier(): string | undefined;
    activeDescendantId(): string | undefined;
};
type DropdownProps = Record<string, unknown> & { search: string };

type DropdownInputHarness = {
    input: HTMLInputElement;
    props: DropdownProps;
    rerender(): Promise<unknown>;
};

type SuggestionResponsePayload = {
    items: unknown[];
    matches_truncated: boolean;
};

type SuggestionResponse = {
    ok: boolean;
    json(): Promise<SuggestionResponsePayload>;
};

function examineeSuggestion(overrides: Record<string, unknown> = {}) {
    return {
        key: 'examinee:71',
        kind: 'examinee',
        assignment_id: 71,
        attempt_id: null,
        reference_code: 'AP-071',
        name: 'Ada Example',
        exam_session_id: 41,
        session_code: 'EX-AM-2026',
        session_status: 'published',
        room_label: 'Room 4',
        starts_at: '2026-10-08T09:00:00Z',
        ends_at: null,
        grading_session_id: null,
        matched_fields: ['name'],
        destination: {
            url: '/exams/sessions/monitoring/41?focus_assignment_id=71',
            focus_kind: 'assignment',
            focus_id: 71,
        },
        ...overrides,
    };
}

function sessionSuggestion(overrides: Record<string, unknown> = {}) {
    return {
        key: 'session:42',
        kind: 'session',
        assignment_id: null,
        attempt_id: null,
        reference_code: null,
        name: null,
        exam_session_id: 42,
        session_code: 'EX-ROOM-42',
        session_status: 'published',
        room_label: 'Room 12',
        starts_at: '2026-10-08T10:00:00Z',
        ends_at: null,
        grading_session_id: null,
        matched_fields: ['room'],
        destination: {
            url: '/exams/sessions/monitoring/42',
            focus_kind: null,
            focus_id: null,
        },
        ...overrides,
    };
}

function response(
    items: unknown[],
    matchesTruncated = false,
): SuggestionResponse {
    return {
        ok: true,
        json: vi
            .fn()
            .mockResolvedValue({ items, matches_truncated: matchesTruncated }),
    };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>((resolvePromise) => {
        resolve = resolvePromise;
    });

    return { promise, resolve };
}

async function flushMicrotasks() {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}

function mountDropdown(overrides: Record<string, unknown> = {}) {
    const input = document.createElement('input');
    document.body.append(input);
    const onactivate = vi.fn();
    const oninteractionchange = vi.fn();
    const props: DropdownProps = {
        academicCycleId: 12,
        domainContext: 'monitoring',
        tab: 'active',
        date: '2026-10-08',
        roomId: 44,
        contextKey: 'principal:7|workspace:operational',
        onactivate,
        oninteractionchange,
        ...overrides,
        search: typeof overrides.search === 'string' ? overrides.search : '',
        inputRef: input,
    };
    const view = render(SearchSuggestionDropdown, { props });
    const component = view.component as unknown as DropdownHandle;

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');

    input.addEventListener('input', () => {
        component.handleInput(input.value);
    });
    input.addEventListener('focus', () => {
        component.handleFocus();
    });
    input.addEventListener('blur', (event) => {
        component.handleBlur(event as FocusEvent);
    });
    input.addEventListener('keydown', (event) => {
        component.handleKeydown(event as KeyboardEvent);
    });

    return {
        view,
        input,
        props,
        component,
        onactivate,
        oninteractionchange,

        rerender: () => view.rerender({ ...props }),
    };
}

async function typeQuery(harness: DropdownInputHarness, value: string) {
    harness.input.focus();
    harness.input.value = value;
    await fireEvent.input(harness.input);
    harness.props.search = value;
    await harness.rerender();
}

async function advanceDebounce() {
    await vi.advanceTimersByTimeAsync(250);
    await flushMicrotasks();
}

afterEach(() => {
    cleanup();
    document.body.replaceChildren();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('SearchSuggestionDropdown rendered interaction', () => {
    it('does not look up on mount or focus and starts a fresh lookup only 250ms after input', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockResolvedValue(response([examineeSuggestion()]));
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown({ search: 'already committed' });
        await flushMicrotasks();

        harness.input.focus();
        expect(fetchMock).not.toHaveBeenCalled();

        await typeQuery(harness, 'a');
        expect(fetchMock).not.toHaveBeenCalled();
        expect(
            harness.view.getByRole('option', {
                name: /Filter sessions matching "a"/,
            }),
        ).toBeTruthy();

        await typeQuery(harness, 'ada');
        expect(
            harness.view.queryByRole('option', { name: /Ada Example/ }),
        ).toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(249);
        expect(fetchMock).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        await flushMicrotasks();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const requestUrl = new URL(
            String(fetchMock.mock.calls[0][0]),
            window.location.origin,
        );
        expect(requestUrl.pathname).toBe('/exams/search-suggestions');
        expect(requestUrl.searchParams.get('q')).toBe('ada');
        expect(requestUrl.searchParams.get('academic_cycle_id')).toBe('12');
        expect(requestUrl.searchParams.get('domain_context')).toBe(
            'monitoring',
        );
        expect(requestUrl.searchParams.get('tab')).toBe('active');
        expect(requestUrl.searchParams.get('date')).toBe('2026-10-08');
        expect(requestUrl.searchParams.get('room_id')).toBe('44');
        expect(
            harness.view.getByRole('option', { name: /Ada Example/ }),
        ).toBeTruthy();
        expect(harness.component.activeDescendantId()).toBeUndefined();
        expect(harness.input.getAttribute('aria-controls')).toBe(
            harness.view.getByRole('listbox').id,
        );
        expect(harness.input.hasAttribute('aria-activedescendant')).toBe(false);
    });

    it('reports active input interaction and releases it after blur', () => {
        const harness = mountDropdown();

        harness.input.focus();
        expect(harness.oninteractionchange).toHaveBeenLastCalledWith(true);
        harness.input.blur();

        expect(harness.oninteractionchange).toHaveBeenLastCalledWith(false);
        expect(harness.component.isExpanded()).toBe(false);
    });

    it('announces truncation outside the selectable option list', async () => {
        vi.useFakeTimers();
        const items = Array.from({ length: 8 }, (_, index) => {
            const assignmentId = 71 + index;

            return examineeSuggestion({
                key: `examinee:${assignmentId}`,
                assignment_id: assignmentId,
                name: `Applicant ${assignmentId}`,
                reference_code: `AP-${assignmentId}`,
                destination: {
                    url: `/exams/sessions/monitoring/41?focus_assignment_id=${assignmentId}`,
                    focus_kind: 'assignment',
                    focus_id: assignmentId,
                },
            });
        });
        const fetchMock = vi.fn().mockResolvedValue(response(items, true));
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'applicant');
        await advanceDebounce();

        expect(harness.view.getByRole('status').textContent).toContain(
            'Showing the top 8 matches',
        );
        expect(harness.view.getAllByRole('option')).toHaveLength(9);
        expect(
            harness.view.getByRole('option', {
                name: /Filter sessions matching "applicant"/,
            }),
        ).toBeTruthy();
    });

    it('dispatches examinee navigation and session filtering as exclusive pointer actions while retaining input focus', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(response([examineeSuggestion()]))
            .mockResolvedValueOnce(response([sessionSuggestion()]));
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'ada');
        await advanceDebounce();
        const examineeOption = harness.view.getByRole('option', {
            name: /Open examinee/,
        });
        const pointerDown = new Event('pointerdown', {
            bubbles: true,
            cancelable: true,
        });
        examineeOption.dispatchEvent(pointerDown);
        expect(pointerDown.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(harness.input);
        await fireEvent.click(examineeOption);

        expect(harness.onactivate).toHaveBeenCalledTimes(1);
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'navigate',
            url: '/exams/sessions/monitoring/41?focus_assignment_id=71',
            key: 'examinee:71',
        });

        harness.onactivate.mockClear();
        await typeQuery(harness, 'room');
        await advanceDebounce();
        const sessionOption = harness.view.getByRole('option', {
            name: /EX-ROOM-42/,
        });
        await fireEvent.pointerDown(sessionOption);
        expect(document.activeElement).toBe(harness.input);
        await fireEvent.click(sessionOption);

        expect(harness.onactivate).toHaveBeenCalledTimes(1);
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'filter',
            query: 'EX-ROOM-42',
            source: 'session',
        });
    });

    it('keeps selection manual, navigates the complete option order, and returns to free-text Enter at the first option', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockResolvedValue(
                response([examineeSuggestion(), sessionSuggestion()]),
            );
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'session');
        await advanceDebounce();
        expect(harness.component.activeDescendantId()).toBeUndefined();

        const examineeId = harness.view.getByRole('option', {
            name: /Open examinee/,
        }).id;
        const sessionId = harness.view.getByRole('option', {
            name: /EX-ROOM-42/,
        }).id;
        const fallbackId = harness.view.getByRole('option', {
            name: /Filter sessions matching/,
        }).id;

        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBe(fallbackId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBe(sessionId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBe(examineeId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBeUndefined();

        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        expect(harness.component.activeDescendantId()).toBe(examineeId);
        expect(harness.input.getAttribute('aria-activedescendant')).toBe(
            examineeId,
        );
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        expect(harness.component.activeDescendantId()).toBe(sessionId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        expect(harness.component.activeDescendantId()).toBe(fallbackId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        expect(harness.component.activeDescendantId()).toBe(fallbackId);

        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBe(sessionId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBe(examineeId);
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        expect(harness.component.activeDescendantId()).toBeUndefined();
        expect(
            harness.view
                .getByRole('option', { name: /Open examinee/ })
                .getAttribute('aria-selected'),
        ).toBe('false');

        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenCalledTimes(1);
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'filter',
            query: 'session',
            source: 'enter',
        });
        harness.onactivate.mockClear();
        await typeQuery(harness, 'select examinee');
        await advanceDebounce();
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'navigate',
            url: '/exams/sessions/monitoring/41?focus_assignment_id=71',
            key: 'examinee:71',
        });

        harness.onactivate.mockClear();
        await typeQuery(harness, 'select session');
        await advanceDebounce();
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'filter',
            query: 'EX-ROOM-42',
            source: 'session',
        });
    });

    it('supports fallback keyboard filtering, empty and one-character Enter, and ignores composing Enter', async () => {
        vi.useFakeTimers();
        const fetchMock = vi.fn().mockResolvedValue(response([]));
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'room');
        await advanceDebounce();
        expect(harness.view.getByRole('status').textContent).toContain(
            'No matching sessions',
        );
        await fireEvent.keyDown(harness.input, { key: 'ArrowUp' });
        const fallback = harness.view.getByRole('option', {
            name: /Filter sessions matching "room"/,
        });
        expect(harness.component.activeDescendantId()).toBe(fallback.id);
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenLastCalledWith({
            type: 'filter',
            query: 'room',
            source: 'fallback',
        });

        harness.onactivate.mockClear();
        await typeQuery(harness, 'x');
        await fireEvent.keyDown(harness.input, {
            key: 'Enter',
            isComposing: true,
        });
        expect(harness.onactivate).not.toHaveBeenCalled();
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenCalledWith({
            type: 'filter',
            query: 'x',
            source: 'enter',
        });

        harness.onactivate.mockClear();
        await typeQuery(harness, '');
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenCalledWith({
            type: 'filter',
            query: '',
            source: 'enter',
        });
        const callsBeforeOverlengthInput = fetchMock.mock.calls.length;
        harness.onactivate.mockClear();
        await typeQuery(harness, 'x'.repeat(101));
        expect(harness.view.getByRole('alert').textContent).toContain(
            '100 characters or fewer',
        );
        expect(harness.component.listboxIdentifier()).toBeUndefined();
        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).not.toHaveBeenCalled();
        expect(fetchMock).toHaveBeenCalledTimes(callsBeforeOverlengthInput);
    });

    it('keeps the explicit fallback keyboard-selectable during loading without exposing stale active-descendant metadata', async () => {
        vi.useFakeTimers();
        const json = deferred<SuggestionResponsePayload>();
        const jsonMock = vi.fn(() => json.promise);
        const fetchMock = vi
            .fn()
            .mockResolvedValue({ ok: true, json: jsonMock });
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'pending');
        await advanceDebounce();
        expect(harness.view.getByRole('status').getAttribute('aria-busy')).toBe(
            'true',
        );

        await fireEvent.keyDown(harness.input, { key: 'ArrowDown' });
        const fallback = harness.view.getByRole('option', {
            name: /Filter sessions matching "pending"/,
        });
        expect(fallback.getAttribute('aria-selected')).toBe('true');
        expect(harness.component.activeDescendantId()).toBeUndefined();
        expect(harness.input.hasAttribute('aria-activedescendant')).toBe(false);

        await fireEvent.keyDown(harness.input, { key: 'Enter' });
        expect(harness.onactivate).toHaveBeenCalledWith({
            type: 'filter',
            query: 'pending',
            source: 'fallback',
        });
        expect(harness.component.isExpanded()).toBe(false);

        json.resolve({
            items: [examineeSuggestion()],
            matches_truncated: false,
        });
        await flushMicrotasks();
        expect(
            harness.view.queryByRole('option', { name: /Ada Example/ }),
        ).toBeNull();
    });

    it.each([
        ['unknown DTO kind', examineeSuggestion({ kind: 'roster' })],
        [
            'malformed external destination',
            examineeSuggestion({
                destination: {
                    url: 'https://outside.invalid/roster',
                    focus_kind: 'assignment',
                    focus_id: 71,
                },
            }),
        ],
    ])(
        'fails closed for %s while preserving the explicit free-text fallback',
        async (_label, invalidItem) => {
            vi.useFakeTimers();
            const fetchMock = vi
                .fn()
                .mockResolvedValue(response([invalidItem]));
            vi.stubGlobal('fetch', fetchMock);
            const harness = mountDropdown();

            await typeQuery(harness, 'ada');
            await advanceDebounce();

            expect(harness.view.getByRole('status').textContent).toContain(
                'Suggestions are unavailable',
            );
            expect(
                harness.view.queryByRole('option', { name: /Ada Example/ }),
            ).toBeNull();
            expect(harness.onactivate).not.toHaveBeenCalled();
            await fireEvent.click(
                harness.view.getByRole('option', {
                    name: /Filter sessions matching "ada"/,
                }),
            );
            expect(harness.onactivate).toHaveBeenCalledWith({
                type: 'filter',
                query: 'ada',
                source: 'fallback',
            });
        },
    );

    it('keeps the free-text fallback available after an HTTP suggestion failure', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockResolvedValue({ ok: false, json: vi.fn() });
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'offline');
        await advanceDebounce();

        expect(harness.view.getByRole('status').textContent).toContain(
            'Suggestions are unavailable',
        );
        await fireEvent.click(
            harness.view.getByRole('option', {
                name: /Filter sessions matching "offline"/,
            }),
        );
        expect(harness.onactivate).toHaveBeenCalledWith({
            type: 'filter',
            query: 'offline',
            source: 'fallback',
        });
    });

    it.each([
        ['academic cycle', { academicCycleId: 13 }],
        ['domain', { domainContext: 'grading' }],
        ['tab', { tab: 'history' }],
        ['date', { date: '2026-10-09' }],
        ['room', { roomId: 45 }],
        [
            'principal and workspace',
            { contextKey: 'principal:8|workspace:practice-set-2' },
        ],
    ])(
        'invalidates an in-flight JSON response when the %s context changes',
        async (_label, contextChange) => {
            vi.useFakeTimers();
            const json = deferred<SuggestionResponsePayload>();
            const jsonMock = vi.fn(() => json.promise);
            const pendingResponse = { ok: true, json: jsonMock };
            const fetchMock = vi
                .fn()
                .mockResolvedValueOnce(pendingResponse)
                .mockResolvedValueOnce(response([sessionSuggestion()]));
            vi.stubGlobal('fetch', fetchMock);
            const harness = mountDropdown();

            await typeQuery(harness, 'ada');
            await advanceDebounce();
            expect(jsonMock).toHaveBeenCalledTimes(1);

            Object.assign(harness.props, contextChange);
            await harness.rerender();
            expect(harness.component.isExpanded()).toBe(true);
            await advanceDebounce();
            expect(fetchMock).toHaveBeenCalledTimes(2);
            const refreshedUrl = new URL(
                String(fetchMock.mock.calls[1][0]),
                window.location.origin,
            );
            if ('academicCycleId' in contextChange) {
                expect(refreshedUrl.searchParams.get('academic_cycle_id')).toBe(
                    String(contextChange.academicCycleId),
                );
            }
            if ('domainContext' in contextChange) {
                expect(refreshedUrl.searchParams.get('domain_context')).toBe(
                    contextChange.domainContext,
                );
            }
            if ('tab' in contextChange) {
                expect(refreshedUrl.searchParams.get('tab')).toBe(
                    contextChange.tab,
                );
            }
            if ('date' in contextChange) {
                expect(refreshedUrl.searchParams.get('date')).toBe(
                    contextChange.date,
                );
            }
            if ('roomId' in contextChange) {
                expect(refreshedUrl.searchParams.get('room_id')).toBe(
                    String(contextChange.roomId),
                );
            }
            expect(
                harness.view.getByRole('option', { name: /EX-ROOM-42/ }),
            ).toBeTruthy();
            json.resolve({
                items: [examineeSuggestion()],
                matches_truncated: false,
            });
            await flushMicrotasks();

            expect(
                harness.view.getByRole('option', { name: /EX-ROOM-42/ }),
            ).toBeTruthy();
            expect(
                harness.view.queryByRole('option', { name: /Ada Example/ }),
            ).toBeNull();
            expect(harness.component.activeDescendantId()).toBeUndefined();
            expect(harness.onactivate).not.toHaveBeenCalled();
        },
    );

    it.each(['Escape', 'Tab', 'outside pointer'])(
        'keeps a dismissed popup closed after a late response (%s)',
        async (dismissal) => {
            vi.useFakeTimers();
            const pendingFetch = deferred<SuggestionResponse>();
            const fetchMock = vi.fn().mockReturnValue(pendingFetch.promise);
            vi.stubGlobal('fetch', fetchMock);
            const harness = mountDropdown();

            await typeQuery(harness, 'ada');
            await advanceDebounce();
            expect(fetchMock).toHaveBeenCalledTimes(1);

            if (dismissal === 'outside pointer') {
                await fireEvent.pointerDown(document.body);
            } else if (dismissal === 'Tab') {
                const tabEvent = new KeyboardEvent('keydown', {
                    key: 'Tab',
                    bubbles: true,
                    cancelable: true,
                });
                harness.input.dispatchEvent(tabEvent);
                expect(tabEvent.defaultPrevented).toBe(false);
            } else {
                await fireEvent.keyDown(harness.input, { key: dismissal });
            }

            expect(harness.component.isExpanded()).toBe(false);
            expect(harness.input.hasAttribute('aria-controls')).toBe(false);
            expect(harness.input.hasAttribute('aria-activedescendant')).toBe(
                false,
            );
            const lateJson = vi.fn().mockResolvedValue({
                items: [examineeSuggestion()],
                matches_truncated: false,
            });
            pendingFetch.resolve({ ok: true, json: lateJson });
            await flushMicrotasks();

            expect(lateJson).not.toHaveBeenCalled();
            expect(
                harness.view.queryByRole('option', { name: /Ada Example/ }),
            ).toBeNull();
            expect(harness.component.listboxIdentifier()).toBeUndefined();
            expect(harness.onactivate).not.toHaveBeenCalled();
        },
    );

    it('does not let an older request finally-state overwrite a newer query result', async () => {
        vi.useFakeTimers();
        const oldFetch = deferred<SuggestionResponse>();
        const fetchMock = vi
            .fn()
            .mockReturnValueOnce(oldFetch.promise)
            .mockResolvedValueOnce(response([sessionSuggestion()]));
        vi.stubGlobal('fetch', fetchMock);
        const harness = mountDropdown();

        await typeQuery(harness, 'old');
        await advanceDebounce();
        await typeQuery(harness, 'new');
        await advanceDebounce();
        expect(
            harness.view.getByRole('option', { name: /EX-ROOM-42/ }),
        ).toBeTruthy();

        oldFetch.resolve(response([examineeSuggestion()]));
        await flushMicrotasks();

        expect(
            harness.view.getByRole('option', { name: /EX-ROOM-42/ }),
        ).toBeTruthy();
        expect(
            harness.view.queryByRole('option', { name: /Ada Example/ }),
        ).toBeNull();
        expect(harness.component.isExpanded()).toBe(true);
    });
});
