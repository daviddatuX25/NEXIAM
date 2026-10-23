<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import { Plus, Pencil, Trash2, Play, Pause, FileText } from 'lucide-svelte';

    let { templates = [] } = $props();

    let deleteTarget = $state(null);

    const paperLabels = { a4: 'A4', legal: 'Legal', letter: 'Letter' };
    const unitLabels = {
        full: 'Full page',
        half_a4: 'Half A4',
        half_legal: 'Half Legal',
        half_letter: 'Half Letter',
    };

    function confirmDelete(template) {
        deleteTarget = template;
    }

    function closeDelete() {
        deleteTarget = null;
    }

    function doDelete() {
        if (!deleteTarget) return;
        router.delete(`/setup/result-templates/${deleteTarget.id}`, {
            onSuccess: () => (deleteTarget = null),
        });
    }

    function activate(t) {
        router.post(`/setup/result-templates/${t.id}/activate`);
    }

    function deactivate(t) {
        router.post(`/setup/result-templates/${t.id}/deactivate`);
    }

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Result Sheet Templates' },
    ];
</script>

<svelte:head><title>Result Sheet Templates · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Result Sheet Templates"
            description="Configure institutional score reports and official result sheet templates for print and distribution."
        >
            {#snippet actions()}
                <Link href="/setup/result-templates/create">
                    <Button class="min-h-10 gap-2">
                        <Plus class="size-4" />
                        New template
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        {#if templates.length === 0}
            <section
                class="border-border bg-card rounded-xl border border-dashed px-6 py-14 text-center"
            >
                <div
                    class="bg-muted mx-auto flex size-12 items-center justify-center rounded-xl"
                >
                    <FileText class="text-muted-foreground size-6" />
                </div>
                <h3 class="text-foreground mt-4 text-base font-semibold">
                    No Result Sheet templates yet
                </h3>
                <p class="text-muted-foreground mx-auto mt-1 max-w-sm text-sm">
                    Create an HTML visual studio template or upload a validated
                    Word (.docx) template to drive official result sheet output.
                </p>
                <div class="mt-5 flex justify-center">
                    <Link href="/setup/result-templates/create">
                        <Button class="min-h-10 gap-2">
                            <Plus class="size-4" />
                            New template
                        </Button>
                    </Link>
                </div>
            </section>
        {:else}
            <section
                class="border-border bg-card divide-border divide-y overflow-hidden rounded-xl border"
                aria-label="Result sheet templates"
            >
                {#each templates as t (t.id)}
                    <article
                        class="group hover:bg-muted/25 grid gap-4 p-4 transition-colors sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5"
                    >
                        <MaterialDocumentPreview
                            src={t.thumbnail_url}
                            kind="result_sheet"
                            mode={t.mode?.toUpperCase() ?? 'HTML'}
                            title={t.name}
                            alt={`${t.name} template preview`}
                        />

                        <div class="min-w-0 self-stretch py-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="text-foreground text-base font-semibold sm:text-lg"
                                >
                                    {t.name}
                                </h2>
                                {#if t.is_active}
                                    <Badge
                                        variant="default"
                                        class="bg-emerald-600 text-white hover:bg-emerald-500 gap-1"
                                    >
                                        <span class="size-1.5 rounded-full bg-white"></span>
                                        Active
                                    </Badge>
                                {:else}
                                    <Badge variant="secondary">Inactive</Badge>
                                {/if}
                                <Badge
                                    variant="outline"
                                    class="font-mono text-[10px] tracking-wider uppercase"
                                >
                                    {t.mode === 'docx' ? 'Word (docx)' : 'HTML Studio'}
                                </Badge>
                            </div>

                            <p
                                class="text-foreground/80 mt-1.5 text-sm font-medium"
                            >
                                {paperLabels[t.paper_size] ?? t.paper_size} · {t.orientation === 'landscape' ? 'Landscape' : 'Portrait'} · {unitLabels[t.logical_unit] ?? t.logical_unit ?? 'Full page'}
                            </p>

                            <p class="text-muted-foreground mt-1 text-xs">
                                {#if t.watermark_text}
                                    Watermark: <span class="font-mono text-foreground">{t.watermark_text}</span> · 
                                {/if}
                                {#if t.updated_at}
                                    Updated {new Date(t.updated_at).toLocaleDateString()}
                                {/if}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 sm:self-center">
                            {#if t.is_active}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-9 gap-1.5 px-3 text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-400"
                                    onclick={() => deactivate(t)}
                                >
                                    <Pause class="size-3.5" />
                                    Deactivate
                                </Button>
                            {:else}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-9 gap-1.5 px-3 text-xs font-semibold text-primary hover:text-primary"
                                    onclick={() => activate(t)}
                                >
                                    <Play class="size-3.5" />
                                    Activate
                                </Button>
                            {/if}
                            <Link href={`/setup/result-templates/${t.id}/edit`}>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-9 gap-1.5 px-3 text-xs font-semibold"
                                >
                                    <Pencil class="size-3.5" />
                                    Edit
                                </Button>
                            </Link>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:bg-destructive/10 h-9 gap-1.5 px-2.5 text-xs font-semibold"
                                onclick={() => confirmDelete(t)}
                                title="Delete template"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </article>
                {/each}
            </section>
        {/if}
    </div>

    {#if deleteTarget}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="bg-card w-full max-w-sm rounded-lg p-6 shadow-lg">
                <h2 class="text-base font-semibold">Delete template</h2>
                <p class="text-muted-foreground mt-2 text-sm">
                    Delete “{deleteTarget.name}”? Printed sheets already
                    generated are not affected.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" onclick={closeDelete}
                        >Cancel</Button
                    >
                    <Button variant="destructive" onclick={doDelete}
                        >Delete</Button
                    >
                </div>
            </div>
        </div>
    {/if}
</StaffLayout>
