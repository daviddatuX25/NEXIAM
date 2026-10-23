<script>
    import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NewAnswerSheetDialog from '@/Components/Setup/NewAnswerSheetDialog.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { toastSuccess, toastError } from '@/lib/toast';
    import {
        Layers,
        Upload,
        Download,
        Trash2,
        Sparkles,
        Check,
        ChevronDown,
        ChevronUp,
        X,
        AlertCircle,
        Loader2,
        CheckCircle2,
        FileArchive,
    } from 'lucide-svelte';

    let {
        drafts = [],
        ready_answer_sheets = [],
        create_url = '',
        kit_context = null,
        selected_template_version_id = null,
    } = $props();

    const breadcrumbs = $derived(
        kit_context
            ? [{ label: 'Setup' }, { label: 'Test Kits' }, { label: kit_context.code }, { label: 'Answer Sheet' }]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Answer Sheets', href: '/setup/exam-materials/answer-sheets' },
                  { label: 'Answer Sheet Studio' },
              ],
    );

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

    function returnToKit() {
        if (kit_context?.back_url) router.visit(kit_context.back_url);
    }

    function formatStage(stage) {
        return (stage || 'draft').replaceAll('_', ' ');
    }

    function stageBadgeVariant(stage) {
        if (stage === 'compiled' || stage === 'physical_qualified')
            return 'default';
        if (stage === 'digital_verified' || stage === 'mapping_complete')
            return 'secondary';
        return 'outline';
    }

    function pageLabel(count) {
        if (!count) return 'No saved preview';
        return `${count} ${count === 1 ? 'page' : 'pages'}`;
    }

    // Modal state for import
    let importModalOpen = $state(false);
    let importFileInput = $state(null);
    let recoveryLedgerInput = $state(null);
    let importFile = $state(null);
    let recoveryLedgerFile = $state(null);
    let importBusy = $state(false);
    let importProgress = $state(null);
    let importError = $state('');
    let isDragging = $state(false);

    // Modal state for discard
    let discardModalOpen = $state(false);
    let draftToDiscard = $state(null);
    let discardBusy = $state(false);
    let discardError = $state('');


    // Compiled and active versions are selectable Kit material, not authoring drafts.
    const authoringDrafts = $derived(
        drafts.filter(
            (draft) =>
                draft.compiled_version_id === null &&
                draft.stage !== 'compiled' &&
                draft.compiled_version_status !== 'retired',
        ),
    );
    const retiredDrafts = $derived(
        drafts.filter((draft) => draft.compiled_version_status === 'retired'),
    );

    function promptDiscard(draft) {
        draftToDiscard = draft;
        discardError = '';
        discardModalOpen = true;
    }

    function confirmDiscard() {
        if (!draftToDiscard || discardBusy) return;
        discardBusy = true;
        discardError = '';
        router.delete(
            withKitContext(
                draftToDiscard.destroy_url ||
                    `/setup/exam-materials/answer-sheets/${draftToDiscard.id}`,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    discardModalOpen = false;
                    toastSuccess(
                        `Draft "${draftToDiscard.name}" discarded cleanly.`,
                    );
                    draftToDiscard = null;
                },
                onError: (errors) => {
                    discardError =
                        typeof errors === 'string'
                            ? errors
                            : errors?.message ||
                              'Failed to discard draft. The draft may be bound to an active exam session.';
                    toastError(discardError);
                },
                onFinish: () => {
                    discardBusy = false;
                },
            },
        );
    }


    function handleFileSelected(event) {
        const file = event.target?.files?.[0];
        if (file) {
            importFile = file;
            recoveryLedgerFile = null;
            importProgress = null;
            importError = '';
        }
    }

    function handleRecoveryLedgerSelected(event) {
        recoveryLedgerFile = event.target?.files?.[0] || null;
        importError = '';
    }

    function handleDrop(event) {
        event.preventDefault();
        isDragging = false;
        const file = event.dataTransfer?.files?.[0];
        if (file) {
            importFile = file;
            recoveryLedgerFile = null;
            importProgress = null;
            importError = '';
        }
    }

    function submitImport(event) {
        event?.preventDefault();
        if (!importFile || importBusy) return;
        importBusy = true;
        importProgress = 0;
        importError = '';
        const formData = new FormData();
        formData.append('bundle', importFile);
        if (recoveryLedgerFile) {
            formData.append('recovery_ledger', recoveryLedgerFile);
        }

        router.post(withKitContext('/setup/exam-materials/answer-sheets/import'), formData, {
            forceFormData: true,
            onProgress: (progress) => {
                const percentage = Number(progress?.percentage);
                if (Number.isFinite(percentage)) {
                    importProgress = Math.max(0, Math.min(100, percentage));
                }
            },
            onSuccess: () => {
                importModalOpen = false;
                importFile = null;
                recoveryLedgerFile = null;
                toastSuccess('Upload received. The archive is being checked.');
            },
            onError: (errors) => {
                importError =
                    errors.bundle || errors.recovery_ledger || 'Could not receive the Answer Sheet bundle.';
                toastError(importError);
            },
            onFinish: () => {
                importBusy = false;
                importProgress = null;
            },
        });
    }
</script>

<svelte:head><title>Answer Sheet Studio · Exam materials</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-8 p-4 sm:p-6">
        <PageHeaderBar
            title="Answer Sheet Studio"
            description="Design and maintain the optical answer sheets used by exam Question Booklets."
        >
            {#snippet actions()}
                {#if kit_context}
                    <div class="flex items-center gap-2">
                        <Button variant="outline" onclick={returnToKit}>
                            Back to {kit_context.code}
                        </Button>
                        {#if !kit_context.read_only}
                            <NewAnswerSheetDialog {create_url} />
                        {/if}
                    </div>
                {:else}
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick={() => {
                                importModalOpen = true;
                                importError = '';
                                importFile = null;
                            }}
                            class="border-border bg-card hover:bg-muted text-foreground inline-flex min-h-10 items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold shadow-xs transition"
                        >
                            <Upload class="size-4 text-muted-foreground" />
                            <span>Import Bundle (.zip)</span>
                        </button>
                        <NewAnswerSheetDialog {create_url} />
                    </div>
                {/if}
            {/snippet}
        </PageHeaderBar>

        {#if kit_context}
            <p class="rounded-lg border border-border bg-muted/30 p-3 text-sm">
                {#if kit_context.answer_sheet_draft_id}
                    Selected preparation draft #{kit_context.answer_sheet_draft_id}.
                {:else if selected_template_version_id}
                    {kit_context.read_only ? 'Read-only exact Test Kit pin' : 'Selected preparation version'}: Answer Sheet version {selected_template_version_id}.
                {:else}
                    No Answer Sheet is selected for this preparation.
                {/if}
            </p>
        {:else}
        <section class="space-y-3" aria-label="Ready Answer Sheets">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                    Ready Answer Sheets ({ready_answer_sheets.length})
                </h2>
                <p class="text-muted-foreground text-xs">
                    Ready material status does not establish production authorization.
                </p>
            </div>
            {#if ready_answer_sheets.length === 0}
                <div class="border-border bg-card rounded-xl border border-dashed px-6 py-8 text-center">
                    <p class="text-foreground text-sm font-medium">No ready Answer Sheet versions.</p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Compiled and active versions appear here when available for Kit selection.
                    </p>
                </div>
            {:else}
                <div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border">
                    {#each ready_answer_sheets as sheet (sheet.id)}
                        <article class="grid gap-4 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:p-5">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-foreground font-semibold">{sheet.name}</h3>
                                    <Badge variant={sheet.status === 'active' ? 'default' : 'secondary'}>
                                        {sheet.status}
                                    </Badge>
                                    {#if Number(selected_template_version_id) === sheet.id}
                                        <Badge variant="outline">Selected for this Kit</Badge>
                                    {/if}
                                </div>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    Version {sheet.version} · {pageLabel(sheet.page_count)}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                {#if sheet.preview_url}
                                    <a href={sheet.preview_url}>
                                        <Button variant="outline" size="sm">Preview</Button>
                                    </a>
                                {/if}
                                {#if sheet.export_url}
                                    <a href={sheet.export_url} download>
                                        <Button variant="outline" size="sm">Export</Button>
                                    </a>
                                {/if}
                                {#if sheet.inspection_url}
                                    <a href={sheet.inspection_url}>
                                        <Button variant="outline" size="sm">Inspect Version</Button>
                                    </a>
                                {/if}
                            </div>
                        </article>
                    {/each}
                </div>
            {/if}
        </section>
        {/if}

        <section class="space-y-3" aria-label="Drafts">
            <div class="flex items-center justify-between">
                <h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                    Drafts ({authoringDrafts.length})
                </h2>
            </div>

            {#if authoringDrafts.length === 0}
                <div
                    class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center"
                >
                    <div
                        class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl"
                    >
                        <Layers class="text-muted-foreground size-6" />
                    </div>
                    <h3 class="mt-4 font-semibold">No Drafts in Progress</h3>
                    <p
                        class="text-muted-foreground mx-auto mt-1 max-w-md text-sm"
                    >
                        Create an Answer Sheet to design with the Sheet Designer
                        or map an uploaded document.
                    </p>
                    {#if !kit_context?.read_only}
                        <div class="mt-5 flex justify-center">
                            <NewAnswerSheetDialog {create_url} />
                        </div>
                    {/if}
                </div>
            {:else}
                <div
                    class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"
                >
                    {#each authoringDrafts as draft (draft.id)}
                        <article
                            class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"
                        >
                            <MaterialDocumentPreview
                                src={draft.preview_url}
                                alt={`${draft.name} saved preview`}
                                kind="answer_sheet"
                                title={draft.name}
                                page_count={draft.preview_page_count}
                            />

                            <div class="min-w-0 self-stretch py-0.5">
                                <div class="flex flex-wrap items-start gap-2">
                                    <h3
                                        class="text-foreground text-base font-semibold sm:text-lg"
                                    >
                                        {draft.name}
                                    </h3>
                                    <Badge
                                        variant={stageBadgeVariant(draft.stage)}
                                        class="capitalize"
                                    >
                                        {formatStage(draft.stage)}
                                    </Badge>
                                    {#if !draft.can_edit}
                                        <Badge variant="destructive"
                                            >Read-only</Badge
                                        >
                                    {/if}
                                </div>

                                <p
                                    class="text-foreground/80 mt-1 text-sm font-medium"
                                >
                                    {draft.authoring_method}
                                    {#if draft.compiled_version_number}
                                        · Version {draft.compiled_version_number}
                                    {/if}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {pageLabel(draft.preview_page_count)}
                                </p>
                                <p class="text-muted-foreground mt-3 text-xs">
                                    Updated {new Date(
                                        draft.updated_at,
                                    ).toLocaleDateString()}
                                </p>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-2 sm:self-center"
                            >
                                <a
                                    href={`/setup/exam-materials/answer-sheets/${draft.id}/export`}
                                    download
                                    class="border-border bg-card hover:bg-muted text-foreground inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition"
                                    title="Export .zip bundle"
                                    aria-label={`Export ${draft.name} .zip bundle`}
                                >
                                    <Download
                                        class="text-muted-foreground size-4"
                                    />
                                    <span>Export</span>
                                </a>

                                {#if draft.can_discard && !kit_context?.read_only}
                                    <button
                                        type="button"
                                        onclick={() => promptDiscard(draft)}
                                        class="border-rose-500/30 bg-rose-500/10 text-rose-700 hover:bg-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/30 inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-xs transition"
                                        title="Discard answer sheet draft"
                                        aria-label={`Discard ${draft.name} draft`}
                                    >
                                        <Trash2 class="size-4" />
                                        <span class="hidden md:inline"
                                            >Discard</span
                                        >
                                    </button>
                                {/if}

                                <Link href={withKitContext(draft.url)} viewTransition>
                                    <Button
                                        variant="outline"
                                        class="min-h-11 w-full sm:w-auto"
                                    >
                                        {draft.can_edit
                                            ? 'Continue editing'
                                            : 'Open Studio'}
                                    </Button>
                                </Link>
                            </div>
                        </article>
                    {/each}
                </div>
            {/if}
        </section>

        <!-- 3. ARCHIVED & PAST VERSIONS SECTION -->
        {#if retiredDrafts.length > 0}
            <details
                class="border-border bg-card group rounded-xl border p-4 transition"
            >
                <summary
                    class="text-muted-foreground flex cursor-pointer list-none items-center justify-between text-xs font-bold tracking-wider uppercase select-none"
                >
                    <span
                        >Archived & Past Versions ({retiredDrafts.length})</span
                    >
                    <ChevronDown
                        class="size-4 transition-transform group-open:rotate-180"
                    />
                </summary>
                <div class="divide-border mt-4 divide-y border-t pt-2">
                    {#each retiredDrafts as draft (draft.id)}
                        <div
                            class="flex items-center justify-between py-3 text-xs"
                        >
                            <div>
                                <span class="text-foreground font-semibold"
                                    >{draft.name}</span
                                >
                                <span class="text-muted-foreground ml-2"
                                    >Version {draft.compiled_version_number ??
                                        1} · Retired</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <a
                                    href={`/setup/exam-materials/answer-sheets/${draft.id}/export`}
                                    download
                                    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 rounded-md px-2 py-1 transition"
                                >
                                    <Download class="size-3.5" />
                                    <span>Export</span>
                                </a>
                                <Link
                                    href={withKitContext(draft.url)}
                                >
                                    <span>View</span>
                                </Link>
                            </div>
                        </div>
                    {/each}
                </div>
            </details>
        {/if}

        <!-- Discard Answer Sheet Draft Modal Dialog -->
        {#if discardModalOpen && draftToDiscard}
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
                role="presentation"
                onclick={(e) => {
                    if (e.target === e.currentTarget && !discardBusy)
                        discardModalOpen = false;
                }}
                onkeydown={(e) => {
                    if (e.key === 'Escape' && !discardBusy)
                        discardModalOpen = false;
                }}
            >
                <div
                    class="border-border bg-card text-foreground w-full max-w-md space-y-4 rounded-2xl border p-6 shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="discard-modal-title"
                    tabindex="-1"
                >
                    <div
                        class="border-border flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex size-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400"
                            >
                                <Trash2 class="size-5" />
                            </div>
                            <div>
                                <h3
                                    id="discard-modal-title"
                                    class="text-foreground text-base font-bold"
                                >
                                    Discard Answer Sheet Draft?
                                </h3>
                                <p class="text-muted-foreground text-xs">
                                    {draftToDiscard.code} · {draftToDiscard.name}
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            onclick={() => (discardModalOpen = false)}
                            disabled={discardBusy}
                            class="text-muted-foreground hover:text-foreground rounded-lg p-1 transition"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    {#if discardError}
                        <div
                            class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <span class="leading-relaxed">{discardError}</span>
                        </div>
                    {/if}

                    <div class="text-muted-foreground space-y-2 text-xs">
                        <p>
                            Are you sure you want to permanently discard this
                            answer sheet draft?
                        </p>
                        <ul
                            class="list-inside list-disc space-y-1 text-slate-700 dark:text-slate-300"
                        >
                            <li>
                                All recognition bubble mappings and declared
                                items will be deleted.
                            </li>
                            <li>
                                Temporary sandbox verification test runs and
                                images will be purged.
                            </li>
                            <li>
                                This action is permanent and cannot be undone.
                            </li>
                        </ul>
                    </div>

                    <div
                        class="border-border flex items-center justify-end gap-2 border-t pt-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            disabled={discardBusy}
                            onclick={() => (discardModalOpen = false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={discardBusy}
                            onclick={confirmDiscard}
                        >
                            {#if discardBusy}
                                <Loader2 class="mr-1.5 size-4 animate-spin" />
                                Discarding…
                            {:else}
                                <Trash2 class="mr-1.5 size-4" />
                                Yes, Discard Draft
                            {/if}
                        </Button>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Import Answer Sheet Bundle Modal Dialog -->
        {#if importModalOpen}
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-xs"
                role="presentation"
                onclick={(e) => {
                    if (e.target === e.currentTarget && !importBusy)
                        importModalOpen = false;
                }}
            >
                <div
                    class="border-border bg-card text-foreground w-full max-w-lg space-y-4 rounded-2xl border p-6 shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="import-modal-title"
                    tabindex="-1"
                >
                    <div
                        class="border-border flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <Upload class="size-5 text-indigo-500" />
                            <h3
                                id="import-modal-title"
                                class="text-foreground text-base font-bold"
                            >
                                Import Answer Sheet Bundle (.zip)
                            </h3>
                        </div>
                        <button
                            type="button"
                            onclick={() => (importModalOpen = false)}
                            disabled={importBusy}
                            class="text-muted-foreground hover:text-foreground rounded-lg p-1 transition"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    {#if importError}
                        <div
                            class="flex items-start gap-2.5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 dark:text-rose-300"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <span class="leading-relaxed">{importError}</span>
                        </div>
                    {/if}

                    <div
                        class="border-border/80 hover:border-primary/50 relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors {isDragging
                            ? 'border-primary bg-primary/5'
                            : 'bg-muted/10'}"
                        ondragover={(e) => {
                            e.preventDefault();
                            isDragging = true;
                        }}
                        ondragleave={() => (isDragging = false)}
                        ondrop={handleDrop}
                        role="region"
                        aria-label="File upload dropzone"
                    >
                        <input
                            type="file"
                            accept=".zip"
                            bind:this={importFileInput}
                            onchange={handleFileSelected}
                            class="hidden"
                        />
                        <div
                            class="bg-muted mb-3 flex size-12 items-center justify-center rounded-2xl shadow-xs"
                        >
                            <FileArchive
                                class="size-6 text-indigo-500"
                                aria-hidden="true"
                            />
                        </div>
                        {#if importFile}
                            <p
                                class="text-foreground text-sm font-semibold break-all"
                            >
                                {importFile.name}
                            </p>
                            <p class="text-muted-foreground mt-0.5 text-xs">
                                {(importFile.size / 1024 / 1024).toFixed(2)} MB
                            </p>
                            <button
                                type="button"
                                onclick={() => {
                                    importFile = null;
                                    recoveryLedgerFile = null;
                                    if (importFileInput) importFileInput.value = '';
                                    if (recoveryLedgerInput) recoveryLedgerInput.value = '';
                                }}
                                class="text-muted-foreground hover:text-foreground mt-2 text-xs font-semibold underline"
                            >
                                Choose a different file
                            </button>
                        {:else}
                            <p class="text-foreground text-sm font-semibold">
                                Drop your exported bundle here
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Accepts standard .zip export bundles from
                                NEXIAM.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="mt-4"
                                onclick={() => importFileInput?.click()}
                            >
                                Select ZIP File
                            </Button>
                        {/if}
                    </div>
                    {#if importFile}
                        <div class="border-border bg-muted/20 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                            <div class="space-y-0.5">
                                <p class="text-foreground text-xs font-semibold">Recovery notes (optional)</p>
                                <p class="text-muted-foreground text-xs">Only needed with the reviewed replacement archive.</p>
                            </div>
                            <input
                                type="file"
                                accept=".json,application/json"
                                bind:this={recoveryLedgerInput}
                                onchange={handleRecoveryLedgerSelected}
                                class="hidden"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={importBusy}
                                onclick={() => recoveryLedgerInput?.click()}
                            >
                                {recoveryLedgerFile?.name || 'Select notes'}
                            </Button>
                        </div>
                    {/if}
                    {#if importBusy && importProgress !== null}
                        <div class="space-y-1.5" role="status" aria-live="polite">
                            <div class="flex justify-between gap-3 text-xs">
                                <span class="text-muted-foreground">Uploading archive</span>
                                <span class="text-foreground font-medium">{importProgress}%</span>
                            </div>
                            <progress class="bg-muted h-2 w-full overflow-hidden rounded-full accent-primary" value={importProgress} max="100"></progress>
                        </div>
                    {/if}

                    <div
                        class="border-border flex items-center justify-end gap-2 border-t pt-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            disabled={importBusy}
                            onclick={() => (importModalOpen = false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={!importFile || importBusy}
                            onclick={submitImport}
                        >
                            {#if importBusy}
                                <Loader2 class="mr-2 size-4 animate-spin" />
                                Uploading…
                            {:else}
                                Upload ZIP
                            {/if}
                        </Button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</StaffLayout>
