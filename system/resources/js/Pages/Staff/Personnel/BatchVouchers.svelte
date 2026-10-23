<script>
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import {
        AlertTriangle,
        Download,
        Printer,
        RefreshCw,
        ShieldCheck,
    } from 'lucide-svelte';

    let { targets = [], canIssueNonExpiring = false } = $props();
    let open = $state(false);
    let busy = $state(false);
    let error = $state('');
    let purpose = $state('activation');
    let mode = $state('extended_180d');
    let customExpiry = $state('');
    let sendEmail = $state(false);
    let selectedIds = $state([]);
    let replaceExisting = $state(false);
    let lastReplace = $state(false);
    let operationId = $state('');
    let receiptRows = $state([]);
    let objectUrl = $state(null);

    const eligibleRows = $derived(
        targets.filter((target) => selectedIds.includes(target.id)),
    );
    const revealedRows = $derived(
        receiptRows.filter(
            (row) => row.status === 'issued' && row.token && row.fallback,
        ),
    );

    function openDialog() {
        selectedIds = targets.map((target) => target.id);
        replaceExisting = false;
        lastReplace = false;
        receiptRows = [];
        operationId = '';
        error = '';
        open = true;
    }

    function toggleTarget(id) {
        selectedIds = selectedIds.includes(id)
            ? selectedIds.filter((value) => value !== id)
            : [...selectedIds, id];
    }

    function normalizeMode() {
        if (
            purpose !== 'activation' &&
            !['standard_24h', 'extended_7d'].includes(mode)
        ) {
            mode = 'standard_24h';
            customExpiry = '';
        }
    }

    async function issue(replace = null, isRetry = false) {
        if (busy || eligibleRows.length === 0) return;
        const replaceMode = replace !== null ? replace : replaceExisting;
        if (
            replaceMode &&
            !isRetry &&
            !window.confirm(
                'Replace these vouchers? Existing QR codes, fallback codes, and unfinished attempts will be invalidated.',
            )
        )
            return;
        if (!isRetry && (replaceMode || !operationId)) operationId = crypto.randomUUID();
        lastReplace = replaceMode;
        busy = true;
        error = '';
        try {
            const response = await fetch('/staff/personnel/vouchers/batch', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                },
                body: JSON.stringify({
                    operation_id: operationId,
                    targets: eligibleRows.map(({ id }) => ({ id })),
                    purpose,
                    mode,
                    ...(mode === 'custom'
                        ? { custom_expiry: customExpiry }
                        : {}),
                    send_email: sendEmail,
                    replace: replaceMode,
                }),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    data.message ?? 'The batch voucher request failed.',
                );
            const currentReveals = new Map(
                receiptRows
                    .filter((row) => row.status === 'issued' && row.token)
                    .map((row) => [row.user_id, row]),
            );
            receiptRows = (data.rows ?? []).map((row) =>
                row.status === 'already_issued'
                    ? (currentReveals.get(row.user_id) ?? row)
                    : row,
            );
        } catch (caught) {
            error =
                caught instanceof Error
                    ? caught.message
                    : 'The batch voucher request failed. Retry the same operation safely or explicitly replace lost credentials.';
        } finally {
            busy = false;
        }
    }

    async function printGrid(format = 'pdf', layout = 'grouped') {
        if (busy || revealedRows.length === 0) return;
        busy = true;
        error = '';
        try {
            const response = await fetch(
                '/staff/personnel/vouchers/batch/print',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept:
                            format === 'pdf' ? 'application/pdf' : 'text/html',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector('meta[name="csrf-token"]')
                                ?.content ?? '',
                    },
                    body: JSON.stringify({
                        format,
                        layout,
                        cards: revealedRows.map((row) => ({
                            user_id: row.user_id,
                            reference: row.public_reference,
                            token: row.token,
                            fallback: row.fallback,
                            hash: row.print_hash,
                        })),
                    }),
                },
            );
            if (!response.ok)
                throw new Error(
                    'A selected voucher is no longer valid or outside your print authority. Refresh the list and issue a replacement if needed.',
                );
            const blob = await response.blob();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(blob);
            if (format === 'html') {
                window.open(objectUrl, '_blank', 'noopener');
            } else {
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = 'NEXIAM-Staff-Voucher-Grid.pdf';
                link.click();
            }
            window.setTimeout(() => {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
            }, 60000);
        } catch (caught) {
            error =
                caught instanceof Error
                    ? caught.message
                    : 'The grouped voucher document could not be generated.';
        } finally {
            busy = false;
        }
    }

    function close() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        receiptRows = [];
        operationId = '';
        open = false;
    }
</script>

<Button
    type="button"
    variant="outline"
    onclick={openDialog}
    disabled={targets.length === 0}>Issue grouped vouchers</Button
>

<Dialog.Root {open} onOpenChange={(value) => (value ? (open = true) : close())}>
    <Dialog.Portal>
        <Dialog.Overlay />
        <Dialog.Content
            class="border-border/80 max-h-[92vh] overflow-y-auto border p-6 shadow-2xl sm:max-w-4xl"
        >
            <Dialog.Header class="border-border/60 border-b pb-3">
                <Dialog.Title class="text-xl font-bold"
                    >Grouped staff vouchers</Dialog.Title
                >
                <Dialog.Description
                    >Issue private, single-use credentials for the selected
                    authorized staff. QR slips are grouped by office,
                    department, and program.</Dialog.Description
                >
            </Dialog.Header>

            {#if error}
                <div
                    role="alert"
                    class="bg-destructive/10 text-destructive mt-4 flex items-start gap-2 rounded-lg p-3 text-sm"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />{error}
                </div>
            {/if}

            {#if receiptRows.length === 0}
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="space-y-3">
                        <label
                            class="block text-sm font-medium"
                            for="batch-voucher-purpose">Purpose</label
                        >
                        <select
                            id="batch-voucher-purpose"
                            bind:value={purpose}
                            onchange={normalizeMode}
                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="activation"
                                >Initial staff activation</option
                            >
                            <option value="password_reset"
                                >Password recovery</option
                            >
                            <option value="mfa_reset">MFA recovery</option>
                        </select>
                        <label
                            class="block text-sm font-medium"
                            for="batch-voucher-mode">Voucher lifetime</label
                        >
                        <select
                            id="batch-voucher-mode"
                            bind:value={mode}
                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="standard_24h">24 hours</option>
                            <option value="extended_7d">7 days</option>
                            {#if purpose === 'activation'}
                                <option value="extended_30d">30 days</option>
                                <option value="extended_90d">90 days</option>
                                <option value="extended_180d"
                                    >180 days · Recommended</option
                                >
                                <option value="custom"
                                    >Custom date and time</option
                                >
                            {/if}
                            {#if purpose === 'activation' && canIssueNonExpiring}
                                <option value="never_expires_single_use"
                                    >No calendar expiry · HR only</option
                                >
                            {/if}
                        </select>
                        {#if mode === 'custom'}
                            <label
                                for="batch-custom-expiry"
                                class="block text-sm font-medium"
                                >Expires at (institution time)</label
                            >
                            <input
                                id="batch-custom-expiry"
                                type="datetime-local"
                                bind:value={customExpiry}
                                required
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                            />
                        {/if}
                        <label class="flex items-start gap-2 text-sm"
                            ><input
                                type="checkbox"
                                bind:checked={replaceExisting}
                                class="mt-1"
                            /><span
                                >Replace existing vouchers (reissue credentials
                                and invalidate prior QR codes or pending setup attempts)</span
                            ></label
                        >
                        <label class="flex items-start gap-2 text-sm"
                            ><input
                                type="checkbox"
                                bind:checked={sendEmail}
                                class="mt-1"
                            /><span
                                >Queue an invitation where a real email address
                                is on file. Missing addresses stay awaiting
                                recipient email choice.</span
                            ></label
                        >
                        <div
                            class="border-border/60 bg-muted/30 text-muted-foreground flex items-start gap-2 rounded-md border p-3 text-xs"
                        >
                            <ShieldCheck class="size-4 shrink-0" />Secrets
                            appear once in this response only. Keep the grid
                            private and hand slips to recipients separately.
                        </div>
                    </div>
                    <div
                        class="border-border/60 max-h-72 space-y-2 overflow-y-auto rounded-md border p-3"
                        aria-label="Selected staff recipients"
                    >
                        {#each targets as target (target.id)}
                            <label
                                class="hover:bg-muted/40 flex cursor-pointer items-start gap-2 rounded-md p-2"
                            >
                                <input
                                    type="checkbox"
                                    checked={selectedIds.includes(target.id)}
                                    onchange={() => toggleTarget(target.id)}
                                    class="mt-1"
                                />
                                <span class="min-w-0"
                                    ><span class="block text-sm font-medium"
                                        >{target.name}</span
                                    ><span
                                        class="text-muted-foreground block text-xs"
                                        >{target.office}{#if target.program_codes?.length}
                                            · {target.program_codes.join(
                                                ', ',
                                            )}{/if} · {target.email ??
                                            'Email to be supplied during setup'}</span
                                    ></span
                                >
                            </label>
                        {/each}
                    </div>
                </div>
                <div
                    class="border-border/60 mt-5 flex flex-col-reverse justify-between gap-2 border-t pt-4 sm:flex-row"
                >
                    <Button type="button" variant="outline" onclick={close}
                        >Cancel</Button
                    >
                    <Button
                        type="button"
                        onclick={() => issue()}
                        disabled={busy ||
                            eligibleRows.length === 0 ||
                            (mode === 'custom' && !customExpiry)}
                        >{busy
                            ? 'Issuing…'
                            : `Issue ${eligibleRows.length} voucher${eligibleRows.length === 1 ? '' : 's'}`}</Button
                    >
                </div>
            {:else}
                <div class="mt-4 space-y-3" aria-live="polite">
                    {#each receiptRows as row (row.user_id)}
                        <article class="border-border/60 rounded-md border p-3">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <strong class="text-sm"
                                    >{targets.find(
                                        (target) => target.id === row.user_id,
                                    )?.name ?? `Staff #${row.user_id}`}</strong
                                ><span
                                    class="text-muted-foreground text-xs font-semibold uppercase"
                                    >{row.status}</span
                                >
                            </div>
                            {#if row.status === 'issued'}
                                <p class="text-muted-foreground mt-2 text-xs">
                                    Expiry: {row.expires_at_display}
                                </p>
                                <p class="mt-2 font-mono text-xs break-all">
                                    Reference: {row.public_reference} · Fallback:
                                    {row.fallback}
                                </p>
                                {#if row.email_blocked}
                                    <p
                                        role="status"
                                        class="mt-2 text-xs text-amber-700"
                                    >
                                        No email on file. Print this slip;
                                        recipient chooses an address during
                                        setup.
                                    </p>
                                {:else if row.email_queue_failed}
                                    <p
                                        role="status"
                                        class="mt-2 text-xs text-amber-700"
                                    >
                                        The voucher is issued, but the
                                        invitation could not be queued. Keep
                                        this reveal for printing.
                                    </p>
                                {:else if row.email_queued}
                                    <p
                                        role="status"
                                        class="text-muted-foreground mt-2 text-xs"
                                    >
                                        {row.email_message}
                                    </p>
                                {/if}
                            {:else}
                                <p class="text-muted-foreground mt-2 text-xs">
                                    {row.message ??
                                        'No voucher was issued for this staff member.'}
                                </p>
                            {/if}
                        </article>
                    {/each}
                </div>
                {#if revealedRows.length > 0}
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            onclick={() => printGrid('html')}
                            disabled={busy}
                            ><Printer class="mr-2 size-4" />Print grouped slips</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => printGrid('pdf')}
                            disabled={busy}
                            ><Download class="mr-2 size-4" />Download grouped
                            PDF</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => printGrid('html', 'individual')}
                            disabled={busy}>Individual-slip alternative</Button
                        >
                    </div>
                {/if}
                {#if receiptRows.some((row) => row.status === 'failed')}
                    <Button
                        type="button"
                        variant="outline"
                        class="mt-3"
                        onclick={() => issue(lastReplace, true)}
                        disabled={busy}>Retry failed rows safely</Button
                    >
                {/if}
                {#if receiptRows.some( (row) => ['already_issued', 'conflict', 'replace_required'].includes(row.status) )}
                    <div
                        class="mt-4 rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm"
                        role="note"
                    >
                        A prior voucher exists or its one-time reveal is
                        unavailable. A completed receipt never reveals the old
                        secret; explicit replacement invalidates its prior QR,
                        fallback, and unfinished attempt.
                        <Button
                            type="button"
                            variant="destructive"
                            class="mt-3"
                            onclick={() => issue(true)}
                            disabled={busy}
                            ><RefreshCw class="mr-2 size-4" />Confirm
                            replacement</Button
                        >
                    </div>
                {/if}
                <div
                    class="border-border/60 mt-5 flex justify-end border-t pt-4"
                >
                    <Button type="button" variant="outline" onclick={close}
                        >Close</Button
                    >
                </div>
            {/if}
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>
