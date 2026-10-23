<script lang="ts">
    import { onDestroy, onMount, untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Textarea } from '@/Components/ui/textarea';
    import { Badge } from '@/Components/ui/badge';
    import CaseConversation from '@/Components/Admission/CaseConversation.svelte';
    import type { CaseConversationSnapshot } from '@/lib/case-conversation';
    import { filterFaqs, matchFaqDeflection, type PublicFaqCategory, type PublicFaqItem } from '@/lib/public-faq';
    import { INQUIRY_SUBJECT_PRESETS, type InquirySubjectPreset } from '@/lib/inquiry-subject-presets';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import { ArrowLeft, HelpCircle, MessageSquarePlus, Trash2 } from 'lucide-svelte';

    export const DEVICE_TOKEN_STORAGE_KEY = 'nexiam.inquiry_device_token';

    type ModalView = 'faqs' | 'new_inquiry' | 'thread';

    type InquirySummaryRow = {
        id: string;
        public_reference_code?: string | null;
        office?: string;
        office_label: string;
        subject: string;
        status: string;
        unread_count: number;
        latest_message_at?: string | null;
        updated_at?: string | null;
    };

    type InquirySnapshot = CaseConversationSnapshot & {
        thread: CaseConversationSnapshot['thread'] & {
            office_label?: string;
            public_reference_code?: string | null;
            is_pre_application?: boolean;
        };
    };

    type InquiryUrls = {
        index: string;
        create: string | null;
        snapshot: string | null;
        send: string | null;
        read: string | null;
        snapshot_template?: string | null;
        send_template?: string | null;
        read_template?: string | null;
        clear_device?: string | null;
    };

    type InquiryStatePayload = {
        available: boolean;
        destinations: { code: string; label: string }[];
        subject_presets?: readonly InquirySubjectPreset[];
        inquiries: InquirySummaryRow[];
        initial: InquirySnapshot | null;
        principal_key: string;
        attempt_key: string;
        synthetic_only: boolean;
        urls: InquiryUrls;
    };

    let {
        open = $bindable(false),
        initialFaqs = [] as PublicFaqItem[],
        initialCategories = [] as PublicFaqCategory[],
        defaultView = 'faqs' as ModalView,
    }: {
        open?: boolean;
        initialFaqs?: PublicFaqItem[];
        initialCategories?: PublicFaqCategory[];
        defaultView?: ModalView;
    } = $props();

    let activeView = $state<ModalView>(untrack(() => defaultView));
    let faqs = $state<PublicFaqItem[]>(untrack(() => initialFaqs));
    let categories = $state<PublicFaqCategory[]>(untrack(() => initialCategories));
    let authenticatedApplicant = $state(false);
    let loadingModal = $state(false);
    let modalError = $state('');

    let activeCategory = $state('all');
    let faqQuery = $state('');
    let openFaqs = $state<Record<string, boolean>>({});

    let inquiryState = $state<InquiryStatePayload>({
        available: true,
        destinations: [
            { code: 'registrar', label: 'Registrar' },
            { code: 'guidance', label: 'Guidance' },
        ],
        subject_presets: INQUIRY_SUBJECT_PRESETS,
        inquiries: [],
        initial: null,
        principal_key: '',
        attempt_key: '',
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
    });

    let selectedPresetKey = $state('');
    let autoRoutedOffice = $state(false);
    let office = $state('');
    let subject = $state('');
    let body = $state('');
    let dismissedFaqId = $state<string | null>(null);
    let captured = $state<{
        office: string;
        subject: string;
        body: string;
        idempotency_key: string;
    } | null>(null);
    let attemptedCreate = $state(false);
    let abandonConfirmed = $state(false);
    let busy = $state(false);
    let feedback = $state('');
    let fieldErrors = $state<Record<string, string>>({});
    let retryBlocked = $state(false);
    let retryAt = $state('');
    let retryTimer: ReturnType<typeof setTimeout> | null = null;

    const categoryOptions = $derived(
        categories.length > 0
            ? categories
            : [
                  { id: 'all', label: 'All Topics' },
                  { id: 'campus', label: 'General / Admin' },
                  { id: 'admissions', label: 'Admissions / Reg.' },
                  { id: 'testing', label: 'Guidance / Testing' },
              ],
    );

    const filteredFaqs = $derived(filterFaqs(faqs, faqQuery, activeCategory));
    const presetOptions = $derived(
        (inquiryState.subject_presets?.length
            ? inquiryState.subject_presets
            : INQUIRY_SUBJECT_PRESETS) as readonly InquirySubjectPreset[],
    );
    const matchingFaq = $derived(matchFaqDeflection(faqs, subject));
    const suggestedFaq = $derived(matchingFaq?.id === dismissedFaqId ? null : matchingFaq);
    const subjectLength = $derived(Array.from(subject).length);
    const bodyLength = $derived(Array.from(body).length);
    const totalUnread = $derived(
        inquiryState.inquiries.reduce((sum, row) => sum + (Number(row.unread_count) || 0), 0),
    );
    const currentSnapshot = $derived(inquiryState.initial);
    const activeInquiryId = $derived(currentSnapshot?.thread?.id ?? null);

    const activeThreadUrls = $derived.by(() => {
        if (!activeInquiryId) {
            return { snapshot: null, send: null, read: null };
        }
        const snapTpl = inquiryState.urls.snapshot_template;
        const sendTpl = inquiryState.urls.send_template;
        const readTpl = inquiryState.urls.read_template;
        return {
            snapshot: snapTpl
                ? snapTpl.replace('{inquiry}', String(activeInquiryId))
                : inquiryState.urls.snapshot,
            send: sendTpl
                ? sendTpl.replace('{inquiry}', String(activeInquiryId))
                : inquiryState.urls.send,
            read: readTpl
                ? readTpl.replace('{inquiry}', String(activeInquiryId))
                : inquiryState.urls.read,
        };
    });

    function readStoredDeviceToken(): string | null {
        if (typeof window === 'undefined') return null;
        try {
            return window.localStorage.getItem(DEVICE_TOKEN_STORAGE_KEY);
        } catch {
            return null;
        }
    }

    function persistDeviceToken(token: string | null | undefined): void {
        if (typeof window === 'undefined' || !token) return;
        try {
            window.localStorage.setItem(DEVICE_TOKEN_STORAGE_KEY, token);
        } catch {
            // Ignore private browsing storage restrictions.
        }
    }

    function clearStoredDeviceToken(): void {
        if (typeof window === 'undefined') return;
        try {
            window.localStorage.removeItem(DEVICE_TOKEN_STORAGE_KEY);
        } catch {
            // Ignore storage restrictions.
        }
    }

    function deviceHeaders(): Record<string, string> {
        const token = readStoredDeviceToken();
        return token ? { 'X-Inquiry-Device-Token': token } : {};
    }

    function officeBadge(faq: PublicFaqItem): string {
        const code = faq.office_code.toLocaleLowerCase();
        if (code === 'admin' || code === 'central_admin') return 'Central Admin';
        if (code === 'registrar' || code === 'admissions') return 'Registrar / Admissions';
        if (code === 'guidance') return 'Guidance / Testing';
        return faq.office_label;
    }

    function faqAnswerExcerpt(faq: PublicFaqItem): string {
        const excerpt = faq.answer_excerpt?.trim() || faq.answer.trim();
        return excerpt.length > 240 ? `${excerpt.slice(0, 237).trimEnd()}…` : excerpt;
    }

    function toggleFaq(id: string): void {
        openFaqs[id] = !openFaqs[id];
    }

    function openMatchingFaqInModal(faq: PublicFaqItem): void {
        openFaqs[faq.id] = true;
        activeCategory = 'all';
        faqQuery = faq.question;
        activeView = 'faqs';
    }

    export async function loadModalState(selectedInquiryId?: string | number | null): Promise<void> {
        loadingModal = true;
        modalError = '';
        try {
            const url = new URL('/public/inquiries/modal', typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
            if (selectedInquiryId !== undefined && selectedInquiryId !== null && String(selectedInquiryId) !== '') {
                url.searchParams.set('inquiry_id', String(selectedInquiryId));
            }
            const response = await fetch(url.pathname + url.search, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...deviceHeaders(),
                },
            });
            if (!response.ok) {
                throw new Error('Failed to load FAQ and inquiry state.');
            }
            const data = await response.json();
            if (Array.isArray(data.faqs)) {
                faqs = data.faqs;
            }
            if (Array.isArray(data.categories) && data.categories.length > 0) {
                categories = data.categories;
            }
            authenticatedApplicant = Boolean(data.authenticated_applicant);
            if (typeof data.device_token === 'string' && data.device_token) {
                persistDeviceToken(data.device_token);
            }
            if (data.inquiry_state && typeof data.inquiry_state === 'object') {
                inquiryState = data.inquiry_state as InquiryStatePayload;
            }
        } catch {
            modalError = 'Showing cached guidance. Live inquiry status could not be refreshed right now.';
        } finally {
            loadingModal = false;
        }
    }

    function selectPreset(preset: InquirySubjectPreset): void {
        if (busy) return;
        selectedPresetKey = preset.key;
        dismissedFaqId = null;
        if (
            preset.office &&
            inquiryState.destinations.some((destination) => destination.code === preset.office)
        ) {
            office = preset.office;
            autoRoutedOffice = true;
            delete fieldErrors.office;
        } else if (preset.key === 'custom') {
            autoRoutedOffice = false;
        }
        if (preset.key !== 'custom' && preset.subject) {
            subject = preset.subject;
            delete fieldErrors.subject;
        }
    }

    function startNewInquiry(presetKey?: string): void {
        feedback = '';
        fieldErrors = {};
        if (!captured) {
            if (presetKey) {
                const found = presetOptions.find((item) => item.key === presetKey);
                if (found) {
                    selectPreset(found);
                }
            }
        }
        activeView = 'new_inquiry';
    }

    function reviewDraft(event: SubmitEvent): void {
        event.preventDefault();
        if (busy || captured) return;
        feedback = '';
        fieldErrors = {};
        const draft = {
            office,
            subject: subject.trim(),
            body: body.trim(),
        };
        if (!inquiryState.destinations.some((destination) => destination.code === draft.office)) {
            fieldErrors.office = 'Choose an available office.';
        }
        if (!draft.subject || Array.from(draft.subject).length > 120) {
            fieldErrors.subject = 'Enter a subject of 1–120 characters.';
        }
        if (
            !draft.body ||
            Array.from(draft.body).length > 4000 ||
            new TextEncoder().encode(draft.body).length > 16384
        ) {
            fieldErrors.body = 'Enter a message of 1–4,000 characters (at most 16 KiB).';
        }
        if (Object.keys(fieldErrors).length > 0) return;

        captured = {
            ...draft,
            idempotency_key: newIdempotencyKey(),
        };
        attemptedCreate = false;
        abandonConfirmed = false;
    }

    function editDraft(): void {
        if (!captured || busy) return;
        if (attemptedCreate && !abandonConfirmed) {
            abandonConfirmed = true;
            return;
        }
        office = captured.office;
        subject = captured.subject;
        body = captured.body;
        captured = null;
        attemptedCreate = false;
        abandonConfirmed = false;
        feedback = '';
        fieldErrors = {};
    }

    function applyRateLimitWait(response: Response): void {
        const header = response.headers.get('Retry-After');
        const seconds = header === null ? NaN : Number(header);
        const delay =
            Number.isFinite(seconds) && seconds >= 0
                ? seconds * 1000
                : header
                  ? Date.parse(header) - Date.now()
                  : 10_000;
        const wait = Math.min(2_147_000_000, Math.max(1000, Number.isFinite(delay) ? delay : 10_000));
        if (retryTimer !== null) clearTimeout(retryTimer);
        retryBlocked = true;
        retryAt = new Date(Date.now() + wait).toLocaleTimeString();
        retryTimer = setTimeout(() => {
            retryBlocked = false;
            retryTimer = null;
        }, wait);
    }

    async function submitCapturedInquiry(): Promise<void> {
        if (!captured || !inquiryState.urls.create || busy || retryBlocked) return;
        busy = true;
        attemptedCreate = true;
        feedback = '';
        fieldErrors = {};
        try {
            const response = await fetch(inquiryState.urls.create, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                    ...deviceHeaders(),
                },
                body: JSON.stringify(captured),
            });
            const payload = (await response.json().catch(() => ({}))) as Record<string, any>;
            if (!response.ok) {
                if (response.status === 429) {
                    applyRateLimitWait(response);
                    feedback = 'Too many requests. Please wait before retrying.';
                } else if (response.status === 422) {
                    feedback = payload.message || 'Review the highlighted fields before sending.';
                    if (payload.errors && typeof payload.errors === 'object') {
                        for (const [key, messages] of Object.entries(payload.errors)) {
                            const first = Array.isArray(messages) ? messages[0] : messages;
                            if (typeof first === 'string') {
                                fieldErrors[key] = first;
                            }
                        }
                    }
                } else {
                    feedback = payload.message || 'Could not confirm the inquiry right now. Retry the same request.';
                }
                return;
            }

            if (typeof payload.device_token === 'string' && payload.device_token) {
                persistDeviceToken(payload.device_token);
            }

            const createdId = String(payload.inquiry_id ?? '');
            captured = null;
            attemptedCreate = false;
            abandonConfirmed = false;
            office = '';
            subject = '';
            body = '';
            selectedPresetKey = '';
            autoRoutedOffice = false;

            if (payload.snapshot && typeof payload.snapshot === 'object') {
                const snap = payload.snapshot as InquirySnapshot;
                inquiryState.initial = snap;
                const newRow: InquirySummaryRow = {
                    id: String(snap.thread.id),
                    public_reference_code:
                        snap.thread.public_reference_code ??
                        (typeof payload.public_reference_code === 'string'
                            ? payload.public_reference_code
                            : null),
                    office: snap.thread.office,
                    office_label: snap.thread.office_label ?? String(snap.thread.office ?? ''),
                    subject: snap.thread.subject,
                    status: snap.thread.status,
                    unread_count: Number(snap.unread_count ?? 0),
                    updated_at: snap.as_of ?? new Date().toISOString(),
                };
                inquiryState.inquiries = [
                    newRow,
                    ...inquiryState.inquiries.filter((row) => String(row.id) !== newRow.id),
                ];
            } else {
                await loadModalState(createdId || null);
            }
            activeView = 'thread';
        } catch {
            feedback = 'Network interrupted. Retry the exact captured inquiry to avoid duplicates.';
        } finally {
            busy = false;
        }
    }

    async function clearDeviceSession(): Promise<void> {
        if (busy || !inquiryState.urls.clear_device) return;
        busy = true;
        try {
            await fetch(inquiryState.urls.clear_device, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                    ...deviceHeaders(),
                },
            });
            clearStoredDeviceToken();
            await loadModalState(null);
            activeView = 'faqs';
        } finally {
            busy = false;
        }
    }

    $effect(() => {
        if (open) {
            void loadModalState(activeInquiryId);
        }
    });

    onMount(() => {
        function handleOpenModal(event: Event): void {
            const custom = event as CustomEvent<{ view?: ModalView; presetKey?: string } | undefined>;
            const requestedView = custom.detail?.view ?? 'faqs';
            const presetKey = custom.detail?.presetKey;
            open = true;
            if (requestedView === 'new_inquiry') {
                startNewInquiry(presetKey);
            } else {
                activeView = requestedView;
            }
        }
        window.addEventListener('nexiam:open-faq-inquiry-modal', handleOpenModal);
        return () => {
            window.removeEventListener('nexiam:open-faq-inquiry-modal', handleOpenModal);
        };
    });

    onDestroy(() => {
        if (retryTimer !== null) clearTimeout(retryTimer);
    });
</script>

<Dialog.Root bind:open>
    <Dialog.Content
        class="max-h-[90dvh] w-[calc(100vw-1.5rem)] max-w-2xl overflow-y-auto p-5 sm:max-w-2xl sm:p-6 lg:max-w-3xl"
        portalProps={{}}
    >
        {#if activeView === 'faqs'}
            <Dialog.Header class="space-y-1 text-left">
                <div class="flex items-center gap-2">
                    <span class="bg-primary/10 text-primary inline-flex size-8 items-center justify-center rounded-full">
                        <HelpCircle class="size-4.5" />
                    </span>
                    <Dialog.Title class="text-xl font-semibold tracking-tight">
                        Frequently Asked Questions
                    </Dialog.Title>
                </div>
                <Dialog.Description class="text-muted-foreground text-sm">
                    Official guidance for admissions, examinations, and requirements—without leaving this page.
                </Dialog.Description>
            </Dialog.Header>

            <div class="mt-4 space-y-4" data-faq-modal-view="faqs">
                {#if modalError}
                    <p role="status" class="border-border bg-muted/40 text-muted-foreground rounded-lg border px-3 py-2 text-xs">
                        {modalError}
                    </p>
                {/if}

                <div class="relative min-w-0">
                    <label for="modal-faq-search" class="sr-only">Search frequently asked questions</label>
                    <input
                        id="modal-faq-search"
                        type="search"
                        bind:value={faqQuery}
                        placeholder="Search questions or answers (e.g., Form 138, exam slot, transferee)..."
                        class="border-border/80 bg-card text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-11 w-full rounded-xl border px-3.5 pr-10 text-sm shadow-2xs outline-none focus-visible:ring-2"
                    />
                    {#if faqQuery}
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground absolute top-1 right-1 inline-flex size-9 items-center justify-center rounded-lg"
                            aria-label="Clear FAQ search"
                            onclick={() => (faqQuery = '')}
                        >
                            ×
                        </button>
                    {/if}
                </div>

                <div class="flex flex-wrap gap-1.5" role="group" aria-label="FAQ categories">
                    {#each categoryOptions as category (category.id)}
                        <button
                            type="button"
                            aria-pressed={activeCategory === category.id}
                            class="border-border/80 min-h-9 rounded-full border px-3.5 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none {activeCategory === category.id ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:text-foreground'}"
                            onclick={() => (activeCategory = category.id)}
                        >
                            {category.label}
                        </button>
                    {/each}
                </div>

                <p class="text-muted-foreground text-xs" aria-live="polite">
                    {filteredFaqs.length} {filteredFaqs.length === 1 ? 'answer' : 'answers'}
                </p>

                {#if filteredFaqs.length > 0}
                    <div class="max-h-[46dvh] space-y-2.5 overflow-y-auto pr-1">
                        {#each filteredFaqs as faq (faq.id)}
                            <article class="border-border/80 bg-card rounded-xl border p-3.5 shadow-2xs">
                                <button
                                    type="button"
                                    aria-expanded={Boolean(openFaqs[faq.id])}
                                    onclick={() => toggleFaq(faq.id)}
                                    class="flex min-h-10 w-full min-w-0 items-start justify-between gap-3 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span class="text-foreground min-w-0 break-words text-sm font-semibold">
                                        {faq.question}
                                    </span>
                                    <span class="bg-muted text-muted-foreground shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium">
                                        {officeBadge(faq)}
                                    </span>
                                </button>
                                {#if openFaqs[faq.id]}
                                    <div class="border-border/50 mt-3 border-t pt-3">
                                        <p class="text-muted-foreground whitespace-pre-line break-words text-xs leading-5 sm:text-sm sm:leading-6">
                                            {faq.answer}
                                        </p>
                                        {#if faq.governing_policy}
                                            <p class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                                <span class="text-muted-foreground">Governing policy:</span>
                                                <Link
                                                    href={`/policies/${faq.governing_policy.slug}`}
                                                    class="text-primary inline-flex min-h-8 items-center font-medium underline underline-offset-4"
                                                >
                                                    {faq.governing_policy.title} ({faq.governing_policy.revision_label})
                                                </Link>
                                            </p>
                                        {/if}
                                    </div>
                                {/if}
                            </article>
                        {/each}
                    </div>
                {:else}
                    <p role="status" class="border-border/80 bg-card text-muted-foreground rounded-xl border border-dashed p-5 text-sm">
                        {faqs.length === 0
                            ? 'No published frequently asked questions are available yet.'
                            : 'No frequently asked questions match your search or topic.'}
                    </p>
                {/if}

                <footer class="border-border/70 bg-muted/20 flex flex-col gap-3 rounded-xl border p-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-foreground text-sm font-medium">Still need help?</p>
                        <p class="text-muted-foreground text-xs">
                            {#if authenticatedApplicant}
                                Message the Registrar or Guidance Office directly.
                            {:else}
                                Ask the Registrar or Guidance Office—when you submit /apply on this device, your inquiry links to your account automatically.
                            {/if}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        {#if inquiryState.inquiries.length > 0}
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-10 gap-1.5 text-xs"
                                data-open-thread-view
                                onclick={() => (activeView = 'thread')}
                            >
                                <span>View your inquiry</span>
                                {#if totalUnread > 0}
                                    <Badge variant="default" class="px-1.5 py-0 text-[10px]">{totalUnread} new</Badge>
                                {/if}
                            </Button>
                        {/if}
                        <Button
                            type="button"
                            class="min-h-10 gap-1.5 text-xs"
                            data-open-new-inquiry-view
                            disabled={!inquiryState.available || inquiryState.destinations.length === 0}
                            onclick={() => startNewInquiry()}
                        >
                            <MessageSquarePlus class="size-4" />
                            <span>New inquiry</span>
                        </Button>
                    </div>
                </footer>
            </div>
        {:else if activeView === 'new_inquiry'}
            <div class="space-y-4" data-faq-modal-view="new_inquiry">
                <div class="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        class="text-primary inline-flex min-h-9 items-center gap-1.5 text-xs font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-back-to-faqs
                        onclick={() => (activeView = 'faqs')}
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Back to FAQs</span>
                    </button>
                    {#if inquiryState.inquiries.length > 0}
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground inline-flex min-h-9 items-center text-xs font-medium underline underline-offset-4"
                            onclick={() => (activeView = 'thread')}
                        >
                            View existing inquiry ({inquiryState.inquiries.length})
                        </button>
                    {/if}
                </div>

                <Dialog.Header class="space-y-1 text-left">
                    <Dialog.Title class="text-xl font-semibold tracking-tight">
                        {captured ? 'Review inquiry before sending' : 'New office inquiry'}
                    </Dialog.Title>
                    <Dialog.Description class="text-muted-foreground text-sm">
                        {captured
                            ? 'Only the office, subject, and message shown below will be sent.'
                            : 'Choose a topic or office and write your question. Replies are available right here—no email needed.'}
                    </Dialog.Description>
                </Dialog.Header>

                {#if captured}
                    <dl class="min-w-0 space-y-3 text-sm">
                        <div>
                            <dt class="text-muted-foreground text-xs">Office</dt>
                            <dd class="font-medium">
                                {inquiryState.destinations.find((d) => d.code === captured?.office)?.label ?? captured.office}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Subject</dt>
                            <dd class="font-medium [overflow-wrap:anywhere]">{captured.subject}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Message</dt>
                            <dd class="border-border bg-muted/20 max-h-56 overflow-y-auto rounded-md border p-3 [overflow-wrap:anywhere] whitespace-pre-wrap">
                                {captured.body}
                            </dd>
                        </div>
                    </dl>
                    {#if feedback}
                        <p role="alert" class="text-destructive text-xs font-medium">{feedback}</p>
                    {/if}
                    {#each Object.entries(fieldErrors) as [field, error]}
                        <p class="text-destructive text-xs">{field}: {error}</p>
                    {/each}
                    {#if retryBlocked}
                        <p role="status" class="text-muted-foreground text-xs">Retry available after {retryAt}.</p>
                    {/if}
                    <Dialog.Footer class="gap-2">
                        <Button type="button" variant="outline" class="min-h-10" disabled={busy} onclick={editDraft}>
                            {abandonConfirmed ? 'Confirm edit as new draft' : 'Edit draft'}
                        </Button>
                        <Button
                            type="button"
                            class="min-h-10"
                            disabled={busy || retryBlocked}
                            onclick={() => void submitCapturedInquiry()}
                        >
                            {busy ? 'Sending…' : attemptedCreate ? 'Retry exact inquiry' : 'Send inquiry'}
                        </Button>
                    </Dialog.Footer>
                {:else}
                    <form class="space-y-4" onsubmit={reviewDraft}>
                        <div class="space-y-2" role="group" aria-label="Common inquiry topics">
                            <p class="text-xs font-medium sm:text-sm">
                                Common topics <span class="text-muted-foreground font-normal">(auto-selects the receiving office, or edit below)</span>
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                {#each presetOptions as preset (preset.key)}
                                    <button
                                        type="button"
                                        aria-pressed={selectedPresetKey === preset.key}
                                        data-modal-preset-topic={preset.key}
                                        class="min-h-9 rounded-full border px-3 py-1.5 text-left text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none {selectedPresetKey === preset.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted/30 text-foreground hover:bg-muted'}"
                                        onclick={() => selectPreset(preset)}
                                    >
                                        <span>{preset.label}</span>
                                        {#if preset.office}
                                            <span class="ml-1 opacity-75">→ {preset.office_label}</span>
                                        {/if}
                                    </button>
                                {/each}
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label for="modal-inquiry-office" class="text-sm font-medium">Office</label>
                                {#if autoRoutedOffice && office}
                                    <span class="text-primary text-xs font-medium" data-modal-auto-routed>
                                        Auto-selected from topic — you can change this
                                    </span>
                                {/if}
                            </div>
                            <select
                                id="modal-inquiry-office"
                                bind:value={office}
                                onchange={() => (autoRoutedOffice = false)}
                                required
                                class="border-input bg-background focus-visible:outline-ring flex min-h-11 w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-2"
                            >
                                <option value="" disabled>Choose an office</option>
                                {#each inquiryState.destinations as destination (destination.code)}
                                    <option value={destination.code}>{destination.label}</option>
                                {/each}
                            </select>
                            {#if fieldErrors.office}
                                <p class="text-destructive text-xs">{fieldErrors.office}</p>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <label for="modal-inquiry-subject" class="text-sm font-medium">Subject</label>
                            <Input
                                id="modal-inquiry-subject"
                                type="text"
                                class="w-full"
                                disabled={false}
                                bind:value={subject}
                                required
                                maxlength={120}
                            />
                            <p class="text-muted-foreground text-xs">{subjectLength}/120 characters</p>
                            {#if fieldErrors.subject}
                                <p role="alert" class="text-destructive text-xs">{fieldErrors.subject}</p>
                            {/if}
                            {#if suggestedFaq}
                                <aside class="border-border/80 bg-muted/30 mt-2.5 space-y-2.5 rounded-xl border p-3.5" aria-label="Suggested FAQ answer">
                                    <p class="text-muted-foreground whitespace-pre-line break-words text-xs leading-5 sm:text-sm">
                                        <span class="text-foreground font-semibold">Helpful answer: {suggestedFaq.question} — </span>
                                        {faqAnswerExcerpt(suggestedFaq)}
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            class="min-h-9 text-xs"
                                            onclick={() => openMatchingFaqInModal(suggestedFaq)}
                                        >
                                            Read full FAQ
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="min-h-9 text-xs"
                                            onclick={() => (dismissedFaqId = suggestedFaq?.id ?? null)}
                                        >
                                            Proceed with ticket
                                        </Button>
                                    </div>
                                </aside>
                            {/if}
                        </div>

                        <div class="space-y-1">
                            <label for="modal-inquiry-body" class="text-sm font-medium">Message</label>
                            <Textarea
                                id="modal-inquiry-body"
                                bind:value={body}
                                required
                                rows={5}
                                maxlength={4000}
                                class="min-h-28 resize-y"
                                disabled={false}
                            />
                            <p class="text-muted-foreground text-xs">
                                {bodyLength}/4,000 characters · Plain text only.
                            </p>
                            {#if fieldErrors.body}
                                <p role="alert" class="text-destructive text-xs">{fieldErrors.body}</p>
                            {/if}
                        </div>

                        <Dialog.Footer class="gap-2">
                            <Button type="button" variant="outline" class="min-h-10" onclick={() => (activeView = 'faqs')}>
                                Cancel
                            </Button>
                            <Button type="submit" class="min-h-10" disabled={inquiryState.destinations.length === 0}>
                                Review inquiry
                            </Button>
                        </Dialog.Footer>
                    </form>
                {/if}
            </div>
        {:else if activeView === 'thread'}
            <div class="space-y-4" data-faq-modal-view="thread">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button
                        type="button"
                        class="text-primary inline-flex min-h-9 items-center gap-1.5 text-xs font-semibold underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-back-to-faqs
                        onclick={() => (activeView = 'faqs')}
                    >
                        <ArrowLeft class="size-3.5" />
                        <span>Back to FAQs</span>
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        {#if currentSnapshot?.thread?.public_reference_code}
                            <Badge variant="outline" class="text-xs font-semibold">
                                {currentSnapshot.thread.public_reference_code}
                            </Badge>
                        {/if}
                        {#if inquiryState.destinations.length > 0}
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-9 px-3 text-xs"
                                onclick={() => startNewInquiry()}
                            >
                                New inquiry
                            </Button>
                        {/if}
                        {#if !authenticatedApplicant && inquiryState.urls.clear_device}
                            <Button
                                type="button"
                                variant="ghost"
                                class="text-muted-foreground hover:text-destructive min-h-9 gap-1 px-2.5 text-xs"
                                disabled={busy}
                                onclick={() => void clearDeviceSession()}
                                title="Detach this browser from pre-application inquiries"
                            >
                                <Trash2 class="size-3.5" />
                                <span>Clear device</span>
                            </Button>
                        {/if}
                    </div>
                </div>

                {#if inquiryState.inquiries.length > 1}
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Your inquiries on this device">
                        {#each inquiryState.inquiries as row (row.id)}
                            <button
                                type="button"
                                class="min-h-8 rounded-full border px-3 py-1 text-xs font-medium transition-colors {String(activeInquiryId) === String(row.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted/30 text-foreground hover:bg-muted'}"
                                onclick={() => void loadModalState(row.id)}
                            >
                                <span>{row.public_reference_code || row.office_label}: {row.subject.slice(0, 28)}{row.subject.length > 28 ? '…' : ''}</span>
                                {#if row.unread_count > 0}
                                    <span class="ml-1 font-bold">({row.unread_count})</span>
                                {/if}
                            </button>
                        {/each}
                    </div>
                {/if}

                {#if !authenticatedApplicant}
                    <p class="border-border/70 bg-muted/20 text-muted-foreground rounded-lg border px-3 py-2 text-xs">
                        Saved to this browser automatically. When you submit <strong>/apply</strong> on this device, this conversation will automatically link to your Applicant Portal account.
                    </p>
                {/if}

                {#if currentSnapshot && activeThreadUrls.snapshot && activeThreadUrls.send && activeThreadUrls.read}
                    <CaseConversation
                        initial={currentSnapshot}
                        snapshotUrl={activeThreadUrls.snapshot}
                        sendUrl={activeThreadUrls.send}
                        readUrl={activeThreadUrls.read}
                        principalContext={inquiryState.principal_key}
                        attemptContext={inquiryState.attempt_key}
                    />
                {:else}
                    <p role="status" class="border-border text-muted-foreground rounded-xl border border-dashed p-5 text-sm">
                        {loadingModal ? 'Loading conversation…' : 'Select or start an inquiry to view its messages.'}
                    </p>
                {/if}
            </div>
        {/if}
    </Dialog.Content>
</Dialog.Root>
