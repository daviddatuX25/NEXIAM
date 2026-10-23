<script>
    import BentoCard from '@/Components/kit/BentoCard.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import * as Table from '@/Components/ui/table';

    let {
        subtests = $bindable([]),
        metrics = [],
        connections = {},
        errors = {},
    } = $props();

    let editingIndex = $state(null);
    let sectionDraft = $state(null);
    let itemCount = $state(0);
    let optionTokens = $state('A, B, C, D');
    let dialogOpen = $state(false);
    let searchQuery = $state('');
    let selectedStatus = $state('All');
    let viewMode = $state('table');

    const statuses = ['All', 'Mapped', 'Partial', 'Unmapped', 'Manual'];

    const filteredSubtests = $derived(subtests.filter((subtest) => {
        const query = searchQuery.trim().toLowerCase();
        const matchesQuery = !query || `${subtest.name ?? ''} ${subtest.code ?? ''}`.toLowerCase().includes(query);
        const connection = connectionFor(subtest);
        return matchesQuery && (selectedStatus === 'All' || statusFor(connection) === selectedStatus);
    }));

    function nextCode(prefix, rows) {
        let sequence = 1;
        while (rows.some((row) => row.code === `${prefix}_${sequence}`)) {
            sequence += 1;
        }
        return `${prefix}_${sequence}`;
    }

    function connectionFor(subtest) {
        return connections[subtest.code] ?? subtest.connection ?? null;
    }

    function statusFor(connection) {
        if (!connection) return 'Unmapped';
        return ({ mapped: 'Mapped', partial: 'Partial', unmapped: 'Unmapped', manual: 'Manual' })[connection.status] ?? 'Unmapped';
    }

    function statusVariant(status) {
        return ({ Mapped: 'default', Partial: 'warning', Unmapped: 'secondary', Manual: 'outline' })[status] ?? 'secondary';
    }

    function responseLabel(subtest) {
        return subtest.response_mode === 'recorded_measure' ? 'Recorded measure' : 'Keyed items';
    }

    function scoredItems(subtest) {
        return subtest.items?.filter((item) => item.is_scored).length ?? 0;
    }

    function connectionLabel(connection) {
        if (!connection) return 'No answer sheet connection';
        const sheet = `${connection.sheet_title} v${connection.version_number}`;
        const mapped = `${connection.pages_summary} (${connection.mapped_items_count}/${connection.total_items_count})`;
        return `${sheet} · ${mapped}`;
    }

    function openEditor(index = null) {
        editingIndex = index;
        const existing = index === null ? null : subtests[index];
        sectionDraft = existing
            ? { ...existing }
            : {
                  code: nextCode('SECTION', subtests),
                  name: '',
                  display_order: subtests.length + 1,
                  response_mode: 'keyed_items',
                  item_order_policy: 'fixed',
                  items: [],
              };
        itemCount = existing?.items?.length ?? 0;
        optionTokens = (existing?.items?.[0]?.option_tokens ?? ['A', 'B', 'C', 'D']).join(', ');
        dialogOpen = true;
    }

    function resizeItems(existingItems, requestedCount, tokens) {
        const count = Math.max(0, Math.min(1000, Math.floor(Number(requestedCount) || 0)));
        const normalizedTokens = tokens.split(',').map((token) => token.trim()).filter(Boolean);
        const items = existingItems.slice(0, count).map((item) => ({ ...item }));
        while (items.length < count) {
            items.push({
                item_code: nextCode('ITEM', items),
                local_number: String(items.length + 1),
                display_order: items.length + 1,
                is_scored: true,
                option_tokens: [...normalizedTokens],
            });
        }
        return items.map((item, index) => ({ ...item, display_order: index + 1, option_tokens: [...normalizedTokens] }));
    }

    function saveSection() {
        if (!sectionDraft) return;
        const next = [...subtests];
        const priorItems = editingIndex === null ? [] : (next[editingIndex]?.items ?? []);
        next[editingIndex ?? next.length] = {
            ...sectionDraft,
            name: sectionDraft.name.trim(),
            code: sectionDraft.code.trim(),
            display_order: (editingIndex ?? next.length) + 1,
            items: resizeItems(priorItems, itemCount, optionTokens),
        };
        subtests = next;
        closeEditor();
    }

    function closeEditor() {
        dialogOpen = false;
        sectionDraft = null;
        editingIndex = null;
    }

    function isReferenced(subtest) {
        return metrics.some((metric) => metric.subtest_code === subtest.code);
    }

    function removeSection(subtest) {
        subtests = subtests.filter((row) => row.code !== subtest.code).map((row, index) => ({ ...row, display_order: index + 1 }));
    }
</script>

<section class="space-y-4" aria-label="Subtests">
    {#if errors.subtests}<p class="text-sm text-destructive" role="alert">{errors.subtests}</p>{/if}
    <div class="flex flex-wrap items-center gap-2">
        <input aria-label="Search subtests" class="border-input bg-background min-h-10 flex-1 min-w-[280px] rounded-md border px-3 text-sm" placeholder="Search subtests…" bind:value={searchQuery} />
        <select aria-label="Filter by answer sheet status" class="border-input bg-background min-h-10 rounded-md border px-3 text-sm" bind:value={selectedStatus}>
            {#each statuses as status}<option value={status}>{status}</option>{/each}
        </select>
        <Button type="button" onclick={() => openEditor()}>+ Add Subtest</Button>
    </div>

    <SwitchableListView bind:viewMode cardsInset={false}>
        {#snippet table()}
            <Table.Root class="w-full min-w-[920px] text-sm">
                <Table.Header class="bg-muted/40 border-b border-border"><Table.Row>
                    <Table.Head>SUBTEST / CODE</Table.Head><Table.Head>RESPONSE</Table.Head><Table.Head>ITEMS</Table.Head><Table.Head>ANSWER SHEET CONNECTION</Table.Head><Table.Head>STATUS</Table.Head><Table.Head class="text-right">ACTIONS</Table.Head>
                </Table.Row></Table.Header>
                <Table.Body>
                    {#each filteredSubtests as subtest (subtest.code)}
                        {@const connection = connectionFor(subtest)}
                        {@const status = statusFor(connection)}
                        <Table.Row>
                            <Table.Cell><span class="font-medium">{subtest.name || 'Untitled subtest'}</span><span class="text-muted-foreground ml-2 font-mono text-xs">{subtest.code}</span></Table.Cell>
                            <Table.Cell>{responseLabel(subtest)}</Table.Cell><Table.Cell>{scoredItems(subtest)} items</Table.Cell>
                            <Table.Cell class="max-w-80 truncate">{connectionLabel(connection)}</Table.Cell>
                            <Table.Cell><Badge variant={statusVariant(status)}>{status}</Badge></Table.Cell>
                            <Table.Cell class="text-right"><div class="inline-flex items-center gap-1"><Button type="button" variant="ghost" size="sm" onclick={() => openEditor(subtests.indexOf(subtest))}>Edit</Button><ActionDropdown items={[{ label: 'Delete', disabled: isReferenced(subtest), onclick: () => removeSection(subtest) }]} /></div></Table.Cell>
                        </Table.Row>
                    {/each}
                </Table.Body>
            </Table.Root>
        {/snippet}
        {#snippet cards()}
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                {#each filteredSubtests as subtest (subtest.code)}
                    {@const connection = connectionFor(subtest)}
                    {@const status = statusFor(connection)}
                    <BentoCard class="space-y-3 p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-semibold">{subtest.name || 'Untitled subtest'}</h3><p class="font-mono text-xs text-muted-foreground">{subtest.code}</p></div><Badge variant={statusVariant(status)}>{status}</Badge></div>
                        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm"><dt class="text-muted-foreground">Response</dt><dd>{responseLabel(subtest)}</dd><dt class="text-muted-foreground">Items</dt><dd>{scoredItems(subtest)} items</dd><dt class="text-muted-foreground">Answer sheet</dt><dd class="min-w-0 break-words">{connectionLabel(connection)}</dd></dl>
                        <div class="flex justify-end gap-1"><Button type="button" variant="ghost" size="sm" onclick={() => openEditor(subtests.indexOf(subtest))}>Edit</Button><ActionDropdown items={[{ label: 'Delete', disabled: isReferenced(subtest), onclick: () => removeSection(subtest) }]} /></div>
                    </BentoCard>
                {/each}
            </div>
        {/snippet}
    </SwitchableListView>

    <Dialog.Root bind:open={dialogOpen}>
        <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <Dialog.Header><Dialog.Title>{editingIndex === null ? 'Add Subtest' : 'Edit Subtest'}</Dialog.Title></Dialog.Header>
            {#if sectionDraft}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5"><label for="subtest-name" class="text-sm font-medium">Name</label><input id="subtest-name" bind:value={sectionDraft.name} maxlength="255" required class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm" /></div>
                    <div class="space-y-1.5"><label for="subtest-code" class="text-sm font-medium">Code</label><input id="subtest-code" bind:value={sectionDraft.code} maxlength="32" required class="border-input bg-background min-h-10 w-full rounded-md border px-3 font-mono text-sm" /></div>
                    <div class="space-y-1.5"><div class="flex items-center gap-1.5"><label for="subtest-response" class="text-sm font-medium">Response mode</label><InfoPopover title="Keyed items vs. Recorded measure" content="Keyed items define multiple-choice questions with response tokens (e.g., A–D or S/D). Recorded measure captures a direct numeric or timed count without individual item bubbles." /></div><select id="subtest-response" bind:value={sectionDraft.response_mode} class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"><option value="keyed_items">Keyed items</option><option value="recorded_measure">Recorded measure</option></select></div>
                    <div class="space-y-1.5"><div class="flex items-center gap-1.5"><label for="subtest-count" class="text-sm font-medium">Items count</label><InfoPopover title="Scored item ceiling" content="Total scored items in this subtest (0–1000). Determines the maximum raw score for direct subtest metrics." /></div><input id="subtest-count" type="number" min="0" max="1000" bind:value={itemCount} class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm" /></div>
                    <div class="space-y-1.5 sm:col-span-2"><div class="flex items-center gap-1.5"><label for="subtest-options" class="text-sm font-medium">Option tokens</label><InfoPopover title="Response choice tokens" content="Comma-separated answer choices per item (e.g., 'A, B, C, D', 'A-B, A-C, A-D, B-C, B-D, C-D', or 'S, D')." /></div><input id="subtest-options" bind:value={optionTokens} class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm" placeholder="A, B, C, D" /></div>
                </div>
                <Dialog.Footer><Button type="button" variant="outline" onclick={closeEditor}>Cancel</Button><Button type="button" onclick={saveSection}>Apply</Button></Dialog.Footer>
            {/if}
        </Dialog.Content>
    </Dialog.Root>
</section>
