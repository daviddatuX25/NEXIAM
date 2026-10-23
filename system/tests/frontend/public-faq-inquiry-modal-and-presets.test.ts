// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import OfficeInquiryWorkspace from '../../resources/js/Components/Admission/OfficeInquiryWorkspace.svelte';
import PublicFaqInquiryModal from '../../resources/js/Components/public/PublicFaqInquiryModal.svelte';
import XiamAssistPanel from '../../resources/js/Components/kit/Xiam/XiamAssistPanel.svelte';
import ApplicantLayout from '../../resources/js/Pages/Applicant/ApplicantLayout.svelte';
import PublicMovingLauncher from '../../resources/js/Components/public/PublicMovingLauncher.svelte';
import type { PublicFaqItem } from '../../resources/js/lib/public-faq';

beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});
vi.mock('@inertiajs/svelte', () => ({
    router: {
        replace: vi.fn(),
        reload: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
        visit: vi.fn(),
        on: vi.fn(() => () => {}),
    },
    usePage: () => ({
        url: '/apply',
        props: {
            auth: { user: null },
            institution: { name: 'ISPSC', short_name: 'I' },
            navigation_badges: { groups: { messaging: {} } },
        },
    }),
    usePoll: () => ({ start: vi.fn(), stop: vi.fn() }),
    Link: () => null,
}));

const sampleFaqs: PublicFaqItem[] = [
    {
        id: 'faq-exam-1',
        slug: 'entrance-exam-schedule-slot-reservation',
        question: 'How does Entrance Exam Schedule & Slot Reservation work?',
        answer: 'Once your application is submitted, open Exam Scheduling in the Applicant Portal to pick an available slot.',
        answer_excerpt: 'Once your application is submitted, open Exam Scheduling in the Applicant Portal.',
        category: 'testing',
        office_code: 'guidance',
        office_label: 'Guidance / Testing',
        governing_policy: null,
    },
    {
        id: 'faq-doc-1',
        slug: 'document-requirements-form-138',
        question: 'What are the Document Requirements (Form 138, PSA, TOR)?',
        answer: 'Bring original Form 138 and PSA Birth Certificate during Admission Clearance.',
        answer_excerpt: 'Bring original Form 138 and PSA Birth Certificate during Admission Clearance.',
        category: 'admissions',
        office_code: 'registrar',
        office_label: 'Registrar / Admissions',
        governing_policy: null,
    },
];

describe('OfficeInquiryWorkspace preset subjects and auto-office routing', () => {
    it('auto-selects the receiving office and triggers FAQ deflection when a preset topic is clicked, while allowing manual override', async () => {
        render(OfficeInquiryWorkspace, {
            props: {
                inquiries: [],
                next_cursor: null,
                destinations: [
                    { code: 'registrar', label: 'Registrar' },
                    { code: 'guidance', label: 'Guidance' },
                ],
                initial: null,
                faqs: sampleFaqs,
                staff_mode: false,
                office_label: '',
                synthetic_only: true,
                unavailable: null,
                principal_key: 'principal-1',
                attempt_key: 'attempt-1',
                urls: {
                    index: '/applicant/admission-support',
                    create: '/applicant/inquiries',
                    show: {},
                    snapshot: null,
                    send: null,
                    read: null,
                    resolve: null,
                },
                embedded: false,
                return_url: null,
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: 'New inquiry' }));
        await waitFor(() => expect(screen.getByRole('dialog')).toBeTruthy());

        const examPresetBtn = document.querySelector('[data-preset-topic="exam_schedule_slot"]') as HTMLButtonElement;
        expect(examPresetBtn).toBeTruthy();
        await fireEvent.click(examPresetBtn);

        const officeSelect = screen.getByLabelText('Office') as HTMLSelectElement;
        const subjectInput = screen.getByLabelText('Subject') as HTMLInputElement;

        expect(officeSelect.value).toBe('guidance');
        expect(subjectInput.value).toBe('Entrance Exam Schedule & Slot Reservation');
        expect(document.querySelector('[data-auto-routed-notice]')).toBeTruthy();

        // Matching FAQ deflection appears immediately from the preset subject
        expect(screen.getByLabelText('Suggested FAQ answer')).toBeTruthy();

        // User still has flexibility to change the office manually and edit the subject
        await fireEvent.change(officeSelect, { target: { value: 'registrar' } });
        expect(officeSelect.value).toBe('registrar');
        expect(document.querySelector('[data-auto-routed-notice]')).toBeNull();

        await fireEvent.input(subjectInput, { target: { value: 'Entrance Exam Schedule & Slot Reservation — Saturday' } });
        expect(subjectInput.value).toBe('Entrance Exam Schedule & Slot Reservation — Saturday');
    });
});

describe('PublicFaqInquiryModal single-focus transitions', () => {
    it('renders focused FAQs first, swaps in-place to New Inquiry with preset auto-routing, and transitions to Thread on submit', async () => {
        const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url = typeof input === 'string' ? input : input.toString();
            if (url.includes('/public/inquiries/modal')) {
                return new Response(
                    JSON.stringify({
                        faqs: sampleFaqs,
                        categories: [
                            { id: 'all', label: 'All Topics' },
                            { id: 'admissions', label: 'Admissions / Reg.' },
                            { id: 'testing', label: 'Guidance / Testing' },
                        ],
                        authenticated_applicant: false,
                        device_token: 'device-token-abc123456789012345678901234567890',
                        inquiry_state: {
                            available: true,
                            destinations: [
                                { code: 'registrar', label: 'Registrar' },
                                { code: 'guidance', label: 'Guidance' },
                            ],
                            inquiries: [],
                            initial: null,
                            principal_key: 'guest-principal',
                            attempt_key: 'guest-attempt',
                            synthetic_only: true,
                            urls: {
                                index: '/public/inquiries/modal',
                                create: '/public/inquiries',
                                snapshot: null,
                                send: null,
                                read: null,
                                snapshot_template: '/public/inquiries/{inquiry}/snapshot',
                                send_template: '/public/inquiries/{inquiry}/messages',
                                read_template: '/public/inquiries/{inquiry}/read',
                                clear_device: '/public/inquiries/clear-device',
                            },
                        },
                    }),
                    { status: 200, headers: { 'Content-Type': 'application/json' } },
                );
            }
            if (url === '/public/inquiries' && init?.method === 'POST') {
                return new Response(
                    JSON.stringify({
                        inquiry_id: '42',
                        public_reference_code: 'INQ-26-00042',
                        message_id: '101',
                        sequence: '1',
                        office: 'registrar',
                        status: 'open',
                        receipt_id: 'op-1',
                        replayed: false,
                        device_token: 'device-token-abc123456789012345678901234567890',
                        snapshot: {
                            thread: {
                                id: '42',
                                public_reference_code: 'INQ-26-00042',
                                is_pre_application: true,
                                subject: 'Document Requirements (Form 138, PSA, TOR)',
                                thread_type: 'applicant_office_inquiry',
                                program_code: '',
                                office: 'registrar',
                                office_label: 'Registrar',
                                status: 'open',
                                can_read: true,
                                can_send: true,
                            },
                            messages: [
                                {
                                    id: '101',
                                    sequence: '1',
                                    sender: { display_name: 'You', is_self: true },
                                    body: 'Can I submit Form 138 next week?',
                                    created_at: new Date().toISOString(),
                                },
                            ],
                            page: {
                                oldest_sequence: '1',
                                newest_sequence: '1',
                                has_older: false,
                                has_more_after: false,
                            },
                            latest_sequence: '1',
                            own_read_sequence: '1',
                            unread_count: 0,
                            observation_token: 'obs-token',
                            as_of: new Date().toISOString(),
                            attempt_key: 'guest-attempt',
                        },
                    }),
                    { status: 200, headers: { 'Content-Type': 'application/json' } },
                );
            }
            return new Response(JSON.stringify({}), { status: 200 });
        });
        vi.stubGlobal('fetch', fetchMock);

        render(PublicFaqInquiryModal, {
            props: {
                open: true,
                initialFaqs: sampleFaqs,
            },
        });

        await waitFor(() => expect(document.querySelector('[data-faq-modal-view="faqs"]')).toBeTruthy());
        expect(screen.getByText('How does Entrance Exam Schedule & Slot Reservation work?')).toBeTruthy();

        // Transition in-place to focused New Inquiry view
        const newInquiryBtn = document.querySelector('[data-open-new-inquiry-view]') as HTMLButtonElement;
        await fireEvent.click(newInquiryBtn);

        await waitFor(() => expect(document.querySelector('[data-faq-modal-view="new_inquiry"]')).toBeTruthy());
        expect(document.querySelector('[data-faq-modal-view="faqs"]')).toBeNull();

        // Click a preset topic -> auto-selects Registrar
        const docPreset = document.querySelector('[data-modal-preset-topic="document_requirements"]') as HTMLButtonElement;
        await fireEvent.click(docPreset);

        const officeSelect = screen.getByLabelText('Office') as HTMLSelectElement;
        expect(officeSelect.value).toBe('registrar');
        expect(document.querySelector('[data-modal-auto-routed]')).toBeTruthy();

        const messageInput = screen.getByLabelText('Message') as HTMLTextAreaElement;
        await fireEvent.input(messageInput, { target: { value: 'Can I submit Form 138 next week?' } });

        await fireEvent.click(screen.getByRole('button', { name: 'Review inquiry' }));
        expect(screen.getByText('Review inquiry before sending')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Send inquiry' }));
        await waitFor(() => expect(document.querySelector('[data-faq-modal-view="thread"]')).toBeTruthy());
        expect(screen.getByText('INQ-26-00042')).toBeTruthy();
    });
});

describe('Applicant Portal 3-action desktop capsule and mobile tandem drop-up menu', () => {
    it('expands the desktop moving island into 3 actions (? FAQs & Office Inquiry, Compass, and Xiam AI) and dispatches the FAQ modal event', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(JSON.stringify({}), { status: 200 })),
        );
        const modalEvents: CustomEvent[] = [];
        const listener = (event: Event) => modalEvents.push(event as CustomEvent);
        window.addEventListener('nexiam:open-faq-inquiry-modal', listener);

        try {
            render(XiamAssistPanel, {
                props: {
                    enabled: true,
                    mode: 'degraded',
                    session: {
                        contract_version: 1,
                        availability: 'degraded',
                        active_mode: 'inquiry',
                        interaction_modes: [{ key: 'inquiry', label: 'Inquiry' }],
                        conversation: { id: null, run_id: null, cursor: 0 },
                        proposals: [],
                    },
                },
            });

            const launcher = screen.getByLabelText('Campus Tools & AI Assistant');
            await fireEvent.click(launcher);

            await waitFor(() => expect(document.querySelector('[data-xiam-capsule-root]')).toBeTruthy());
            const faqAction = document.querySelector('[data-xiam-capsule-faq]') as HTMLButtonElement;
            const compassAction = document.querySelector('[data-xiam-capsule-compass]') as HTMLButtonElement;
            const aiAction = document.querySelector('[data-xiam-capsule-ai]') as HTMLButtonElement;

            expect(faqAction).toBeTruthy();
            expect(compassAction).toBeTruthy();
            expect(aiAction).toBeTruthy();

            await fireEvent.click(faqAction);
            expect(modalEvents).toHaveLength(1);
            expect(modalEvents[0].detail).toEqual({ view: 'faqs' });
        } finally {
            window.removeEventListener('nexiam:open-faq-inquiry-modal', listener);
        }
    });

    it('renders the mobile tandem (? + Sparkles) quick-bar button and opens the vertical drop-up menu with Xiam Chat and FAQs & Office Inquiry', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(JSON.stringify({ faqs: sampleFaqs, categories: [] }), { status: 200 })),
        );
        render(ApplicantLayout, {
            props: {
                title: 'Exam Scheduling',
            },
        });

        const tandemTrigger = document.querySelector('[data-mobile-tandem-trigger]') as HTMLButtonElement;
        expect(tandemTrigger).toBeTruthy();
        expect(document.querySelector('[data-mobile-tandem-menu]')).toBeNull();

        await fireEvent.click(tandemTrigger);
        await waitFor(() => expect(document.querySelector('[data-mobile-tandem-menu]')).toBeTruthy());

        const xiamItem = document.querySelector('[data-mobile-tandem-xiam]') as HTMLButtonElement;
        const faqItem = document.querySelector('[data-mobile-tandem-faq]') as HTMLButtonElement;
        expect(xiamItem?.textContent).toContain('Xiam Chat');
        expect(faqItem?.textContent).toContain('FAQs & Office Inquiry');

        await fireEvent.click(faqItem);
        await waitFor(() => expect(document.querySelector('[data-faq-modal-view="faqs"]')).toBeTruthy());
    });
});

describe('PublicMovingLauncher unauthenticated movable icon', () => {
    it('renders the movable launcher bottom-right by default, without mobile-hidden restriction, and opens PublicFaqInquiryModal on click', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(JSON.stringify({ faqs: sampleFaqs, categories: [] }), { status: 200 })),
        );
        window.localStorage.removeItem('nexiam.public.inquiry-launcher');

        render(PublicMovingLauncher);

        const launcher = document.querySelector('[data-public-moving-island]') as HTMLButtonElement;
        expect(launcher).toBeTruthy();
        expect(launcher.title).toBe('FAQs & Ask an Office');
        expect(launcher.getAttribute('aria-label')).toBe('FAQs and Official Inquiry Help');

        // Must be flex across all viewports (not hidden md:flex)
        expect(launcher.className).toContain('flex');
        expect(launcher.className).not.toContain('hidden');

        // Initial positioning is bottom-right
        expect(launcher.style.position).toBe('fixed');

        // Click opens the focused FAQ modal
        await fireEvent.click(launcher);
        await waitFor(() => expect(document.querySelector('[data-faq-modal-view="faqs"]')).toBeTruthy());
    });

    it('supports drag tracking and persists snapped position to public storage key', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => new Response(JSON.stringify({ faqs: sampleFaqs, categories: [] }), { status: 200 })),
        );
        window.localStorage.removeItem('nexiam.public.inquiry-launcher');

        render(PublicMovingLauncher);

        const launcher = document.querySelector('[data-public-moving-island]') as HTMLButtonElement;
        expect(launcher).toBeTruthy();
        launcher.setPointerCapture = vi.fn();
        launcher.releasePointerCapture = vi.fn();
        launcher.getBoundingClientRect = vi.fn(() => ({
            left: 200,
            top: 400,
            width: 48,
            height: 48,
            right: 248,
            bottom: 448,
            x: 200,
            y: 400,
            toJSON: () => {},
        }));
        await fireEvent.pointerDown(launcher, {
            isPrimary: true,
            button: 0,
            pointerId: 1,
            clientX: 300,
            clientY: 500,
        });

        await fireEvent.pointerMove(launcher, {
            pointerId: 1,
            clientX: 50,
            clientY: 200,
        });

        await fireEvent.pointerUp(launcher, {
            pointerId: 1,
            clientX: 50,
            clientY: 200,
        });

        const saved = window.localStorage.getItem('nexiam.public.inquiry-launcher');
        expect(saved).toBeTruthy();
        const parsed = JSON.parse(saved!);
        expect(parsed.side).toBe('left');
    });
});
