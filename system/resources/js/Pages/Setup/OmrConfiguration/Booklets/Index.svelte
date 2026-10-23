<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import {
        BookOpen,
        Plus,
        ArrowRight,
        Eye,
        AlertCircle,
        Trash2,
    } from 'lucide-svelte';
    import {
        success as toastSuccess,
        error as toastError,
    } from '@/lib/toast.js';

    let { booklets = [], areas = [], errors = {} } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Booklets' },
    ];

    let confirmKey = $state(null);
    let lastRetireError = null;

    $effect(() => {
        if (errors.omr_retire && errors.omr_retire !== lastRetireError) {
            lastRetireError = errors.omr_retire;
            toastError(errors.omr_retire);
        } else if (!errors.omr_retire) {
            lastRetireError = null;
        }
    });

    function latestVersion(booklet) {
        return booklet.versions?.[0] ?? null;
    }

    function retire(kind, id) {
        router.post(
            '/setup/omr/retire',
            { kind, id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    confirmKey = null;
                    toastSuccess('Booklet version retired.');
                },
                onError: (e) => {
                    confirmKey = null;
                    toastError(e.omr_retire ?? 'Could not retire.');
                },
            },
        );
    }
</script>

<StaffLayout {breadcrumbs}>
    <PageHeaderBar
        title="Answer Booklets"
        description="Question booklets and verified answer keys configured for pairing with optical answer-sheet templates."
    >
        {#snippet actions()}
            <Button href="/setup/omr/booklets/create" size="sm"
                >Author new booklet</Button
            >
        {/snippet}
    </PageHeaderBar>

    <section class="space-y-6 p-6">
        {#if errors.omr_retire}
            <div
                class="border-destructive/50 bg-destructive/10 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3 text-sm"
            >
                <AlertCircle class="size-4 shrink-0" />
                <span>{errors.omr_retire}</span>
            </div>
        {/if}

        <div class="grid gap-4 sm:grid-cols-2">
            {#each booklets as booklet (booklet.id)}
                {@const latest = latestVersion(booklet)}
                {@const drafts =
                    booklet.versions?.filter((v) => v.status === 'draft') ?? []}
                {@const activeVer =
                    booklet.versions?.find((v) => v.status === 'active') ??
                    null}

                <div
                    class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 shadow-xs transition-all hover:shadow-xs"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                >
                                    <BookOpen class="size-5" />
                                </div>
                                <div>
                                    <h3
                                        class="text-foreground text-sm font-semibold"
                                    >
                                        <Link
                                            href={`/setup/omr/booklets/${booklet.id}`}
                                            class="hover:text-primary hover:underline"
                                        >
                                            {booklet.name}
                                        </Link>
                                    </h3>
                                    <p
                                        class="text-muted-foreground font-mono text-xs"
                                    >
                                        {booklet.code}
                                    </p>
                                </div>
                            </div>

                            {#if activeVer}
                                <Badge variant="default" class="text-[10px]"
                                    >Active v{activeVer.version}</Badge
                                >
                            {:else if drafts.length > 0}
                                <Badge variant="secondary" class="text-[10px]"
                                    >Draft v{drafts[0].version}</Badge
                                >
                            {/if}
                        </div>

                        <!-- Version summary info -->
                        <div
                            class="border-border/60 bg-muted/20 text-muted-foreground mt-4 space-y-2 rounded-lg border p-3 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span>Total versions:</span>
                                <span class="text-foreground font-medium"
                                    >{booklet.versions?.length ?? 0}</span
                                >
                            </div>
                            {#if latest}
                                <div class="flex items-center justify-between">
                                    <span>Questions in v{latest.version}:</span>
                                    <span class="text-foreground font-medium"
                                        >{latest.questions_count ?? 0} questions</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between font-mono text-[11px]"
                                >
                                    <span>Key digest:</span>
                                    <span title={latest.answer_key_hash ?? ''}>
                                        {(latest.answer_key_hash ?? '').slice(
                                            0,
                                            12,
                                        )}…
                                    </span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Bottom Actions -->
                    <div
                        class="border-border/60 mt-5 flex items-center justify-between border-t pt-3.5"
                    >
                        <Button
                            href={`/setup/omr/booklets/${booklet.id}`}
                            variant="outline"
                            size="xs"
                        >
                            View key & versions
                        </Button>

                        {#if drafts.length > 0}
                            <Button
                                href={`/setup/omr/booklets/create?draft=${drafts[0].id}`}
                                size="xs"
                            >
                                Edit draft v{drafts[0].version}
                            </Button>
                        {/if}
                    </div>
                </div>
            {/each}

            {#if booklets.length === 0}
                <div
                    class="border-border/80 col-span-2 rounded-xl border border-dashed p-10 text-center"
                >
                    <BookOpen class="text-muted-foreground mx-auto size-9" />
                    <h3 class="text-foreground mt-2 text-sm font-semibold">
                        No question booklets yet
                    </h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Author an answer booklet with aptitude area questions to
                        pair with answer sheets.
                    </p>
                    <Button
                        href="/setup/omr/booklets/create"
                        size="sm"
                        class="mt-4">Author first booklet</Button
                    >
                </div>
            {/if}
        </div>
    </section>
</StaffLayout>
