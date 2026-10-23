<script lang="ts">
    import { onDestroy, untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Textarea } from '@/Components/ui/textarea';
    import { Badge } from '@/Components/ui/badge';
    import * as Dialog from '@/Components/ui/dialog';
    import CaseConversation from '@/Components/Admission/CaseConversation.svelte';
    import type { CaseConversationSnapshot } from '@/lib/case-conversation';
    import { matchFaqDeflection, type PublicFaqItem } from '@/lib/public-faq';
    import { INQUIRY_SUBJECT_PRESETS, type InquirySubjectPreset } from '@/lib/inquiry-subject-presets';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';

    type InquiryRow = {
        id: string;
        public_reference_code?: string | null;
        is_pre_application?: boolean;
        office?: string;
        office_label: string;
        subject: string;
        status: string;
        unread_count: number;
        updated_at?: string | null;
        latest_message_at?: string | null;
        show_url: string;
        applicant?: {
            display_name: string;
            reference_code: string;
            is_public?: boolean;
        };
    };
    type InquirySnapshot = CaseConversationSnapshot & {
        thread: CaseConversationSnapshot['thread'] & {
            office_label?: string;
            public_reference_code?: string | null;
            is_pre_application?: boolean;
            applicant?: {
                display_name: string;
                reference_code: string;
                is_public?: boolean;
            };
        };
    };
    type CapturedInquiry = {
        office: string;
        subject: string;
        body: string;
        idempotency_key: string;
    };
    type Failure = 'idle' | 'uncertain' | 'validation' | 'conflict' | 'session' | 'denied' | 'limited';
    type Operation = 'create' | 'resolve';

    let {
        inquiries = [], next_cursor = null, destinations = [], initial = null,
        inquiry_draft = null,
        faqs = [],
        subject_presets = INQUIRY_SUBJECT_PRESETS,
        staff_mode = false, office_label = '',
        principal_key, attempt_key, urls, unavailable = null,
        embedded = false, return_url = null,
    } = $props<{
        inquiries?: InquiryRow[];
        next_cursor?: string | null;
        destinations?: { code: string; label: string }[];
        initial?: InquirySnapshot | null;
        inquiry_draft?: {
            reason: 'exam_time_extension' | 'program_considering' | 'program_changed' | 'program_enrolled_elsewhere' | 'program_help';
            principal_key: string;
            attempt_key: string;
            office: string;
            subject: string;
            body: string;
        } | null;
        faqs?: PublicFaqItem[];
        subject_presets?: readonly InquirySubjectPreset[];
        staff_mode?: boolean;
        synthetic_only?: boolean;
        unavailable?: { available: false; reason: string; message: string } | null;
        principal_key: string;
        attempt_key: string;
        urls: {
            index: string;
            return?: string | null;
            create: string | null;
            show: Record<string, string>;
            snapshot: string | null;
            send: string | null;
            read: string | null;
            resolve: string | null;
        };
        embedded?: boolean;
        return_url?: string | null;
    }>();
    const availableDestinations = $derived(destinations as { code: string; label: string }[]);
    const presetOptions = $derived((subject_presets?.length ? subject_presets : INQUIRY_SUBJECT_PRESETS) as readonly InquirySubjectPreset[]);

    export function openNewInquiry() {
        beginInquiry();
    }

    let newOpen = $state(false);
    let resolveOpen = $state(false);
    let office = $state('');
    let subject = $state('');
    let body = $state('');
    let selectedPresetKey = $state('');
    let autoRoutedOffice = $state(false);
    let captured = $state<CapturedInquiry | null>(null);
    let resolveCapture = $state<{ inquiry_id: string; idempotency_key: string; url: string } | null>(null);
    let attemptedCreate = $state(false);
    let abandonConfirmed = $state(false);
    let busy = $state(false);
    let navigationLoading = $state(false);
    let feedback = $state('');
    let failure = $state<Failure>('idle');
    let feedbackOperation = $state<Operation>('create');
    let fieldErrors = $state<Record<string, string>>({});
    let retryBlocked = $state(false);
    let retryAt = $state('');
    let controller: AbortController | null = null;
    let retryTimer: ReturnType<typeof setTimeout> | null = null;
    let generation = 0;
    let activeContext = untrack(() => `${principal_key}\u0000${attempt_key}`);
    let activeThread = untrack(() => initial?.thread.id ?? '');

    const matchingFaq = $derived(matchFaqDeflection(faqs, subject));
    let dismissedFaqId = $state<string | null>(null);
    let consumedDraftContext = untrack(() => '');

    $effect(() => {
        const draft = inquiry_draft;
        if (!draft || !['exam_time_extension', 'program_considering', 'program_changed', 'program_enrolled_elsewhere', 'program_help'].includes(draft.reason)
            || draft.principal_key !== principal_key || draft.attempt_key !== attempt_key) return;
        const draftContext = `${draft.principal_key}\u0000${draft.attempt_key}\u0000${draft.reason}`;
        if (draftContext === consumedDraftContext) return;
        if (initial || captured || attemptedCreate || busy || newOpen || office || subject || body) return;
        if (!destinations.some((destination: { code: string; label: string }) => destination.code === draft.office)) return;
        consumedDraftContext = draftContext;
        office = draft.office;
        subject = draft.subject;
        body = draft.body;
        newOpen = true;
    });
    const suggestedFaq = $derived(matchingFaq?.id === dismissedFaqId ? null : matchingFaq);
    function faqAnswerExcerpt(faq: PublicFaqItem): string {
        const excerpt = faq.answer_excerpt?.trim() || faq.answer.trim();
        return excerpt.length > 240 ? `${excerpt.slice(0, 237).trimEnd()}…` : excerpt;
    }
    const cannotRetry = $derived(busy || retryBlocked || failure === 'session' || failure === 'denied' || failure === 'conflict');
    const bodyLength = $derived(Array.from(body).length);
    const subjectLength = $derived(Array.from(subject).length);

    $effect(() => {
        const context = `${principal_key}\u0000${attempt_key}`;
        const thread = initial?.thread.id ?? '';
        const status = initial?.thread.status;
        untrack(() => {
            if (context !== activeContext || thread !== activeThread) {
                generation += 1;
                controller?.abort();
                clearRetry();
                busy = false;
                feedback = '';
                failure = 'idle';
                fieldErrors = {};
                resolveCapture = null;
                resolveOpen = false;
                if (context !== activeContext) {
                    captured = null;
                    office = '';
                    subject = '';
                    body = '';
                    selectedPresetKey = '';
                    autoRoutedOffice = false;
                    newOpen = false;
                    attemptedCreate = false;
                }
                activeContext = context;
                activeThread = thread;
            }
            if (status === 'closed' && resolveCapture) {
                resolveCapture = null;
                resolveOpen = false;
            }
        });
    });

    onDestroy(() => {
        generation += 1;
        controller?.abort();
        clearRetry();
    });

    function clearRetry() {
        if (retryTimer !== null) clearTimeout(retryTimer);
        retryTimer = null;
        retryBlocked = false;
        retryAt = '';
    }

    function resetFeedback(operation: Operation) {
        clearRetry();
        feedbackOperation = operation;
        feedback = '';
        fieldErrors = {};
        failure = 'idle';
        abandonConfirmed = false;
    }

    function beginInquiry() {
        if (busy) return;
        if (!captured) resetFeedback('create');
        newOpen = true;
    }

    function selectPreset(preset: InquirySubjectPreset) {
        if (busy) return;
        selectedPresetKey = preset.key;
        dismissedFaqId = null;
        if (preset.office && availableDestinations.some((destination) => destination.code === preset.office)) {
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

    function reviewInquiry(event: SubmitEvent) {
        event.preventDefault();
        if (busy || captured) return;
        resetFeedback('create');
        const draft = { office, subject: subject.trim(), body: body.trim() };
        if (!destinations.some((destination: { code: string; label: string }) => destination.code === draft.office)) fieldErrors.office = 'Choose an available office.';
        if (!draft.subject || Array.from(draft.subject).length > 120) fieldErrors.subject = 'Enter a subject of 1–120 characters.';
        if (!draft.body || Array.from(draft.body).length > 4000 || new TextEncoder().encode(draft.body).length > 16384) fieldErrors.body = 'Enter a message of 1–4,000 characters (at most 16 KiB).';
        if (Object.keys(fieldErrors).length) return;
        captured = { ...draft, idempotency_key: newIdempotencyKey() };
        attemptedCreate = false;
    }

    function editInquiry() {
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
        resetFeedback('create');
    }

    function beginResolve() {
        if (busy || !initial || !urls.resolve) return;
        if (!resolveCapture) {
            resetFeedback('resolve');
            resolveCapture = {
                inquiry_id: initial.thread.id,
                idempotency_key: newIdempotencyKey(),
                url: urls.resolve,
            };
        }
        resolveOpen = true;
    }

    function refreshTruth() {
        if (busy || navigationLoading) return;
        navigationLoading = true;
        router.reload({
            onFinish: () => { navigationLoading = false; },
        });
    }

    function olderInquiries() {
        if (!next_cursor || navigationLoading || busy) return;
        navigationLoading = true;
        router.visit(urls.index, {
            data: { before_id: next_cursor },
            onFinish: () => { navigationLoading = false; },
        });
    }

    function rateLimit(response: Response) {
        const header = response.headers.get('Retry-After');
        const seconds = header === null ? NaN : Number(header);
        const delay = Number.isFinite(seconds) && seconds >= 0
            ? seconds * 1000
            : header ? Date.parse(header) - Date.now() : 10_000;
        const wait = Math.min(2_147_000_000, Math.max(1000, Number.isFinite(delay) ? delay : 10_000));
        clearRetry();
        retryBlocked = true;
        retryAt = new Date(Date.now() + wait).toLocaleTimeString();
        retryTimer = setTimeout(() => {
            retryBlocked = false;
            retryTimer = null;
        }, wait);
    }

    function handleFailure(response: Response, payload: Record<string, unknown>) {
        if (response.status === 401 || response.status === 419) {
            failure = 'session';
            feedback = 'Your session has expired. Sign in again before retrying. Nothing will be sent automatically.';
        } else if (response.status === 403 || response.status === 404) {
            failure = 'denied';
            feedback = 'This inquiry or office is no longer available to this account. Refresh to check current access.';
        } else if (response.status === 409) {
            failure = 'conflict';
            feedback = 'The inquiry, office availability, or operation identity has changed. Refresh to check the saved state before trying another action.';
        } else if (response.status === 422) {
            failure = 'validation';
            feedback = 'The server could not accept this request. Review the fields below before editing and reviewing a new draft.';
            if (payload.errors && typeof payload.errors === 'object') {
                for (const [key, messages] of Object.entries(payload.errors)) {
                    if (!['office', 'subject', 'body', 'idempotency_key'].includes(key)) continue;
                    const value = Array.isArray(messages) ? messages[0] : messages;
                    if (typeof value === 'string') fieldErrors[key] = value;
                }
            }
        } else if (response.status === 429) {
            failure = 'limited';
            rateLimit(response);
            feedback = 'Too many requests. Wait before retrying the same captured request.';
        } else {
            failure = 'uncertain';
            feedback = 'The server could not confirm the outcome. The action may already be saved. Retry only the same captured request, or refresh to check the saved state.';
        }
    }

    async function submit(operation: Operation) {
        const pending = operation === 'create' ? captured : resolveCapture;
        const url = operation === 'create' ? urls.create : resolveCapture?.url;
        if (!pending || !url || cannotRetry) return;
        const requestGeneration = generation;
        const input = operation === 'create' ? { ...captured } : { idempotency_key: resolveCapture!.idempotency_key };
        feedbackOperation = operation;
        feedback = '';
        fieldErrors = {};
        busy = true;
        if (operation === 'create') attemptedCreate = true;
        controller = new AbortController();
        const requestController = controller;
        const timeout = setTimeout(() => requestController.abort(), 10_000);
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                signal: requestController.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify(input),
            });
            const result: unknown = await response.json().catch(() => null);
            if (requestGeneration !== generation) return;
            const payload = result && typeof result === 'object' ? result as Record<string, unknown> : {};
            if (!response.ok) {
                handleFailure(response, payload);
                return;
            }
            if (operation === 'create') {
                if (typeof payload.redirect_url !== 'string' || !payload.inquiry_id) throw new Error('Unconfirmed receipt');
                const destination = new URL(payload.redirect_url, window.location.origin);
                if (destination.origin !== window.location.origin) throw new Error('Invalid receipt destination');
                navigationLoading = true;
                router.visit(payload.redirect_url, {
                    onFinish: () => { navigationLoading = false; },
                });
            } else {
                if (String(payload.inquiry_id) !== resolveCapture?.inquiry_id || payload.status !== 'closed') throw new Error('Unconfirmed receipt');
                resolveOpen = false;
                resolveCapture = null;
                failure = 'idle';
                feedback = 'The server confirmed this inquiry is closed. Refreshing its saved state…';
                navigationLoading = true;
                router.reload({
                    onFinish: () => { navigationLoading = false; },
                });
            }
        } catch {
            if (requestGeneration !== generation) return;
            failure = 'uncertain';
            feedback = 'No confirmed response was received. The action may already be saved. Retry the exact captured request to avoid duplicates; editing cannot undo a saved inquiry.';
        } finally {
            clearTimeout(timeout);
            if (requestGeneration === generation) {
                busy = false;
                controller = null;
            }
        }
    }

    function dateTime(value: string | null | undefined): string | null {
        if (!value) return null;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    }
</script>

{#if unavailable}
    <section class="rounded-xl border border-border bg-card p-6" aria-labelledby="office-inquiries-unavailable">
        <h1 id="office-inquiries-unavailable" class="text-2xl font-semibold tracking-tight">Office inquiries</h1>
        <p class="mt-2 text-sm text-muted-foreground" role="status">{unavailable.message}</p>
    </section>
{:else}
<div class="min-w-0 space-y-5">
    {#if !embedded}
        <header class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">{staff_mode ? `${office_label} inquiries` : 'Office inquiries'}</h1>
                <p class="text-sm text-muted-foreground">{staff_mode ? 'Applicant inquiries assigned to your office. Open one conversation to reply or resolve it.' : 'Ask the Registrar or Guidance Office about your application. Select an inquiry to read its replies.'}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" class="min-h-11" disabled={busy || navigationLoading} onclick={refreshTruth}>{navigationLoading ? 'Loading…' : 'Refresh page'}</Button>
                {#if !staff_mode && urls.create}
                    <Button type="button" class="min-h-11" disabled={busy || (!captured && destinations.length === 0)} onclick={beginInquiry}>New inquiry</Button>
                {/if}
            </div>
        </header>

        {#if !staff_mode && destinations.length === 0}
            <p class="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground" role="status">No office is available for a new inquiry right now. Existing conversations remain listed below. Refresh to check availability.</p>
        {/if}
        {#if navigationLoading}<p role="status" class="text-sm text-muted-foreground">Loading the current saved state…</p>{/if}
    {/if}

    {#if initial}
        <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card/70 px-3 py-2">
            {#if !embedded}
                <Link href={return_url ?? urls.return ?? urls.index} class="inline-flex min-h-11 items-center rounded-md text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring">Back to inquiry list</Link>
            {:else}
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="min-w-0 truncate text-sm font-medium text-foreground">{initial.thread.office_label ?? office_label}</span>
                    {#if staff_mode && initial.thread.applicant}
                        <Badge variant={initial.thread.is_pre_application ? 'secondary' : 'outline'} class="text-[11px]">
                            {initial.thread.is_pre_application ? `Public · ${initial.thread.applicant.reference_code}` : `Applicant · ${initial.thread.applicant.reference_code}`}
                        </Badge>
                    {:else if initial.thread.public_reference_code}
                        <Badge variant="outline" class="text-[11px]">{initial.thread.public_reference_code}</Badge>
                    {/if}
                </div>
            {/if}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {#if staff_mode && urls.resolve && initial.thread.status === 'open'}
                    <Button type="button" variant="outline" class="min-h-9 px-3 text-xs" disabled={busy || navigationLoading} onclick={beginResolve}>Resolve inquiry</Button>
                {/if}
                {#if embedded && !staff_mode && urls.create}
                    <Button type="button" variant="outline" class="min-h-9 px-3 text-xs" disabled={busy || (!captured && destinations.length === 0)} onclick={beginInquiry}>New inquiry</Button>
                {/if}
            </div>
        </div>
        {#if urls.snapshot && urls.send && urls.read}
            <CaseConversation
                {initial}
                snapshotUrl={urls.snapshot}
                sendUrl={urls.send}
                readUrl={urls.read}
                principalContext={principal_key}
                attemptContext={attempt_key}
            />
        {:else}
            <p role="alert" class="rounded-lg border border-border p-4 text-sm">Conversation URLs are unavailable. Refresh the page to check access.</p>
        {/if}
        {#if feedbackOperation === 'resolve' && feedback && !resolveOpen}
            <p role="status" class="text-sm text-muted-foreground">{feedback}</p>
        {/if}
    {:else if !embedded}
        <section aria-label={staff_mode ? 'Office inquiry queue' : 'Your inquiries'} aria-busy={navigationLoading} class="min-w-0 overflow-hidden rounded-xl border border-border bg-card">
            {#if inquiries.length === 0}
                <div class="space-y-2 p-6 text-center">
                    <h2 class="text-base font-semibold">{staff_mode ? 'No inquiries in this office queue' : 'No inquiries on this page'}</h2>
                    <p class="text-sm text-muted-foreground">{staff_mode ? 'Applicant inquiries for your office will appear here.' : 'Start a new inquiry when you have an application question for an available office.'}</p>
                </div>
            {:else}
                <ul class="divide-y divide-border">
                    {#each inquiries as inquiry (inquiry.id)}
                        <li>
                            <Link href={inquiry.show_url} class="flex min-w-0 flex-col gap-2 p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring focus-visible:-outline-offset-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium [overflow-wrap:anywhere]">{inquiry.subject}</p>
                                        {#if staff_mode && inquiry.applicant}
                                            <Badge variant={inquiry.is_pre_application ? 'secondary' : 'outline'} class="text-[11px]">
                                                {inquiry.is_pre_application ? `Public · ${inquiry.applicant.reference_code}` : `Applicant · ${inquiry.applicant.reference_code}`}
                                            </Badge>
                                        {:else if inquiry.public_reference_code}
                                            <Badge variant="outline" class="text-[11px]">{inquiry.public_reference_code}</Badge>
                                        {/if}
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {#if staff_mode && inquiry.applicant}
                                            {inquiry.applicant.display_name} · 
                                        {/if}
                                        {inquiry.office_label}
                                        {#if (inquiry.latest_message_at ?? inquiry.updated_at) && dateTime(inquiry.latest_message_at ?? inquiry.updated_at)}
                                            · <time datetime={inquiry.latest_message_at ?? inquiry.updated_at}>{dateTime(inquiry.latest_message_at ?? inquiry.updated_at)}</time>
                                        {/if}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    {#if inquiry.unread_count > 0}<Badge class="">{inquiry.unread_count} unread</Badge>{/if}
                                    <Badge class="" variant={inquiry.status === 'open' ? 'outline' : 'secondary'}>{inquiry.status === 'open' ? 'Open' : 'Closed'}</Badge>
                                    <span class="text-sm text-primary">View inquiry</span>
                                </div>
                            </Link>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>
        {#if next_cursor}
            <Button type="button" variant="outline" class="min-h-11" disabled={navigationLoading || busy} onclick={olderInquiries}>Older inquiries</Button>
        {/if}
    {:else}
        <div class="rounded-xl border border-dashed border-border p-8 text-center">
            <p class="text-sm text-muted-foreground">Select an inquiry to view its conversation.</p>
            {#if !staff_mode && urls.create}
                <div class="mt-4">
                    <Button type="button" class="min-h-11" disabled={busy || (!captured && destinations.length === 0)} onclick={beginInquiry}>New inquiry</Button>
                </div>
            {/if}
        </div>
    {/if}
</div>
{/if}

<Dialog.Root bind:open={newOpen}>
    <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl" portalProps={{}}>
        <Dialog.Header class="">
            <Dialog.Title class="">{captured ? 'Review inquiry before sending' : 'New office inquiry'}</Dialog.Title>
            <Dialog.Description class="">{captured ? 'Only the office, subject and message shown below will be sent. No Xiam conversation is forwarded.' : 'Choose an office and write your own message. You will review it before sending.'}</Dialog.Description>
        </Dialog.Header>
        {#if captured}
            <dl class="min-w-0 space-y-4 text-sm">
                <div><dt class="text-muted-foreground">Office</dt><dd class="font-medium">{destinations.find((destination: { code: string; label: string }) => destination.code === captured?.office)?.label ?? captured.office}</dd></div>
                <div><dt class="text-muted-foreground">Subject</dt><dd class="font-medium [overflow-wrap:anywhere]">{captured.subject}</dd></div>
                <div><dt class="text-muted-foreground">Message</dt><dd class="max-h-64 overflow-y-auto rounded-md border border-border bg-muted/20 p-3 [overflow-wrap:anywhere] whitespace-pre-wrap">{captured.body}</dd></div>
            </dl>
            {#if attemptedCreate}<p class="text-sm text-muted-foreground">The exact message and retry identity are retained. A lost response does not mean the inquiry was not saved.</p>{/if}
            {#if abandonConfirmed}<p role="alert" class="text-sm text-destructive">Editing stops retrying this request. It does not undo a saved inquiry, and sending an edited draft may create a second inquiry. Check the inquiry list first if the outcome is uncertain.</p>{/if}
            {#if feedbackOperation === 'create' && feedback}<p role="alert" class="text-sm text-foreground">{feedback}</p>{/if}
            {#each Object.entries(fieldErrors) as [field, error]}<p class="text-sm text-destructive">{field}: {error}</p>{/each}
            {#if retryBlocked}<p role="status" class="text-sm text-muted-foreground">Retry available after {retryAt}. Sending will still require your click.</p>{/if}
            <Dialog.Footer class="">
                <Button type="button" variant="outline" class="min-h-11" disabled={busy} onclick={editInquiry}>{abandonConfirmed ? 'Confirm edit as new draft' : 'Edit draft'}</Button>
                {#if failure === 'conflict' || failure === 'denied' || failure === 'session'}
                    <Button type="button" variant="outline" class="min-h-11" disabled={busy || navigationLoading} onclick={refreshTruth}>Refresh saved state</Button>
                {/if}
                <Button type="button" class="min-h-11" disabled={cannotRetry || navigationLoading} onclick={() => void submit('create')}>{busy ? 'Sending…' : attemptedCreate ? 'Retry exact inquiry' : 'Send inquiry'}</Button>
            </Dialog.Footer>
        {:else}
            <form class="space-y-4" onsubmit={reviewInquiry}>
                <div class="space-y-2" role="group" aria-label="Common inquiry topics">
                    <p class="text-sm font-medium">
                        Common topics <span class="text-xs font-normal text-muted-foreground">(auto-selects the receiving office, or edit below)</span>
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        {#each presetOptions as preset (preset.key)}
                            <button
                                type="button"
                                aria-pressed={selectedPresetKey === preset.key}
                                data-preset-topic={preset.key}
                                class="min-h-9 rounded-full border px-3 py-1.5 text-left text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring {selectedPresetKey === preset.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-muted/30 text-foreground hover:bg-muted'}"
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
                        <label for="inquiry-office" class="text-sm font-medium">Office</label>
                        {#if autoRoutedOffice && office}
                            <span class="text-xs font-medium text-primary" data-auto-routed-notice>Auto-selected from topic — you can change this</span>
                        {/if}
                    </div>
                    <select id="inquiry-office" bind:value={office} onchange={() => { autoRoutedOffice = false; }} required class="flex min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring" aria-invalid={!!fieldErrors.office} aria-describedby={fieldErrors.office ? 'inquiry-office-error' : undefined}>
                        <option value="" disabled>Choose an office</option>
                        {#each availableDestinations as destination (destination.code)}<option value={destination.code}>{destination.label}</option>{/each}
                    </select>
                    {#if fieldErrors.office}<p id="inquiry-office-error" class="text-sm text-destructive">{fieldErrors.office}</p>{/if}
                </div>
                <div class="space-y-1">
                    <label for="inquiry-subject" class="text-sm font-medium">Subject</label>
                    <Input id="inquiry-subject" type="text" class="w-full" disabled={false} bind:value={subject} required maxlength={120} aria-invalid={!!fieldErrors.subject} aria-describedby="inquiry-subject-help" />
                    <p id="inquiry-subject-help" class="text-xs text-muted-foreground">{subjectLength}/120 characters</p>
                    {#if fieldErrors.subject}<p role="alert" class="text-sm text-destructive">{fieldErrors.subject}</p>{/if}
                    {#if suggestedFaq}
                        <aside class="border-border/80 bg-muted/30 mt-3 space-y-3 rounded-xl border p-4" aria-label="Suggested FAQ answer">
                            <p class="text-muted-foreground whitespace-pre-line break-words text-sm leading-6"><span class="text-foreground font-semibold">Helpful answer: {suggestedFaq.question} — </span>{faqAnswerExcerpt(suggestedFaq)}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <Link href={`/faqs#${suggestedFaq.slug}`} class="text-primary inline-flex min-h-[44px] items-center text-sm font-medium underline underline-offset-4">View FAQ</Link>
                                <Button type="button" variant="outline" class="min-h-[44px]" disabled={false} onclick={() => dismissedFaqId = suggestedFaq?.id ?? null}>Proceed with ticket</Button>
                            </div>
                        </aside>
                    {/if}
                </div>
                <div class="space-y-1">
                    <label for="inquiry-body" class="text-sm font-medium">Message</label>
                    <Textarea id="inquiry-body" bind:value={body} required rows={6} maxlength={4000} class="min-h-32 resize-y" disabled={false} aria-invalid={!!fieldErrors.body} aria-describedby="inquiry-body-help" />
                    <p id="inquiry-body-help" class="text-xs text-muted-foreground">{bodyLength}/4,000 characters. Enter adds a new line. Use synthetic details only; never include credentials or clinical information.</p>
                    {#if fieldErrors.body}<p role="alert" class="text-sm text-destructive">{fieldErrors.body}</p>{/if}
                </div>
                <Dialog.Footer class="">
                    <Button type="button" variant="outline" class="min-h-11" disabled={false} onclick={() => { newOpen = false; }}>Cancel</Button>
                    <Button type="submit" class="min-h-11" disabled={destinations.length === 0}>Review inquiry</Button>
                </Dialog.Footer>
            </form>
        {/if}
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={resolveOpen}>
    <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-lg" portalProps={{}}>
        <Dialog.Header class="">
            <Dialog.Title class="">Resolve this inquiry?</Dialog.Title>
            <Dialog.Description class="">Closing the inquiry stops new replies. Its history remains readable. This action does not change the applicant's admission status or grant a policy exception.</Dialog.Description>
        </Dialog.Header>
        <p class="font-medium [overflow-wrap:anywhere]">{initial?.thread.subject}</p>
        <p class="text-sm text-muted-foreground">{initial?.thread.office_label ?? office_label}. There is no reopen action.</p>
        {#if feedbackOperation === 'resolve' && feedback}<p role="alert" class="text-sm">{feedback}</p>{/if}
        {#if retryBlocked}<p role="status" class="text-sm text-muted-foreground">Retry available after {retryAt}. The same confirmation identity will be reused.</p>{/if}
        <Dialog.Footer class="">
            <Button type="button" variant="outline" class="min-h-11" disabled={busy} onclick={() => { resolveOpen = false; }}>Back to inquiry</Button>
            {#if failure === 'conflict' || failure === 'denied' || failure === 'session'}
                <Button type="button" variant="outline" class="min-h-11" disabled={busy || navigationLoading} onclick={refreshTruth}>Refresh saved state</Button>
            {/if}
            <Button type="button" class="min-h-11" disabled={cannotRetry || navigationLoading || !resolveCapture} onclick={() => void submit('resolve')}>{busy ? 'Resolving…' : failure === 'idle' ? 'Confirm resolve' : 'Retry exact resolve'}</Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
