<script>
    import { onMount, untrack } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { Plus } from 'lucide-svelte';
    import { Button } from '@/Components/ui/button';
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import { governanceRoute, governanceRequest, governanceError, isUncertainCommandOutcome } from '@/lib/governanceRequests';
    import { newIdempotencyKey } from '@/lib/utils';

    let {
        programs = [],
        cycleCode = '',
        principalContext = '',
        attempt = null,
        initialProgramCode = '',
        initialAudience = 'internal_ph_guidance',
        allowApplicantSupport = true,
        applicantLookupUrl = null,
        destination = 'messages',
        destinationUrl = '',
        disabled = false,
    } = $props();

    let dialogOpen = $state(false);
    let programCode = $state('');
    let audience = $state('internal_ph_guidance');
    let searchText = $state('');
    let applicants = $state([]);
    let hasMoreApplicants = $state(false);
    let selectedApplicantId = $state('');
    let subject = $state('');
    let body = $state('');
    let draftResetNotice = $state('');
    let error = $state('');
    let discoveryBusy = $state(false);
    let discoveryError = $state('');
    let submitting = $state(false);
    let pendingOpening = $state(null);
    let abandonConfirmation = $state(false);
    let accessUnavailable = $state(false);
    let lookupSequence = 0;
    let lookupController = null;
    let activeOpeningController = null;
    let lastContextKey = null;
    let lastDisabled = null;

    const contextKey = $derived([
        principalContext ?? '',
        cycleCode ?? '',
        attempt?.id ?? '',
        destination,
        applicantLookupUrl ?? '',
        allowApplicantSupport ? 'applicant' : 'staff-only',
        (programs ?? []).map((program) => program.code).join(','),
    ].join(':'));
    const selectedProgram = $derived((programs ?? []).find((program) => program.code === programCode) ?? null);
    const selectedApplicant = $derived(applicants.find((applicant) => applicant.id === selectedApplicantId) ?? null);
    const captured = $derived(Boolean(pendingOpening));
    const locked = $derived(submitting || captured || disabled || accessUnavailable);
    const hasApplicantTarget = $derived(Boolean(attempt?.id || selectedApplicant));
    const canSubmit = $derived(Boolean(
        !locked
        && selectedProgram
        && cycleCode
        && hasApplicantTarget
        && subject.trim()
        && body.trim()
        && (audience === 'internal_ph_guidance' || (audience === 'applicant_support' && allowApplicantSupport)),
    ));
    const audienceValue = $derived(
        allowApplicantSupport && initialAudience === 'applicant_support'
            ? 'applicant_support'
            : 'internal_ph_guidance',
    );

    $effect(() => {
        const choices = programs ?? [];
        const requestedCode = initialProgramCode;
        untrack(() => {
            if (!choices.some((program) => program.code === programCode)) {
                const validated = choices.find((program) => program.code === requestedCode);
                programCode = validated?.code ?? (choices.length === 1 ? choices[0].code : '');
            }
        });
    });

    $effect(() => {
        const nextAudience = audienceValue;
        untrack(() => {
            if (pendingOpening) return;
            if (audience !== nextAudience) {
                audience = nextAudience;
                clearDraft('Audience changed. The unsent subject and message were cleared.');
            }
        });
    });

    $effect(() => {
        const nextContext = contextKey;
        const disabledNow = disabled;
        const unavailable = disabledNow || !(programs ?? []).length;
        untrack(() => {
            const contextChanged = lastContextKey !== null && lastContextKey !== nextContext;
            const disabledChanged = lastDisabled !== null && lastDisabled !== disabledNow;
            if (contextChanged || unavailable) {
                resetProtectedState(true);
                accessUnavailable = unavailable;
                if (!unavailable) {
                    const choices = programs ?? [];
                    const requested = choices.find((program) => program.code === initialProgramCode);
                    programCode = requested?.code ?? (choices.length === 1 ? choices[0].code : '');
                }
            } else if (disabledChanged) {
                accessUnavailable = false;
                const choices = programs ?? [];
                const requested = choices.find((program) => program.code === initialProgramCode);
                programCode = requested?.code ?? (choices.length === 1 ? choices[0].code : '');
            }
            lastContextKey = nextContext;
            lastDisabled = disabledNow;
        });
    });

    function clearDraft(message = '') {
        subject = '';
        body = '';
        draftResetNotice = message;
        error = '';
    }

    function cancelLookup() {
        lookupSequence += 1;
        lookupController?.abort();
        lookupController = null;
        discoveryBusy = false;
    }

    function resetProtectedState(clearProgram = false) {
        cancelLookup();
        activeOpeningController?.abort();
        activeOpeningController = null;
        submitting = false;
        pendingOpening = null;
        abandonConfirmation = false;
        dialogOpen = false;
        applicants = [];
        hasMoreApplicants = false;
        selectedApplicantId = '';
        searchText = '';
        discoveryError = '';
        error = '';
        subject = '';
        body = '';
        draftResetNotice = '';
        if (clearProgram) {
            programCode = '';
        }
    }

    function changeProgram(nextProgramCode) {
        if (locked || nextProgramCode === programCode) return;
        cancelLookup();
        programCode = nextProgramCode;
        applicants = [];
        hasMoreApplicants = false;
        selectedApplicantId = '';
        searchText = '';
        discoveryError = '';
        clearDraft('Program changed. Applicant results, selection, subject, and message were cleared. Search again for eligible applicants.');
    }

    function changeApplicant(nextApplicantId) {
        if (locked || nextApplicantId === selectedApplicantId) return;
        selectedApplicantId = nextApplicantId;
        clearDraft('Applicant changed. The unsent subject and message were cleared.');
    }

    function changeAudience(nextAudience) {
        if (locked || nextAudience === audience) return;
        if (nextAudience !== 'internal_ph_guidance' && !(nextAudience === 'applicant_support' && allowApplicantSupport)) return;
        audience = nextAudience;
        clearDraft('Audience changed. The unsent subject and message were cleared.');
    }

    async function findApplicants() {
        if (locked || attempt || !applicantLookupUrl || !selectedProgram || !cycleCode) return;
        cancelLookup();
        const sequence = lookupSequence;
        const capturedContext = contextKey;
        const capturedProgramCode = selectedProgram.code;
        const controller = new AbortController();
        lookupController = controller;
        discoveryBusy = true;
        discoveryError = '';
        applicants = [];
        hasMoreApplicants = false;
        if (selectedApplicantId) {
            selectedApplicantId = '';
            clearDraft('Applicant search changed. The previous applicant and unsent subject and message were cleared.');
        } else {
            selectedApplicantId = '';
        }

        const url = new URL(applicantLookupUrl, window.location.origin);
        url.searchParams.set('program_code', capturedProgramCode);
        url.searchParams.set('cycle_code', cycleCode);
        if (searchText.trim()) url.searchParams.set('search', searchText.trim());

        try {
            const result = await governanceRequest(url.toString(), undefined, controller.signal);
            if (sequence !== lookupSequence || capturedContext !== contextKey || capturedProgramCode !== programCode || disabled || accessUnavailable) return;
            if (result?.program_code !== capturedProgramCode || result?.cycle_code !== cycleCode || !Array.isArray(result?.data)) {
                accessUnavailable = true;
                resetProtectedState(true);
                discoveryError = 'Applicant discovery context is no longer available. Reload Messages before continuing.';
                return;
            }
            const safeResults = result.data.filter((applicant) =>
                typeof applicant?.id === 'string'
                && typeof applicant?.name === 'string'
                && typeof applicant?.reference_code === 'string'
                && applicant?.cycle_code === cycleCode,
            );
            applicants = safeResults;
            hasMoreApplicants = result.has_more === true;
            if (!safeResults.length) discoveryError = 'No eligible applicants matched this search in the selected program and academic year.';
        } catch (failure) {
            if (sequence !== lookupSequence || capturedContext !== contextKey) return;
            if ([401, 403, 404, 419].includes(failure?.status)) {
                resetProtectedState(true);
                accessUnavailable = true;
                discoveryError = 'Applicant discovery is no longer available. Reload Messages before continuing.';
            } else {
                discoveryError = governanceError(failure);
            }
        } finally {
            if (sequence === lookupSequence) {
                discoveryBusy = false;
                lookupController = null;
            }
        }
    }

    function openingUrl(program, targetId, threadType) {
        return governanceRoute(
            threadType === 'applicant_support' ? 'case-conversations.applicant.open' : 'program-head.threads.open',
            { program, attempt: targetId },
        );
    }

    function isCurrentOpening(operation) {
        return pendingOpening?.payload?.idempotency_key === operation.payload.idempotency_key
            && operation.context === contextKey
            && !disabled
            && !accessUnavailable;
    }

    function destinationHref(operation, threadId) {
        const target = new URL(destinationUrl, window.location.origin);
        if (destination === 'case') {
            target.searchParams.set('tab', 'threads');
        } else {
            target.searchParams.set('group', 'case_threads');
            target.searchParams.delete('inquiry_id');
            target.searchParams.delete('office_search');
            target.searchParams.delete('office_cursor');
            target.searchParams.delete('case_search');
            target.searchParams.delete('case_cursor');
        }
        target.searchParams.set('program_code', operation.programCode);
        target.searchParams.set('cycle_code', operation.cycleCode);
        target.searchParams.set('thread_id', String(threadId));
        target.searchParams.set('thread_type', operation.threadType);
        return `${target.pathname}${target.search}`;
    }

    async function submitOpening() {
        if (submitting || disabled || accessUnavailable) return;
        let operation = pendingOpening;
        if (!operation) {
            const targetId = attempt?.id ? String(attempt.id) : selectedApplicant?.id;
            if (!selectedProgram || !targetId || !subject.trim() || !body.trim() || !cycleCode) return;
            const threadType = audience;
            operation = {
                context: contextKey,
                programCode: selectedProgram.code,
                cycleCode,
                applicantId: targetId,
                threadType,
                url: openingUrl(selectedProgram.code, targetId, threadType),
                payload: {
                    subject: subject.trim(),
                    body: body.trim(),
                    idempotency_key: newIdempotencyKey(),
                },
            };
            pendingOpening = operation;
        }
        if (!isCurrentOpening(operation)) {
            resetProtectedState(true);
            accessUnavailable = true;
            return;
        }

        const controller = new AbortController();
        activeOpeningController = controller;
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        submitting = true;
        error = '';

        try {
            const receipt = await governanceRequest(operation.url, operation.payload, controller.signal);
            if (!isCurrentOpening(operation)) return;
            if (!receipt?.thread?.id || receipt?.message?.body !== operation.payload.body) {
                throw new Error('The opening receipt could not be confirmed. Retry the same opening.');
            }

            const href = destinationHref(operation, receipt.thread.id);
            pendingOpening = null;
            submitting = false;
            activeOpeningController = null;
            dialogOpen = false;
            subject = '';
            body = '';
            draftResetNotice = '';
            router.get(href, {}, { preserveState: true, preserveScroll: true });
        } catch (failure) {
            if (!isCurrentOpening(operation)) return;
            if ([401, 403, 404, 419].includes(failure?.status)) {
                resetProtectedState(true);
                accessUnavailable = true;
                error = 'This case or staff session is no longer available. Reload before continuing.';
            } else {
                if (!isUncertainCommandOutcome(failure)) pendingOpening = null;
                error = failure?.name === 'AbortError'
                    ? 'The request timed out or the connection closed. It may have committed; retry the same opening.'
                    : governanceError(failure);
            }
        } finally {
            window.clearTimeout(timeout);
            if (activeOpeningController === controller) {
                activeOpeningController = null;
                submitting = false;
            }
        }
    }

    function abandonOpening() {
        if (submitting) return;
        pendingOpening = null;
        subject = '';
        body = '';
        error = '';
        draftResetNotice = '';
        abandonConfirmation = false;
        dialogOpen = false;
    }

    function handleDialogChange(open) {
        if (!open && (submitting || pendingOpening)) {
            error = 'Retry the captured opening, or explicitly abandon it before closing.';
            return;
        }
        dialogOpen = open;
        if (open) {
            error = '';
        }
    }

    function handleSubmit(event) {
        event.preventDefault();
        submitOpening();
    }
    function openStarter() {
        if (locked) return;
        if (audience !== audienceValue) {
            audience = audienceValue;
            clearDraft('Audience changed. The unsent subject and message were cleared.');
        }
        dialogOpen = true;
        error = '';
    }

    onMount(() => {
        const removeBefore = router.on('before', (event) => {
            if (!submitting && !pendingOpening) return;
            event.preventDefault();
            error = 'Finish or explicitly abandon this opening before leaving this page.';
            dialogOpen = true;
        });
        return () => {
            removeBefore();
            lookupController?.abort();
            activeOpeningController?.abort();
        };
    });
</script>

<div class="inline-flex flex-col items-start gap-2">
    <Button size="sm" variant="outline" disabled={locked || !(programs ?? []).length} onclick={openStarter}>
        <Plus class="mr-1.5 size-3.5" /> Start conversation
    </Button>
    {#if accessUnavailable && !disabled}
        <p role="status" class="max-w-sm text-xs text-muted-foreground">Conversation access changed. Reload this page before starting another conversation.</p>
    {/if}
</div>

<Dialog open={dialogOpen} onOpenChange={handleDialogChange}>
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
            <DialogTitle>Start conversation</DialogTitle>
            <DialogDescription>
                {#if attempt}
                    Start a conversation about {attempt.name ?? attempt.reference_code ?? 'this applicant'}. The two audiences remain separate.
                {:else}
                    Choose an academic program and eligible applicant before writing. The two audiences remain separate.
                {/if}
            </DialogDescription>
        </DialogHeader>

        <form onsubmit={handleSubmit} class="space-y-4 py-2">
            <div class="space-y-1.5">
                <Label for="case-conversation-program">Degree program</Label>
                {#if (programs ?? []).length > 1}
                    <select
                        id="case-conversation-program"
                        value={programCode}
                        onchange={(event) => changeProgram(event.currentTarget.value)}
                        disabled={locked}
                        required
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <option value="">Select a degree program…</option>
                        {#each programs as program (program.code)}
                            <option value={program.code}>[{program.code}] {program.name}</option>
                        {/each}
                    </select>
                {:else if selectedProgram}
                    <p class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm font-medium">[{selectedProgram.code}] {selectedProgram.name}</p>
                {:else}
                    <p role="status" class="text-sm text-muted-foreground">No authorized academic program is available for starting a conversation.</p>
                {/if}
            </div>

            {#if attempt}
                <div class="space-y-1.5">
                    <Label>Applicant</Label>
                    <p class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
                        <span class="font-medium">{attempt.name ?? 'Applicant'}</span>
                        {#if attempt.reference_code}<span class="ml-2 font-mono text-xs text-muted-foreground">{attempt.reference_code}</span>{/if}
                    </p>
                </div>
            {:else}
                <div class="space-y-2">
                    <Label for="case-conversation-applicant-search">Find eligible applicant</Label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Input
                            id="case-conversation-applicant-search"
                            bind:value={searchText}
                            disabled={locked || discoveryBusy || !selectedProgram || !applicantLookupUrl}
                            placeholder="Search by applicant name or reference"
                            onkeydown={(event) => { if (event.key === 'Enter') { event.preventDefault(); findApplicants(); } }}
                        />
                        <Button type="button" variant="outline" disabled={locked || discoveryBusy || !selectedProgram || !applicantLookupUrl} onclick={findApplicants}>
                            {discoveryBusy ? 'Searching…' : 'Search applicants'}
                        </Button>
                    </div>
                    {#if applicants.length}
                        <div class="space-y-1.5">
                            <Label for="case-conversation-applicant">Eligible applicants</Label>
                            <select
                                id="case-conversation-applicant"
                                value={selectedApplicantId}
                                onchange={(event) => changeApplicant(event.currentTarget.value)}
                                disabled={locked}
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <option value="">Select an applicant…</option>
                                {#each applicants as applicant (applicant.id)}
                                    <option value={applicant.id}>{applicant.name} · {applicant.reference_code}</option>
                                {/each}
                            </select>
                            {#if hasMoreApplicants}
                                <p class="text-xs text-muted-foreground">More eligible applicants may match. Refine the search to find another applicant.</p>
                            {/if}
                        </div>
                    {/if}
                    {#if discoveryBusy}
                        <p role="status" class="text-sm text-muted-foreground">Searching eligible applicants for this program and academic year…</p>
                    {:else if discoveryError}
                        <p role="status" class="text-sm text-muted-foreground">{discoveryError}</p>
                    {/if}
                </div>
            {/if}

            <div class="space-y-1.5">
                <Label for="case-conversation-audience">Conversation audience</Label>
                <select
                    id="case-conversation-audience"
                    value={audience}
                    onchange={(event) => changeAudience(event.currentTarget.value)}
                    disabled={locked}
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <option value="internal_ph_guidance">Staff discussion · Guidance and authorized academic staff only</option>
                    {#if allowApplicantSupport}
                        <option value="applicant_support">With applicant · Applicant-visible conversation</option>
                    {/if}
                </select>
                <p class="text-xs text-muted-foreground">
                    {audience === 'internal_ph_guidance'
                        ? 'The applicant cannot see staff discussion messages.'
                        : 'This conversation is visible to the applicant; private staff discussion stays separate.'}
                </p>
                <p class="text-xs text-muted-foreground">Changing the program, applicant, or audience clears unsent text. Once opening is captured, those choices and the message stay locked for retry.</p>
            </div>

            {#if draftResetNotice}
                <p role="status" class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-sm">{draftResetNotice}</p>
            {/if}

            <div class="space-y-1.5">
                <Label for="case-conversation-subject">Subject / case reason</Label>
                <Input
                    id="case-conversation-subject"
                    bind:value={subject}
                    disabled={locked}
                    oninput={() => (draftResetNotice = '')}
                    maxlength={120}
                    placeholder="e.g. Non-traditional strand review, bridging math requirement"
                    required
                />
            </div>

            <div class="space-y-1.5">
                <Label for="case-conversation-body">First message</Label>
                <Textarea
                    id="case-conversation-body"
                    bind:value={body}
                    disabled={locked}
                    maxlength={4000}
                    oninput={() => (draftResetNotice = '')}
                    placeholder="Write the first message or case discussion notes…"
                    rows={4}
                    required
                />
            </div>

            {#if error}
                <p role="alert" class="text-sm text-destructive">{error}</p>
            {/if}
            {#if pendingOpening && !submitting}
                <div class="space-y-2 rounded-lg border border-border p-3 text-sm">
                    <p>The opening may already have committed. Retry uses the same captured target, audience, message, and operation key.</p>
                    {#if abandonConfirmation}
                        <p class="text-muted-foreground">Abandoning only discards this retry. It does not remove a conversation that already committed; check Messages before starting again.</p>
                        <Button type="button" variant="outline" onclick={abandonOpening}>Abandon this opening</Button>
                    {:else}
                        <Button type="button" variant="ghost" onclick={() => { abandonConfirmation = true; }}>Abandon retry…</Button>
                    {/if}
                </div>
            {/if}

            <DialogFooter>
                <Button type="button" variant="outline" disabled={submitting} onclick={() => handleDialogChange(false)}>Cancel</Button>
                <Button type="submit" disabled={submitting || (!pendingOpening && !canSubmit)}>
                    {submitting ? 'Opening…' : pendingOpening ? 'Retry same opening' : 'Start conversation'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
