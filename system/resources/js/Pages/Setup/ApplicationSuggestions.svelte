<script lang="ts">
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { router } from '@inertiajs/svelte';
    import { RefreshCw, Save, Upload } from 'lucide-svelte';
    import {
        appendSuggestionEntries,
        createSuggestionDrafts,
        parseSuggestionCsv,
        parseSuggestionDraft,
        suggestionCategories,
        type SuggestionCategory,
        type SuggestionDrafts,
        type SuggestionEntry,
        type SuggestionList,
    } from '@/lib/application-suggestion-editor';

    type Lists = Record<SuggestionCategory, SuggestionList>;
    let { suggestion_lists } = $props<{ suggestion_lists: Lists }>();
    let selected = $state<SuggestionCategory>('schools');
    let drafts = $state<SuggestionDrafts>(
        createSuggestionDrafts(suggestion_lists),
    );
    let saving = $state(false);
    let uploading = $state(false);
    let reloading = $state(false);
    let status = $state('');
    const names: Record<SuggestionCategory, string> = {
        schools: 'Schools',
        shs: 'Strands / tracks',
        programs: 'Previous courses',
    };
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Application suggestions' },
    ];
    const draft = $derived(drafts[selected]);
    const busy = $derived(saving || uploading || reloading);

    function save(): void {
        const category = selected;
        const targetDraft = drafts[category];
        const entries = parseSuggestionDraft(targetDraft.text);
        if (entries.length > 5000) {
            targetDraft.error = 'A list can contain at most 5,000 names.';
            return;
        }
        targetDraft.error = '';
        status = '';
        targetDraft.stale = false;
        saving = true;
        router.put(
            '/setup/application-suggestions',
            {
                category,
                expected_revision: targetDraft.revision,
                entries,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const updated = page.props.suggestion_lists as
                        Lists | undefined;
                    if (updated) {
                        drafts[category] =
                            createSuggestionDrafts(updated)[category];
                        suggestion_lists = updated;
                    }
                    status = `${names[category]} saved.`;
                },
                onError: (errors) => {
                    const message = Object.values(errors).find(
                        (value) => typeof value === 'string',
                    );
                    if (typeof message === 'string') {
                        targetDraft.error = message;
                        targetDraft.stale = 'expected_revision' in errors;
                    } else {
                        targetDraft.error =
                            'Could not save this list. Your draft is still here; try again.';
                    }
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }

    function reloadLatest(): void {
        const category = selected;
        status = '';
        reloading = true;
        router.get(
            '/setup/application-suggestions',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    const updated = page.props.suggestion_lists as
                        Lists | undefined;
                    if (!updated) return;
                    drafts[category] =
                        createSuggestionDrafts(updated)[category];
                    suggestion_lists = updated;
                    status = 'Saved list reloaded; this draft was replaced.';
                },
                onFinish: () => {
                    reloading = false;
                },
            },
        );
    }

    async function stageFile(event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) return;
        const category = selected;
        const targetDraft = drafts[category];
        uploading = true;
        targetDraft.error = '';
        try {
            if (file.size > 2 * 1024 * 1024)
                throw new Error('CSV must be 2 MB or smaller.');
            if (
                !file.name.toLowerCase().endsWith('.csv') &&
                file.type !== 'text/csv' &&
                file.type !== 'text/plain'
            ) {
                throw new Error(
                    'Choose a CSV text file; PDF and links are not supported.',
                );
            }
            const bytes = await file.arrayBuffer();
            const text = new TextDecoder('utf-8', { fatal: true }).decode(
                bytes,
            );
            const entries: SuggestionEntry[] = parseSuggestionCsv(text);
            if (
                parseSuggestionDraft(targetDraft.text).length + entries.length >
                5000
            )
                throw new Error('Combined draft would exceed 5,000 names.');
            targetDraft.text = appendSuggestionEntries(
                targetDraft.text,
                entries,
            );
            status = `${entries.length} entries staged in ${names[category]}; save to publish.`;
        } catch (error) {
            targetDraft.error =
                error instanceof Error
                    ? error.message
                    : 'Could not read this UTF-8 CSV.';
        } finally {
            input.value = '';
            uploading = false;
        }
    }

    function changeCategory(event: Event): void {
        selected = (event.currentTarget as HTMLSelectElement)
            .value as SuggestionCategory;
        status = '';
    }
</script>

<svelte:head><title>Application suggestions · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-5xl min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Application suggestions"
            description="Optional names applicants can choose while entering academic history."
        />
        <section
            class="border-border bg-card space-y-4 rounded-lg border p-5 sm:p-6"
            aria-label="Application suggestion list editor"
        >
            <div class="max-w-sm space-y-2">
                <label for="suggestion-category" class="text-sm font-medium"
                    >List</label
                >
                <select
                    id="suggestion-category"
                    disabled={busy}
                    class="border-input bg-background focus-visible:ring-ring min-h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                    value={selected}
                    onchange={changeCategory}
                >
                    {#each suggestionCategories as category (category)}
                        <option value={category}>{names[category]}</option>
                    {/each}
                </select>
            </div>
            <div class="space-y-2">
                <label for="suggestion-lines" class="text-sm font-medium"
                    >Names and optional aliases</label
                >
                <p id="suggestion-format" class="text-muted-foreground text-sm">
                    One per line; add aliases after <span
                        class="text-foreground">|</span
                    >, separated by commas.
                </p>
                <textarea
                    id="suggestion-lines"
                    aria-describedby="suggestion-format"
                    disabled={busy}
                    bind:value={draft.text}
                    rows="12"
                    spellcheck="false"
                    placeholder="Example School | ES, Example HS"
                    class="border-input bg-background focus-visible:ring-ring min-h-64 w-full resize-y rounded-md border px-3 py-3 text-sm leading-6 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                ></textarea>
            </div>
            {#if draft.error}<p class="text-destructive text-sm" role="alert">
                    {draft.error}
                </p>{/if}
            {#if status}<p class="text-muted-foreground text-sm" role="status">
                    {status}
                </p>{/if}
            <div
                class="border-border flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-muted-foreground text-sm">
                    CSV appends to this draft; Save publishes it. UTF-8, 2 MB
                    maximum.
                </p>
                <div class="flex flex-wrap gap-2">
                    {#if draft.stale}<Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onclick={reloadLatest}
                            ><RefreshCw class="size-4" />Reload saved list
                            (replaces this draft)</Button
                        >{/if}
                    <label
                        class="border-input bg-background hover:bg-muted focus-within:ring-ring inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-4 text-sm font-medium focus-within:ring-2 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
                    >
                        <Upload class="size-4" />{uploading
                            ? 'Reading CSV…'
                            : 'Upload CSV'}
                        <input
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            disabled={busy}
                            onchange={stageFile}
                            class="sr-only"
                        />
                    </label>
                    <Button
                        type="button"
                        onclick={save}
                        disabled={busy}
                        class="min-h-11 gap-2"
                        ><Save class="size-4" />{saving
                            ? 'Saving…'
                            : 'Save'}</Button
                    >
                </div>
            </div>
        </section>
    </div>
</StaffLayout>
