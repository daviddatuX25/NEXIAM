<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import * as Table from '@/Components/ui/table';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { Input } from '@/Components/ui/input';
    import {
        AlertTriangle,
        ArrowRight,
        Check,
        Download,
        Plus,
        Printer,
        RefreshCw,
        Trash2,
        Users,
    } from 'lucide-svelte';

    let {
        cycles = [],
        target_cycle_code = '',
        personnel = [],
        programs = [],
        can_full_hr = false,
        actor_office = 'admin',
    } = $props();

    let targetCycle = $state(target_cycle_code || (cycles[0]?.code ?? ''));
    let selectedIds = $state([]);
    let dispositions = $state(
        new Map(
            personnel.map((p) => [
                p.id,
                {
                    user_id: p.id,
                    disposition: 'retain',
                    program_codes: [...(p.program_codes ?? [])],
                },
            ]),
        ),
    );

    const breadcrumbs = [
        { label: 'Staff Workspace', href: '/staff' },
        { label: 'Personnel Directory', href: '/staff/personnel' },
        { label: 'Academic Cycle Rollover' },
    ];

    let activeTab = $state('rollover'); // 'rollover' | 'onboarding'
    let busy = $state(false);
    let successMessage = $state('');
    let errorMessage = $state('');

    // Fast-entry state
    let voucherMode = $state(can_full_hr ? 'never_expires_single_use' : 'extended_180d');
    let fastRows = $state([
        createEmptyRow(),
    ]);

    // Output modal state
    let outputModalOpen = $state(false);
    let provisionedCards = $state([]);
    let printObjectUrl = $state(null);

    function createEmptyRow() {
        return {
            operation_id: crypto.randomUUID(),
            name: '',
            office: can_full_hr ? 'program' : actor_office,
            roles: can_full_hr ? ['program_head'] : (actor_office === 'registrar' ? ['registrar_clerk'] : ['guidance_support_staff']),
            program_codes: [],
        };
    }

    function addRow() {
        fastRows = [...fastRows, createEmptyRow()];
    }

    function removeRow(index) {
        if (fastRows.length <= 1) {
            fastRows = [createEmptyRow()];
            return;
        }
        fastRows = fastRows.filter((_, i) => i !== index);
    }

    function updateDisposition(userId, field, value) {
        const current = dispositions.get(userId) ?? { user_id: userId, disposition: 'retain' };
        dispositions.set(userId, { ...current, [field]: value });
        dispositions = new Map(dispositions);
    }

    function toggleSelectAll() {
        if (selectedIds.length === personnel.length) {
            selectedIds = [];
        } else {
            selectedIds = personnel.map((p) => p.id);
        }
    }

    function toggleSelect(id) {
        selectedIds = selectedIds.includes(id)
            ? selectedIds.filter((x) => x !== id)
            : [...selectedIds, id];
    }

    function bulkSetDisposition(disposition) {
        selectedIds.forEach((id) => {
            updateDisposition(id, 'disposition', disposition);
        });
    }

    async function commitRollover() {
        if (busy || personnel.length === 0) return;
        busy = true;
        errorMessage = '';
        successMessage = '';

        const payloadDispositions = personnel.map((p) => {
            const entry = dispositions.get(p.id) ?? { user_id: p.id, disposition: 'retain' };
            return {
                user_id: p.id,
                disposition: entry.disposition,
                program_codes: entry.program_codes ?? [],
            };
        });

        try {
            const response = await fetch('/staff/personnel/cycle-dispositions', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    destination_cycle_code: targetCycle,
                    dispositions: payloadDispositions,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message ?? 'Failed to apply academic cycle dispositions.');
            }

            successMessage = `Cycle dispositions successfully committed: ${data.retained} retained, ${data.rotated} rotated, ${data.archived} archived.`;
            selectedIds = [];
        } catch (e) {
            errorMessage = e instanceof Error ? e.message : 'Failed to apply academic cycle dispositions.';
        } finally {
            busy = false;
        }
    }

    async function commitFastProvision() {
        if (busy) return;
        const validRows = fastRows.filter((r) => r.name.trim().length > 0);
        if (validRows.length === 0) {
            errorMessage = 'Enter at least one staff name to provision.';
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
                    operation_id: crypto.randomUUID(),
                    recipients: validRows,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message ?? 'Failed to provision staff roster.');
            }

            provisionedCards = (data.rows ?? []).filter((r) => r.status === 'issued');
            outputModalOpen = true;
            fastRows = [createEmptyRow()];
            successMessage = `Successfully provisioned ${provisionedCards.length} new staff accounts.`;
        } catch (e) {
            errorMessage = e instanceof Error ? e.message : 'Failed to provision staff roster.';
        } finally {
            busy = false;
        }
    }

    async function printGroupedSlips(format = 'pdf') {
        if (provisionedCards.length === 0) return;
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
                    cards: provisionedCards.map((row) => ({
                        user_id: row.user_id,
                        reference: row.public_reference,
                        token: row.token,
                        fallback: row.fallback,
                        hash: row.print_hash,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error('Could not generate printed voucher slips.');
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

<svelte:head>
    <title>Academic Cycle Rollover · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6 max-w-7xl mx-auto">
        <PageHeaderBar
            title="Academic Cycle Rollover & Staff Provisioning"
            description="Manage personnel retention, rotations, and deactivations for upcoming academic cycles, or quickly onboard incoming staff rosters."
            tourKey="page:Staff/Personnel/Rollover"
        />

        {#if successMessage}
            <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
                <Check class="size-4 shrink-0" />
                <span>{successMessage}</span>
            </div>
        {/if}

        {#if errorMessage}
            <div class="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive flex items-center gap-2" role="alert">
                <AlertTriangle class="size-4 shrink-0" />
                <span>{errorMessage}</span>
            </div>
        {/if}

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/60 pb-4">
            <div class="flex items-center gap-3">
                <label for="destination-cycle-select" class="text-sm font-medium shrink-0">Destination Academic Cycle:</label>
                <select
                    id="destination-cycle-select"
                    bind:value={targetCycle}
                    class="border-input bg-background min-h-9 rounded-md border px-3 text-sm font-medium"
                >
                    {#each cycles as cycle (cycle.code)}
                        <option value={cycle.code}>
                            {cycle.name} ({cycle.code}) {cycle.is_active ? '· Current Active' : ''}
                        </option>
                    {/each}
                </select>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant={activeTab === 'rollover' ? 'default' : 'outline'}
                    size="sm"
                    onclick={() => (activeTab = 'rollover')}
                >
                    <Users class="mr-1.5 size-4" />
                    Review Incumbents ({personnel.length})
                </Button>
                <Button
                    type="button"
                    variant={activeTab === 'onboarding' ? 'default' : 'outline'}
                    size="sm"
                    onclick={() => (activeTab = 'onboarding')}
                >
                    <Plus class="mr-1.5 size-4" />
                    Fast Roster Additions
                </Button>
            </div>
        </div>

        {#if activeTab === 'rollover'}
            <Card.Root>
                <Card.Header class="pb-3">
                    <Card.Title class="text-base font-semibold">Incumbent Personnel Retention & Archival</Card.Title>
                    <Card.Description>
                        Select how each staff member transitions into cycle {targetCycle}. Retained personnel keep their login credentials; archived accounts have active access revoked while preserving audit history.
                    </Card.Description>
                </Card.Header>
                <Card.Content class="p-0">
                    {#if personnel.length === 0}
                        <div class="p-8 text-center text-sm text-muted-foreground">
                            No active staff members found in your manageable scope.
                        </div>
                    {:else}
                        <!-- Desktop Table -->
                        <div class="hidden md:block overflow-x-auto">
                            <Table.Root>
                                <Table.Header>
                                    <Table.Row>
                                        <Table.Head class="w-10">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.length === personnel.length && personnel.length > 0}
                                                onchange={toggleSelectAll}
                                                class="rounded border-border"
                                                aria-label="Select all personnel"
                                            />
                                        </Table.Head>
                                        <Table.Head>Staff Member</Table.Head>
                                        <Table.Head>Office / Dept</Table.Head>
                                        <Table.Head>Role</Table.Head>
                                        <Table.Head>Cycle Status</Table.Head>
                                        <Table.Head class="min-w-44">Target Disposition</Table.Head>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each personnel as person (person.id)}
                                        {@const currentDisp = dispositions.get(person.id)?.disposition ?? 'retain'}
                                        <Table.Row class="hover:bg-muted/40">
                                            <Table.Cell>
                                                <input
                                                type="checkbox"
                                                checked={selectedIds.includes(person.id)}
                                                onchange={() => toggleSelect(person.id)}
                                                class="rounded border-border"
                                                aria-label={`Select ${person.name}`}
                                            />
                                            </Table.Cell>
                                            <Table.Cell>
                                                <span class="font-medium text-sm block">{person.name}</span>
                                                <span class="text-xs text-muted-foreground font-mono">{person.email ?? 'No email on file'}</span>
                                            </Table.Cell>
                                            <Table.Cell class="text-xs">
                                                <span class="capitalize">{person.office}</span>
                                                {#if person.department}
                                                    <span class="text-muted-foreground block text-[11px]">{person.department}</span>
                                                {/if}
                                            </Table.Cell>
                                            <Table.Cell class="text-xs">
                                                {#each person.roles as role}
                                                    <Badge variant="outline" class="mr-1 text-[11px]">
                                                        {role.replace('_', ' ')}
                                                    </Badge>
                                                {/each}
                                                {#if person.program_codes?.length}
                                                    <span class="text-muted-foreground block text-[11px] mt-0.5">
                                                        {person.program_codes.join(', ')}
                                                    </span>
                                                {/if}
                                            </Table.Cell>
                                            <Table.Cell class="text-xs">
                                                <Badge variant={person.active_cycle_code === targetCycle ? 'success' : 'muted'} class="text-[11px]">
                                                    {person.active_cycle_code ?? 'None'}
                                                </Badge>
                                            </Table.Cell>
                                            <Table.Cell>
                                                <select
                                                    value={currentDisp}
                                                    onchange={(e) => updateDisposition(person.id, 'disposition', e.currentTarget.value)}
                                                    class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full max-w-48"
                                                >
                                                    <option value="retain">Retain Active Access</option>
                                                    <option value="rotate">Rotate Assignment</option>
                                                    <option value="archive">Archive Account</option>
                                                </select>
                                            </Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        </div>

                        <!-- Mobile Cards (<= 390px parity) -->
                        <div class="md:hidden divide-y divide-border/60">
                            {#each personnel as person (person.id)}
                                {@const currentDisp = dispositions.get(person.id)?.disposition ?? 'retain'}
                                <div class="p-3.5 space-y-2.5">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-start gap-2 min-w-0">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.includes(person.id)}
                                                onchange={() => toggleSelect(person.id)}
                                                class="rounded border-border mt-1"
                                                aria-label={`Select ${person.name}`}
                                            />
                                            <div class="min-w-0">
                                                <strong class="text-sm font-semibold block truncate">{person.name}</strong>
                                                <span class="text-xs text-muted-foreground font-mono block truncate">{person.email ?? 'No email on file'}</span>
                                            </div>
                                        </div>
                                        <Badge variant={person.active_cycle_code === targetCycle ? 'success' : 'muted'} class="text-[10px] shrink-0">
                                            {person.active_cycle_code ?? 'No cycle'}
                                        </Badge>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 text-xs pt-1 border-t border-border/40">
                                        <span class="text-muted-foreground capitalize">{person.office}</span>
                                        <select
                                            value={currentDisp}
                                            onchange={(e) => updateDisposition(person.id, 'disposition', e.currentTarget.value)}
                                            class="border-input bg-background h-8 rounded-md border px-2 text-xs"
                                        >
                                            <option value="retain">Retain</option>
                                            <option value="rotate">Rotate</option>
                                            <option value="archive">Archive</option>
                                        </select>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </Card.Content>
                <Card.Footer class="border-t border-border/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        Retained staff automatically carry forward credentials. Archived staff lose access immediately.
                    </p>
                    <Button
                        type="button"
                        onclick={commitRollover}
                        disabled={busy || personnel.length === 0}
                    >
                        {busy ? 'Applying...' : `Commit Cycle Dispositions (${personnel.length})`}
                    </Button>
                </Card.Footer>
            </Card.Root>

            {#if selectedIds.length > 0}
                <BulkActionBar
                    count={selectedIds.length}
                    hint={`${selectedIds.length} staff selected for batch disposition`}
                    onClear={() => (selectedIds = [])}
                >
                    {#snippet actions()}
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onclick={() => bulkSetDisposition('retain')}
                            >
                                Retain Selected
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                onclick={() => bulkSetDisposition('archive')}
                            >
                                Archive Selected
                            </Button>
                        </div>
                    {/snippet}
                </BulkActionBar>
            {/if}

        {:else}
            <!-- Fast Roster Onboarding Tab -->
            <Card.Root>
                <Card.Header class="pb-3">
                    <Card.Title class="text-base font-semibold">Fast Roster Onboarding</Card.Title>
                    <Card.Description>
                        Define incoming personnel for {targetCycle}. Each provisioned account receives a random initial secret and a single-use voucher for immediate printing.
                    </Card.Description>
                </Card.Header>
                <Card.Content class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <label for="voucher-mode-select" class="text-xs font-medium">Voucher Mode:</label>
                            <select
                                id="voucher-mode-select"
                                bind:value={voucherMode}
                                class="border-input bg-background h-8 rounded-md border px-2 text-xs"
                            >
                                <option value="standard_24h">24 Hours</option>
                                <option value="extended_7d">7 Days</option>
                                <option value="extended_30d">30 Days</option>
                                <option value="extended_180d">180 Days (Recommended)</option>
                                {#if can_full_hr}
                                    <option value="never_expires_single_use">No Calendar Expiry (HR Single-Use)</option>
                                {/if}
                            </select>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onclick={addRow}
                        >
                            <Plus class="mr-1.5 size-3.5" />
                            Add Staff Row
                        </Button>
                    </div>

                    <div class="space-y-3">
                        {#each fastRows as row, index (row.operation_id)}
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2.5 rounded-lg border border-border/60 bg-muted/20 p-3">
                                <span class="text-xs font-mono text-muted-foreground w-6 shrink-0">{index + 1}.</span>
                                <div class="flex-1 w-full sm:w-auto">
                                    <Input
                                        placeholder="Full Name"
                                        bind:value={row.name}
                                        class="h-8 text-xs bg-background"
                                    />
                                </div>
                                <div class="w-full sm:w-36">
                                    <select
                                        bind:value={row.office}
                                        class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                    >
                                        {#if can_full_hr}
                                            <option value="admin">Admin</option>
                                            <option value="registrar">Registrar</option>
                                            <option value="guidance">Guidance</option>
                                            <option value="program">Academic</option>
                                        {:else}
                                            <option value={actor_office} selected>{actor_office}</option>
                                        {/if}
                                    </select>
                                </div>
                                <div class="w-full sm:w-44">
                                    <select
                                        value={row.roles[0] ?? ''}
                                        onchange={(e) => (row.roles = [e.currentTarget.value])}
                                        class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                    >
                                        {#if row.office === 'program'}
                                            <option value="program_head">Program Head</option>
                                            <option value="dean">Dean</option>
                                        {:else if row.office === 'registrar'}
                                            <option value="registrar_clerk">Registrar Clerk</option>
                                            <option value="registrar_support_staff">Registrar Support</option>
                                        {:else if row.office === 'guidance'}
                                            <option value="guidance_support_staff">Guidance Support</option>
                                            <option value="guidance_clerk">Guidance Clerk</option>
                                        {:else}
                                            <option value="institutional_admin">Admin Office Staff</option>
                                        {/if}
                                    </select>
                                </div>
                                {#if row.office === 'program'}
                                    <div class="w-full sm:w-36">
                                        <select
                                            value={row.program_codes[0] ?? ''}
                                            onchange={(e) => (row.program_codes = [e.currentTarget.value])}
                                            class="border-input bg-background h-8 rounded-md border px-2 text-xs w-full"
                                        >
                                            <option value="">Select Program</option>
                                            {#each programs as program}
                                                <option value={program.code}>{program.code}</option>
                                            {/each}
                                        </select>
                                    </div>
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
                </Card.Content>
                <Card.Footer class="border-t border-border/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-xs text-muted-foreground">
                        Each new staff account receives a single-use QR slip immediately ready for printing.
                    </p>
                    <Button
                        type="button"
                        onclick={commitFastProvision}
                        disabled={busy || fastRows.filter((r) => r.name.trim().length > 0).length === 0}
                    >
                        {busy ? 'Provisioning...' : `Commit & Generate Vouchers (${fastRows.filter((r) => r.name.trim().length > 0).length})`}
                    </Button>
                </Card.Footer>
            </Card.Root>
        {/if}
    </div>

    <!-- Output Modal: Grouped Voucher Printing -->
    <Dialog.Root open={outputModalOpen} onOpenChange={(val) => { if (!val) outputModalOpen = false; }}>
        <Dialog.Portal>
            <Dialog.Overlay />
            <Dialog.Content class="border-border/80 max-h-[90dvh] overflow-y-auto border p-6 shadow-2xl sm:max-w-2xl">
                <Dialog.Header class="border-border/60 border-b pb-3">
                    <Dialog.Title class="text-lg font-bold">Grouped Staff Activation Slips</Dialog.Title>
                    <Dialog.Description>
                        {provisionedCards.length} staff accounts have been provisioned for {targetCycle}. Print or download these slips now; secrets are displayed once only.
                    </Dialog.Description>
                </Dialog.Header>

                <div class="my-4 space-y-3" aria-live="polite">
                    {#each provisionedCards as card (card.user_id)}
                        <div class="rounded-md border border-border/60 p-3 space-y-1">
                            <div class="flex items-center justify-between">
                                <strong class="text-sm font-semibold">{card.name}</strong>
                                <Badge variant="outline" class="text-xs">{card.office}</Badge>
                            </div>
                            <p class="font-mono text-xs text-muted-foreground">
                                Ref: {card.public_reference} · Fallback PIN: {card.fallback}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                Expiry: {card.expires_at_display}
                            </p>
                        </div>
                    {/each}
                </div>

                <Dialog.Footer class="border-border/60 border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            onclick={() => printGroupedSlips('html')}
                            disabled={busy}
                        >
                            <Printer class="mr-2 size-4" />
                            Print Grouped Slips
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => printGroupedSlips('pdf')}
                            disabled={busy}
                        >
                            <Download class="mr-2 size-4" />
                            Download PDF Grid
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (outputModalOpen = false)}
                    >
                        Close
                    </Button>
                </Dialog.Footer>
            </Dialog.Content>
        </Dialog.Portal>
    </Dialog.Root>
</StaffLayout>
