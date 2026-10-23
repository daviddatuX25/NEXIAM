<script>
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Copy, Ellipsis, History, Share2, StickyNote, Undo2 } from 'lucide-svelte';

    let { notes = { items: [], pagination: null, counts: null }, selectedNoteId = null, selectedNote = null, isShared = false, loading = false, error = null, denied = false, canEdit = false, canPublish = false, busy = false, onSelect, onNew, onShare, onHistory, onCopy, onRevert, onPage, onSearch, search = $bindable(''), children } = $props();
    const noteItems = $derived(notes?.items ?? []);
    const noNotes = $derived(!loading && !error && !denied && !selectedNote && !search.trim() && notes?.counts?.total === 0);
    let moreOpen = $state(false);
</script>

<section class="min-w-0 space-y-4" aria-label="Advising notes">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold">Advising notes</h2><p class="text-sm text-muted-foreground">Personal or program-staff notes. Consultation sharing is separate.</p></div>
        {#if canEdit && !noNotes && (selectedNoteId !== null || !selectedNote)}<Button type="button" variant="outline" onclick={onNew} disabled={busy}>New note</Button>{/if}
    </header>
    {#if denied}
        <p class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="status">Advising notes are not available for this programme and academic year.</p>
    {:else if loading}
        <p class="py-8 text-sm text-muted-foreground" role="status">Loading notes…</p>
    {:else if error}
        <p class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">{error}</p>
    {:else if noNotes}
        <div class="flex min-h-28 items-center justify-center rounded-lg border border-dashed bg-muted/20 p-5">
            {#if canEdit}
                <Button type="button" variant="outline" class="min-h-11 gap-2 rounded-lg shadow-sm" onclick={onNew} disabled={busy}>
                    <StickyNote class="size-4" aria-hidden="true" /><span>Add note</span>
                </Button>
            {:else}
                <p class="text-sm text-muted-foreground">No advising notes are available.</p>
            {/if}
        </div>
    {:else}
        {#if notes.counts?.total > 0 || search.trim()}
            {#if typeof notes.counts?.total === 'number' && typeof notes.counts?.drafts === 'number' && typeof notes.counts?.shared === 'number'}<p class="text-sm text-muted-foreground">{notes.counts.total} notes · {notes.counts.drafts} drafts · {notes.counts.shared} shared</p>{/if}
            <form class="flex items-center gap-2" onsubmit={(event) => { event.preventDefault(); onSearch?.(); }}>
                <label class="sr-only" for="advising-note-search">Search notes</label>
                <Input id="advising-note-search" bind:value={search} maxlength={120} placeholder="Find note text" />
                <Button type="submit" variant="outline" disabled={busy}>Search</Button>
            </form>
        {/if}
        <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(14rem,0.8fr)_minmax(0,1.4fr)]">
            {#if noteItems.length > 0 || search.trim()}
                <nav aria-label="Notes in this programme and academic year" class="max-h-[55vh] min-w-0 space-y-1 overflow-y-auto border-b pb-3 lg:border-b-0 lg:border-r lg:pr-4">
                    {#each noteItems as note (note.note_id)}
                        <button type="button" aria-current={String(selectedNoteId ?? '') === String(note.note_id) ? 'true' : undefined} class={`block min-h-11 w-full rounded-md px-3 py-2 text-left ${String(selectedNoteId ?? '') === String(note.note_id) ? 'bg-muted font-medium' : 'hover:bg-muted/60'}`} onclick={() => onSelect(note.note_id)}>
                            <span class="block line-clamp-3 whitespace-pre-wrap break-words text-sm">{note.content || 'Untitled note'}</span>
                            <span class="mt-1 block text-xs text-muted-foreground">{note.author_name ?? 'Staff'} · {note.audience === 'private' ? 'Only me' : 'Program staff'} · {note.published_publication_id ? 'Shared for consultation' : 'Draft'}{#if typeof note.version === 'number'} · v{note.version}{/if}</span>
                        </button>
                    {:else}
                        <p class="px-3 py-2 text-sm text-muted-foreground" role="status">{search.trim() ? 'No notes match this search.' : 'No notes are available for this context.'}</p>
                    {/each}
                </nav>
            {/if}
            <div class="min-w-0 space-y-3">
                {#if selectedNote}
                    <div class="flex flex-wrap items-center gap-2" aria-label="Selected note actions">
                        {#if canPublish && selectedNoteId}<Button type="button" variant="outline" class="gap-1.5" disabled={busy} onclick={onShare}><Share2 class="size-4" aria-hidden="true" />{isShared ? 'Stop sharing for consultation' : 'Share saved version for consultation'}</Button>{/if}
                        <Button type="button" variant="ghost" size="icon" class="size-11" aria-label="Note history" title="Note history" disabled={busy} onclick={onHistory}><History class="size-4" aria-hidden="true" /></Button>
                        <div class="relative">
                            <Button type="button" variant="outline" class="min-h-11 gap-2" aria-expanded={moreOpen} aria-controls="advising-note-more-actions" onclick={() => { moreOpen = !moreOpen; }}>
                                <Ellipsis class="size-4" aria-hidden="true" /><span>More</span>
                            </Button>
                            {#if moreOpen}
                                <div id="advising-note-more-actions" class="absolute right-0 z-20 mt-1 grid min-w-48 gap-1 rounded-md border bg-background p-2 shadow-md" aria-label="More note actions">
                                    <button type="button" class="flex min-h-11 items-center gap-2 rounded px-2 text-left text-sm hover:bg-muted" disabled={busy} onclick={() => { moreOpen = false; onCopy?.(); }}><Copy class="size-4" aria-hidden="true" />Copy to year</button>
                                    <button type="button" class="flex min-h-11 items-center gap-2 rounded px-2 text-left text-sm hover:bg-muted" disabled={busy} onclick={() => { moreOpen = false; onRevert?.(); }}><Undo2 class="size-4" aria-hidden="true" />Restore a version</button>
                                </div>
                            {/if}
                        </div>
                    </div>
                    {@render children?.()}
                {:else if noteItems.length > 0}
                    <p class="py-8 text-sm text-muted-foreground">Choose a note to view or edit it.</p>
                {/if}
            </div>
        </div>
        {#if notes.pagination?.last_page > 1}
            <div class="flex items-center gap-3 border-t pt-3" aria-label="Advising note pages">
                <Button type="button" variant="outline" size="sm" disabled={busy || notes.pagination.current_page <= 1} onclick={() => onPage(notes.pagination.current_page - 1)}>Previous</Button>
                <span class="text-xs text-muted-foreground">Page {notes.pagination.current_page} of {notes.pagination.last_page}</span>
                <Button type="button" variant="outline" size="sm" disabled={busy || notes.pagination.current_page >= notes.pagination.last_page} onclick={() => onPage(notes.pagination.current_page + 1)}>Next</Button>
            </div>
        {/if}
    {/if}
</section>
