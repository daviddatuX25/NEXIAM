<script lang="ts">
    import { Check, RefreshCw } from 'lucide-svelte';

    type PendingPage = {
        page_no: number;
        active?: boolean;
        capture_count?: number;
        detected_applicant_number?: string | null;
        manual_applicant_number?: string | null;
        write_in_region?: { page_no: number; x: number; y: number; w: number; h: number } | null;
        recognition_code?: string | null;
    };
    type RosterEntry = {
        id?: number;
        applicant_number?: string | null;
        reference_code?: string;
        name?: string;
    };
    type PendingCapture = {
        id: string;
        revision: number;
        status: string;
        identity_mode?: string;
        page_count: number;
        active_page_no: number;
        pages: PendingPage[];
        pairing_code?: string | null;
        resolution_code?: string | null;
        recovery?: {
            title?: string;
            explanation?: string;
            owner?: string;
        } | null;
        adopted_sheet_id?: number | null;
    };
    let {
        pending,
        production = false,
        busy = false,
        roster = [],
        onRetake,
        onAdopt,
        onConfirmIdentity,
    }: {
        pending: PendingCapture;
        production?: boolean;
        busy?: boolean;
        roster?: RosterEntry[];
        onRetake?: (pageNo: number, reason: string) => void;
        onAdopt?: () => void | Promise<void>;
        onConfirmIdentity?: (
            selections: Array<{ page_no: number; applicant_number: string }>,
        ) => void | Promise<void>;
    } = $props();

    let retakePage = $state<number | null>(null);
    let replacementReason = $state('');
    let manualSelections = $state<Record<number, string>>({});
    const pages = $derived(Array.isArray(pending.pages) ? pending.pages : []);
    const captured = $derived(
        pages.filter((page) => (page.capture_count ?? 0) > 0).length,
    );
    const observed = $derived(
        pages.find((page) => page.detected_applicant_number)
            ?.detected_applicant_number ?? null,
    );
    const paired = $derived(
        pending.status === 'ready' && !pending.pairing_code,
    );
    const needsManualIdentity = $derived(
        pending.identity_mode === 'write_in_manual' &&
            pending.status === 'captured' &&
            (pending.pairing_code === 'identity_review_required' ||
                pending.pairing_code === 'pairing_conflict'),
    );
    const needsPairing = $derived(
        pending.pairing_code === 'pairing_missing' ||
            pending.pairing_code === 'pairing_conflict',
    );
    const canSubmitManualIdentity = $derived(
        needsManualIdentity &&
            pages.length > 0 &&
            pages.every((page) =>
                Boolean(
                    (
                        manualSelections[page.page_no] ??
                        page.manual_applicant_number ??
                        ''
                    ).trim(),
                ),
            ),
    );
    function pageState(page: PendingPage): string {
        if (
            page.recognition_code ||
            (page.capture_count && !page.detected_applicant_number)
        )
            return 'Needs review';
        if (page.capture_count) return 'Captured';
        return page.active ? 'Current' : 'Waiting';
    }

    function confirmRetake(): void {
        if (retakePage === null || !replacementReason.trim() || busy) return;
        onRetake?.(retakePage, replacementReason.trim());
        retakePage = null;
        replacementReason = '';
    }

    function submitManualIdentity(): void {
        if (!canSubmitManualIdentity || busy) return;
        onConfirmIdentity?.(
            pages.map((page) => ({
                page_no: page.page_no,
                applicant_number: (
                    manualSelections[page.page_no] ??
                    page.manual_applicant_number ??
                    ''
                ).trim(),
            })),
        );
    }
</script>

<section
    class="border-border bg-card min-w-0 rounded-xl border p-4 sm:p-5"
    aria-label="Current examinee page progress"
    data-omr-pending-pages
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold">
                Current examinee · {captured} of {pending.page_count} pages captured
            </h3>
            <p class="text-muted-foreground mt-1 text-xs">
                {#if observed}Observed Applicant Number: <strong
                        class="text-foreground">{observed}</strong
                    >{:else}Applicant Number not yet confirmed.{/if}
                Each page must show the same number before review.
            </p>
        </div>
        <span
            class="border-border bg-muted/40 rounded-full border px-2.5 py-1 text-xs font-medium"
            role="status"
            aria-live="polite"
        >
            {#if pending.adopted_sheet_id}Ready for sheet review
            {:else if pending.resolution_code}Adoption blocked
            {:else if pending.pairing_code === 'identity_review_required'}Write-in confirmation required
            {:else if needsPairing}Pairing needs attention
            {:else if paired}{production
                    ? 'Ready to adopt'
                    : 'Pages paired · diagnostic only'}
            {:else}Page {pending.active_page_no} of {pending.page_count}{/if}
        </span>
    </div>

    <ol class="mt-4 flex flex-wrap gap-2" aria-label="Page capture status">
        {#each pages as page (page.page_no)}
            <li
                class={`border-border bg-background flex min-w-28 items-center gap-2 rounded-lg border px-3 py-2 text-xs ${page.active && pending.status === 'pending' ? 'border-primary' : ''}`}
            >
                {#if page.capture_count && !page.recognition_code}<Check
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />{:else}<span class="text-muted-foreground font-semibold"
                        >{page.page_no}</span
                    >{/if}
                <span
                    ><strong>Page {page.page_no}</strong><span
                        class="text-muted-foreground block"
                        >{pageState(page)}{page.detected_applicant_number
                            ? ` · ${page.detected_applicant_number}`
                            : ''}</span
                    ></span
                >
                {#if page.capture_count && !pending.adopted_sheet_id && onRetake}
                    <button
                        type="button"
                        class="text-primary focus-visible:outline-primary ml-auto rounded px-1 py-1 font-medium underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
                        disabled={busy}
                        aria-label={`Retake page ${page.page_no}`}
                        onclick={() => {
                            retakePage = page.page_no;
                            replacementReason = '';
                        }}>Retake</button
                    >
                {/if}
            </li>
        {/each}
    </ol>

    {#if retakePage !== null}
        <form
            class="border-border bg-muted/30 mt-3 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-end"
            onsubmit={(event) => {
                event.preventDefault();
                confirmRetake();
            }}
        >
            <label
                class="min-w-0 flex-1 text-xs font-medium"
                for="omr-retake-reason"
                >Reason for retaking Page {retakePage}
                <input
                    id="omr-retake-reason"
                    class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-3 py-2 text-sm"
                    bind:value={replacementReason}
                    required
                    maxlength="500"
                    disabled={busy}
                    placeholder="Why this page needs a new capture"
                />
            </label>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="border-border rounded-md border px-3 py-2 text-xs font-medium"
                    onclick={() => (retakePage = null)}>Cancel</button
                >
                <button
                    type="submit"
                    class="bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold disabled:opacity-50"
                    disabled={busy || !replacementReason.trim()}
                    ><RefreshCw
                        class="mr-1 inline size-3"
                        aria-hidden="true"
                    />Capture Page {retakePage}</button
                >
            </div>
        </form>
    {/if}

    {#if needsPairing}
        <p
            class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-200"
            role="alert"
        >
            {pending.pairing_code === 'pairing_missing'
                ? 'Applicant Number is missing on at least one page.'
                : 'The pages show different Applicant Numbers.'}
            Compare the per-page results above, then retake the affected page. No
            applicant or score is attached.
        </p>
    {/if}
    {#if needsManualIdentity && onConfirmIdentity}
        <form
            class="border-border bg-muted/30 mt-3 space-y-3 rounded-lg border p-3"
            onsubmit={(event) => {
                event.preventDefault();
                submitManualIdentity();
            }}
            data-omr-manual-identity-form
        >
            <p class="text-xs font-medium">
                Confirm the write-in Applicant Number on every page before adoption:
            </p>
            <div class="grid gap-2 sm:grid-cols-2">
                {#each pages as page (page.page_no)}
                    <label class="text-xs font-medium" for={`manual-id-page-${page.page_no}`}>
                        Page {page.page_no} examinee
                        {#if page.write_in_region}
                            <span class="text-muted-foreground font-normal">
                                (region {page.write_in_region.x},{page.write_in_region.y} · {page.write_in_region.w}×{page.write_in_region.h}px)
                            </span>
                        {/if}
                        {#if roster.length > 0}
                            <select
                                id={`manual-id-page-${page.page_no}`}
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-2.5 py-1.5 text-sm"
                                value={manualSelections[page.page_no] ?? page.manual_applicant_number ?? ''}
                                disabled={busy}
                                onchange={(event) => {
                                    manualSelections = {
                                        ...manualSelections,
                                        [page.page_no]: (event.currentTarget as HTMLSelectElement).value,
                                    };
                                }}
                            >
                                <option value="">Select eligible examinee</option>
                                {#each roster as candidate (candidate.id ?? candidate.applicant_number)}
                                    {#if candidate.applicant_number}
                                        <option value={candidate.applicant_number}>
                                            {candidate.applicant_number} · {candidate.name ?? candidate.reference_code ?? 'Examinee'}
                                        </option>
                                    {/if}
                                {/each}
                            </select>
                        {:else}
                            <input
                                id={`manual-id-page-${page.page_no}`}
                                class="border-input bg-background text-foreground mt-1 w-full rounded-md border px-2.5 py-1.5 text-sm"
                                value={manualSelections[page.page_no] ?? page.manual_applicant_number ?? ''}
                                disabled={busy}
                                placeholder="Enter Applicant Number"
                                oninput={(event) => {
                                    manualSelections = {
                                        ...manualSelections,
                                        [page.page_no]: (event.currentTarget as HTMLInputElement).value,
                                    };
                                }}
                            />
                        {/if}
                    </label>
                {/each}
            </div>
            <button
                type="submit"
                class="bg-primary text-primary-foreground rounded-md px-3 py-2 text-xs font-semibold disabled:opacity-50"
                disabled={busy || !canSubmitManualIdentity}
            >
                Confirm write-in Applicant Number
            </button>
        </form>
    {/if}
    {#if pending.recovery}
        <div
            class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-900 dark:text-amber-200"
            role="alert"
        >
            <strong
                >{pending.recovery.title ?? 'Adoption needs attention'}</strong
            >
            {pending.recovery.explanation ?? ''}
            {#if pending.recovery.owner}<span class="mt-1 block text-xs"
                    >Owner: {pending.recovery.owner}</span
                >{/if}
        </div>
    {/if}
    {#if paired && production && onAdopt && !pending.adopted_sheet_id}
        <button
            type="button"
            class="bg-primary text-primary-foreground mt-3 rounded-md px-3 py-2 text-sm font-semibold disabled:opacity-50"
            disabled={busy}
            onclick={() => onAdopt?.()}
            >{busy
                ? 'Checking adoption…'
                : pending.resolution_code
                  ? 'Retry adoption'
                  : 'Continue to sheet review'}</button
        >
    {/if}
    {#if paired && !production}
        <p class="text-muted-foreground mt-3 text-xs" role="status">
            This is an isolated bundle acceptance trial. It cannot create production paper claims or scores.
        </p>
    {/if}
</section>
