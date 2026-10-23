<script>
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import * as Table from '@/Components/ui/table';
    import { ArrowDown, ArrowUp, Layers, Pencil, Plus, Trash2 } from 'lucide-svelte';

    let {
        subtests = $bindable([]),
        metrics = $bindable([]),
        readOnly = false,
        onDirty = () => {},
    } = $props();

    let dialogOpen = $state(false);
    let deleteTarget = $state(null);
    let editingCode = $state(null);
    let code = $state('');
    let name = $state('');
    let responseMode = $state('keyed_items');
    let defaultTokens = $state('A, B, C, D');
    let itemCount = $state(30);

    const inputClass = 'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

    function makeItem(subtestCode, index, tokens) {
        return {
            item_code: `${subtestCode}_${index + 1}`,
            local_number: String(index + 1),
            display_order: index + 1,
            is_scored: true,
            option_tokens: tokens,
        };
    }

    function beginAdd() {
        if (readOnly) return;
        editingCode = null;
        code = '';
        name = '';
        responseMode = 'keyed_items';
        defaultTokens = 'A, B, C, D';
        itemCount = 30;
        dialogOpen = true;
    }

    function beginEdit(subtest) {
        if (readOnly) return;
        editingCode = subtest.code;
        code = subtest.code ?? '';
        name = subtest.name ?? '';
        responseMode = subtest.response_mode ?? 'keyed_items';
        const itemTokens = subtest.items?.[0]?.option_tokens;
        defaultTokens = Array.isArray(itemTokens) ? itemTokens.join(', ') : 'A, B, C, D';
        itemCount = subtest.response_mode === 'recorded_measure' ? 0 : subtest.item_count ?? subtest.items?.length ?? 0;
        dialogOpen = true;
    }
    function saveSubtest() {
        if (readOnly || !code.trim() || !name.trim()) return;
        const normalizedCode = code.trim().toUpperCase();
        const existingIndex = editingCode === null ? -1 : subtests.findIndex((entry) => entry.code === editingCode);
        if (subtests.some((entry, index) => entry.code === normalizedCode && index !== existingIndex)) return;

        const tokens = defaultTokens.split(',').map((token) => token.trim().toUpperCase()).filter(Boolean);
        const previous = existingIndex >= 0 ? subtests[existingIndex] : null;
        const count = responseMode === 'recorded_measure' ? 0 : Number(itemCount);
        if (!Number.isInteger(count) || count < 0 || count > 500) return;
        const existingItems = responseMode === 'recorded_measure' ? [] : previous?.items ?? [];
        const items = existingItems.slice(0, count).map((item) => ({ ...item }));
        for (let index = items.length; index < count; index++) {
            items.push(makeItem(normalizedCode, index, tokens));
        }
        const nextSubtest = {
            ...(previous ?? {}),
            code: normalizedCode,
            name: name.trim(),
            response_mode: responseMode,
            item_order_policy: previous?.item_order_policy ?? 'fixed',
            items,
        };
        if (previous && previous.code !== normalizedCode) {
            metrics = metrics.map((metric) => metric.subtest_code === previous.code
                ? { ...metric, subtest_code: normalizedCode }
                : metric);
        }
        if (existingIndex >= 0) {
            subtests = subtests.map((entry, index) => index === existingIndex ? nextSubtest : entry);
        } else {
            subtests = [...subtests, nextSubtest];
        }
        onDirty(true);
        dialogOpen = false;
    }

    function move(index, offset) {
        if (readOnly) return;
        const target = index + offset;
        if (target < 0 || target >= subtests.length) return;
        const reordered = [...subtests];
        [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
        subtests = reordered;
        onDirty(true);
    }

    function deleteSubtest(subtest) {
        if (readOnly || metrics.some((metric) => metric.subtest_code === subtest.code)) return;
        deleteTarget = subtest;
    }

    function confirmDelete() {
        if (readOnly || !deleteTarget || metrics.some((metric) => metric.subtest_code === deleteTarget.code)) return;
        subtests = subtests.filter((entry) => entry !== deleteTarget);
        deleteTarget = null;
        onDirty(true);
    }

    function summary(subtest) {
        if (subtest.response_mode === 'recorded_measure') {
            return `Range: ${subtest.minimum ?? 0}-${subtest.maximum ?? subtest.max_score ?? 50} pts`;
        }
        const items = subtest.items ?? [];
        const tokens = [...new Set(items.flatMap((item) => item.option_tokens ?? []))];
        return `${items.length || 0} items${tokens.length ? ` (${tokens.join(',')})` : ''}`;
    }

</script>
<section id="subtests" class="rounded-xl border border-border bg-card p-5 shadow-xs space-y-4 sm:p-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><Layers class="size-5" aria-hidden="true" /></div>
            <div>
                <h2 class="flex items-center gap-1.5 text-base font-semibold">
                    <span>Subtest Sections</span>
                    <InfoPopover
                        title="Exam subtest inventory"
                        content="Each subtest represents an administered section of the exam (e.g., Vocabulary 40 items, Computation 30 items, Spatial 20 items, Word Comparison 100 items)."
                    />
                </h2>
                <p class="text-sm text-muted-foreground">Configure sections and response items.</p>
            </div>
            <Badge variant="secondary">{subtests.length}</Badge>
        </div>
        <Button type="button" class="gap-2" disabled={readOnly} onclick={beginAdd}><Plus class="size-4" />Add Subtest</Button>
    </header>

    {#if subtests.length === 0}
        <div class="rounded-lg border border-dashed border-border px-5 py-10 text-center">
            <Layers class="mx-auto mb-3 size-8 text-muted-foreground" aria-hidden="true" />
            <p class="font-medium">No subtests yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Add a subtest section to start defining this instrument.</p>
        </div>
    {:else}
        <div class="overflow-x-auto rounded-lg border border-border">
            <Table.Root aria-label="Subtests" class="w-full min-w-[760px] text-sm">
                <Table.Header class="bg-muted/40"><Table.Row>
                    <Table.Head class="w-20">#</Table.Head><Table.Head>Code</Table.Head><Table.Head>Name</Table.Head><Table.Head>Mode</Table.Head><Table.Head>Items / Tokens</Table.Head><Table.Head class="text-right">Actions</Table.Head>
                </Table.Row></Table.Header>
                <Table.Body>
                    {#each subtests as subtest, index (subtest.code)}
                        {@const reference = metrics.find((metric) => metric.subtest_code === subtest.code)}
                        <Table.Row class="transition-colors hover:bg-muted/40">
                            <Table.Cell><div class="flex items-center gap-1"><span class="w-5 font-mono tabular-nums">{index + 1}</span><Button variant="ghost" size="icon" class="size-8" aria-label="Move {subtest.name} up" disabled={readOnly || index === 0} onclick={() => move(index, -1)}><ArrowUp class="size-4" /></Button><Button variant="ghost" size="icon" class="size-8" aria-label="Move {subtest.name} down" disabled={readOnly || index === subtests.length - 1} onclick={() => move(index, 1)}><ArrowDown class="size-4" /></Button></div></Table.Cell>
                            <Table.Cell class="font-mono">{subtest.code}</Table.Cell>
                            <Table.Cell class="font-medium">{subtest.name}</Table.Cell>
                            <Table.Cell><Badge class={subtest.response_mode === 'recorded_measure' ? 'border-purple-500/20 bg-purple-500/10 font-medium text-purple-700 dark:text-purple-300' : 'border-sky-500/20 bg-sky-500/10 font-medium text-sky-700 dark:text-sky-300'}>{subtest.response_mode === 'recorded_measure' ? 'Recorded measure' : 'Keyed items'}</Badge></Table.Cell>
                            <Table.Cell class="text-muted-foreground">{summary(subtest)}</Table.Cell>
                            <Table.Cell><div class="flex justify-end gap-1"><Button variant="ghost" size="sm" class="gap-1" disabled={readOnly} onclick={() => beginEdit(subtest)}><Pencil class="size-4" />Edit</Button><Button variant="ghost" size="sm" class="gap-1 text-destructive hover:text-destructive" disabled={readOnly || Boolean(reference)} title={reference ? `Referenced by ${reference.code}` : 'Delete subtest'} onclick={() => deleteSubtest(subtest)}><Trash2 class="size-4" />Delete</Button>{#if reference}<span class="self-center text-xs text-muted-foreground">Referenced by {reference.code}</span>{/if}</div></Table.Cell>
                        </Table.Row>
                    {/each}
                </Table.Body>
            </Table.Root>
        </div>
    {/if}
</section>

<Dialog.Root bind:open={dialogOpen}>
    <Dialog.Content class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <Dialog.Header>
            <Dialog.Title>{editingCode === null ? 'Add Subtest' : 'Edit Subtest'}</Dialog.Title>
            <Dialog.Description>Set the section identity and response format.</Dialog.Description>
        </Dialog.Header>
        <form class="space-y-4 py-2" onsubmit={(event) => { event.preventDefault(); saveSubtest(); }}>
            <div class="space-y-2"><Label for="subtest-code">Code</Label><Input id="subtest-code" bind:value={code} class="font-mono uppercase" maxlength="32" /></div>
            <div class="space-y-2"><Label for="subtest-name">Name</Label><Input id="subtest-name" bind:value={name} /></div>
            <fieldset class="space-y-2"><legend class="inline-flex items-center gap-1.5 text-sm font-medium"><span>Response Mode</span><InfoPopover title="Keyed items vs. Recorded measure" content="Keyed items define multiple-choice questions with response tokens (e.g., A–D or S/D). Recorded measure captures a direct numeric or timed count without individual item bubbles." /></legend><label class="flex items-center gap-2 text-sm"><input type="radio" name="response-mode" value="keyed_items" bind:group={responseMode} />Keyed items</label><label class="flex items-center gap-2 text-sm"><input type="radio" name="response-mode" value="recorded_measure" bind:group={responseMode} />Recorded measure</label></fieldset>
            <div class="space-y-2"><div class="flex items-center gap-1.5"><Label for="subtest-default-tokens">Default Option Tokens</Label><InfoPopover title="Response choice tokens" content="Comma-separated answer choices per item (e.g., 'A, B, C, D', 'A-B, A-C, A-D, B-C, B-D, C-D', or 'S, D')." /></div><Input id="subtest-default-tokens" bind:value={defaultTokens} placeholder="A, B, C, D" /></div>
            <div class="space-y-2"><div class="flex items-center gap-1.5"><Label for="subtest-item-count">Item Count (maximum 500)</Label><InfoPopover title="Scored item ceiling" content="Sets the number of scored items in this subtest, which automatically determines the maximum raw score (0 to Item Count) for direct subtest metrics." /></div><Input id="subtest-item-count" type="number" min="0" max="500" step="1" bind:value={itemCount} disabled={responseMode === 'recorded_measure'} /></div>
            <Dialog.Footer><Button type="button" variant="outline" onclick={() => (dialogOpen = false)}>Cancel</Button><Button type="submit">Save Subtest</Button></Dialog.Footer>
        </form>
    </Dialog.Content>
</Dialog.Root>
<Dialog.Root open={deleteTarget !== null} onOpenChange={(open) => { if (!open) deleteTarget = null; }}>
    <Dialog.Content>
        <Dialog.Header>
            <Dialog.Title>Delete Subtest</Dialog.Title>
            <Dialog.Description>Delete {deleteTarget?.name ?? 'this subtest'}? This action cannot be undone.</Dialog.Description>
        </Dialog.Header>
        <Dialog.Footer>
            <Button type="button" variant="outline" onclick={() => (deleteTarget = null)}>Cancel</Button>
            <Button type="button" variant="destructive" onclick={confirmDelete}>Delete Subtest</Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
