<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import {
        AlertCircle,
        ChevronLeft,
        ChevronRight,
        Clock3,
        FileSpreadsheet,
        RefreshCw,
    } from 'lucide-svelte';

    let {
        batch = {},
        rows = {},
        review_mode = 'standard',
        capabilities = {},
        office_view_mode = null,
        can_approve = false,
        can_resume = false,
    } = $props();
    let selectedRowIds = $state([]);
    let rejectionOpen = $state(false);
    let rejectionReason = $state('');
    let submitting = $state(false);
    let actionError = $state('');

    const rowItems = $derived(Array.isArray(rows) ? rows : (rows?.data ?? []));
    const eligibleRows = $derived(
        rowItems
            .filter(
                (row) =>
                    row.eligible === true ||
                    row.is_eligible === true ||
                    row.status === 'valid',
            )
            .map((row) => String(row.id)),
    );
    const selectedEligible = $derived(selectedRowIds);
    const allEligibleSelected = $derived(
        eligibleRows.length > 0 &&
            eligibleRows.every((id) => selectedEligible.includes(id)),
    );
    const pageLinks = $derived(Array.isArray(rows?.links) ? rows.links : []);
    const isRegistrarOffice = $derived(office_view_mode === 'registrar');
    const isRegistrar = $derived(
        ['registrar_consent', 'applicant_consent'].includes(review_mode) ||
            (isRegistrarOffice &&
                !['guidance_execution'].includes(review_mode)),
    );
    const isGuidance = $derived(
        review_mode === 'guidance_execution' ||
            can_resume ||
            capabilities?.can_resume ||
            capabilities?.can_view_scores ||
            capabilities?.can_import_scores,
    );
    const canApprove = $derived(
        Boolean(
            can_approve ||
            capabilities?.can_approve ||
            capabilities?.['registrar.applications.import'] ||
            capabilities?.approve_applicant_import,
        ),
    );
    const canResume = $derived(
        Boolean(
            can_resume ||
            capabilities?.can_resume ||
            capabilities?.['guidance.scoring.import'] ||
            capabilities?.resume_score_import,
        ),
    );
    const canCommit = $derived(
        Boolean(
            capabilities?.can_commit ||
            capabilities?.['guidance.scoring.import'] ||
            capabilities?.['registrar.applications.import'] ||
            capabilities?.commit_import,
        ),
    );
    const canSelectRows = $derived(canApprove || canCommit);
    const canDownloadErrors = $derived(
        Boolean(capabilities?.can_download_errors),
    );
    const title = $derived(
        isRegistrar
            ? 'Review applicant intake'
            : isGuidance
              ? 'Review exam scores'
              : 'Review import',
    );

    function paginatorLabel(label) {
        return String(label ?? '')
            .replaceAll('&laquo;', '‹')
            .replaceAll('&raquo;', '›')
            .replace(/<[^>]*>/g, '');
    }
    function statusLabel(status) {
        const labels = {
            staged: 'Saved for review',
            pending_approval: 'Awaiting Registrar approval',
            awaiting_guidance: 'Ready for Guidance',
            valid: 'Ready',
            invalid: 'Needs correction',
            duplicate: 'Possible duplicate',
            skipped: 'Not selected',
            completed: 'Completed',
            rejected: 'Rejected',
            failed: 'Needs attention',
        };
        return (
            labels[status] ??
            String(status ?? 'Unknown status').replaceAll('_', ' ')
        );
    }

    function toggleRow(id) {
        const key = String(id);
        selectedRowIds = selectedRowIds.includes(key)
            ? selectedRowIds.filter((item) => item !== key)
            : [...selectedRowIds, key];
    }

    function selectAllEligible() {
        selectedRowIds = allEligibleSelected
            ? selectedRowIds.filter((id) => !eligibleRows.includes(id))
            : [...new Set([...selectedRowIds, ...eligibleRows])];
    }

    function submitApproval() {
        if (!canApprove || selectedEligible.length === 0) return;
        submitting = true;
        actionError = '';
        router.post(
            `/imports/${batch.id}/approve`,
            { selected_row_ids: selectedEligible },
            {
                onError: () => {
                    actionError =
                        'Approval could not be completed. Review the messages and try again.';
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function submitResume() {
        if (!canResume) return;
        submitting = true;
        actionError = '';
        router.post(
            `/imports/${batch.id}/resume`,
            {},
            {
                onError: () => {
                    actionError =
                        'This batch could not continue. Review its row messages and try again.';
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function submitCommit() {
        if (!canCommit || selectedEligible.length === 0) return;
        submitting = true;
        actionError = '';
        router.post(
            `/imports/${batch.id}/commit`,
            { selected_row_ids: selectedEligible },
            {
                onError: () => {
                    actionError =
                        'This import could not be processed. Review its row messages and try again.';
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function rejectBatch() {
        if (!rejectionReason.trim()) return;
        submitting = true;
        router.post(
            `/imports/${batch.id}/reject`,
            { reason: rejectionReason.trim() },
            {
                onError: () => {
                    actionError = 'The batch could not be rejected. Try again.';
                },
                onSuccess: () => {
                    rejectionOpen = false;
                    rejectionReason = '';
                },
                onFinish: () => {
                    submitting = false;
                },
            },
        );
    }

    function applicantFields(row) {
        return Object.entries(
            row.applicant_fields ?? row.fields ?? row.applicant ?? {},
        ).filter(([, value]) => value !== null && value !== '');
    }
    function scoreFields(row) {
        return row.scores ?? row.score_values ?? {};
    }

    const breadcrumbs = [
        { label: 'Imports', href: '/imports' },
        { label: title },
    ];
</script>

<svelte:head><title>{title} · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <main class="mx-auto max-w-6xl min-w-0 space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Link
                    href="/imports"
                    class="text-primary text-sm font-medium hover:underline"
                    >← Back to Import Hub</Link
                >
                <h1 class="text-2xl font-bold tracking-tight">{title}</h1>
                <p class="text-muted-foreground text-sm">
                    Saved spreadsheet rows for {batch.academic_cycle?.label ??
                        batch.academic_cycle_label ??
                        'this academic cycle'}. Review the source and row status
                    before taking action.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div
                    class="border-border bg-card rounded-xl border p-3 text-sm"
                >
                    <div class="flex items-center gap-2 font-semibold">
                        <FileSpreadsheet
                            class="text-primary size-4"
                        />{batch.file_name ??
                            batch.filename ??
                            `Batch ${batch.id}`}
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {batch.import_type_label ??
                            batch.import_type ??
                            'Import'} · {batch.status_label ??
                            statusLabel(batch.status)} · {batch.total_rows ??
                            rows?.total ??
                            rowItems.length} rows
                    </p>
                </div>
                {#if canDownloadErrors}
                    <a
                        href={`/imports/${batch.id}/errors`}
                        download
                        class="text-primary text-sm font-medium underline-offset-4 hover:underline"
                        >Download row errors</a
                    >
                {/if}
            </div>
        </div>

        {#if batch.receipt}<section
                class="border-primary/20 bg-primary/5 rounded-xl border p-4"
            >
                <h2 class="font-semibold">Batch tracking</h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    {batch.receipt}
                </p>
            </section>{/if}
        {#if actionError}<p
                role="alert"
                class="border-destructive/30 bg-destructive/10 text-destructive rounded-lg border p-3 text-sm"
            >
                {actionError}
            </p>{/if}
        {#if isRegistrar}<aside
                class="border-primary/20 bg-primary/5 rounded-xl border p-4 text-sm"
            >
                <strong>Applicant consent review</strong>
                <p class="text-muted-foreground mt-1">
                    You can review applicant information and row checks.
                    Assessment scores are not part of Registrar review.
                </p>
            </aside>{:else if isGuidance}<aside
                class="border-primary/20 bg-primary/5 rounded-xl border p-4 text-sm"
            >
                <strong>Guidance score review</strong>
                <p class="text-muted-foreground mt-1">
                    Review score information and row checks before continuing
                    this phase.
                </p>
            </aside>{/if}
        {#if isGuidance && batch.status === 'pending_approval'}<aside
                class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm"
            >
                <strong>Waiting for Registrar approval</strong>
                <p class="text-muted-foreground mt-1">
                    New applicant records will not be created until the
                    Registrar reviews and approves eligible rows. Once approved,
                    Guidance can continue exam-session assignment and score
                    processing.
                </p>
            </aside>{/if}

        <section
            class="border-border bg-card overflow-hidden rounded-xl border"
        >
            <div
                class="border-border flex flex-wrap items-center justify-between gap-3 border-b p-4"
            >
                <div>
                    <h2 class="font-bold">Rows in this batch</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {#if canSelectRows}{selectedEligible.length} eligible row{selectedEligible.length ===
                            1
                                ? ''
                                : 's'} selected{:else}{rows?.total ??
                                rowItems.length} rows in this batch{/if}
                    </p>
                </div>
                {#if canSelectRows}
                    <div class="flex items-center gap-2">
                        {#if selectedRowIds.length > 0}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-destructive hover:bg-destructive/10"
                                onclick={() => (selectedRowIds = [])}
                            >
                                Clear selection
                            </Button>
                        {/if}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={eligibleRows.length === 0}
                            onclick={selectAllEligible}
                        >
                            {allEligibleSelected
                                ? 'Deselect visible'
                                : 'Select all eligible on this page'}
                        </Button>
                    </div>
                {/if}
            </div>
            {#if rowItems.length === 0}<div class="p-10 text-center">
                    <Clock3 class="text-muted-foreground mx-auto size-8" />
                    <h3 class="mt-3 font-semibold">No rows to show</h3>
                    <p class="text-muted-foreground mt-1 text-sm">
                        This batch may be empty or its rows are not available.
                    </p>
                </div>
            {:else}<div class="divide-border divide-y">
                    {#each rowItems as row (row.id)}
                        <article class="space-y-3 p-4">
                            <div class="flex flex-wrap items-start gap-3">
                                <input
                                    id={`row-${row.id}`}
                                    type="checkbox"
                                    checked={selectedRowIds.includes(
                                        String(row.id),
                                    )}
                                    disabled={!canSelectRows ||
                                        !eligibleRows.includes(String(row.id))}
                                    onchange={() => toggleRow(row.id)}
                                    class="accent-primary mt-1 size-4 {canSelectRows
                                        ? ''
                                        : 'hidden'}"
                                    aria-label={`Select eligible row ${row.source?.row_index ?? row.row_index ?? row.id}`}
                                />
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3 class="font-semibold">
                                            Source row {row.source?.row_index ??
                                                row.row_index ??
                                                row.source_row_number ??
                                                row.id}
                                        </h3>
                                        <span
                                            class="border-border rounded-full border px-2 py-0.5 text-xs font-medium"
                                            >{row.status_label ??
                                                statusLabel(row.status)}</span
                                        >{#if row.eligible || row.is_eligible || row.status === 'valid'}<span
                                                class="text-xs font-medium text-emerald-700 dark:text-emerald-300"
                                                >Eligible</span
                                            >{/if}
                                    </div>
                                    <p
                                        class="text-muted-foreground mt-1 text-xs"
                                    >
                                        Sheet: {row.source?.sheet_name ??
                                            row.sheet_name ??
                                            row.source_sheet ??
                                            '—'}
                                    </p>
                                </div>
                            </div>
                            {#if isRegistrar}<dl
                                    class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2"
                                >
                                    {#each applicantFields(row) as [field, value] (field)}<div
                                            class="min-w-0"
                                        >
                                            <dt
                                                class="text-muted-foreground text-xs"
                                            >
                                                {field.replaceAll('_', ' ')}
                                            </dt>
                                            <dd class="text-sm break-words">
                                                {value}
                                            </dd>
                                        </div>{:else}<p
                                            class="text-muted-foreground text-sm"
                                        >
                                            No applicant details are available
                                            for this row.
                                        </p>{/each}
                                </dl>
                            {:else if isGuidance}{#if Object.keys(scoreFields(row) ?? {}).length}<dl
                                        class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2"
                                    >
                                        {#each Object.entries(scoreFields(row) ?? {}) as [field, value] (field)}<div
                                            >
                                                <dt
                                                    class="text-muted-foreground text-xs"
                                                >
                                                    {field.replaceAll('_', ' ')}
                                                </dt>
                                                <dd class="text-sm font-medium">
                                                    {value ?? '—'}
                                                </dd>
                                            </div>{/each}
                                    </dl>{/if}{#if row.applicant_display_name}<p
                                        class="text-sm"
                                    >
                                        Applicant: <strong
                                            >{row.applicant_display_name}</strong
                                        >
                                    </p>{/if}
                            {:else if row.display_fields}<dl
                                    class="bg-muted/30 grid gap-2 rounded-lg p-3 sm:grid-cols-2"
                                >
                                    {#each Object.entries(row.display_fields) as [field, value] (field)}<div
                                        >
                                            <dt
                                                class="text-muted-foreground text-xs"
                                            >
                                                {field.replaceAll('_', ' ')}
                                            </dt>
                                            <dd class="text-sm break-words">
                                                {value ?? '—'}
                                            </dd>
                                        </div>{/each}
                                </dl>{/if}
                            {#if row.errors?.length}<div
                                    class="border-destructive/20 bg-destructive/5 rounded-lg border p-3"
                                >
                                    <p
                                        class="text-destructive flex items-center gap-2 text-sm font-semibold"
                                    >
                                        <AlertCircle class="size-4" />Row needs
                                        attention
                                    </p>
                                    <ul
                                        class="text-muted-foreground mt-2 list-inside list-disc space-y-1 text-sm"
                                    >
                                        {#each row.errors as error, index (index)}<li
                                            >
                                                {typeof error === 'string'
                                                    ? error
                                                    : (error.message ??
                                                      error.label ??
                                                      JSON.stringify(error))}
                                            </li>{/each}
                                    </ul>
                                </div>{/if}
                        </article>
                    {/each}
                </div>{/if}
            {#if pageLinks.length > 0}<nav
                    aria-label="Batch row pages"
                    class="border-border flex flex-wrap items-center justify-between gap-2 border-t p-4"
                >
                    {#each pageLinks as pageLink, index (`${pageLink.label}-${index}`)}{#if pageLink.url}<Link
                                href={pageLink.url}
                                preserveState={true}
                                preserveScroll={true}
                                aria-current={pageLink.active
                                    ? 'page'
                                    : undefined}
                                class="border-border inline-flex min-h-10 items-center gap-1 rounded-md border px-3 text-sm {pageLink.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted'}"
                                >{#if String(pageLink.label)
                                    .toLowerCase()
                                    .includes('previous')}<ChevronLeft
                                        class="size-4"
                                    />{/if}{paginatorLabel(
                                    pageLink.label,
                                )}{#if String(pageLink.label)
                                    .toLowerCase()
                                    .includes('next')}<ChevronRight
                                        class="size-4"
                                    />{/if}</Link
                            >{:else}<span
                                class="text-muted-foreground inline-flex min-h-10 items-center rounded-md px-3 text-sm"
                                aria-disabled="true"
                                >{paginatorLabel(pageLink.label)}</span
                            >{/if}{/each}
                </nav>{/if}
        </section>

        {#if isRegistrar && canApprove}<section
                class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    Only the rows you explicitly check will receive applicant
                    consent. An empty selection cannot be approved.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={() => {
                            rejectionOpen = true;
                            rejectionReason = '';
                        }}>Reject batch</Button
                    ><Button
                        type="button"
                        disabled={submitting || selectedEligible.length === 0}
                        onclick={submitApproval}
                        >{#if submitting}<RefreshCw
                                class="mr-2 size-4 animate-spin"
                            />Approving…{:else}Approve applicant intake ({selectedEligible.length}){/if}</Button
                    >
                </div>
            </section>
        {:else if isGuidance && canResume}<section
                class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    This continues every applicant row approved by the
                    Registrar. The operation is batch-wide, so no row checkboxes
                    are needed here.
                </p>
                <Button
                    type="button"
                    disabled={submitting ||
                        Number(
                            batch.guidance_pending_rows ??
                                batch.imported_rows ??
                                eligibleRows.length,
                        ) === 0}
                    onclick={submitResume}
                    >{#if submitting}<RefreshCw
                            class="mr-2 size-4 animate-spin"
                        />Continuing…{:else}Continue approved rows ({batch.guidance_pending_rows ??
                            batch.imported_rows ??
                            eligibleRows.length}){/if}</Button
                >
            </section>
        {:else if review_mode === 'standard' && canCommit}<section
                class="border-border bg-card flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    Select eligible rows to process this saved import.
                </p>
                <Button
                    type="button"
                    disabled={submitting || selectedEligible.length === 0}
                    onclick={submitCommit}
                    >Process selected rows ({selectedEligible.length})</Button
                >
            </section>
        {:else}<p
                class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-100"
            >
                You can review this batch, but your current permissions do not
                allow its next action.
            </p>{/if}

        {#if rejectionOpen}<div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                role="presentation"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="reject-title"
                    class="border-border bg-card w-full max-w-lg space-y-4 rounded-xl border p-5 shadow-xl"
                >
                    <h2 id="reject-title" class="text-lg font-bold">
                        Reject batch
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        Explain why this applicant batch cannot be approved. A
                        reason is required.
                    </p>
                    <label for="rejection-reason" class="text-sm font-medium"
                        >Reason</label
                    ><textarea
                        id="rejection-reason"
                        bind:value={rejectionReason}
                        rows="4"
                        class="border-input bg-background w-full rounded-md border p-3 text-sm"
                        required></textarea>
                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onclick={() => {
                                rejectionOpen = false;
                            }}>Cancel</Button
                        ><Button
                            variant="destructive"
                            disabled={!rejectionReason.trim() || submitting}
                            onclick={rejectBatch}>Reject batch</Button
                        >
                    </div>
                </section>
            </div>{/if}
    </main>
</StaffLayout>
