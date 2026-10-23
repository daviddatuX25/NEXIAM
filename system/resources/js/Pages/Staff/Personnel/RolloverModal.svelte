<script>
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { AlertTriangle, Check, RefreshCw } from 'lucide-svelte';

    let {
        open = $bindable(false),
        cycles = [],
        personnel = [],
        programs = [],
        initialTargetIds = [],
        initialDisposition = 'retain',
        onSuccess = () => {},
    } = $props();

    let targetCycle = $state('');
    let busy = $state(false);
    let errorMessage = $state('');
    let successMessage = $state('');

    // Only eligible active personnel can participate in a rollover.
    let modalTargets = $derived.by(() => {
        const eligible = personnel.filter((person) =>
            person.status === 'active'
            && person.can_manage_user === true
            && person.is_self !== true
        );
        if (initialTargetIds.length > 0) {
            return eligible.filter((person) => initialTargetIds.includes(person.id));
        }
        return eligible;
    });

    let dispositions = $state(new Map());
    let wasOpen = false;

    $effect(() => {
        const isOpen = open;
        if (isOpen && !wasOpen) {
            targetCycle = cycles[0]?.code ?? '';
            errorMessage = '';
            successMessage = '';
            const nextMap = new Map();
            modalTargets.forEach((person) => {
                nextMap.set(person.id, {
                    user_id: person.id,
                    disposition: initialDisposition,
                    program_codes: [...(person.program_codes ?? [])],
                });
            });
            dispositions = nextMap;
        }
        wasOpen = isOpen;
    });

    function updateItem(userId, field, value) {
        const current = dispositions.get(userId) ?? { user_id: userId, disposition: 'retain' };
        dispositions.set(userId, { ...current, [field]: value });
        dispositions = new Map(dispositions);
    }

    async function commit() {
        if (busy || modalTargets.length === 0) return;
        busy = true;
        errorMessage = '';
        successMessage = '';

        const payload = modalTargets.map((p) => {
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
                    dispositions: payload,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message ?? 'Failed to apply cycle dispositions.');
            }

            successMessage = `Dispositions applied: ${data.retained} retained, ${data.rotated} rotated, ${data.archived} archived.`;
            onSuccess();
            setTimeout(() => {
                open = false;
            }, 1200);
        } catch (e) {
            errorMessage = e instanceof Error ? e.message : 'Cycle disposition failed.';
        } finally {
            busy = false;
        }
    }
</script>

<Dialog.Root bind:open>
    <Dialog.Portal>
        <Dialog.Overlay />
        <Dialog.Content class="border-border/80 min-w-0 max-h-[90dvh] overflow-x-hidden overflow-y-auto border p-6 shadow-2xl sm:max-w-3xl [&>*]:min-w-0">
            <Dialog.Header class="border-border/60 border-b pb-3">
                <Dialog.Title class="text-lg font-bold">Academic Cycle Personnel Rollover</Dialog.Title>
                <Dialog.Description>
                    Review staff retention and deactivations for the target cycle. Retained staff preserve credentials; archived staff lose active access.
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

            <div class="my-4 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <label for="modal-destination-cycle" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Target Academic Cycle:
                    </label>
                    <select
                        id="modal-destination-cycle"
                        bind:value={targetCycle}
                        class="border-input bg-background h-9 w-full min-w-0 max-w-full rounded-md border px-3 text-sm font-medium"
                    >
                        {#each cycles as cycle (cycle.code)}
                            <option value={cycle.code}>
                                {cycle.name} ({cycle.code}) {cycle.is_active ? '· Current Active' : ''}
                            </option>
                        {/each}
                    </select>
                </div>

                <div class="rounded-lg border border-border/60 divide-y divide-border/60 overflow-hidden">
                    <div class="bg-muted/40 px-3.5 py-2 text-xs font-semibold text-muted-foreground flex items-center justify-between">
                        <span>Staff Member ({modalTargets.length})</span>
                        <span>Target Disposition</span>
                    </div>

                    <div class="max-h-72 overflow-y-auto divide-y divide-border/40">
                        {#each modalTargets as person (person.id)}
                            {@const item = dispositions.get(person.id) ?? { disposition: 'retain' }}
                            <div class="p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                                <div class="min-w-0">
                                    <strong class="text-sm font-semibold block break-words">{person.name}</strong>
                                    <span class="text-xs text-muted-foreground block truncate">
                                        <span class="capitalize">{person.office}</span>
                                        {#if person.department}· {person.department}{/if}
                                        {#if person.program_codes?.length}· {person.program_codes.join(', ')}{/if}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <select
                                        value={item.disposition}
                                        aria-label={`Disposition for ${person.name}`}
                                        onchange={(e) => updateItem(person.id, 'disposition', e.currentTarget.value)}
                                        class="border-input bg-background h-8 rounded-md border px-2.5 text-xs font-medium"
                                    >
                                        <option value="retain">Retain Active Access</option>
                                        <option value="rotate">Rotate Assignment</option>
                                        <option value="archive">Archive Account</option>
                                    </select>
                                    {#if item.disposition === 'rotate'}
                                        <select
                                            multiple
                                            aria-label={`Programs for ${person.name}`}
                                            onchange={(event) => updateItem(person.id, 'program_codes', Array.from(event.currentTarget.selectedOptions, (option) => option.value))}
                                            class="border-input bg-background h-16 min-w-36 rounded-md border px-2 text-xs"
                                        >
                                            {#each programs as prog (prog.code)}
                                                <option value={prog.code} selected={item.program_codes?.includes(prog.code)}>
                                                    {prog.name ?? prog.code}
                                                </option>
                                            {/each}
                                        </select>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>

            <Dialog.Footer class="border-border/60 border-t pt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                <Button type="button" variant="ghost" onclick={() => (open = false)}>Cancel</Button>
                <Button type="button" onclick={commit} disabled={busy || modalTargets.length === 0}>
                    <RefreshCw class="mr-1.5 size-3.5 {busy ? 'animate-spin' : ''}" />
                    {busy ? 'Applying...' : `Confirm Rollover (${modalTargets.length})`}
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>
