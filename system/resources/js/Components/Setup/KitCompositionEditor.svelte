<script>
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';

    let { composition = null, preparationId = null, onSave = () => {} } = $props();
    let dialogOpen = $state(false);
    let activeGroupUid = $state(null);
    let draftRows = $state([]);
    let saving = $state(false);
    let requestError = $state(null);
    let activeTrigger = $state(null);
    let selectedBookletSectionUid = $state('');
    let selectedFormalSubtestId = $state('');
    let practiceItemId = $state('');
    let openedRevision = $state(null);
    let openedComponentVector = $state(null);
    let saveAttemptSignature = $state(null);
    let saveAttemptIdempotencyKey = $state(null);

    const groups = $derived(composition?.groups ?? []);
    const activeGroup = $derived(groups.find((group) => group.physical_section_uid === activeGroupUid) ?? null);
    const activeLocations = $derived(activeGroup?.physical_locations ?? []);
    const bookletSections = $derived(activeGroup?.booklet_sections ?? []);
    const formalSubtests = $derived(activeGroup?.formal_subtests ?? []);
    const selectedBookletSection = $derived(bookletSections.find((section) => String(section.booklet_section_uid) === String(selectedBookletSectionUid)) ?? null);
    const selectedFormalSubtest = $derived(formalSubtests.find((subtest) => String(subtest.formal_subtest_id) === String(selectedFormalSubtestId)) ?? null);
    const selectedBookletItems = $derived(selectedBookletSection?.items ?? []);
    const selectedFormalItems = $derived((selectedFormalSubtest?.items ?? []).filter((item) => item.is_scored !== false));
    const practiceRows = $derived(draftRows.filter((row) => row.disposition === 'nonphysical_practice'));
    const practiceItems = $derived(composition?.nonphysical_practice_items ?? []);
    const staleContext = $derived(
        composition?.revision !== openedRevision ||
        JSON.stringify(composition?.component_vector) !== JSON.stringify(openedComponentVector),
    );
    const canMatchInOrder = $derived.by(() => {
        if (!activeLocations.length || !selectedBookletSection || !selectedFormalSubtest) return false;
        if (selectedBookletItems.length !== activeLocations.length || selectedFormalItems.length !== activeLocations.length) return false;
        return activeLocations.every((location, index) => {
            const physicalTokens = location.option_tokens ?? [];
            return JSON.stringify(physicalTokens) === JSON.stringify(selectedBookletItems[index]?.option_tokens ?? []) &&
                JSON.stringify(physicalTokens) === JSON.stringify(selectedFormalItems[index]?.option_tokens ?? []);
        });
    });

    function invalidateSaveAttempt() {
        saveAttemptSignature = null;
        saveAttemptIdempotencyKey = null;
    }

    function rowFor(location) {
        return draftRows.find((row) => row.physical_item_uid === location.physical_item_uid) ?? null;
    }

    function locateBookletSection(itemId) {
        return bookletSections.find((section) => (section.items ?? []).some((item) => String(item.booklet_item_id) === String(itemId)));
    }

    function openGroup(group, trigger) {
        activeTrigger = trigger;
        activeGroupUid = group.physical_section_uid;
        draftRows = (composition?.rows ?? []).map((row) => ({ ...row, option_tokens: [...(row.option_tokens ?? [])] }));
        requestError = null;
        saveAttemptSignature = null;
        saveAttemptIdempotencyKey = null;
        openedRevision = composition?.revision ?? null;
        openedComponentVector = composition?.component_vector ?? null;
        const savedRow = (composition?.rows ?? []).find((row) => (group.physical_locations ?? []).some((location) => location.physical_item_uid === row.physical_item_uid));
        selectedBookletSectionUid = savedRow ? String(locateBookletSection(savedRow.booklet_item_id)?.booklet_section_uid ?? '') : '';
        selectedFormalSubtestId = savedRow ? String(savedRow.formal_subtest_id ?? '') : '';
        dialogOpen = true;
    }

    function closeDialog() {
        dialogOpen = false;
        requestAnimationFrame(() => activeTrigger?.focus());
    }

    function handleDialogOpenChange(open) {
        if (open) {
            dialogOpen = true;
            return;
        }
        closeDialog();
    }
    function setRowValue(uid, key, value) {
        const location = activeLocations.find((item) => item.physical_item_uid === uid);
        const current = rowFor(location) ?? {
            physical_item_uid: uid,
            booklet_item_id: null,
            formal_subtest_id: null,
            formal_item_id: null,
            option_tokens: [...(location?.option_tokens ?? [])],
            disposition: 'physical',
        };
        const next = { ...current, [key]: value === '' ? null : (key === 'booklet_item_id' ? Number(value) : value), issues: null, mismatch: null };
        invalidateSaveAttempt();
        if (key === 'formal_item_id') next.formal_subtest_id = Number(selectedFormalSubtestId) || null;
        const index = draftRows.findIndex((row) => row.physical_item_uid === uid);
        draftRows = index < 0 ? [...draftRows, next] : draftRows.map((row, rowIndex) => rowIndex === index ? next : row);
    }

    function setBookletSection(event) {
        selectedBookletSectionUid = event.currentTarget.value;
        invalidateSaveAttempt();
        for (const location of activeLocations) setRowValue(location.physical_item_uid, 'booklet_item_id', '');
    }

    function setFormalSubtest(event) {
        selectedFormalSubtestId = event.currentTarget.value;
        invalidateSaveAttempt();
        for (const location of activeLocations) {
            setRowValue(location.physical_item_uid, 'formal_subtest_id', selectedFormalSubtestId);
            setRowValue(location.physical_item_uid, 'formal_item_id', '');
        }
    }

    function selectedBookletTokens(location) {
        const id = rowFor(location)?.booklet_item_id;
        return selectedBookletItems.find((item) => String(item.booklet_item_id) === String(id))?.option_tokens ?? [];
    }

    function selectedFormalTokens(location) {
        const id = rowFor(location)?.formal_item_id;
        return selectedFormalItems.find((item) => String(item.formal_item_id) === String(id))?.option_tokens ?? [];
    }

    function mismatchFor(location) {
        const row = rowFor(location);
        if (row?.issues || row?.mismatch) return row.issues ?? row.mismatch;
        if (!row?.booklet_item_id || !row?.formal_item_id) return null;
        const physicalTokens = location.option_tokens ?? [];
        return JSON.stringify(physicalTokens) === JSON.stringify(selectedBookletTokens(location)) &&
            JSON.stringify(physicalTokens) === JSON.stringify(selectedFormalTokens(location))
            ? null
            : 'Option choices differ across the Answer Sheet, Booklet, and Test Setup.';
    }

    function proposeInOrder() {
        if (!canMatchInOrder) return;
        const proposed = new Map(draftRows.map((row) => [row.physical_item_uid, row]));
        activeLocations.forEach((location, index) => {
            proposed.set(location.physical_item_uid, {
                ...(proposed.get(location.physical_item_uid) ?? {}),
                physical_item_uid: location.physical_item_uid,
                booklet_item_id: Number(selectedBookletItems[index].booklet_item_id),
                formal_subtest_id: Number(selectedFormalSubtestId),
                formal_item_id: Number(selectedFormalItems[index].formal_item_id),
                option_tokens: [...(location.option_tokens ?? [])],
                disposition: 'physical',
                issues: null,
                mismatch: null,
            });
        });
        draftRows = [...proposed.values()];
        invalidateSaveAttempt();
    }

    function addPracticeItem() {
        const item = practiceItems.find((candidate) => String(candidate.formal_item_id) === String(practiceItemId) && candidate.is_scored === false);
        if (!item || practiceRows.some((row) => String(row.formal_item_id) === String(item.formal_item_id))) return;
        invalidateSaveAttempt();
        draftRows = [...draftRows, {
            physical_item_uid: null,
            booklet_item_id: null,
            formal_subtest_id: Number(item.formal_subtest_id),
            formal_item_id: Number(item.formal_item_id),
            option_tokens: [],
            disposition: 'nonphysical_practice',
        }];
        practiceItemId = '';
    }

    function removePracticeItem(formalItemId) {
        invalidateSaveAttempt();
        draftRows = draftRows.filter((row) => !(row.disposition === 'nonphysical_practice' && String(row.formal_item_id) === String(formalItemId)));
    }

    async function save() {
        if (saving || !activeGroup || !composition?.save_url) return;
        if (staleContext) {
            requestError = 'The selected components or saved matches changed. Close this review and reopen the section.';
            return;
        }

        const requestPreparationId = preparationId ?? composition.preparation_id;
        const requestRevision = openedRevision;
        const requestVector = openedComponentVector;
        const payload = {
            preparation_id: requestPreparationId,
            expected_revision: requestRevision,
            component_vector: requestVector,
            rows: draftRows.map(({ physical_item_uid, booklet_item_id, formal_subtest_id, formal_item_id, option_tokens, disposition }) => ({
                physical_item_uid,
                booklet_item_id: booklet_item_id === null ? null : Number(booklet_item_id),
                formal_subtest_id: formal_subtest_id === null ? null : Number(formal_subtest_id),
                formal_item_id: formal_item_id === null ? null : Number(formal_item_id),
                option_tokens: [...option_tokens],
                disposition: disposition ?? 'physical',
            })),
        };
        const signature = JSON.stringify(payload);
        if (signature !== saveAttemptSignature) {
            saveAttemptSignature = signature;
            saveAttemptIdempotencyKey = newIdempotencyKey();
        }

        saving = true;
        requestError = null;
        try {
            const response = await fetch(composition.save_url, {
                method: 'PUT',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ ...payload, idempotency_key: saveAttemptIdempotencyKey }),
            });
            let result = null;
            try {
                result = await response.json();
            } catch {
                result = null;
            }

            if (!response.ok) {
                const validationMessage = Object.values(result?.errors ?? {})
                    .flat()
                    .find((message) => typeof message === 'string' && message.trim() !== '');
                requestError = validationMessage
                    ?? (typeof result?.message === 'string' ? result.message : `Saving the match failed (${response.status}).`);
                return;
            }
            if (Number(result?.preparation_id) !== Number(requestPreparationId)
                || !Number.isInteger(Number(result?.revision))
                || Number(result.revision) <= Number(requestRevision)) {
                requestError = 'The server did not acknowledge this saved draft revision. Keep this review open and retry.';
                return;
            }
            if (Number(preparationId ?? composition.preparation_id) !== Number(requestPreparationId)
                || Number(composition.revision) !== Number(requestRevision)
                || JSON.stringify(composition.component_vector) !== JSON.stringify(requestVector)) {
                return;
            }

            closeDialog();
            onSave(result);
        } catch {
            requestError = 'The save could not be confirmed. Your edits remain available; retry to safely check the same save.';
        } finally {
            saving = false;
        }
    }
</script>

{#if composition}
    <section class="space-y-3 border-t border-border pt-4" aria-label="Match items">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold">Match items</h3>
            <p class="text-xs text-muted-foreground">{#if composition.matched === null || composition.expected === null}Not applicable{:else}{composition.matched} of {composition.expected} matched{/if}</p>
        </div>
        <div class="overflow-x-auto rounded-lg border border-border">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="px-3 py-2 font-medium">Printed section</th><th class="px-3 py-2 font-medium">Matched</th><th class="px-3 py-2 font-medium">Expected</th><th class="px-3 py-2 text-right font-medium">Action</th></tr></thead>
                <tbody class="divide-y divide-border">
                    {#each groups as group (group.physical_section_uid)}
                        <tr>
                            <th scope="row" class="px-3 py-2 font-medium">{group.label}</th>
                            <td class="px-3 py-2 tabular-nums">{group.matched ?? 0}</td>
                            <td class="px-3 py-2 tabular-nums">{group.expected ?? group.physical_locations?.length ?? 0}</td>
                            <td class="px-3 py-2 text-right"><Button type="button" variant="outline" size="sm" onclick={(event) => openGroup(group, event.currentTarget)}>{group.matched ? 'Review' : 'Match'}</Button></td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </section>
{/if}

<Dialog.Root open={dialogOpen} onOpenChange={handleDialogOpenChange}>
    <Dialog.Content aria-label={activeGroup ? `Match ${activeGroup.label}` : 'Match items'} class="flex max-h-[92dvh] w-[calc(100vw-1rem)] max-w-none flex-col overflow-hidden rounded-2xl border bg-card p-0 shadow-2xl sm:w-[calc(100vw-2rem)] sm:max-w-none lg:w-[min(72rem,calc(100vw-4rem))]">
        {#if activeGroup}
            <Dialog.Header class="shrink-0 border-b px-4 py-4 pr-12 sm:px-6 sm:pr-14">
                <Dialog.Title class="break-words text-lg font-semibold">Match {activeGroup.label}</Dialog.Title>
                <Dialog.Description class="mt-1 text-sm text-muted-foreground">Choose the exact section and subtest, then review each item before saving.</Dialog.Description>
            </Dialog.Header>
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-6">
                {#if requestError}<p class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">{requestError}</p>{/if}
                {#if staleContext}<p class="text-sm text-amber-700 dark:text-amber-300" role="status">Saved selections changed. Close and reopen this review.</p>{/if}
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm">Booklet section
                        <select value={selectedBookletSectionUid} onchange={setBookletSection} class="h-10 min-w-0 rounded-md border border-input bg-background px-2">
                            <option value="">Select a Booklet section</option>
                            {#each bookletSections as section (section.booklet_section_uid)}<option value={section.booklet_section_uid}>{section.label}</option>{/each}
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm">Test Setup subtest
                        <select value={selectedFormalSubtestId} onchange={setFormalSubtest} class="h-10 min-w-0 rounded-md border border-input bg-background px-2">
                            <option value="">Select a subtest</option>
                            {#each formalSubtests as subtest (subtest.formal_subtest_id)}<option value={subtest.formal_subtest_id}>{subtest.label}</option>{/each}
                        </select>
                    </label>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Button type="button" variant="outline" size="sm" onclick={proposeInOrder} disabled={!canMatchInOrder || saving}>Match in order</Button>
                    {#if !canMatchInOrder}<p class="text-xs text-muted-foreground">Order matching is unavailable until the selected inventories have exact ordered option sets.</p>{/if}
                </div>
                <div class="overflow-x-auto rounded-lg border border-border">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead class="bg-muted/40 text-xs text-muted-foreground"><tr><th class="px-3 py-2 font-medium">Paper</th><th class="px-3 py-2 font-medium">Booklet item</th><th class="px-3 py-2 font-medium">Scoring item</th></tr></thead>
                        <tbody class="divide-y divide-border">
                            {#each activeLocations as location, rowIndex (location.physical_item_uid)}
                                {@const mismatch = mismatchFor(location)}
                                <tr>
                                    <th scope="row" class="px-3 py-2 font-medium"><span>{location.label ?? location.printed_number ?? `Item ${rowIndex + 1}`}</span><span class="block text-xs font-normal text-muted-foreground">Page {location.page_no}</span></th>
                                    <td class="px-3 py-2">
                                        <select value={rowFor(location)?.booklet_item_id ?? ''} onchange={(event) => setRowValue(location.physical_item_uid, 'booklet_item_id', event.currentTarget.value)} disabled={!selectedBookletSectionUid} class="h-10 min-w-0 w-full rounded-md border border-input bg-background px-2">
                                            <option value="">Select item</option>
                                            {#each selectedBookletItems as item (item.booklet_item_id)}<option value={item.booklet_item_id}>{item.label}</option>{/each}
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select value={rowFor(location)?.formal_item_id ?? ''} onchange={(event) => setRowValue(location.physical_item_uid, 'formal_item_id', event.currentTarget.value)} disabled={!selectedFormalSubtestId} class="h-10 min-w-0 w-full rounded-md border border-input bg-background px-2">
                                            <option value="">Select item</option>
                                            {#each selectedFormalItems as item (item.formal_item_id)}<option value={item.formal_item_id}>{item.label}</option>{/each}
                                        </select>
                                    </td>
                                </tr>
                                <tr class="bg-muted/20">
                                    <td colspan="3" class="px-3 pb-2 text-xs text-muted-foreground">
                                        <div class="grid gap-x-4 gap-y-1 sm:grid-cols-3">
                                            <p><span class="font-medium text-foreground">Paper:</span> {(location.option_tokens ?? []).join(' · ') || 'Unavailable'}</p>
                                            <p><span class="font-medium text-foreground">Booklet:</span> {selectedBookletTokens(location).join(' · ') || '—'}</p>
                                            <p><span class="font-medium text-foreground">Test Setup choices</span>: {selectedFormalTokens(location).join(' · ') || '—'}</p>
                                        </div>
                                        {#if mismatch}<p class="mt-1 text-destructive" role="alert">{typeof mismatch === 'string' ? mismatch : (mismatch.message ?? 'Selected items are incompatible.')}</p>{/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                {#if practiceItems.length > 0}
                    <section class="space-y-2 rounded-lg border border-border p-3" aria-label="Nonphysical practice items">
                        <h3 class="text-sm font-medium">Nonphysical practice</h3>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <label for="kit-practice-item" class="sr-only">Choose an unscored practice item</label>
                            <select id="kit-practice-item" aria-label="Choose an unscored practice item" bind:value={practiceItemId} class="h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-sm">
                                <option value="">Choose unscored item</option>
                                {#each practiceItems as item (`${item.formal_subtest_id}:${item.formal_item_id}`)}<option value={item.formal_item_id}>{item.subtest_label} · {item.label}</option>{/each}
                            </select>
                            <Button type="button" variant="outline" size="sm" onclick={addPracticeItem} disabled={!practiceItemId}>Add practice item</Button>
                        </div>
                        {#if practiceRows.length > 0}<ul class="divide-y divide-border text-sm">{#each practiceRows as row (row.formal_item_id)}{@const item = practiceItems.find((candidate) => String(candidate.formal_item_id) === String(row.formal_item_id))}<li class="flex items-center justify-between gap-3 py-2"><span>{item?.subtest_label} · {item?.label}</span><Button type="button" variant="outline" size="sm" onclick={() => removePracticeItem(row.formal_item_id)}>Remove</Button></li>{/each}</ul>{/if}
                    </section>
                {/if}
            </div>
            <Dialog.Footer class="mx-0 mb-0 shrink-0 flex-wrap justify-end gap-2 border-t px-4 py-3 sm:px-6 sm:py-4">
                <Button type="button" variant="outline" onclick={closeDialog} disabled={saving}>Cancel</Button>
                <Button type="button" onclick={save} disabled={saving || staleContext || !composition?.save_url}>{saving ? 'Saving…' : 'Save'}</Button>
            </Dialog.Footer>
        {/if}
    </Dialog.Content>
</Dialog.Root>
