<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { router } from '@inertiajs/svelte';
    import {
        FileText,
        BookOpen,
        Link2,
        Printer,
        Eye,
        AlertCircle,
        ArrowRight,
        Sparkles,
    } from 'lucide-svelte';
    import {
        error as toastError,
        success as toastSuccess,
    } from '@/lib/toast.js';

    let {
        templates = [],
        booklets = [],
        pairs = [],
        print_jobs = [],
        readiness = {
            active_pair: false,
            draft_templates: false,
            draft_booklets: false,
            paper: 'long',
        },
        errors = {},
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'OMR Configuration' },
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

    const draftTemplatesCount = $derived(
        templates.reduce(
            (acc, t) =>
                acc +
                (t.versions?.filter((v) => v.status === 'draft').length || 0),
            0,
        ),
    );

    const draftBookletsCount = $derived(
        booklets.reduce(
            (acc, b) =>
                acc +
                (b.versions?.filter((v) => v.status === 'draft').length || 0),
            0,
        ),
    );

    const activePairs = $derived(pairs.filter((p) => p.status === 'active'));

    function formatDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return Number.isNaN(d.getTime())
            ? iso
            : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function retire(kind, id) {
        router.post(
            '/setup/omr/retire',
            { kind, id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    confirmKey = null;
                    toastSuccess('Configuration retired.');
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
        title="OMR Configuration"
        description="Answer-sheet designs, answer keys, and exam scanning pairings."
    >
        {#snippet actions()}
            <Button
                href="/setup/exam-materials/answer-sheets"
                variant="outline"
                size="sm">Answer Sheets</Button
            >
            <Button
                href="/setup/omr/booklets"
                variant="outline"
                size="sm">Booklets</Button
            >
        {/snippet}
    </PageHeaderBar>

    <section class="space-y-8 p-6">
        {#if errors.omr_retire}
            <div
                class="border-destructive/50 bg-destructive/10 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3 text-sm"
            >
                <AlertCircle class="size-4 shrink-0" />
                <span>{errors.omr_retire}</span>
            </div>
        {/if}

        <!-- 1. The Two Main Doors -->
        <div class="grid gap-5 md:grid-cols-2">
            <!-- Door 1: Answer Sheets -->
            <div
                class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"
            >
                <div>
                    <div class="flex items-start justify-between">
                        <div
                            class="flex size-11 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                        >
                            <FileText class="size-6" />
                        </div>
                        <Badge variant="secondary" class="text-xs">
                            {templates.length}
                            {templates.length === 1 ? 'template' : 'templates'}
                            {#if draftTemplatesCount > 0}
                                <span class="text-muted-foreground ml-1"
                                    >· {draftTemplatesCount} draft</span
                                >
                            {/if}
                        </Badge>
                    </div>

                    <h3 class="text-foreground mt-4 text-lg font-semibold">
                        Answer-sheet templates
                    </h3>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Bubble-sheet designs with version history, printable
                        text, and PDF previews.
                    </p>
                </div>

                <div
                    class="border-border/60 mt-5 flex flex-wrap items-center justify-between gap-2 border-t pt-4"
                >
                    <Button
                        href="/setup/exam-materials/answer-sheets"
                        variant="outline"
                        size="xs">Answer Sheets</Button
                    >
                    <Button
                        href="/setup/omr/templates"
                        variant="ghost"
                        size="xs"
                    >
                        View templates ({templates.length})
                        <ArrowRight class="ml-1 size-3" />
                    </Button>
                </div>
            </div>

            <!-- Door 2: Question Booklets -->
            <div
                class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"
            >
                <div>
                    <div class="flex items-start justify-between">
                        <div
                            class="flex size-11 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400"
                        >
                            <BookOpen class="size-6" />
                        </div>
                        <Badge variant="secondary" class="text-xs">
                            {booklets.length}
                            {booklets.length === 1 ? 'booklet' : 'booklets'}
                            {#if draftBookletsCount > 0}
                                <span class="text-muted-foreground ml-1"
                                    >· {draftBookletsCount} draft</span
                                >
                            {/if}
                        </Badge>
                    </div>

                    <h3 class="text-foreground mt-4 text-lg font-semibold">
                        Question booklets
                    </h3>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Answer keys with correct options (A–D) grouped by
                        aptitude area.
                    </p>
                </div>

                <div
                    class="border-border/60 mt-5 flex flex-wrap items-center justify-between gap-2 border-t pt-4"
                >
                    <Button
                        href="/setup/omr/booklets"
                        variant="outline"
                        size="xs">Booklets</Button
                    >
                </div>
            </div>
        </div>

        <!-- 2. Visual Pairing Diagram -->
        <div class="border-border/90 bg-card rounded-xl border p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-foreground text-base font-semibold">
                            Active scanning pairs
                        </h2>
                        <Badge variant="secondary" class="font-mono text-xs"
                            >{activePairs.length} active</Badge
                        >
                    </div>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        What gets scanned and scored together. Each pair couples
                        one template version with an answer booklet version.
                    </p>
                </div>

                <Button href="/setup/omr/booklets" size="sm"
                    >Booklets</Button
                >
            </div>

            <div class="mt-6 space-y-4">
                {#if activePairs.length > 0}
                    {#each activePairs as pair (pair.id)}
                        <div
                            class="border-border/70 bg-muted/20 rounded-xl border p-4"
                        >
                            <div
                                class="flex flex-col gap-3.5 md:flex-row md:items-center md:justify-between"
                            >
                                <!-- Left: Answer Sheet Template -->
                                <div
                                    class="border-border/60 bg-card flex min-w-0 flex-1 items-center gap-3 rounded-lg border p-3.5"
                                >
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                    >
                                        <FileText class="size-4.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <strong
                                                class="text-foreground truncate text-sm font-semibold"
                                                >{pair.template_name}</strong
                                            >
                                            <Badge
                                                variant="secondary"
                                                class="shrink-0 text-[10px]"
                                                >v{pair.template_version}</Badge
                                            >
                                        </div>
                                        <div
                                            class="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs"
                                        >
                                            <span class="font-mono"
                                                >{pair.template_code}</span
                                            >
                                            <span>·</span>
                                            <span
                                                >{pair.page_width}×{pair.page_height}</span
                                            >
                                        </div>
                                    </div>
                                    {#if pair.template_version_id}
                                        <Button
                                            href={`/setup/omr/templates/${pair.template_version_id}/preview`}
                                            variant="ghost"
                                            size="icon-xs"
                                            class="text-muted-foreground shrink-0"
                                            title="Preview sheet PDF"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <Eye class="size-3.5" />
                                        </Button>
                                    {/if}
                                </div>

                                <!-- Center: Connector -->
                                <div
                                    class="flex shrink-0 items-center justify-center"
                                >
                                    <div
                                        class="border-primary/30 bg-primary/10 text-primary flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold"
                                    >
                                        <Link2 class="size-3.5" />
                                        <span>Paired</span>
                                    </div>
                                </div>

                                <!-- Right: Question Booklet -->
                                <div
                                    class="border-border/60 bg-card flex min-w-0 flex-1 items-center gap-3 rounded-lg border p-3.5"
                                >
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400"
                                    >
                                        <BookOpen class="size-4.5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <strong
                                                class="text-foreground truncate text-sm font-semibold"
                                                >{pair.booklet_name}</strong
                                            >
                                            <Badge
                                                variant="secondary"
                                                class="shrink-0 text-[10px]"
                                                >v{pair.booklet_version}</Badge
                                            >
                                        </div>
                                        <div
                                            class="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs"
                                        >
                                            <span class="font-mono"
                                                >{pair.booklet_code}</span
                                            >
                                            <span>·</span>
                                            <span>Answer key active</span>
                                        </div>
                                    </div>
                                    {#if pair.booklet_id}
                                        <Button
                                            href={`/setup/omr/booklets/${pair.booklet_id}`}
                                            variant="outline"
                                            size="xs"
                                            class="shrink-0"
                                        >
                                            View key
                                        </Button>
                                    {/if}
                                </div>

                                <!-- Actions -->
                                <div
                                    class="flex shrink-0 items-center justify-end"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-muted-foreground hover:text-destructive"
                                        onclick={() =>
                                            (confirmKey = `pair-${pair.id}`)}
                                    >
                                        Unpair
                                    </Button>
                                </div>
                            </div>

                            {#if confirmKey === `pair-${pair.id}`}
                                <div
                                    class="border-destructive/40 bg-destructive/10 text-destructive mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-xs"
                                >
                                    <span
                                        >Retire this active pair? Open grading
                                        sessions keep their pinned pair; new
                                        sessions cannot select it.</span
                                    >
                                    <div class="flex items-center gap-2">
                                        <Button
                                            variant="destructive"
                                            size="xs"
                                            onclick={() =>
                                                retire('pair', pair.id)}
                                            >Confirm retire</Button
                                        >
                                        <Button
                                            variant="outline"
                                            size="xs"
                                            onclick={() => (confirmKey = null)}
                                            >Cancel</Button
                                        >
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/each}
                {:else}
                    <div
                        class="border-border/80 rounded-xl border border-dashed p-8 text-center"
                    >
                        <Link2 class="text-muted-foreground mx-auto size-8" />
                        <p class="text-foreground mt-2 text-sm font-medium">
                            No active scanning pairs
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Pair an answer-sheet template with a question
                            booklet to enable OMR scoring.
                        </p>
                        <Button href="/setup/omr/booklets" size="sm" class="mt-4"
                            >Booklets</Button
                        >
                    </div>
                {/if}
            </div>

            <!-- Ready to Pair -->
            {#if draftTemplatesCount > 0 || draftBookletsCount > 0}
                <div
                    class="text-muted-foreground mt-5 rounded-lg border border-dashed border-amber-500/40 bg-amber-500/5 p-3.5 text-xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <Sparkles
                                class="size-4 text-amber-600 dark:text-amber-400"
                            />
                            <span>
                                <strong>Unpaired drafts available:</strong>
                                {#if draftTemplatesCount > 0}{draftTemplatesCount}
                                    draft template(s){/if}
                                {#if draftTemplatesCount > 0 && draftBookletsCount > 0}
                                    and
                                {/if}
                                {#if draftBookletsCount > 0}{draftBookletsCount} draft
                                    booklet(s){/if}.
                            </span>
                        </div>
                        <Button
                            href="/setup/omr/booklets"
                            variant="outline"
                            size="xs">Booklets</Button
                        >
                    </div>
                </div>
            {/if}
        </div>

        <!-- 3. Printing Queue Bar -->
        <div class="border-border/70 bg-card rounded-xl border p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div
                        class="bg-muted text-muted-foreground flex size-9 items-center justify-center rounded-lg"
                    >
                        <Printer class="size-4.5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-foreground text-sm font-semibold">
                                Blank-sheet printing
                            </h3>
                            {#if print_jobs.length > 0}
                                <Badge variant="secondary" class="text-[10px]"
                                    >{print_jobs.length} recent</Badge
                                >
                            {/if}
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Rendered on configured paper ({readiness.paper})
                            with millimeter precision.
                        </p>
                    </div>
                </div>

                {#if print_jobs.length > 0}
                    <div class="flex flex-wrap items-center gap-2">
                        {#each print_jobs.slice(0, 3) as job (job.id)}
                            <div
                                class="border-border/60 bg-muted/40 flex items-center gap-2 rounded-md border px-2.5 py-1 text-xs"
                            >
                                <span
                                    class="text-muted-foreground font-mono text-[11px]"
                                    >#{job.id}</span
                                >
                                <Badge
                                    variant={job.status === 'ready'
                                        ? 'default'
                                        : 'secondary'}
                                    class="text-[10px]">{job.status}</Badge
                                >
                                <span class="text-muted-foreground text-[11px]"
                                    >{formatDate(job.created_at)}</span
                                >
                                {#if job.status === 'ready'}
                                    <a
                                        href={`/printing/jobs/${job.id}/download`}
                                        class="text-primary font-medium hover:underline"
                                        >PDF</a
                                    >
                                {/if}
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    </section>
</StaffLayout>
