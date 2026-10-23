<script>
    import { Link, useForm } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import DocumentRequirementCard from '@/Components/Admission/DocumentRequirementCard.svelte';

    let { requirement = null, tracks = [], kinds = [], defaults = {}, indexUrl = '/setup/requirements' } = $props();
    const editing = requirement !== null;
    const form = useForm(editing ? {
        label: requirement.label,
        applicant_track: requirement.applicant_track,
        item_kind: requirement.item_kind,
        sort_order: requirement.sort_order,
        is_mandatory: Boolean(requirement.is_mandatory),
        is_active: Boolean(requirement.is_active),
        expected_version: requirement.version ?? 1,
    } : {
        code: '',
        label: '',
        applicant_track: defaults.applicant_track ?? 'freshman',
        item_kind: defaults.item_kind ?? 'document',
        sort_order: defaults.sort_order ?? 100,
        is_mandatory: defaults.is_mandatory ?? true,
        is_active: defaults.is_active ?? true,
    });

    function submit() {
        if (editing) {
            form.put(`/setup/requirements/${requirement.id}`);
            return;
        }
        form.post('/setup/requirements');
    }

    function normalizeOptions(options) {
        if (Array.isArray(options)) return options.map((item) => typeof item === 'string' ? { value: item, label: item } : { value: item.value ?? item.id, label: item.label ?? item.name ?? item.value });
        if (options && typeof options === 'object') return Object.entries(options).map(([value, label]) => ({ value, label: String(label) }));
        return [];
    }
    const trackList = $derived(normalizeOptions(tracks));
    const kindList = $derived(normalizeOptions(kinds));
    const trackLabel = $derived(trackList.find((track) => track.value === form.applicant_track)?.label ?? form.applicant_track);
</script>

<form onsubmit={(event) => { event.preventDefault(); submit(); }} class="space-y-6">
    {#if form.errors.expected_version}
        <div role="alert" class="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
            This requirement changed since you opened it. Reload the page to review the latest version before saving.
            <p class="mt-1">{form.errors.expected_version}</p>
        </div>
    {/if}
    {#if editing}
        <div role="note" class="rounded-lg border border-border bg-muted/40 p-4 text-sm text-muted-foreground">
            Changes apply to future checklist initializations and do not alter already-inspected applicant checklists.
        </div>
    {/if}
    <div class="grid min-w-0 gap-6 lg:grid-cols-12">
        <section class="min-w-0 rounded-xl border border-border bg-card p-5 lg:col-span-8" aria-label="Requirement details">
            <div class="space-y-5">
                {#if editing}
                    <div class="space-y-2">
                        <Label for="requirement-code">Code</Label>
                        <Input id="requirement-code" value={requirement.code} readonly aria-describedby="requirement-code-help" class="select-text" />
                        <p id="requirement-code-help" class="text-sm text-muted-foreground">Code cannot be changed after creation.</p>
                    </div>
                {:else}
                    <div class="space-y-2">
                        <Label for="requirement-code">Code</Label>
                        <Input id="requirement-code" bind:value={form.code} autocomplete="off" />
                        {#if form.errors.code}<p class="text-sm text-destructive">{form.errors.code}</p>{/if}
                    </div>
                {/if}
                <div class="space-y-2">
                    <Label for="requirement-label">Requirement label</Label>
                    <Input id="requirement-label" bind:value={form.label} required />
                    {#if form.errors.label}<p class="text-sm text-destructive">{form.errors.label}</p>{/if}
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <Label for="requirement-track">Applicant track</Label>
                            <InfoPopover
                                title="Track applicability"
                                content="Assigns this checklist item to all applicants or only to a specific intake track (e.g., Freshman vs. Transferee)."
                            />
                        </div>
                        <select id="requirement-track" bind:value={form.applicant_track} class="border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm">
                            {#each trackList as track (track.value)}<option value={track.value}>{track.label}</option>{/each}
                        </select>
                        {#if form.errors.applicant_track}<p class="text-sm text-destructive">{form.errors.applicant_track}</p>{/if}
                    </div>
                    <div class="space-y-2">
                        <Label for="requirement-kind">Item kind</Label>
                        <select id="requirement-kind" bind:value={form.item_kind} class="border-input bg-background min-h-11 w-full rounded-md border px-3 text-sm">
                            {#each kindList as kind (kind.value)}<option value={kind.value}>{kind.label}</option>{/each}
                        </select>
                        {#if form.errors.item_kind}<p class="text-sm text-destructive">{form.errors.item_kind}</p>{/if}
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="requirement-sort">Sort order</Label>
                    <Input id="requirement-sort" type="number" min="0" bind:value={form.sort_order} />
                    {#if form.errors.sort_order}<p class="text-sm text-destructive">{form.errors.sort_order}</p>{/if}
                </div>
                <div class="flex flex-wrap gap-6">
                    <span class="inline-flex items-center gap-1.5">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={form.is_mandatory} />Mandatory</label>
                        <InfoPopover
                            title="Clearance gate"
                            content="Mandatory items must be verified by the Registrar before Step 7 Admission Clearance can be confirmed."
                        />
                    </span>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" bind:checked={form.is_active} />Active</label>
                </div>
                <div class="flex flex-wrap items-center gap-3 border-t border-border pt-5">
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : editing ? 'Save changes' : 'Create requirement'}</Button>
                    <Link href={indexUrl} class="text-sm text-muted-foreground hover:text-foreground">Cancel</Link>
                </div>
            </div>
        </section>
        <aside class="min-w-0 lg:col-span-4" aria-label="Live requirement preview">
            <p class="mb-3 text-sm font-medium text-foreground">Preview</p>
            <DocumentRequirementCard
                label={form.label || 'Untitled requirement'}
                code={editing ? requirement.code : form.code || null}
                itemKind={form.item_kind}
                isMandatory={form.is_mandatory}
                trackLabel={trackLabel}
            />
        </aside>
    </div>
</form>
