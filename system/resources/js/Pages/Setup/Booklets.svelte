<script>
    import BookletPasteKeysModal from '@/Components/Setup/BookletPasteKeysModal.svelte';
    import BookletSubtestKeyGrid from '@/Components/Setup/BookletSubtestKeyGrid.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NewBookletDialog from '@/Components/Setup/NewBookletDialog.svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { ArrowLeft, BookOpen, FileText, KeyRound, RefreshCcw } from 'lucide-svelte';
    import * as toast from '@/lib/toast.js';
    let {
        booklets = [],
        selected_booklet_id = null,
        errors = {},
        can_view_question_content = false,
        enable_multi_set_booklets = true,
        kit_context = null,
    } = $props();

    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup' },
                  { label: 'Test Kits' },
                  { label: kit_context.code },
                  { label: 'Booklet' },
              ]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Booklets', href: '/setup/exam-materials/booklets' },
                  { label: selectedBooklet?.name ?? 'Booklet Workspace' },
              ],
    );
    const optionCodes = $derived(activeArea?.option_codes ?? []);

    let selectedBookletId = $state(
        selected_booklet_id ??
        (kit_context ? null : booklets[0]?.id ?? null),
    );
    let activeAreaId = $state(null);
    let activeSubtestId = $state(null);
    let activeSetCode = $state('A');
    let editorItems = $state([]);
    let keyRows = $state({});
    let newSectionName = $state('');
    let newSectionExpectedCount = $state('1');
    let newSectionTokens = $state('');
    let pasteKeysOpen = $state(false);
    let dirtyKeySets = $state([]);
    let saving = $state(false);
    let showPreview = $state(true);
    let selectedQuestion = $state(null);
    let confirmDisableMultipleSets = $state(false);
    let discardDialogOpen = $state(false);
    let archiveDialogOpen = $state(false);
    let returnDialogOpen = $state(false);
    let restoreDialogOpen = $state(false);
    let initializedWorkspaceVersionId = null;
    const selectedBooklet = $derived(
        booklets.find((booklet) => booklet.id === Number(selectedBookletId)) ??
            (kit_context ? null : booklets[0] ?? null),
    );
    const workspace = $derived(selectedBooklet?.workspace ?? null);
    const setCodes = $derived(
        enable_multi_set_booklets &&
            (workspace?.version?.allow_multiple_sets ??
                workspace?.allow_multiple_sets) &&
            (workspace?.required_set_codes ?? []).length > 1
            ? ['A', 'B', 'C', 'D']
            : ['A'],
    );
    const multiSetMode = $derived(setCodes.length > 1);
    const intrinsicAreas = $derived.by(() => {
        const sections = new Map(
            (workspace?.sections ?? []).map((section) => [section.uid, { ...section, id: section.uid }]),
        );
        for (const item of editorItems) {
            const current = sections.get(item.booklet_section_uid) ?? {
                id: item.booklet_section_uid,
                uid: item.booklet_section_uid,
                name: item.section_name,
                display_order: item.section_display_order,
                required_count: item.section_expected_count,
                option_codes: item.option_tokens ?? [],
            };
            sections.set(item.booklet_section_uid, {
                ...current,
                completed_count: editorItems.filter((candidate) => candidate.booklet_section_uid === item.booklet_section_uid).length,
            });
        }
        return [...sections.values()].sort((left, right) => left.display_order - right.display_order);
    });
    const activeArea = $derived(
        intrinsicAreas.find((area) => area.uid === activeAreaId) ?? intrinsicAreas[0] ?? null,
    );
    const activeSet = $derived(
        workspace?.sets?.find((set) => set.set_code === activeSetCode) ?? null,
    );
    const activeSubtest = $derived(
        workspace?.section_items?.find(
            (section) => Number(section.id) === Number(activeSubtestId),
        ) ??
            workspace?.section_items?.[0] ??
            null,
    );
    const activeSubtestForPaste = $derived(
        activeSubtest
            ? {
                  ...activeSubtest,
                  items: activeSubtest.items.map((item) => ({
                      ...item,
                      item_number: Number(item.local_number),
                  })),
              }
            : null,
    );
    const activeSubtestKeys = $derived.by(() => {
        const keys = {};
        for (const item of activeSubtest?.items ?? []) {
            const row = (keyRows[activeSetCode] ?? []).find(
                (candidate) => candidate.booklet_item_uid === item.booklet_item_uid,
            );
            if (row?.selected_token) {
                keys[String(item.id)] = row.selected_token;
            }
        }
        return keys;
    });
    const keySaveErrors = $derived(
        Object.entries(errors).filter(
            ([field]) =>
                field === 'items' ||
                field.startsWith('items.') ||
                field === 'lock_version',
        ),
    );
    const alternateSetsWithData = $derived(
        (workspace?.sets ?? [])
            .filter((set) => set.set_code !== 'A')
            .filter(
                (set) =>
                    (set.items ?? []).some((item) =>
                        Boolean(item.correct_option_code),
                    ) ||
                    (keyRows[set.set_code] ?? []).some((item) =>
                        Boolean(item.correct_option_code),
                    ),
            )
            .map((set) => set.set_code),
    );

    $effect(() => {
        if (dirtyKeySets.length === 0) return;

        const guardUnsavedKeys = (event) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', guardUnsavedKeys);

        return () => window.removeEventListener('beforeunload', guardUnsavedKeys);
    });

    $effect(() => {
        const versionId = workspace?.version_id;
        if (!versionId || versionId === initializedWorkspaceVersionId) return;
        initializedWorkspaceVersionId = versionId;

        editorItems = (workspace.items ?? []).map((item) => ({ ...item }));
        keyRows = Object.fromEntries(
            (workspace.sets ?? []).map((set) => [
                set.set_code,
                (set.items ?? []).map((item) => ({
                    booklet_item_uid: item.booklet_item_uid,
                    booklet_section_uid: item.booklet_section_uid,
                    global_position: item.global_position,
                    selected_token: item.correct_option_code ?? '',
                })),
            ]),
        );
        activeAreaId = workspace.sections?.[0]?.uid ?? null;
        activeSubtestId = workspace.section_items?.[0]?.id ?? null;
        activeSetCode = 'A';
        dirtyKeySets = [];
        pasteKeysOpen = false;
    });

    function withKitContext(path) {
        if (!kit_context) return path;

        const url = new URL(path, window.location.origin);
        if (url.origin !== window.location.origin) return path;

        url.searchParams.delete('preparation_id');
        url.searchParams.delete('test_kit_version_id');
        url.searchParams.set('test_kit_id', String(kit_context.test_kit_id));
        if (kit_context.preparation_id !== null) {
            url.searchParams.set(
                'preparation_id',
                String(kit_context.preparation_id),
            );
        }
        if (kit_context.test_kit_version_id !== null) {
            url.searchParams.set(
                'test_kit_version_id',
                String(kit_context.test_kit_version_id),
            );
        }
        if (kit_context.authoring_booklet_version_id !== null) {
            url.searchParams.set(
                'authoring_booklet_version_id',
                String(kit_context.authoring_booklet_version_id),
            );
        }

        return `${url.pathname}${url.search}${url.hash}`;
    }

    function setMultipleSets(enabled, confirmed = false) {
        if (!workspace?.version_id || workspace.is_finalized || saving) return;
        if (!enabled && alternateSetsWithData.length > 0 && !confirmed) {
            confirmDisableMultipleSets = true;
            return;
        }

        saving = true;
        router.post(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}/sets/mode`),
            {
                allow_multiple_sets: enabled,
                lock_version: workspace.lock_version,
                confirm_discard_alternates: confirmed,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    activeSetCode = 'A';
                },
                onFinish: () => {
                    saving = false;
                    confirmDisableMultipleSets = false;
                },
            },
        );
    }


    function normalizedItems() {
        const positions = {};
        return editorItems.map((item) => {
            const sectionUid = item.booklet_section_uid;
            const position = (positions[sectionUid] ?? 0) + 1;
            positions[sectionUid] = position;
            return { ...item, authored_position: position };
        });
    }

    function saveDraft() {
        if (!workspace?.version_id || saving) return;
        saving = true;
        router.put(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}`),
            {
                lock_version: workspace.lock_version,
                complete: false,
                items: normalizedItems(),
            },
            { preserveScroll: true, onFinish: () => (saving = false) },
        );
    }

    function saveSetKeys(setCode) {
        if (!workspace?.version_id || saving) return;
        const savedSubtestName = activeSubtest?.name ?? 'subtest';
        const savedSetMode = multiSetMode;
        saving = true;
        router.post(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}/sets/${setCode}/keys`),
            {
                lock_version: workspace.lock_version,
                complete: false,
                items: keyRows[setCode] ?? [],
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    dirtyKeySets = dirtyKeySets.filter((code) => code !== setCode);
                    toast.success(
                        savedSetMode
                            ? `Set ${setCode} answer key saved`
                            : `Answer key for ${savedSubtestName} saved`,
                    );
                },
                onFinish: () => (saving = false),
            },
        );
    }

    function selectSubtest(subtest) {
        activeSubtestId = subtest.id;
        selectedQuestion = null;
    }

    function selectArea(areaId) {
        activeAreaId = areaId;
        selectedQuestion = null;
    }

    function selectSet(setCode) {
        activeSetCode = setCode;
        selectedQuestion = null;
    }

    function areaCompletedCount(area) {
        return area.completed_count;
    }

    function subtestCompletedCount(subtest) {
        const requiredItems = (subtest.items ?? []).filter((item) => item.key_required !== false);
        const itemUids = new Set(requiredItems.map((item) => item.booklet_item_uid));
        return (keyRows[activeSetCode] ?? []).filter(
            (row) => itemUids.has(row.booklet_item_uid) && Boolean(row.selected_token),
        ).length;
    }

    function subtestRequiredCount(subtest) {
        return (subtest.items ?? []).filter((item) => item.key_required !== false).length;
    }

    function existingSetItem(item) {
        return (keyRows[activeSetCode] ?? []).find(
            (row) => row.booklet_item_uid === item.booklet_item_uid,
        ) ?? null;
    }

    function markSetKeysDirty(setCode) {
        if (!dirtyKeySets.includes(setCode)) {
            dirtyKeySets = [...dirtyKeySets, setCode];
        }
    }

    function updateSubtestKey(item, token) {
        if (!item.key_required) return;
        const existing = existingSetItem(item);
        if (!existing) return;
        keyRows = {
            ...keyRows,
            [activeSetCode]: (keyRows[activeSetCode] ?? []).map((row) =>
                row.booklet_item_uid === item.booklet_item_uid
                    ? { ...row, selected_token: token }
                    : row,
            ),
        };
        markSetKeysDirty(activeSetCode);
    }

    function clearSubtestKey(item) {
        const existing = existingSetItem(item);
        if (!existing) return;
        keyRows = {
            ...keyRows,
            [activeSetCode]: (keyRows[activeSetCode] ?? []).map((row) =>
                row.booklet_item_uid === item.booklet_item_uid
                    ? { ...row, selected_token: '' }
                    : row,
            ),
        };
        markSetKeysDirty(activeSetCode);
    }

    function applyPastedSubtestKeys(mappedKeys) {
        const rows = keyRows[activeSetCode] ?? [];
        keyRows = {
            ...keyRows,
            [activeSetCode]: rows.map((row) => {
                const item = activeSubtest?.items?.find(
                    (candidate) => candidate.booklet_item_uid === row.booklet_item_uid,
                );
                if (!item?.key_required) return row;
                return {
                    ...row,
                    selected_token: mappedKeys[String(item.id)] ?? '',
                };
            }),
        };
        markSetKeysDirty(activeSetCode);
    }

    function generateSets() {
        if (!workspace?.version_id || saving) return;
        router.post(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}/sets/generate`),
            { lock_version: workspace.lock_version },
            { preserveScroll: true },
        );
    }

    function finalizeBooklet() {
        if (!workspace?.version_id || saving) return;
        if (!window.confirm('Finalize this Booklet? Intrinsic content, response tokens, keys, and Sets become immutable.')) {
            return;
        }
        router.post(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}/finalize`),
            { lock_version: workspace.lock_version },
            { preserveScroll: true },
        );
    }

    function copyToDraft() {
        if (!workspace?.version_id || saving) return;
        router.post(
            withKitContext(`/setup/exam-materials/booklets/${workspace.version_id}/copy`),
            {},
            { preserveScroll: true },
        );
    }

    function addSection() {
        if (!workspace?.version_id || !newSectionName.trim()) return;
        const optionTokens = newSectionTokens.split(',').map((token) => token.trim());
        if (optionTokens.length === 0 || optionTokens.some((token) => token === '') || new Set(optionTokens).size !== optionTokens.length) {
            toast.error('Enter the section’s literal response tokens, separated by commas.');
            return;
        }
        const expectedCount = Number(newSectionExpectedCount);
        if (!Number.isInteger(expectedCount) || expectedCount < 1 || expectedCount > 500) {
            toast.error('Enter an expected item count from 1 to 500.');
            return;
        }
        const sectionUid = crypto.randomUUID();
        const displayOrder = intrinsicAreas.length + 1;
        const sectionItem = {
            booklet_item_uid: crypto.randomUUID(),
            booklet_section_uid: sectionUid,
            section_name: newSectionName.trim(),
            section_display_order: displayOrder,
            section_expected_count: expectedCount,
            option_tokens: optionTokens,
            key_required: true,
            authored_position: 1,
            correct_option_code: '',
            content: { prompt: '', choices: {} },
        };
        editorItems = [...editorItems, sectionItem];
        activeAreaId = sectionUid;
        newSectionName = '';
        newSectionExpectedCount = '1';
        newSectionTokens = '';
    }

    function toggleItemKeyRequired(itemIndex, required) {
        const item = editorItems[itemIndex];
        editorItems[itemIndex] = {
            ...item,
            key_required: required,
            correct_option_code: required ? (item.correct_option_code ?? '') : '',
        };
        editorItems = [...editorItems];
    }

    function addItem() {
        if (!activeArea) return;
        const sectionItems = editorItems.filter(
            (item) => item.booklet_section_uid === activeArea.uid,
        );
        if (sectionItems.length >= Number(activeArea.required_count)) return;
        editorItems = [
            ...editorItems,
            {
                booklet_item_uid: crypto.randomUUID(),
                booklet_section_uid: activeArea.uid,
                section_name: activeArea.name,
                section_display_order: activeArea.display_order,
                section_expected_count: activeArea.required_count,
                option_tokens: activeArea.option_codes,
                key_required: true,
                authored_position: sectionItems.length + 1,
                correct_option_code: '',
                content: { prompt: '', choices: {} },
            },
        ];
    }

    function updateChoice(itemIndex, option, value) {
        const item = editorItems[itemIndex];
        editorItems[itemIndex] = {
            ...item,
            content: {
                ...(item.content ?? {}),
                choices: { ...(item.content?.choices ?? {}), [option]: value },
            },
        };
        editorItems = [...editorItems];
    }

    function removeItem(itemIndex) {
        editorItems = editorItems.filter((_, index) => index !== itemIndex);
    }
    function requestKitReturn() {
        if (!kit_context?.back_url) return;
        if (dirtyKeySets.length > 0) {
            returnDialogOpen = true;
            return;
        }

        router.visit(kit_context.back_url);
    }

    function returnToKit() {
        if (!kit_context?.back_url) return;
        returnDialogOpen = false;
        router.visit(kit_context.back_url);
    }

    function viewFinalizedKeys() {
        document.getElementById('finalized-booklet-keys')?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    }

    function discardDraft() {
        if (!workspace?.destroy_url) return;
        router.delete(withKitContext(workspace.destroy_url));
    }

    function archiveBooklet() {
        if (!workspace?.archive_url) return;
        router.post(withKitContext(workspace.archive_url), {}, { preserveScroll: true });
    }

    function restoreBooklet() {
        if (!workspace?.restore_url) return;
        router.post(withKitContext(workspace.restore_url), {}, { preserveScroll: true });
    }
</script>
<svelte:head><title>Booklets · Exam materials</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-8 p-4 sm:p-6">
        <PageHeaderBar
            title={selectedBooklet?.name ?? 'Booklets'}
            description={workspace?.mode === 'answer_keys_only'
                ? (multiSetMode
                    ? 'Maintain subtest answer keys across Sets.'
                    : 'Maintain the single-set subtest answer key.')
                : (multiSetMode
                    ? 'Author protected questions and choices across Sets.'
                    : 'Author protected questions and choices for one Answer Key.')}
        >
            {#snippet actions()}
                <div class="flex items-center gap-2">
                    {#if kit_context}
                        <Button
                            variant="outline"
                            class="min-h-11 gap-1.5"
                            onclick={requestKitReturn}
                        >
                            <ArrowLeft class="size-4" />
                            <span>Back to {kit_context.code}</span>
                        </Button>
                    {:else}
                        <Button
                            href="/setup/exam-materials/booklets"
                            variant="outline"
                            class="min-h-11 gap-1.5"
                        >
                            <ArrowLeft class="size-4" />
                            <span>All Booklets</span>
                        </Button>
                    {/if}
                </div>
            {/snippet}
        </PageHeaderBar>

        {#if errors.booklet}
            <p
                class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"
            >
                {errors.booklet}
            </p>
        {/if}
        {#if kit_context && kit_context.booklet_version_id === null}
            <p class="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                Not selected for {kit_context.code}.
            </p>
        {/if}

        <section class="space-y-4" aria-labelledby="booklets-title">
            {#if workspace}
                    <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,0.9fr)]">
                        <div
                            class="order-2 min-w-0 rounded-xl border border-border bg-card p-5 xl:order-1"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h3 class="text-xl font-semibold">
                                            {selectedBooklet.name}
                                        </h3>
                                        {#if workspace.mode === 'answer_keys_only'}
                                            <Badge variant="outline" class="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 font-semibold text-xs">
                                                <KeyRound class="size-3 mr-1" /> Answer Key Only (External Booklet)
                                            </Badge>
                                        {:else}
                                            <Badge variant="outline" class="border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 font-semibold text-xs">
                                                <FileText class="size-3 mr-1" /> Stored Questions & Choices
                                            </Badge>
                                        {/if}
                                    </div>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        Version {workspace.version} · {workspace.mode === 'answer_keys_only'
                                            ? 'Questions printed externally; scoring keys managed here'
                                            : 'Questions and choices authored in system'}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    {#if workspace.can_discard && workspace.destroy_url}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="text-destructive hover:bg-destructive/10"
                                            onclick={() => (discardDialogOpen = true)}
                                        >
                                            Discard Draft
                                        </Button>
                                    {/if}
                                    {#if !kit_context?.read_only && workspace.can_archive && workspace.archive_url}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onclick={() => (archiveDialogOpen = true)}
                                        >
                                            Archive Booklet
                                        </Button>
                                    {/if}
                                    {#if !kit_context?.read_only && workspace.can_restore && workspace.restore_url}
                                        <Badge variant="secondary">Archived · Historical Record</Badge>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onclick={() => (restoreDialogOpen = true)}
                                        >
                                            Restore to Official
                                        </Button>
                                    {/if}
                                    {#if workspace.is_finalized}
                                        {#if kit_context}
                                            <details class="relative">
                                                <summary class="cursor-pointer list-none rounded-md border px-3 py-1.5 text-sm font-medium">
                                                    More
                                                </summary>
                                                <div class="absolute right-0 z-20 mt-1 grid min-w-36 gap-1 rounded-md border bg-popover p-1 shadow-md">
                                                    <Button size="sm" variant="ghost" onclick={viewFinalizedKeys}>View keys</Button>
                                                    {#if !kit_context.read_only}
                                                        <Button size="sm" variant="ghost" onclick={copyToDraft}>Revise</Button>
                                                    {/if}
                                                </div>
                                            </details>
                                        {:else}
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onclick={copyToDraft}
                                                >New Draft</Button
                                            >
                                        {/if}
                                    {/if}
                                    <Badge
                                        variant={workspace.is_finalized
                                            ? 'default'
                                            : 'secondary'}
                                        >{workspace.is_finalized
                                            ? 'Finalized'
                                            : 'Draft'}</Badge
                                    >
                                </div>
                            </div>

                            {#if !workspace.is_finalized}
                                <section class="mt-4 space-y-3 rounded-lg border border-border/70 bg-card/50 p-3" aria-label="Intrinsic Booklet sections">
                                    <h2 class="text-sm font-semibold">Booklet sections</h2>
                                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1.5fr)_minmax(8rem,.6fr)_minmax(0,1.2fr)_auto]">
                                        <label class="grid gap-1 text-xs font-medium">
                                            Section name
                                            <Input bind:value={newSectionName} maxlength="120" placeholder="Enter printed section name" disabled={saving} />
                                        </label>
                                        <label class="grid gap-1 text-xs font-medium">
                                            Expected items
                                            <Input bind:value={newSectionExpectedCount} type="number" min="1" max="500" disabled={saving} />
                                        </label>
                                        <label class="grid gap-1 text-xs font-medium">
                                            Literal response tokens
                                            <Input bind:value={newSectionTokens} placeholder="e.g. A, A-B, S" disabled={saving} />
                                        </label>
                                        <Button class="self-end" variant="outline" onclick={addSection} disabled={saving || !newSectionName.trim()}>Add section</Button>
                                    </div>
                                    {#if editorItems.length > (workspace.items ?? []).length}
                                        <Button variant="secondary" onclick={saveDraft} disabled={saving}>
                                            {saving ? 'Saving…' : 'Save section and item inventory'}
                                        </Button>
                                    {/if}
                                </section>
                            {/if}


                            {#if enable_multi_set_booklets}
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2">
                                <div>
                                    <p class="text-sm font-medium">Multiple Sets</p>
                                    <p class="text-xs text-muted-foreground">Use one answer key or enable alternate keys.</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    aria-label="Multiple Sets"
                                    aria-checked={workspace.allow_multiple_sets}
                                    class="relative h-6 w-11 rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                    class:bg-primary={workspace.allow_multiple_sets}
                                    onclick={() => setMultipleSets(!workspace.allow_multiple_sets)}
                                    disabled={workspace.is_finalized || saving}
                                >
                                    <span class={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-transform ${workspace.allow_multiple_sets ? 'translate-x-5' : 'translate-x-0.5'}`}></span>
                                </button>
                            </div>
                            {/if}

                            {#if intrinsicAreas.length > 0}
                                <div class="mt-5 flex flex-wrap gap-2" role="tablist" aria-label="Booklet sections">
                                    {#each intrinsicAreas as area (area.uid)}
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={activeArea?.uid === area.uid}
                                            class="rounded-full border border-border/80 px-3 py-1.5 text-sm"
                                            class:bg-primary={activeArea?.uid === area.uid}
                                            class:text-primary-foreground={activeArea?.uid === area.uid}
                                            onclick={() => selectArea(area.uid)}
                                        >{area.name} {area.completed_count ?? 0}/{area.required_count}</button>
                                    {/each}
                                </div>
                                {#if workspace.mode === 'answer_keys_only' && activeArea && !workspace.is_finalized}
                                    <Button variant="outline" size="sm" onclick={addItem} disabled={saving || (activeArea.completed_count ?? 0) >= activeArea.required_count}>Add item</Button>
                                    {#each editorItems.filter((item) => item.booklet_section_uid === activeArea.uid) as item, itemIndex (item.booklet_item_uid)}
                                        <label class="inline-flex min-h-9 items-center gap-2 rounded-md border border-border/70 px-3 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={item.key_required !== false}
                                                disabled={saving || workspace.is_finalized}
                                                onchange={(event) => toggleItemKeyRequired(editorItems.indexOf(item), event.currentTarget.checked)}
                                            />
                                            Item {item.authored_position} requires a key
                                        </label>
                                    {/each}
                                    {#if editorItems.some((item) => item.booklet_section_uid === activeArea.uid && item.key_required !== workspace.items.find((saved) => saved.booklet_item_uid === item.booklet_item_uid)?.key_required)}
                                        <Button variant="secondary" size="sm" onclick={saveDraft} disabled={saving}>Save item requirements</Button>
                                    {/if}
                                {/if}
                            {/if}

                            {#if multiSetMode}
                                <div class="mt-4 flex flex-wrap gap-2 border-b border-border/80 pb-3" role="tablist" aria-label="Booklet Sets">
                                    {#each setCodes as setCode (setCode)}
                                        <button
                                            type="button"
                                            role="tab"
                                            aria-selected={activeSetCode === setCode}
                                            class="rounded-md border border-border/80 px-3 py-1.5 text-sm"
                                            class:bg-primary={activeSetCode === setCode}
                                            class:text-primary-foreground={activeSetCode === setCode}
                                            onclick={() => selectSet(setCode)}
                                            disabled={saving}
                                        >Set {setCode}</button>
                                    {/each}
                                </div>
                            {:else if workspace.mode === 'answer_keys_only'}
                                <div class="mt-4 border-b border-border/80 pb-3">
                                    <h4 class="font-semibold">Answer Key (Single Set)</h4>
                                </div>
                            {/if}

                            {#if activeArea && workspace.mode === 'stored_questions' && can_view_question_content}
                                <div class="mt-6 space-y-4" role="tabpanel">
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <h4 class="font-semibold">
                                                {activeArea.name} questions
                                            </h4>
                                            <p
                                                class="text-muted-foreground text-sm"
                                            >
                                                Choices follow the selected Answer Sheet; generated
                                                {multiSetMode ? 'Sets' : 'answer keys'} move the complete item.
                                            </p>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onclick={addItem}
                                            disabled={workspace.is_finalized ||
                                                activeArea.completed_count >=
                                                    activeArea.required_count}
                                            >Add question</Button
                                        >
                                    </div>
                                    {#each editorItems as item, itemIndex (item.id ?? itemIndex)}
                                        {#if item.booklet_section_uid === activeArea.uid}
                                            <article
                                                class="space-y-3 rounded-lg border p-4"
                                            >
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <strong
                                                        >Question {item.authored_position}</strong
                                                    ><Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onclick={() =>
                                                            removeItem(
                                                                itemIndex,
                                                            )}
                                                        disabled={workspace.is_finalized}
                                                        >Remove</Button
                                                    >
                                                </div>
                                                <Input
                                                    bind:value={
                                                        item.content.prompt
                                                    }
                                                    placeholder="Question stem"
                                                    disabled={workspace.is_finalized}
                                                />
                                                <div
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    {#each optionCodes as option (option)}
                                                        <label
                                                            class="flex min-w-0 items-center gap-2"
                                                            ><span
                                                                class="min-w-0 max-w-16 break-all text-sm font-medium"
                                                                title={option}
                                                                >{option}</span
                                                            ><Input
                                                                value={item
                                                                    .content
                                                                    ?.choices?.[
                                                                    option
                                                                ] ?? ''}
                                                                onchange={(
                                                                    event,
                                                                ) =>
                                                                    updateChoice(
                                                                        itemIndex,
                                                                        option,
                                                                        event
                                                                            .currentTarget
                                                                            .value,
                                                                    )}
                                                                disabled={workspace.is_finalized}
                                                                aria-label={`Question ${item.authored_position} choice ${option}`}
                                                            /></label
                                                        >
                                                    {/each}
                                                </div>
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input
                                                        type="checkbox"
                                                        checked={item.key_required !== false}
                                                        disabled={workspace.is_finalized}
                                                        onchange={(event) => toggleItemKeyRequired(itemIndex, event.currentTarget.checked)}
                                                    />
                                                    <span>Requires a scored key</span>
                                                </label>
                                                {#if item.key_required !== false}
                                                    <div class="max-w-48 space-y-1">
                                                        <Label>Correct answer</Label>
                                                        <select
                                                            id={`question-${workspace.version_id}-${item.booklet_section_uid}-${item.authored_position}`}
                                                            class={selectClass}
                                                            bind:value={item.correct_option_code}
                                                            disabled={workspace.is_finalized}
                                                        >
                                                            <option value="">Choose a token</option>
                                                            {#each optionCodes as option (option)}
                                                                <option value={option}>{option}</option>
                                                            {/each}
                                                        </select>
                                                    </div>
                                                {:else}
                                                    <p class="text-xs text-muted-foreground">This item is explicitly keyless; its formal scoring disposition is verified in Test Kit.</p>
                                                {/if}
                                            </article>
                                        {/if}
                                    {:else}
                                        <p
                                            class="text-muted-foreground rounded-lg border border-dashed p-4 text-sm"
                                        >
                                            No questions in this aptitude yet.
                                        </p>
                                    {/each}
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            onclick={saveDraft}
                                            disabled={workspace.is_finalized ||
                                                saving}>Save draft</Button
                                        ><Button
                                            variant="outline"
                                            onclick={generateSets}
                                            disabled={workspace.is_finalized ||
                                                saving ||
                                                workspace.next_action !==
                                                    'generate_sets'}
                                            ><RefreshCcw
                                                class="mr-2 h-4 w-4"
                                            />{multiSetMode ? 'Generate Sets' : 'Generate Answer Keys'}</Button
                                        >
                                    </div>
                                </div>
                            {:else if workspace.mode === 'answer_keys_only'}
                                {#if workspace.next_action === 'generate_sets'}
                                    <Button class="mb-4" variant="outline" onclick={generateSets} disabled={saving}>
                                        <RefreshCcw class="mr-2 size-4" />Generate Set A keys
                                    </Button>
                                {/if}
                                <div class="mt-5 grid min-w-0 gap-5 lg:grid-cols-[15rem_minmax(0,1fr)]" role="tabpanel">
                                    <nav class="space-y-1" aria-label="Booklet sections">
                                        {#each workspace.section_items ?? [] as subtest (subtest.id)}
                                            <button
                                                type="button"
                                                aria-current={Number(activeSubtest?.id) === Number(subtest.id) ? 'true' : undefined}
                                                class={`flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition-colors ${Number(activeSubtest?.id) === Number(subtest.id) ? 'border-primary/50 bg-primary/10' : 'border-border/80 hover:bg-muted/50'}`}
                                                onclick={() => selectSubtest(subtest)}
                                                disabled={saving}
                                            >
                                                <span class="min-w-0 truncate font-medium">{subtest.name}</span>
                                                <span class="shrink-0 font-mono text-xs tabular-nums text-muted-foreground">
                                                    {subtestCompletedCount(subtest)}/{subtestRequiredCount(subtest)}
                                                </span>
                                            </button>
                                        {:else}
                                            <p class="rounded-lg border border-border/80 p-3 text-sm text-muted-foreground">Add a Booklet section and its items first.</p>
                                        {/each}
                                    </nav>

                                    <div id="finalized-booklet-keys" class="min-w-0 space-y-4">
                                        {#if activeSubtest}
                                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 pb-3">
                                                <div class="min-w-0">
                                                    <h3 class="truncate text-base font-semibold">{activeSubtest.name}</h3>
                                                    <p class="mt-1 text-xs text-muted-foreground">
                                                        {subtestCompletedCount(activeSubtest)}/{subtestRequiredCount(activeSubtest)} required keys
                                                        {dirtyKeySets.includes(activeSetCode) ? ' · Unsaved edits' : ''}
                                                    </p>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onclick={() => (pasteKeysOpen = true)}
                                                    disabled={workspace.is_finalized || saving || activeSubtest.items.length === 0}
                                                >Paste Keys...</Button>
                                            </div>
                                            <BookletSubtestKeyGrid
                                                subtest={activeSubtest}
                                                keys={activeSubtestKeys}
                                                readOnly={workspace.is_finalized || saving}
                                                onSelectKey={updateSubtestKey}
                                                onClearKey={clearSubtestKey}
                                            />
                                            <Button
                                                onclick={() => saveSetKeys(activeSetCode)}
                                                disabled={workspace.is_finalized || saving}
                                            >{saving ? 'Saving...' : multiSetMode ? `Save Set ${activeSetCode}` : 'Save Answer Key'}</Button>
                                        {/if}

                                        {#if keySaveErrors.length > 0}
                                            <div class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive" role="alert">
                                                {#each keySaveErrors as error (error[0])}
                                                    <p><span class="font-medium">{error[0]}:</span> {error[1]}</p>
                                                {/each}
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                                <BookletPasteKeysModal
                                    open={pasteKeysOpen}
                                    subtest={activeSubtestForPaste}
                                    onClose={() => (pasteKeysOpen = false)}
                                    onApply={applyPastedSubtestKeys}
                                />
                            {:else if workspace.mode === 'stored_questions'}
                                <div
                                    class="mt-6 rounded-lg border border-dashed p-4"
                                >
                                    <h4 class="font-semibold">
                                        Question content protected
                                    </h4>
                                    <p
                                        class="text-muted-foreground mt-1 text-sm"
                                    >
                                        Only authorized question authors can
                                        open stems and choices.
                                    </p>
                                </div>
                            {/if}

                            <div class="mt-6 border-t pt-5">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    {#if workspace.mode === 'stored_questions'}
                                        <h4 class="font-semibold">Set summary</h4>
                                    {/if}
                                    <Button
                                        onclick={finalizeBooklet}
                                        disabled={workspace.is_finalized ||
                                            saving ||
                                            workspace.next_action !==
                                                'finalize'}
                                        >Finalize Booklet</Button
                                    >
                                </div>
                                {#if workspace.mode === 'stored_questions'}
                                <div
                                    class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                                >
                                    {#each (multiSetMode ? workspace.sets : workspace.sets.filter((set) => set.set_code === 'A')) as set (set.id)}
                                        <div class="rounded-lg border p-3">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <strong
                                                    >{multiSetMode ? `Set ${set.set_code}` : 'Answer Key'}</strong
                                                ><Badge variant="outline"
                                                    >{set.is_complete
                                                        ? 'Complete'
                                                        : 'Incomplete'}</Badge
                                                >
                                            </div>
                                            <ol
                                                class="text-muted-foreground mt-2 list-inside list-decimal text-xs"
                                            >
                                                {#each set.items.filter((item) => !activeArea || item.booklet_section_uid === activeArea.uid) as item (item.id ?? item.area_position)}<li
                                                    >
                                                        {item.question?.content
                                                            ?.prompt ??
                                                            `Question ${item.area_position}`}
                                                        · <span
                                                            class="inline-block max-w-full break-all"
                                                            title={item.correct_option_code}
                                                            >{item.correct_option_code}</span>
                                                    </li>{/each}
                                            </ol>
                                        </div>
                                    {/each}
                                </div>
                                {/if}
                            </div>
                        </div>
                        <section class="order-1 min-w-0 rounded-xl border border-border/70 bg-card p-4 xl:order-2" aria-label="Intrinsic Booklet preview">
                            <h2 class="text-sm font-semibold">Booklet preview</h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Intrinsic question content and saved Set keys. Physical Answer Sheet matching is managed in Test Kits.
                            </p>
                            <ol class="mt-4 max-h-[34rem] space-y-2 overflow-auto text-sm">
                                {#each (workspace.mode === 'stored_questions' ? workspace.items : activeSet?.items ?? []) as item (item.booklet_item_uid ?? item.id ?? item.global_position)}
                                    <li class="rounded-md border border-border/70 p-2">
                                        <span class="font-medium">
                                            {item.content?.prompt ?? `Item ${item.authored_position ?? item.global_position ?? ''}`}
                                        </span>
                                        {#if item.correct_option_code}
                                            <span class="text-muted-foreground"> · {item.correct_option_code}</span>
                                        {/if}
                                    </li>
                                {/each}
                            </ol>
                        </section>
                        </div>
                        {#if confirmDisableMultipleSets}
                            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation">
                                <div class="w-full max-w-md space-y-4 rounded-xl border border-border bg-card p-6 shadow-xl" role="dialog" aria-modal="true" aria-labelledby="disable-multiple-sets-title" tabindex="-1">
                                    <h2 id="disable-multiple-sets-title" class="text-lg font-semibold">Switch to one Set?</h2>
                                    <p class="text-sm text-muted-foreground">
                                        This removes populated alternate Sets {alternateSetsWithData.join(', ')} from this draft. Set A and source questions will be preserved.
                                    </p>
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" onclick={() => (confirmDisableMultipleSets = false)}>Cancel</Button>
                                        <Button variant="destructive" disabled={saving} onclick={() => setMultipleSets(false, true)}>Remove alternate Sets</Button>
                                    </div>
                                </div>
                            </div>
                        {/if}
            {:else}
                <div
                    class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"
                >
                    <div
                        class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl"
                    >
                        <BookOpen class="text-muted-foreground size-6" />
                    </div>
                    <h3 class="mt-4 font-semibold">No Booklet selected</h3>
                    <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                        Select a Booklet from the Booklets list or author a new one.
                    </p>
                    <div class="mt-5 flex justify-center gap-3">
                        {#if kit_context}
                            <Button variant="outline" onclick={requestKitReturn}>
                                <ArrowLeft class="size-4" /> Back to {kit_context.code}
                            </Button>
                        {:else}
                            <Button href="/setup/exam-materials/booklets" variant="outline">
                                Back to Booklets
                            </Button>
                        {/if}
                    </div>
                </div>
            {/if}
    <Dialog.Root bind:open={discardDialogOpen}>
        <Dialog.Content>
            <Dialog.Header>
                <Dialog.Title>Discard this draft?</Dialog.Title>
                <Dialog.Description>
                    All unfinalized work on this draft will be permanently deleted.
                </Dialog.Description>
            </Dialog.Header>
            <Dialog.Footer>
                <Button variant="outline" onclick={() => (discardDialogOpen = false)}>Cancel</Button>
                <Button variant="destructive" onclick={discardDraft}>Discard Draft</Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
    <Dialog.Root bind:open={returnDialogOpen}>
        <Dialog.Content>
            <Dialog.Header>
                <Dialog.Title>Leave with unsaved answer keys?</Dialog.Title>
                <Dialog.Description>
                    Unsaved changes for {dirtyKeySets.join(', ')} will be lost if you return to the Test Kit.
                </Dialog.Description>
            </Dialog.Header>
            <Dialog.Footer>
                <Button variant="outline" onclick={() => (returnDialogOpen = false)}>Keep editing</Button>
                <Button variant="destructive" onclick={returnToKit}>Leave without saving</Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
    <Dialog.Root bind:open={archiveDialogOpen}>
        <Dialog.Content>
            <Dialog.Header>
                <Dialog.Title>Archive this booklet?</Dialog.Title>
                <Dialog.Description>
                    This will remove this booklet version from new exam session scheduling. Existing scheduled or completed exam sessions will continue to use their saved snapshot.
                </Dialog.Description>
            </Dialog.Header>
            <Dialog.Footer>
                <Button variant="outline" onclick={() => (archiveDialogOpen = false)}>Cancel</Button>
                <Button variant="destructive" onclick={archiveBooklet}>Archive Booklet</Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
    <Dialog.Root bind:open={restoreDialogOpen}>
        <Dialog.Content>
            <Dialog.Header>
                <Dialog.Title>Restore this booklet?</Dialog.Title>
                <Dialog.Description>
                    This booklet version will become available for new exam session scheduling again.
                </Dialog.Description>
            </Dialog.Header>
            <Dialog.Footer>
                <Button variant="outline" onclick={() => (restoreDialogOpen = false)}>Cancel</Button>
                <Button onclick={restoreBooklet}>Restore to Official</Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
        </section>
    </div>
</StaffLayout>
