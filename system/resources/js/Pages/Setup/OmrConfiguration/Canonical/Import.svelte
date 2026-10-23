<script>
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { AlertCircle, ArrowLeft, CheckCircle2, FileArchive, Loader2, RotateCw, Upload, XCircle } from 'lucide-svelte';

    let { operation: initialOperation, kit_context = null } = $props();
    let operation = $state(initialOperation);
    let isRefreshing = $state(false);
    let busyAction = $state('');
    let actionError = $state('');
    let pollError = $state('');
    let showCancelConfirmation = $state(false);
    let replacementFile = $state(null);
    let recoveryLedger = $state(null);
    let replacementInput = $state(null);
    let ledgerInput = $state(null);
    let replacementProgress = $state(null);
    let pollTimer;

    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup' },
                  { label: 'Test Kits' },
                  { label: kit_context.code },
                  { label: 'Answer Sheet' },
                  { label: 'Import' },
              ]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Answer Sheets', href: '/setup/exam-materials/answer-sheets' },
                  { label: 'Import' },
              ],
    );

    const phaseLabels = {
        queued: 'Upload received',
        checking: 'Checking the archive',
        needs_review: 'Review needed',
        ready_to_restore: 'Ready to restore',
        restoring: 'Restoring Answer Sheet',
        completed: 'Answer Sheet restored',
        failed: 'Could not finish',
        cancelled: 'Import cancelled',
    };

    const phaseDescriptions = {
        queued: 'Your upload is saved privately and waiting for an archive check.',
        checking: 'The archive is being checked before any Answer Sheet is created.',
        needs_review: 'The archive cannot be restored safely as provided. Review the issue below or choose a replacement file.',
        ready_to_restore: 'The archive is valid. Confirm to create one editable Answer Sheet draft.',
        restoring: 'The checked archive is being restored. You can leave this page and return later.',
        completed: 'The editable Answer Sheet draft is ready. No scoring setup, keys, or approval were added.',
        failed: 'The operation stopped before a draft was restored. Retry the check or choose a replacement file.',
        cancelled: 'This operation was cancelled. The uploaded archive remains private and unchanged.',
    };

    function withKitContext(path) {
        if (!kit_context) return path;
        const url = new URL(path, window.location.origin);
        if (url.origin !== window.location.origin) return path;
        url.searchParams.delete('preparation_id');
        url.searchParams.delete('test_kit_version_id');
        url.searchParams.set('test_kit_id', String(kit_context.test_kit_id));
        if (kit_context.preparation_id !== null) {
            url.searchParams.set('preparation_id', String(kit_context.preparation_id));
        }
        if (kit_context.test_kit_version_id !== null) {
            url.searchParams.set('test_kit_version_id', String(kit_context.test_kit_version_id));
        }
        return `${url.pathname}${url.search}${url.hash}`;
    }

    function returnToSheets() {
        if (kit_context?.back_url) {
            router.visit(kit_context.back_url);
            return;
        }
        router.visit('/setup/exam-materials/answer-sheets');
    }

    function isActivePhase(phase) {
        return ['queued', 'checking', 'restoring'].includes(phase);
    }

    async function refreshStatus() {
        if (isRefreshing || !isActivePhase(operation?.phase)) return;
        isRefreshing = true;
        try {
            const response = await fetch(withKitContext(operation.status_url), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) {
                if (response.status === 403 || response.status === 404) {
                    pollError = 'Your access to this import has changed. Return to Answer Sheets and sign in again if needed.';
                    return;
                }
                throw new Error('Could not refresh the import status. Try again shortly.');
            }
            operation = await response.json();
            pollError = '';
        } catch (error) {
            pollError = error?.message || 'Could not refresh the import status.';
        } finally {
            isRefreshing = false;
        }
    }

    onMount(() => {
        pollTimer = window.setInterval(refreshStatus, 2500);
        return () => window.clearInterval(pollTimer);
    });

    $effect(() => {
        operation = initialOperation;
    });

    function confirmRestore() {
        if (!operation?.can_confirm || busyAction) return;
        busyAction = 'confirm';
        actionError = '';
        router.post(
            withKitContext(operation.confirm_url),
            {
                review_revision: operation.review_revision,
                review_token: operation.review_token,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    actionError = errors?.review || errors?.message || 'The review changed. Refresh the report and try again.';
                },
                onFinish: () => {
                    busyAction = '';
                },
            },
        );
    }

    function retryCheck() {
        if (!operation?.can_retry || busyAction) return;
        busyAction = 'retry';
        actionError = '';
        router.post(
            withKitContext(operation.retry_url),
            { generation: operation.generation },
            {
                preserveScroll: true,
                onError: (errors) => {
                    actionError = errors?.operation || errors?.message || 'This import changed. Refresh its status before retrying.';
                },
                onFinish: () => {
                    busyAction = '';
                },
            },
        );
    }

    function cancelImport() {
        if (!operation?.can_cancel || busyAction) return;
        busyAction = 'cancel';
        actionError = '';
        router.post(
            withKitContext(operation.cancel_url),
            { generation: operation.generation },
            {
                preserveScroll: true,
                onError: (errors) => {
                    actionError = errors?.operation || errors?.message || 'The import can no longer be cancelled.';
                },
                onFinish: () => {
                    busyAction = '';
                    showCancelConfirmation = false;
                },
            },
        );
    }

    function handleReplacementSelected(event) {
        replacementFile = event.target?.files?.[0] || null;
        actionError = '';
    }

    function handleLedgerSelected(event) {
        recoveryLedger = event.target?.files?.[0] || null;
    }

    function submitReplacement() {
        if (!replacementFile || !operation?.can_replace || busyAction) return;
        busyAction = 'replacement';
        actionError = '';
        replacementProgress = null;
        const formData = new FormData();
        formData.append('bundle', replacementFile);
        if (recoveryLedger) formData.append('recovery_ledger', recoveryLedger);

        router.post(withKitContext(operation.replacement_url), formData, {
            forceFormData: true,
            preserveScroll: true,
            onProgress: (progress) => {
                const percentage = Number(progress?.percentage);
                if (Number.isFinite(percentage)) {
                    replacementProgress = Math.max(0, Math.min(100, percentage));
                }
            },
            onError: (errors) => {
                actionError = errors?.bundle || errors?.recovery_ledger || errors?.message || 'The replacement upload could not be received.';
            },
            onFinish: () => {
                busyAction = '';
            },
        });
    }

    function formatSize(bytes) {
        const value = Number(bytes || 0);
        return value >= 1024 * 1024
            ? `${(value / (1024 * 1024)).toFixed(1)} MB`
            : `${Math.ceil(value / 1024)} KB`;
    }

    function progressLabel() {
        const progress = operation?.progress;
        if (!progress || !progress.total) return '';
        const unit = progress.unit === 'archive_members' ? 'archive files' : 'items';
        return `${progress.done} of ${progress.total} ${unit} checked`;
    }
</script>

<svelte:head><title>Answer Sheet Import · Exam materials</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto min-w-0 max-w-4xl space-y-6 p-4 sm:p-6">
        <PageHeaderBar title="Import Answer Sheet" description="Check a bundle, review any issues, then restore an editable Answer Sheet draft.">
            {#snippet actions()}
                <Button variant="outline" onclick={returnToSheets}>
                    <ArrowLeft class="mr-2 size-4" />Answer Sheets
                </Button>
            {/snippet}
        </PageHeaderBar>

        <section class="border-border bg-card space-y-5 rounded-2xl border p-5 shadow-sm sm:p-6" aria-labelledby="import-status-title" aria-live="polite">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-xl">
                        {#if isActivePhase(operation?.phase)}
                            <Loader2 class="size-5 animate-spin" aria-hidden="true" />
                        {:else if operation?.phase === 'completed'}
                            <CheckCircle2 class="size-5" aria-hidden="true" />
                        {:else if operation?.phase === 'failed' || operation?.phase === 'needs_review'}
                            <AlertCircle class="size-5" aria-hidden="true" />
                        {:else}
                            <FileArchive class="size-5" aria-hidden="true" />
                        {/if}
                    </div>
                    <div class="min-w-0 space-y-1">
                        <h1 id="import-status-title" class="text-foreground text-lg font-semibold">
                            {phaseLabels[operation?.phase] || 'Answer Sheet import'}
                        </h1>
                        <p class="text-muted-foreground text-sm leading-relaxed">
                            {phaseDescriptions[operation?.phase] || 'Import status is available here after upload.'}
                        </p>
                    </div>
                </div>
                {#if operation?.updated_at}
                    <p class="text-muted-foreground text-xs">Updated {new Date(operation.updated_at).toLocaleString()}</p>
                {/if}
            </div>

            <div class="border-border/70 bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-xl border px-4 py-3">
                <div class="min-w-0">
                    <p class="text-foreground truncate text-sm font-medium">{operation?.filename}</p>
                    <p class="text-muted-foreground text-xs">{formatSize(operation?.upload?.bytes)} · Private upload</p>
                </div>
                {#if isActivePhase(operation?.phase)}
                    <Button variant="ghost" size="sm" disabled={isRefreshing} onclick={refreshStatus}>
                        <RotateCw class={`mr-1.5 size-4 ${isRefreshing ? 'animate-spin' : ''}`} />Refresh
                    </Button>
                {/if}
            </div>

            {#if ['checking', 'restoring'].includes(operation?.phase) && operation?.progress?.total > 0}
                <div class="space-y-2" aria-label="Archive processing progress">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-muted-foreground">{operation.phase === 'checking' ? 'Checking files' : 'Restoring files'}</span>
                        <span class="text-foreground font-medium">{progressLabel()}</span>
                    </div>
                    <progress class="bg-muted h-2 w-full overflow-hidden rounded-full accent-primary" value={operation.progress.done} max={operation.progress.total}></progress>
                </div>
            {/if}

            {#if pollError || actionError || operation?.safe_error}
                <div class="flex items-start gap-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-200" role="alert">
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{pollError || actionError || operation.safe_error}</span>
                </div>
            {/if}
        </section>

        {#if operation?.report?.summary && Object.keys(operation.report.summary).length > 0}
            <section class="border-border bg-card space-y-4 rounded-2xl border p-5 shadow-sm sm:p-6" aria-labelledby="import-summary-title">
                <h2 id="import-summary-title" class="text-foreground text-base font-semibold">Archive summary</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="bg-muted/30 rounded-xl p-3"><p class="text-muted-foreground text-xs">Pages</p><p class="text-foreground mt-1 text-lg font-semibold">{operation.report.summary.page_count ?? '—'}</p></div>
                    <div class="bg-muted/30 rounded-xl p-3"><p class="text-muted-foreground text-xs">Recognition guides</p><p class="text-foreground mt-1 text-lg font-semibold">{operation.report.summary.recognition_guide_count ?? '—'}</p></div>
                    {#if operation.report.summary.printed_section_count !== null}
                        <div class="bg-muted/30 rounded-xl p-3"><p class="text-muted-foreground text-xs">Printed sections</p><p class="text-foreground mt-1 text-lg font-semibold">{operation.report.summary.printed_section_count}</p></div>
                    {/if}
                    {#if operation.report.summary.physical_item_count !== null}
                        <div class="bg-muted/30 rounded-xl p-3"><p class="text-muted-foreground text-xs">Physical locations</p><p class="text-foreground mt-1 text-lg font-semibold">{operation.report.summary.physical_item_count}</p></div>
                    {/if}
                </div>
            </section>
        {/if}

        {#if operation?.report?.issues?.length > 0}
            <section class="border-border bg-card space-y-4 rounded-2xl border p-5 shadow-sm sm:p-6" aria-labelledby="import-issues-title">
                <h2 id="import-issues-title" class="text-foreground text-base font-semibold">What needs attention</h2>
                <ul class="space-y-3">
                    {#each operation.report.issues as issue (issue.id)}
                        <li class="rounded-xl border border-rose-500/25 bg-rose-500/5 p-4">
                            <div class="flex items-start gap-2.5">
                                <XCircle class="text-rose-600 mt-0.5 size-4 shrink-0 dark:text-rose-300" />
                                <div class="min-w-0 space-y-1.5">
                                    <p class="text-foreground text-sm font-medium">{issue.cause}</p>
                                    {#if issue.page_no}<p class="text-muted-foreground text-xs">Page {issue.page_no}</p>{/if}
                                    <p class="text-muted-foreground text-sm leading-relaxed">{issue.remedy}</p>
                                </div>
                            </div>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}

        {#if operation?.report?.notes?.length > 0}
            <section class="border-border bg-card space-y-3 rounded-2xl border p-5 shadow-sm sm:p-6" aria-labelledby="import-notes-title">
                <h2 id="import-notes-title" class="text-foreground text-base font-semibold">Archive notes</h2>
                <ul class="space-y-3">
                    {#each operation.report.notes as note (note.code)}
                        <li class="flex items-start gap-2.5">
                            <CheckCircle2 class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                            <div class="space-y-1">
                                <p class="text-foreground text-sm">{note.cause}</p>
                                <p class="text-muted-foreground text-xs leading-relaxed">{note.action}</p>
                            </div>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}

        {#if operation?.phase === 'completed' && operation?.result}
            <section class="border-primary/20 bg-primary/5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-5 sm:p-6" aria-labelledby="import-result-title">
                <div class="space-y-1">
                    <h2 id="import-result-title" class="text-foreground font-semibold">Draft ready</h2>
                    <p class="text-muted-foreground text-sm">{operation.result.name} · {operation.result.page_count} {operation.result.page_count === 1 ? 'page' : 'pages'}</p>
                </div>
                <a href={withKitContext(operation.result.edit_url)} class="bg-primary text-primary-foreground inline-flex min-h-10 items-center rounded-lg px-4 text-sm font-medium shadow-sm transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    Open Answer Sheet
                </a>
            </section>
        {/if}

        <section class="border-border bg-card flex flex-wrap items-center gap-2 rounded-2xl border p-4 shadow-sm sm:p-5" aria-label="Import actions">
            {#if operation?.can_confirm}
                <Button disabled={busyAction !== ''} onclick={confirmRestore}>
                    {#if busyAction === 'confirm'}<Loader2 class="mr-2 size-4 animate-spin" />{/if}
                    Restore Answer Sheet
                </Button>
            {/if}
            {#if operation?.can_retry}
                <Button variant="outline" disabled={busyAction !== ''} onclick={retryCheck}>
                    {#if busyAction === 'retry'}<Loader2 class="mr-2 size-4 animate-spin" />{:else}<RotateCw class="mr-2 size-4" />{/if}
                    Retry check
                </Button>
            {/if}
            {#if operation?.can_cancel && !showCancelConfirmation}
                <Button variant="ghost" disabled={busyAction !== ''} onclick={() => (showCancelConfirmation = true)}>
                    Cancel import
                </Button>
            {/if}
            {#if operation?.can_cancel && showCancelConfirmation}
                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/5 p-2" role="group" aria-label="Confirm import cancellation">
                    <span class="text-muted-foreground px-2 text-sm">Cancel this import?</span>
                    <Button variant="outline" size="sm" disabled={busyAction !== ''} onclick={() => (showCancelConfirmation = false)}>Keep it</Button>
                    <Button variant="destructive" size="sm" disabled={busyAction !== ''} onclick={cancelImport}>Yes, cancel</Button>
                </div>
            {/if}
            {#if operation?.can_replace}
                <Button variant="outline" disabled={busyAction !== ''} onclick={() => replacementInput?.click()}>
                    <Upload class="mr-2 size-4" />Choose replacement
                </Button>
                <input bind:this={replacementInput} type="file" accept=".zip" class="hidden" onchange={handleReplacementSelected} />
            {/if}
            {#if replacementFile}
                <div class="flex w-full flex-wrap items-center gap-2 rounded-xl border border-border p-3 sm:w-auto">
                    <span class="max-w-56 truncate text-xs font-medium">{replacementFile.name}</span>
                    <Button variant="ghost" size="sm" onclick={() => (ledgerInput?.click())}>
                        {recoveryLedger ? recoveryLedger.name : 'Add recovery notes'}
                    </Button>
                    <input bind:this={ledgerInput} type="file" accept=".json,application/json" class="hidden" onchange={handleLedgerSelected} />
                    <Button size="sm" disabled={busyAction !== ''} onclick={submitReplacement}>
                        {#if busyAction === 'replacement'}<Loader2 class="mr-1.5 size-4 animate-spin" />{:else}<Upload class="mr-1.5 size-4" />{/if}
                        Upload replacement
                    </Button>
                    {#if replacementProgress !== null}<span class="text-muted-foreground text-xs">{replacementProgress}% uploaded</span>{/if}
                </div>
            {/if}
        </section>
    </div>
</StaffLayout>
