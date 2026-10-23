// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import OfficeInquiryWorkspace from '../../resources/js/Components/Admission/OfficeInquiryWorkspace.svelte';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});
const integrationMocks = vi.hoisted(() => ({
    replace: vi.fn(),
    reload: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
    pulseCallbacks: [] as Array<() => void>,
}));
vi.mock('@inertiajs/svelte', () => ({
    router: {
        replace: integrationMocks.replace,
        reload: integrationMocks.reload,
        get: integrationMocks.get,
        post: integrationMocks.post,
        on: vi.fn(() => () => {}),
    },
    usePage: () => ({ props: { navigation_badges: { groups: { messaging: {} } } } }),
    Link: () => null,
}));

const draft = {
    reason: 'exam_time_extension' as const,
    principal_key: 'principal-own',
    attempt_key: 'attempt-own',
    office: 'guidance',
    subject: 'Question about exam timing',
    body: 'I would like to ask about an exam timing concern. Please review my request.',
};
const programDraft = {
    reason: 'program_considering' as const,
    principal_key: 'principal-own',
    attempt_key: 'attempt-own',
    office: 'guidance',
    subject: 'Program plan update',
    body: 'I am considering a change to my program plan. Please advise me about the current official program-choice process.',
};

type InquiryDraft = typeof draft | typeof programDraft;

function props(inquiry_draft: InquiryDraft | null = draft) {
    return {
        inquiries: [],
        next_cursor: null,
        destinations: [{ code: 'guidance', label: 'Guidance Office' }],
        initial: null,
        inquiry_draft,
        faqs: [],
        staff_mode: false,
        office_label: '',
        synthetic_only: true,
        unavailable: null,
        principal_key: 'principal-own',
        attempt_key: 'attempt-own',
        urls: {
            index: '/applicant/admission-support',
            create: '/applicant/inquiries',
            show: {},
            snapshot: null,
            send: null,
            read: null,
            resolve: null,
        },
        embedded: true,
        return_url: null,
    };
}

describe('applicant exam help draft', () => {
    it('opens an editable unsent preset once and preserves edits across prop refreshes', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        const view = render(OfficeInquiryWorkspace, { props: props() });

        await waitFor(() => expect(screen.getByRole('dialog')).toBeTruthy());
        const subject = screen.getByLabelText('Subject') as HTMLInputElement;
        const message = screen.getByLabelText('Message') as HTMLTextAreaElement;
        expect(subject.value).toBe(draft.subject);
        expect(message.value).toBe(draft.body);
        expect((screen.getByLabelText('Office') as HTMLSelectElement).value).toBe('guidance');
        expect(screen.getByRole('button', { name: 'Review inquiry' })).toBeTruthy();

        await fireEvent.input(subject, { target: { value: 'My edited subject' } });
        await fireEvent.input(message, { target: { value: 'My edited message' } });
        await view.rerender(props({ ...draft }));
        expect(subject.value).toBe('My edited subject');
        expect(message.value).toBe('My edited message');
        await fireEvent.click(screen.getByRole('button', { name: 'Review inquiry' }));
        expect(screen.getByRole('heading', { name: 'Review inquiry before sending' })).toBeTruthy();
        expect(screen.getByText('My edited message')).toBeTruthy();
        expect(fetchMock).not.toHaveBeenCalled();
    });
    it('opens an editable program-plan draft without sending it', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        render(OfficeInquiryWorkspace, { props: props(programDraft) });

        await waitFor(() => expect(screen.getByRole('dialog')).toBeTruthy());
        expect((screen.getByLabelText('Subject') as HTMLInputElement).value).toBe(programDraft.subject);
        expect((screen.getByLabelText('Message') as HTMLTextAreaElement).value).toBe(programDraft.body);
        expect(screen.getByRole('button', { name: 'Review inquiry' })).toBeTruthy();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('does not open a draft for another principal or attempt context', async () => {
        render(OfficeInquiryWorkspace, {
            props: {
                ...props(),
                inquiry_draft: { ...draft, principal_key: 'other-principal' },
            },
        });

        await tick();
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('replaces the Inertia URL and keeps it clean through a messaging pulse reload', async () => {
        integrationMocks.replace.mockImplementation(({ url }: { url: string }) => {
            window.history.replaceState(window.history.state, '', url);
        });
        integrationMocks.pulseCallbacks.length = 0;

        vi.doMock('@/lib/realtime-pulse.svelte', () => ({
            subscribeRealtimePulse: (_topic: string, callback: () => void) => {
                integrationMocks.pulseCallbacks.push(callback);
                return () => {};
            },
        }));
        vi.doMock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
            default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
        }));
        // The real workspace is retained to verify local composer state through the page reload.
        vi.doMock('@/Components/Admission/CaseConversation.svelte', () => ({ default: () => null }));
        vi.doMock('@/Components/Applicant/ApplicantFaqWidget.svelte', () => ({ default: () => null }));
        vi.doMock('@/Components/kit/NavBadge.svelte', () => ({ default: () => null }));

        window.history.replaceState(null, '', '/applicant/admission-support?group=offices&draft_reason=exam_time_extension');
        // Load the page only after installing its Inertia and child-component mocks.
        const { default: AdmissionSupport } = await import('../../resources/js/Pages/Applicant/AdmissionSupport.svelte');
        const pageProps = {
            groups_available: { program_support: false, offices: true },
            active_group: 'offices' as const,
            destinations: [{ code: 'guidance', label: 'Guidance Office' }],
            inquiries: [],
            initial: null,
            inquiry_draft: draft,
            draft_reason: 'exam_time_extension',
            principal_key: draft.principal_key,
            attempt_key: draft.attempt_key,
            urls: {
                index: '/applicant/admission-support',
                access_history: '/applicant/access-history',
                create: '/applicant/inquiries',
                show: {},
                snapshot: null,
                send: null,
                read: null,
                resolve: null,
            },
        };
        const view = render(AdmissionSupport, { props: pageProps });

        await waitFor(() => expect(integrationMocks.replace).toHaveBeenCalledOnce());
        expect(window.location.search).toBe('?group=offices');
        await waitFor(() => expect(screen.getByRole('dialog')).toBeTruthy());
        const subject = screen.getByLabelText('Subject') as HTMLInputElement;
        const message = screen.getByLabelText('Message') as HTMLTextAreaElement;
        expect(subject.value).toBe(draft.subject);
        expect(message.value).toBe(draft.body);
        await fireEvent.input(subject, { target: { value: 'Edited exam timing question' } });
        await fireEvent.input(message, { target: { value: 'My saved unsent draft' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Review inquiry' }));
        expect(window.location.search).toBe('?group=offices');

        expect(integrationMocks.pulseCallbacks).toHaveLength(1);
        integrationMocks.pulseCallbacks[0]?.();
        expect(integrationMocks.reload).toHaveBeenCalledOnce();
        expect(window.location.search).toBe('?group=offices');
        await view.rerender({ ...pageProps });
        expect(screen.getByRole('heading', { name: 'Review inquiry before sending' })).toBeTruthy();
        expect(screen.getByText('Edited exam timing question')).toBeTruthy();
        expect(screen.getByText('My saved unsent draft')).toBeTruthy();
        expect(window.location.search).toBe('?group=offices');
        expect(integrationMocks.get).not.toHaveBeenCalled();
        expect(integrationMocks.post).not.toHaveBeenCalled();

        view.unmount();
        vi.doUnmock('@/lib/realtime-pulse.svelte');
        vi.doUnmock('@/Pages/Applicant/ApplicantLayout.svelte');
        vi.doUnmock('@/Components/Admission/OfficeInquiryWorkspace.svelte');
        vi.doUnmock('@/Components/Admission/CaseConversation.svelte');
        vi.doUnmock('@/Components/Applicant/ApplicantFaqWidget.svelte');
        vi.doUnmock('@/Components/kit/NavBadge.svelte');
        vi.resetModules();
    });
});
