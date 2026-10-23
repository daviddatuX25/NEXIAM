<script>
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Badge } from '@/Components/ui/badge';
    import {
        AlertTriangle,
        Check,
        Download,
        Plus,
        Printer,
        Trash2,
    } from 'lucide-svelte';

    let {
        open = $bindable(false),
        cycles = [],
        programs = [],
        roleOptions = [],
        can_full_hr = false,
        actor_office = 'admin',
        onSuccess = () => {},
    } = $props();

    let targetCycle = $state('');
    let voucherMode = $state('extended_180d');
    let busy = $state(false);
    let errorMessage = $state('');
    let successMessage = $state('');
    let lostRevealMessage = $state('');

    let rows = $state([createEmptyRow()]);
    let provisionedCards = $state([]);
    let previousReveals = $state(new Map());
    let showPrintOutput = $state(false);
    let printObjectUrl = $state(null);
    let wasOpen = false;
    let batchOperationId = crypto.randomUUID();

    function roleOptionsForOffice(office) {
        const supported = office === 'program'
            ? ['program_head']
            : office === 'registrar'
                ? ['registrar_clerk', 'registrar_support_staff']
                : office === 'guidance'
                    ? ['guidance_support_staff', 'guidance_clerk']
                    : ['institutional_admin'];
        return roleOptions.filter((role) => supported.includes(role.name));
    }

    function createEmptyRow() {
        const office = can_full_hr ? 'program' : actor_office;
        const choices = roleOptionsForOffice(office);
        return {
            operation_id: crypto.randomUUID(),
            name: '',
            office,
            roles: choices.length > 0 ? [choices[0].name] : [],
            program_codes: [],
        };
    }

    $effect(() => {
        const isOpen = open;
        if (isOpen && !wasOpen) {
            targetCycle = cycles[0]?.code ?? '';
            voucherMode = can_full_hr ? 'never_expires_single_use' : 'extended_180d';
            errorMessage = '';
            successMessage = '';
            showPrintOutput = false;
            provisionedCards = [];
            previousReveals = new Map();
            lostRevealMessage = '';
            rows = [createEmptyRow()];
            batchOperationId = crypto.randomUUID();
        }
        wasOpen = isOpen;
    });

    function addRow() {
        rows = [...rows, createEmptyRow()];
    }

    function removeRow(index) {
        if (rows.length <= 1) {
            rows = [createEmptyRow()];
            return;
        }
        rows = rows.filter((_, i) => i !== index);
    }

    async function commit() {
        if (busy) return;
        const validRows = rows.filter((r) => r.name.trim().length > 0);
        if (validRows.length === 0) {
            errorMessage = 'Enter at least one staff name to onboard.';
            return;
        }

        busy = true;
        errorMessage = '';
        successMessage = '';

        try {
            const response = await fetch('/staff/personnel/fast-provision', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    destination_cycle_code: targetCycle,
                    voucher_mode: voucherMode,
                    operation_id: batchOperationId,
                    recipients: validRows,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message ?? 'Failed to provision personnel roster.');
            }

            const results = (data.rows ?? []).map((result) => {
                const input = validRows.find((row) => row.operation_id === result.operation_id) ?? {};
                return { ...input, ...result };
            });
            const hadLostReveal = results.some(
                (row) => row.status === 'already_committed' && !previousReveals.has(row.operation_id),
            );
            for (const result of results) {
                if (result.status === 'issued') {
                    previousReveals.set(result.operation_id, result);
                }
            }
            previousReveals = new Map(previousReveals);
            provisionedCards = results.map((result) => ({
                ...result,
                ...(previousReveals.get(result.operation_id) ?? {}),
                status: result.status,
                message: result.message,
            }));
            showPrintOutput = true;
            const issuedCount = results.filter((row) => row.status === 'issued').length;
            const failedCount = results.filter((row) => row.status === 'failed' || row.status === 'conflicted').length;
            successMessage = issuedCount > 0
                ? `Successfully onboarded ${issuedCount} staff account${issuedCount === 1 ? '' : 's'}.`
                : '';
            lostRevealMessage = hadLostReveal
                ? 'A previous request committed these accounts, but one-time voucher details cannot be recovered. Use the authorized replacement voucher workflow if needed.'
                : '';
            const messages = [];
            if (failedCount > 0) {
                messages.push(`${failedCount} row${failedCount === 1 ? '' : 's'} need correction. Review each result below.`);
            }
            errorMessage = messages.join(' ');
            onSuccess();
        } catch (e) {
            errorMessage = e instanceof Error ? e.message : 'Provisioning failed.';
        } finally {
            busy = false;
        }
    }
    function correctProvisioningRow(card) {
        const index = rows.findIndex((row) => row.operation_id === card.operation_id);
        const row = index >= 0
            ? { ...rows[index] }
            : { ...createEmptyRow(), ...card };
        const availableRoles = roleOptionsForOffice(row.office);
        row.roles = (row.roles ?? []).filter((role) => availableRoles.some((option) => option.name === role));
        if (row.roles.length === 0 && availableRoles.length > 0) {
            row.roles = [availableRoles[0].name];
        }
        if (row.office !== 'program') {
            row.program_codes = [];
        }
        if (card.status === 'conflicted') {
            row.operation_id = crypto.randomUUID();
            batchOperationId = crypto.randomUUID();
        }
        rows = index >= 0
            ? rows.map((current, rowIndex) => (rowIndex === index ? row : current))
            : [...rows, row];
        showPrintOutput = false;
    }


    async function printSlips(format = 'pdf') {
        if (!provisionedCards.some((row) => row.token)) return;
        try {
            const response = await fetch('/staff/personnel/vouchers/batch/print', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: format === 'pdf' ? 'application/pdf' : 'text/html',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    format,
                    layout: 'grouped',
                    cards: provisionedCards.filter((row) => row.token).map((row) => ({
                        user_id: row.user_id,
                        reference: row.public_reference,
                        token: row.token,
                        fallback: row.fallback,
                        hash: row.print_hash,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error('Could not render printable voucher slips.');
            }

            const blob = await response.blob();
            if (printObjectUrl) URL.revokeObjectURL(printObjectUrl);
            printObjectUrl = URL.createObjectURL(blob);

            if (format === 'html') {
                window.open(printObjectUrl, '_blank', 'noopener');
            } else {
                const link = document.createElement('a');
                link.href = printObjectUrl;
                link.download = `NEXIAM-Staff-Slips-${targetCycle}.pdf`;
                link.click();
            }
        } catch (e) {
            errorMessage = e instanceof Error ? e.message : 'Printing failed.';
        }
    }
</script>

<Dialog.Root bind:open>
    <Dialog.Portal>
        <Dialog.Overlay />
        <Dialog.Content class="border-border/80 max-h-[90dvh] overflow-y-auto border p-6 shadow-2xl sm:max-w-3xl">
            <Dialog.Header class="border-border/60 border-b pb-3">
                <Dialog.Title class="text-lg font-bold">Fast Onboard Personnel</Dialog.Title>
                <Dialog.Description>
                    Define incoming staff accounts for {targetCycle || 'active cycle'} and issue private single-use activation vouchers immediately.
                </Dialog.Description>
            </Dialog.Header>

            {#if successMessage}
                <div class="my-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                    <Check class="size-4 shrink-0" />
                    <span>{successMessage}</span>
                </div>
            {/if}

            {#if errorMessage}
                <div class="my-4 rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive flex items-center gap-2" role="alert">
                    <AlertTriangle class="size-4 shrink-0" />
                    <span>{errorMessage}</span>
                </div>
            {/if}

            {#if !showPrintOutput}
                <div class="my-4 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/40 pb-3">
                        <div class="flex items-center gap-2">
                            <label for="fast-destination-cycle" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Cycle:
                            </label>
                            <select
                                id="fast-destination-cycle"
                                bind:value={targetCycle}
                                class="border-input bg-background h-8 rounded-md border px-2.5 text-xs font-medium"
                            >
                                {#each cycles as cycle (cycle.code)}
                                    <option value={cycle.code}>
                                        {cycle.name} ({cycle.code}) {cycle.is_active ? '· Active' : ''}
                                    </option>
                                {/each}
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label for="fast-voucher-lifetime" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Voucher Mode:
                            </label>
                            <select
                                id="fast-voucher-lifetime"
                                bind:value={voucherMode}
                                class="border-input bg-background h-8 rounded-md border px-2.5 text-xs font-medium"
                            >
                                <option value="standard_24h">24 Hours</option>
                                <option value="extended_7d">7 Days</option>
                                <option value="extended_30d">30 Days</option>
                                <option value="extended_180d">180 Days (Recommended)</option>
                                {#if can_full_hr}
                                    <option value="never_expires_single_use">No Expiry (HR Single-Use)</option>
                                {/if}
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        {#each rows as row, index (row.operation_id)}
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 rounded-lg border border-border/60 bg-muted/20 p-2.5">
                                <span class="text-xs font-mono text-muted-foreground w-5 shrink-0">{index + 1}.</span>
                                <div class="flex-1 w-full sm:w-auto">
                                    <Input
                                        placeholder="Full Name"
                                        aria-label={`Full name for row ${index + 1}`}
                                        bind:value={row.name}
                                        class="h-8 text-xs bg-background"
                                    />
                                </div>
                                <div class="w-full sm:w-32">
                                    <select
                                        bind:value={row.office}
                                        aria-label={`Office for row ${index + 1}`}
                                        onchange={(event) => {
                                            row.office = event.currentTarget.value;
                                            const choices = roleOptionsForOffice(row.office);
                                            row.roles = choices.length > 0 ? [choices[0].name] : [];
                                            row.program_codes = [];
                                        }}
                                        class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                    >
                                        {#if can_full_hr}
                                            <option value="admin">Admin</option>
                                            <option value="registrar">Registrar</option>
                                            <option value="guidance">Guidance</option>
                                            <option value="program">Academic</option>
                                        {:else}
                                            <option value={actor_office}>{actor_office}</option>
                                        {/if}
                                    </select>
                                </div>
                                <div class="w-full sm:w-40">
                                    <select
                                        value={row.roles[0] ?? ''}
                                        aria-label={`Role for row ${index + 1}`}
                                        onchange={(event) => (row.roles = [event.currentTarget.value])}
                                        class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                    >
                                        {#each roleOptionsForOffice(row.office) as role (role.name)}
                                            <option value={role.name}>{role.display_name}</option>
                                        {/each}
                                    </select>
                                </div>
                                {#if row.office === 'program'}
                                    <div class="w-full sm:w-32">
                                        <select
                                            value={row.program_codes[0] ?? ''}
                                            aria-label={`Program assignments for row ${index + 1}`}
                                            onchange={(event) => (row.program_codes = event.currentTarget.value ? [event.currentTarget.value] : [])}
                                            class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                        >
                                            <option value="">Select Program</option>
                                            {#each programs as prog (prog.code)}
                                                <option value={prog.code}>{prog.name ?? prog.code}</option>
                                            {/each}
                                        </select>
                                    </div>
                                    <p class="text-[11px] text-muted-foreground">
                                        Dean designation remains in the existing authorized Department assignment workflow.
                                    </p>
                                {/if}
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                    onclick={() => removeRow(index)}
                                    aria-label="Remove row"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        {/each}
                    </div>

                    <Button type="button" size="sm" variant="outline" class="w-full" onclick={addRow}>
                        <Plus class="mr-1.5 size-3.5" />
                        Add Another Staff Row
                    </Button>
                </div>

                <Dialog.Footer class="border-border/60 border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <Button type="button" variant="ghost" onclick={() => (open = false)}>Cancel</Button>
                    <Button
                        type="button"
                        onclick={commit}
                        disabled={busy || rows.filter((r) => r.name.trim().length > 0).length === 0}
                    >
                        {busy ? 'Provisioning...' : `Commit & Generate Vouchers (${rows.filter((r) => r.name.trim().length > 0).length})`}
                    </Button>
                </Dialog.Footer>
            {:else}
                <div class="my-4 space-y-3" aria-live="polite">
                    {#if lostRevealMessage}
                        <p class="rounded-md border border-amber-500/30 bg-amber-500/10 p-3 text-sm" role="status">{lostRevealMessage}</p>
                    {/if}
                    {#each provisionedCards as card (card.operation_id)}
                        <div class="rounded-md border border-border/60 p-3 space-y-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <strong class="text-sm font-semibold">{card.name ?? 'Staff account'}</strong>
                                <Badge variant="outline" class="text-xs">{card.office ?? 'Personnel'}</Badge>
                            </div>
                            <p class="text-xs font-medium">{card.status.replaceAll('_', ' ')}</p>
                            {#if card.message}<p class="text-sm text-muted-foreground">{card.message}</p>{/if}
                            {#if card.status === 'issued' || card.token}
                                <p class="font-mono text-xs text-muted-foreground">Ref: {card.public_reference} · Fallback PIN: {card.fallback}</p>
                                <p class="text-[11px] text-muted-foreground">Expiry: {card.expires_at_display}</p>
                            {:else if card.status === 'failed' || card.status === 'conflicted'}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onclick={() => correctProvisioningRow(card)}
                                >
                                    Correct this row
                                </Button>
                            {/if}
                        </div>
                    {/each}
                </div>

                <Dialog.Footer class="border-border/60 border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button type="button" onclick={() => printSlips('html')}>
                            <Printer class="mr-2 size-4" />
                            Print Grouped Slips (A4)
                        </Button>
                        <Button type="button" variant="outline" onclick={() => printSlips('pdf')}>
                            <Download class="mr-2 size-4" />
                            Download PDF Grid
                        </Button>
                    </div>
                    <Button type="button" variant="ghost" onclick={() => (open = false)}>Close</Button>
                </Dialog.Footer>
            {/if}
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>
