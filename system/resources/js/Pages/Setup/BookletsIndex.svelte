<script>
    import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NewBookletDialog from '@/Components/Setup/NewBookletDialog.svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { BookOpen, FileText, KeyRound } from 'lucide-svelte';

    let {
        booklets = [],
        errors = {},
        can_view_question_content = false,
        enable_multi_set_booklets = false,
        exam_feature_policy_version = 1,
        kit_context = null,
    } = $props();

    let setPolicySaving = $state(false);

    function toggleSetPolicy(enabled) {
        if (setPolicySaving) return;
        setPolicySaving = true;
        router.post(
            '/setup/exam-materials/booklets/set-policy',
            {
                enable_multi_set_booklets: enabled,
                expected_version: exam_feature_policy_version,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setPolicySaving = false;
                },
            },
        );
    }

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Booklets' },
    ];

    let selectedBooklet = $state(null);
    let discardDialogOpen = $state(false);
    let archiveDialogOpen = $state(false);

    const draftBooklets = $derived(
        booklets.filter((b) => b.lifecycle_status === 'draft'),
    );
    const officialBooklets = $derived(
        booklets.filter(
            (b) =>
                b.lifecycle_status === 'official' ||
                (!b.lifecycle_status && b.status === 'finalized'),
        ),
    );
    const archivedBooklets = $derived(
        booklets.filter((b) => b.lifecycle_status === 'archived'),
    );

    function confirmDiscard() {
        if (!selectedBooklet?.destroy_url) return;

        router.delete(selectedBooklet.destroy_url, {
            preserveScroll: true,
            onSuccess: () => {
                discardDialogOpen = false;
                selectedBooklet = null;
            },
        });
    }

    function confirmArchive() {
        if (!selectedBooklet?.archive_url) return;

        router.post(
            selectedBooklet.archive_url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    archiveDialogOpen = false;
                    selectedBooklet = null;
                },
            },
        );
    }
    function modeLabel(mode) {
        return mode === 'stored_questions'
            ? 'Stored questions'
            : 'Answer keys only';
    }

    function progressLabel(booklet) {
        if (booklet.mode === 'stored_questions') {
            return `${booklet.question_count} of ${booklet.required_count} questions`;
        }

        if (booklet.sets.length === 0) return 'No Set keys entered';
        const complete = booklet.sets.filter((set) => set.is_complete).length;
        return `${complete} of ${booklet.sets.length} Sets complete`;
    }

    function nextActionLabel(action, finalized) {
        if (finalized) return 'Open Booklet';
        if (action === 'author_sections' || action === 'author_items') return 'Continue Booklet items';
        if (action === 'generate_sets') return 'Generate Sets';
        if (action === 'enter_answer_keys' || action === 'complete_sets')
            return 'Continue keys';
        if (action === 'finalize') return 'Review Booklet';
        return 'Manage Booklet';
    }
</script>

<svelte:head><title>Question Booklets · Exam Materials</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Question Booklets"
            description="Manage exam question booklets, subject sections, and verified answer keys."
        >
            {#snippet actions()}
                <div class="flex items-center gap-2">
                    {#if kit_context?.back_url}
                        <Button
                            variant="outline"
                            onclick={() => router.visit(kit_context.back_url)}
                        >
                            Back to {kit_context.code}
                        </Button>
                    {/if}
                    <NewBookletDialog {can_view_question_content} {kit_context} />
                </div>
            {/snippet}
        </PageHeaderBar>

        {#if errors.booklet}
            <p
                class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/35 dark:text-red-100"
            >
                {errors.booklet}
            </p>
        {/if}
        {#if kit_context && kit_context.booklet_version_id === null}
            <p class="rounded-lg border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                Not selected for {kit_context.code}.
            </p>
        {/if}
        <section class="craft-card-depth flex flex-col gap-4 rounded-xl border border-border/70 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold">Alternate Booklet Sets</h2>
                    <Badge variant={enable_multi_set_booklets ? 'default' : 'secondary'}>
                        {enable_multi_set_booklets ? 'Available for new drafts' : 'Set A only by default'}
                    </Badge>
                </div>
                <p id="booklet-set-policy-help" class="text-muted-foreground max-w-2xl text-sm">
                    {enable_multi_set_booklets
                        ? 'New Booklets still start with Set A. Staff must opt into A–D on each draft; finalized keys stay unchanged.'
                        : 'Each Booklet uses its own Set A key. Existing finalized Sets and published allocations are retained.'}
                </p>
            </div>
            <label class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2.5 text-sm font-medium select-none">
                <input
                    type="checkbox"
                    role="switch"
                    class="accent-primary size-4 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    checked={enable_multi_set_booklets}
                    aria-checked={enable_multi_set_booklets}
                    aria-describedby="booklet-set-policy-help"
                    disabled={setPolicySaving}
                    onchange={(event) => toggleSetPolicy(event.currentTarget.checked)}
                />
                <span>Allow alternate Sets</span>
                {#if setPolicySaving}<span class="text-muted-foreground text-xs" aria-live="polite">Saving…</span>{/if}
            </label>
        </section>
        {#if errors.policy}
            <p class="text-destructive text-sm" role="alert">{errors.policy}</p>
        {/if}

        {#if booklets.length === 0}
            <section
                class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"
            >
                <div
                    class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl"
                >
                    <BookOpen class="text-muted-foreground size-6" />
                </div>
                <h2 class="mt-4 font-semibold">No Booklets yet</h2>
                <p class="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                    Create a Booklet to maintain answer keys for externally
                    printed test booklets (default) or author questions securely.
                </p>
                <div class="mt-5 flex justify-center">
                    <NewBookletDialog {can_view_question_content} {kit_context} />
                </div>
            </section>
        {:else}
            <!-- Official Booklets section -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2
                        class="text-muted-foreground flex items-center gap-2 text-xs font-bold tracking-wider uppercase"
                    >
                        <span
                            class="size-2.5 rounded-full bg-emerald-500 {officialBooklets.length
                                ? 'animate-pulse'
                                : 'opacity-50'}"
                        ></span>
                        Finalized Booklets ({officialBooklets.length})
                    </h2>
                    {#if officialBooklets.length > 0}
                        <Badge
                            class="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                        >
                            Select in a Test Kit for operational use
                        </Badge>
                    {/if}
                </div>

                {#if officialBooklets.length > 0}
                    <div
                        class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"
                    >
                        {#each officialBooklets as booklet (booklet.id)}
                            <article
                                class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"
                            >
                                <MaterialDocumentPreview
                                    src={booklet.thumbnail_url}
                                    overlay="booklet"
                                    kind={booklet.mode}
                                    title={booklet.name}
                                    sets={booklet.sets}
                                    alt={`${booklet.name} ${modeLabel(booklet.mode)} preview`}
                                />

                                <div class="min-w-0 self-stretch py-0.5">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <h3
                                            class="text-foreground text-base font-semibold sm:text-lg"
                                        >
                                            {booklet.name}
                                        </h3>
                                        <Badge
                                            class="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                        >
                                            Finalized · Not operational alone
                                        </Badge>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        {#if booklet.mode === 'stored_questions'}
                                            <Badge variant="outline" class="border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold">
                                                <FileText class="size-3 mr-1" /> Stored Questions & Choices
                                            </Badge>
                                        {:else}
                                            <Badge variant="outline" class="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold">
                                                <KeyRound class="size-3 mr-1" /> Answer Key Only
                                            </Badge>
                                            <span class="text-xs text-muted-foreground">
                                                Questions external · Keys stored for scoring
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        {progressLabel(booklet)} · {booklet.section_count}
                                        section{booklet.section_count === 1 ? '' : 's'}
                                    </p>

                                    {#if booklet.sets.length > 0}
                                        <div
                                            class="mt-3 flex flex-wrap gap-1.5"
                                            aria-label={`${booklet.name} Sets`}
                                        >
                                            {#each booklet.sets as set (set.code)}
                                                <span
                                                    class="border-border bg-background inline-flex min-h-7 items-center rounded-md border px-2 text-xs font-semibold"
                                                    class:text-emerald-700={set.is_complete}
                                                    class:dark:text-emerald-300={set.is_complete}
                                                    class:text-muted-foreground={!set.is_complete}
                                                    >Set {set.code}{set.is_complete
                                                        ? ' · Complete'
                                                        : ' · Incomplete'}</span
                                                >
                                            {/each}
                                        </div>
                                    {/if}
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:self-center">
                                    <Link href={booklet.url} viewTransition>
                                        <Button variant="outline" class="min-h-11 w-full sm:w-auto">
                                            Open Booklet
                                        </Button>
                                    </Link>
                                    {#if booklet.can_archive && booklet.archive_url}
                                        <Button
                                            variant="outline"
                                            class="min-h-11 w-full sm:w-auto"
                                            onclick={() => {
                                                selectedBooklet = booklet;
                                                archiveDialogOpen = true;
                                            }}
                                        >
                                            Archive
                                        </Button>
                                    {/if}
                                </div>
                            </article>
                        {/each}
                    </div>
                {:else}
                    <div
                        class="border-border bg-card/50 flex flex-col items-center justify-center rounded-xl border border-dashed p-6 text-center"
                    >
                        <p class="text-foreground text-sm font-medium">
                            No finalized Booklets yet.
                        </p>
                        <p
                            class="text-muted-foreground mt-1 max-w-sm text-xs leading-relaxed"
                        >
                            Finalize an authored Booklet below to lock its question content and answer keys for exam session scheduling.
                        </p>
                    </div>
                {/if}
            </section>

            <!-- Drafts section -->
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2
                        class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                    >
                        Drafts ({draftBooklets.length})
                    </h2>
                </div>

                {#if draftBooklets.length > 0}
                    <div
                        class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"
                    >
                        {#each draftBooklets as booklet (booklet.id)}
                            <article
                                class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"
                            >
                                <MaterialDocumentPreview
                                    src={booklet.thumbnail_url}
                                    overlay="booklet"
                                    kind={booklet.mode}
                                    title={booklet.name}
                                    sets={booklet.sets}
                                    alt={`${booklet.name} ${modeLabel(booklet.mode)} preview`}
                                />

                                <div class="min-w-0 self-stretch py-0.5">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <h3
                                            class="text-foreground text-base font-semibold sm:text-lg"
                                        >
                                            {booklet.name}
                                        </h3>
                                        <Badge variant="secondary" class="capitalize">
                                            Draft
                                        </Badge>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        {#if booklet.mode === 'stored_questions'}
                                            <Badge variant="outline" class="border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold">
                                                <FileText class="size-3 mr-1" /> Stored Questions & Choices
                                            </Badge>
                                        {:else}
                                            <Badge variant="outline" class="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold">
                                                <KeyRound class="size-3 mr-1" /> Answer Key Only
                                            </Badge>
                                            <span class="text-xs text-muted-foreground">
                                                Questions external · Keys stored for scoring
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        {progressLabel(booklet)} · {booklet.section_count}
                                        section{booklet.section_count === 1 ? '' : 's'}
                                    </p>

                                    {#if booklet.sets.length > 0}
                                        <div
                                            class="mt-3 flex flex-wrap gap-1.5"
                                            aria-label={`${booklet.name} Sets`}
                                        >
                                            {#each booklet.sets as set (set.code)}
                                                <span
                                                    class="border-border bg-background inline-flex min-h-7 items-center rounded-md border px-2 text-xs font-semibold"
                                                    class:text-emerald-700={set.is_complete}
                                                    class:dark:text-emerald-300={set.is_complete}
                                                    class:text-muted-foreground={!set.is_complete}
                                                    >Set {set.code}{set.is_complete
                                                        ? ' · Complete'
                                                        : ' · Incomplete'}</span
                                                >
                                            {/each}
                                        </div>
                                    {/if}
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:self-center">
                                    <Link
                                        href={booklet.url}
                                        viewTransition
                                    >
                                        <Button variant="outline" class="min-h-11 w-full sm:w-auto">
                                            {nextActionLabel(booklet.next_action, false)}
                                        </Button>
                                    </Link>
                                    {#if booklet.can_discard && booklet.destroy_url}
                                        <Button
                                            variant="outline"
                                            class="min-h-11 w-full text-destructive hover:bg-destructive/10 sm:w-auto"
                                            onclick={() => {
                                                selectedBooklet = booklet;
                                                discardDialogOpen = true;
                                            }}
                                        >
                                            Discard
                                        </Button>
                                    {/if}
                                </div>
                            </article>
                        {/each}
                    </div>
                {:else}
                    <div
                        class="border-border bg-card rounded-xl border border-dashed px-6 py-12 text-center"
                    >
                        <div
                            class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl"
                        >
                            <BookOpen class="text-muted-foreground size-6" />
                        </div>
                        <h3 class="mt-4 font-semibold">No Drafts in Progress</h3>
                        <p
                            class="text-muted-foreground mx-auto mt-1 max-w-md text-sm"
                        >
                            Create a Booklet to author questions securely or maintain
                            answer keys for externally prepared Sets.
                        </p>
                        <div class="mt-5 flex justify-center">
                            <NewBookletDialog {can_view_question_content} {kit_context} />
                        </div>
                    </div>
                {/if}
            </section>
            {#if archivedBooklets.length > 0}
                <section class="space-y-3">
                    <h2 class="text-muted-foreground text-xs font-bold tracking-wider uppercase">
                        Archived Booklets · Retired ({archivedBooklets.length})
                    </h2>
                    <div class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border">
                        {#each archivedBooklets as booklet (booklet.id)}
                            <article class="grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">
                                <MaterialDocumentPreview
                                    src={booklet.thumbnail_url}
                                    overlay="booklet"
                                    kind={booklet.mode}
                                    title={booklet.name}
                                    sets={booklet.sets}
                                    alt={`${booklet.name} ${modeLabel(booklet.mode)} preview`}
                                />
                                <div class="min-w-0 self-stretch py-0.5">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <h3 class="text-foreground text-base font-semibold sm:text-lg">{booklet.name}</h3>
                                        <Badge variant="secondary">Archived · Retired</Badge>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        {#if booklet.mode === 'stored_questions'}
                                            <Badge variant="outline" class="border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300 text-xs font-semibold">
                                                <FileText class="size-3 mr-1" /> Stored Questions
                                            </Badge>
                                        {:else}
                                            <Badge variant="outline" class="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold">
                                                <KeyRound class="size-3 mr-1" /> Answer Key Only
                                            </Badge>
                                        {/if}
                                    </div>
                                    <p class="text-muted-foreground mt-1 text-sm">{progressLabel(booklet)} · {booklet.section_count} sections</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:self-center">
                                    <Link href={booklet.url} viewTransition>
                                        <Button variant="outline" class="min-h-11 w-full sm:w-auto">View Key</Button>
                                    </Link>
                                    {#if booklet.can_restore && booklet.restore_url}
                                        <Button
                                            variant="outline"
                                            class="min-h-11 w-full sm:w-auto"
                                            onclick={() => router.post(booklet.restore_url, {}, { preserveScroll: true })}
                                        >
                                            Restore to Official
                                        </Button>
                                    {/if}
                                </div>
                            </article>
                        {/each}
                    </div>
                </section>
            {/if}
            <Dialog.Root bind:open={discardDialogOpen}>
                <Dialog.Content>
                    <Dialog.Header>
                        <Dialog.Title>Discard draft for {selectedBooklet?.name}?</Dialog.Title>
                        <Dialog.Description>
                            All questions, keys, and configurations authored in this draft version will be permanently deleted. This action cannot be undone.
                        </Dialog.Description>
                    </Dialog.Header>
                    <Dialog.Footer>
                        <Button variant="outline" onclick={() => (discardDialogOpen = false)}>Cancel</Button>
                        <Button variant="destructive" onclick={confirmDiscard}>Discard draft</Button>
                    </Dialog.Footer>
                </Dialog.Content>
            </Dialog.Root>
            <Dialog.Root bind:open={archiveDialogOpen}>
                <Dialog.Content>
                    <Dialog.Header>
                        <Dialog.Title>Archive {selectedBooklet?.name}?</Dialog.Title>
                        <Dialog.Description>
                            This will remove this booklet version from new exam session scheduling. Existing scheduled or completed exam sessions will continue to use their saved snapshot.
                        </Dialog.Description>
                    </Dialog.Header>
                    <Dialog.Footer>
                        <Button variant="outline" onclick={() => (archiveDialogOpen = false)}>Cancel</Button>
                        <Button variant="destructive" onclick={confirmArchive}>Archive Booklet</Button>
                    </Dialog.Footer>
                </Dialog.Content>
            </Dialog.Root>
        {/if}
    </div>
</StaffLayout>
