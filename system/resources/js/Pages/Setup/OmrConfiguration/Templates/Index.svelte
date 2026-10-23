<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { FileText, ScanLine } from 'lucide-svelte';
    import { error as toastError } from '@/lib/toast.js';

    let { templates = [], errors = {} } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test Kits', href: '/setup/exam-materials/test-kits' },
        { label: 'Answer-Sheet Templates' },
    ];

    let lastRetireError = null;

    $effect(() => {
        if (errors.omr_retire && errors.omr_retire !== lastRetireError) {
            lastRetireError = errors.omr_retire;
            toastError(errors.omr_retire);
        } else if (!errors.omr_retire) {
            lastRetireError = null;
        }
    });

    function latestVersion(template) {
        return template.versions?.[0] ?? null;
    }
</script>

<StaffLayout {breadcrumbs}>
    <PageHeaderBar
        title="Answer-Sheet Templates"
        description="Optical mark recognition layout templates with immutable version histories for exam bubble sheets."
    >
        {#snippet actions()}
            <Button href="/setup/exam-materials/answer-sheets" size="sm"
                >New answer sheet</Button
            >
            <Button href="/setup/omr/intake" variant="outline" size="sm"
                >Register bundle</Button
            >
        {/snippet}
    </PageHeaderBar>

    <section class="space-y-6 p-6">
        <div class="grid gap-4 sm:grid-cols-2">
            {#each templates as template (template.id)}
                {@const latest = latestVersion(template)}
                {@const drafts =
                    template.versions?.filter((v) => v.status === 'draft') ??
                    []}
                {@const activeVer =
                    template.versions?.find((v) => v.status === 'active') ??
                    null}

                <div
                    class="border-border/80 bg-card hover:border-primary/50 flex flex-col justify-between rounded-xl border p-5 transition-all"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                >
                                    <FileText class="size-5" />
                                </div>
                                <div>
                                    <h3
                                        class="text-foreground text-sm font-semibold"
                                    >
                                        {template.name}
                                    </h3>
                                    <p
                                        class="text-muted-foreground font-mono text-xs"
                                    >
                                        {template.code}
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

                        <div
                            class="border-border/60 bg-muted/20 text-muted-foreground mt-4 space-y-2 rounded-lg border p-3 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span>Total versions:</span>
                                <span class="text-foreground font-medium"
                                    >{template.versions?.length ?? 0}</span
                                >
                            </div>
                            {#if latest}
                                <div class="flex items-center justify-between">
                                    <span
                                        >Latest sheet (v{latest.version}):</span
                                    >
                                    <span class="text-foreground font-medium"
                                        >{latest.page_count} page{latest.page_count ===
                                        1
                                            ? ''
                                            : 's'} · {latest.page_width}×{latest.page_height}</span
                                    >
                                </div>
                            {/if}
                        </div>
                    </div>

                    <div
                        class="border-border/60 mt-5 flex items-center justify-between border-t pt-3.5"
                    >
                        <Button
                            href={`/setup/omr/templates/${template.id}`}
                            variant="outline"
                            size="xs"
                        >
                            <ScanLine class="mr-1 size-3" />
                            Dry Run
                        </Button>

                        {#if drafts.length > 0}
                            <Button
                                href="/setup/exam-materials/answer-sheets"
                                size="xs"
                            >
                                Edit draft v{drafts[0].version}
                            </Button>
                        {/if}
                    </div>
                </div>
            {/each}

            {#if templates.length === 0}
                <div
                    class="border-border/80 col-span-2 rounded-xl border border-dashed p-10 text-center"
                >
                    <FileText class="text-muted-foreground mx-auto size-9" />
                    <h3 class="text-foreground mt-2 text-sm font-semibold">
                        No answer-sheet templates yet
                    </h3>
                    <p class="text-muted-foreground mt-1 text-xs">
                        Design a bubble sheet in the visual builder or register
                        an existing native bundle.
                    </p>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <Button
                            href="/setup/exam-materials/answer-sheets"
                            size="sm">Create answer sheet</Button
                        >
                        <Button
                            href="/setup/omr/intake"
                            variant="outline"
                            size="sm">Register bundle</Button
                        >
                    </div>
                </div>
            {/if}
        </div>
    </section>
</StaffLayout>
