<script>
    let { comparison, workingCopy = null } = $props();
    let changedOnly = $state(false);
    let showPairedValues = $state(false);
    let showWorkingCopy = $state(false);

    const baseline = $derived(comparison?.baseline ?? null);
    const revision = $derived(comparison?.revision ?? null);
    const activityLabels = $derived(new Map((comparison?.input_schema?.activities ?? baseline?.input_schema?.activities ?? []).map((activity) => [activity.id, activity.label])));
    const sections = $derived.by(() => {
        if (!baseline?.content || !revision?.content) return [];
        const featureByKey = new Map([...(baseline.features ?? []), ...(revision.features ?? [])].map((feature) => [feature.key, feature]));
        const profileRows = [];
        for (const level of ['1', '2', '3', '4', '5']) {
            for (const [key, feature] of featureByKey) {
                const before = baseline.content.tiers?.[level]?.values?.[key] ?? null;
                const after = revision.content.tiers?.[level]?.values?.[key] ?? null;
                profileRows.push({ id: `${level}:${key}`, label: `${profileLabel(level)} · ${feature.label ?? key}`, before, after, category: 'model cell' });
            }
        }
        const rangeKeys = new Set();
        for (const band of ['honors', 'regular', 'cutoff']) {
            for (const key of Object.keys(baseline.content.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
            for (const key of Object.keys(revision.content.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
        }
        const rangeRows = [...rangeKeys].map((identity) => {
            const [band, key] = identity.split(':');
            return { id: identity, label: `${bandLabel(band)} · ${featureByKey.get(key)?.label ?? key}`, before: baseline.content.authoring?.ranges?.[band]?.[key] ?? null, after: revision.content.authoring?.ranges?.[band]?.[key] ?? null, category: 'authored range' };
        });
        const metadataRows = [
            { id: 'interest_activities', label: 'Preferred career activities · metadata only', before: baseline.content.authoring?.interest_activities ?? null, after: revision.content.authoring?.interest_activities ?? null, category: 'programme metadata' },
            { id: 'preferred_strands', label: 'Preferred school strands · metadata only', before: baseline.content.authoring?.preferred_strands ?? null, after: revision.content.authoring?.preferred_strands ?? null, category: 'programme metadata' },
        ];
        const preparationKeys = new Set([
            ...Object.keys(baseline.content.authoring?.preparation ?? {}),
            ...Object.keys(revision.content.authoring?.preparation ?? {}),
        ]);
        for (const key of preparationKeys) {
            metadataRows.push({
                id: `range-preparation:${key}`,
                label: `${featureByKey.get(key)?.label ?? key} · common-range preparation provenance`,
                before: baseline.content.authoring?.preparation?.[key] ?? null,
                after: revision.content.authoring?.preparation?.[key] ?? null,
                category: 'range preparation provenance',
            });
        }
        for (const level of ['1', '2', '3', '4', '5']) {
            metadataRows.push({
                id: `profile_activity_mapping:${level}`,
                label: `${profileLabel(level)} · reviewed activity mapping`,
                before: baseline.content.authoring?.profile_activity_mapping?.[level] ?? null,
                after: revision.content.authoring?.profile_activity_mapping?.[level] ?? null,
                category: 'model input · reviewed mapping',
            });
        }
        const sourceRows = [...featureByKey].map(([key, feature]) => ({
            id: key,
            before: feature.source_definition ?? null,
            after: (revision.features ?? []).find((item) => item.key === key)?.source_definition ?? null,
            category: 'subject/source metadata',
        }));
        return [
            { id: 'model-cells', label: 'Model example cells', rows: profileRows },
            { id: 'ranges', label: 'Authored ranges', rows: rangeRows },
            { id: 'subject-sources', label: 'Subject and source definitions', rows: sourceRows },
            { id: 'riasec', label: 'RIASEC and programme metadata', rows: metadataRows },
        ].map((section) => ({ ...section, rows: section.rows.map((row) => ({ ...row, changed: JSON.stringify(row.before) !== JSON.stringify(row.after) })).filter((row) => !changedOnly || row.changed) }));
    });
    const workingSections = $derived.by(() => {
        if (!revision?.content || !workingCopy) return [];
        const features = revision.features ?? baseline?.features ?? [];
        const profileRows = [];
        for (const level of ['1', '2', '3', '4', '5']) {
            for (const feature of features) {
                const key = feature.key;
                profileRows.push({
                    id: `${level}:${key}`,
                    label: `${profileLabel(level)} · ${feature.label ?? key}`,
                    before: revision.content.tiers?.[level]?.values?.[key] ?? null,
                    after: workingCopy.tiers?.[level]?.values?.[key] ?? null,
                    category: 'model cell',
                });
            }
        }
        const rangeKeys = new Set();
        for (const band of ['honors', 'regular', 'cutoff']) {
            for (const key of Object.keys(revision.content.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
            for (const key of Object.keys(workingCopy.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
        }
        const rangeRows = [...rangeKeys].map((identity) => {
            const [band, key] = identity.split(':');
            return {
                id: identity,
                label: `${bandLabel(band)} · ${features.find((feature) => feature.key === key)?.label ?? key}`,
                before: revision.content.authoring?.ranges?.[band]?.[key] ?? null,
                after: workingCopy.authoring?.ranges?.[band]?.[key] ?? null,
                category: 'authored range',
            };
        });
        const metadataRows = [
            { id: 'interest_activities', label: 'Preferred career activities · metadata only', before: revision.content.authoring?.interest_activities ?? null, after: workingCopy.authoring?.interest_activities ?? null, category: 'programme metadata' },
            { id: 'preferred_strands', label: 'Preferred school strands · metadata only', before: revision.content.authoring?.preferred_strands ?? null, after: workingCopy.authoring?.preferred_strands ?? null, category: 'programme metadata' },
        ];
        const preparationKeys = new Set([
            ...Object.keys(revision.content.authoring?.preparation ?? {}),
            ...Object.keys(workingCopy.authoring?.preparation ?? {}),
        ]);
        for (const key of preparationKeys) {
            metadataRows.push({
                id: `range-preparation:${key}`,
                label: `${features.find((feature) => feature.key === key)?.label ?? key} · common-range preparation provenance`,
                before: revision.content.authoring?.preparation?.[key] ?? null,
                after: workingCopy.authoring?.preparation?.[key] ?? null,
                category: 'range preparation provenance',
            });
        }
        for (const level of ['1', '2', '3', '4', '5']) {
            metadataRows.push({
                id: `profile_activity_mapping:${level}`,
                label: `${profileLabel(level)} · reviewed activity mapping`,
                before: revision.content.authoring?.profile_activity_mapping?.[level] ?? null,
                after: workingCopy.authoring?.profile_activity_mapping?.[level] ?? null,
                category: 'model input · reviewed mapping',
            });
        }
        return [
            { id: 'model-cells', label: 'Model example cells', rows: profileRows },
            { id: 'ranges', label: 'Authored ranges', rows: rangeRows },
            { id: 'riasec', label: 'RIASEC and programme metadata', rows: metadataRows },
        ].map((section) => ({ ...section, rows: section.rows.map((row) => ({ ...row, changed: JSON.stringify(row.before) !== JSON.stringify(row.after) })).filter((row) => !changedOnly || row.changed) }));
    });
    const changeCount = (section) => section.rows.filter((row) => row.changed).length;

    function profileLabel(level) {
        return ({ '1': 'Honors', '2': 'Regular', '3': 'Cutoff', '4': 'Other-strand', '5': 'Below-cutoff' })[level] ?? level;
    }

    function bandLabel(band) {
        return ({ honors: 'Honors', regular: 'Regular', cutoff: 'Cutoff' })[band] ?? band;
    }

    function renderValue(value) {
        if (value === null || value === undefined) return 'Missing';
        if (typeof value === 'boolean') return value ? 'Selected' : 'Not selected';
        if (Array.isArray(value)) return value.length ? value.map((item) => typeof item === 'string' ? activityLabels.get(item) ?? item : renderValue(item)).join(', ') : 'Reviewed empty';
        if (typeof value === 'object') return Object.entries(value).map(([key, item]) => `${key}: ${renderValue(item)}`).join(' · ');
        return String(value);
    }
</script>

<section class="space-y-4" aria-label="Saved benchmark comparison">
    <header class="space-y-1">
        <h2 class="text-base font-semibold">Compare exact benchmark versions</h2>
        <p class="text-sm text-muted-foreground">{baseline?.in_use ? 'In-use baseline' : 'Published but not in use'} #{baseline?.id} v{baseline?.version} against saved revision #{revision?.id} v{revision?.version}. This comparison is read-only.</p>
    </header>
    <div class="flex flex-wrap items-center gap-4 border-y py-3 text-sm">
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" bind:checked={changedOnly} />Changed only</label>
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" bind:checked={showPairedValues} />Show paired values</label>
        {#if workingCopy}<label class="flex min-h-11 items-center gap-2"><input type="checkbox" bind:checked={showWorkingCopy} />Include unsaved working copy</label>{/if}
    </div>
    {#each sections as section (section.id)}
        {@const totalChanges = changeCount(section)}
        <details class="rounded-lg border">
            <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-3 text-sm font-medium"><span>{section.label}</span><span class="text-xs text-muted-foreground">{totalChanges} {totalChanges === 1 ? 'change' : 'changes'}</span></summary>
            <div class="divide-y px-3">
                {#each section.rows as row (row.id)}
                    <article class="grid gap-2 py-3 md:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)]">
                        <div class="min-w-0"><h3 class="text-sm font-medium">{row.label}</h3><p class="text-xs text-muted-foreground">{row.category}</p></div>
                        {#if showPairedValues}
                            <div class="grid min-w-0 gap-2 sm:grid-cols-2">
                                <p class="min-w-0 rounded-md bg-muted/40 p-2 text-sm"><span class="block text-xs text-muted-foreground">{baseline?.in_use ? 'In use' : 'Published · not in use'}</span><span class="break-words">{renderValue(row.before)}</span></p>
                                <p class="min-w-0 rounded-md bg-muted/40 p-2 text-sm"><span class="block text-xs text-muted-foreground">Saved revision r{revision?.id}</span><span class="break-words">{renderValue(row.after)}</span></p>
                            </div>
                        {/if}
                    </article>
                {:else}
                    <p class="py-3 text-sm text-muted-foreground">No differences in this section.</p>
                {/each}
            </div>
        </details>
    {/each}
    {#if showWorkingCopy && workingCopy}
        <div class="space-y-3 rounded-lg border border-dashed p-3">
            <h3 class="text-sm font-medium">Unsaved working copy · local changes, not saved</h3>
            <p class="text-xs text-muted-foreground">This is a separate diff from the exact saved revision above. It does not merge, save, restore, or publish values.</p>
            {#each workingSections as section (section.id)}
                {@const totalChanges = changeCount(section)}
                <details class="rounded-md border">
                    <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-3 text-sm font-medium"><span>{section.label}</span><span class="text-xs text-muted-foreground">{totalChanges} {totalChanges === 1 ? 'change' : 'changes'}</span></summary>
                    <div class="divide-y px-3">
                        {#each section.rows as row (row.id)}
                            <article class="grid gap-2 py-3 md:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)]">
                                <div class="min-w-0"><h4 class="text-sm font-medium">{row.label}</h4><p class="text-xs text-muted-foreground">{row.category}</p></div>
                                {#if showPairedValues}<div class="grid min-w-0 gap-2 sm:grid-cols-2"><p class="min-w-0 rounded-md bg-muted/40 p-2 text-sm"><span class="block text-xs text-muted-foreground">Saved revision r{revision?.id}</span><span class="break-words">{renderValue(row.before)}</span></p><p class="min-w-0 rounded-md bg-muted/40 p-2 text-sm"><span class="block text-xs text-muted-foreground">Unsaved working copy</span><span class="break-words">{renderValue(row.after)}</span></p></div>{/if}
                            </article>
                        {:else}<p class="py-3 text-sm text-muted-foreground">No differences in this section.</p>{/each}
                    </div>
                </details>
            {/each}
        </div>
    {/if}
</section>
