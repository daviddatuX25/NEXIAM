<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
    import { Pause, Play, Plus, Trash2 } from 'lucide-svelte';

    let { surface, templates = [] } = $props();
    let deleteTarget = $state(null);

    const breadcrumbs = $derived([
        { label: 'Setup', href: '/setup' },
        { label: 'Document Templates', href: '/setup/report-templates' },
        { label: surface.title },
    ]);

    function activate(template) {
        router.post(`/setup/report-templates/${surface.surface_key}/${template.id}/activate`);
    }

    function deactivate(template) {
        router.post(`/setup/report-templates/${surface.surface_key}/${template.id}/deactivate`);
    }

    function deleteTemplate() {
        if (!deleteTarget) return;
        router.delete(`/setup/report-templates/${surface.surface_key}/${deleteTarget.id}`, {
            onSuccess: () => (deleteTarget = null),
        });
    }
</script>

<svelte:head><title>{surface.title} Templates · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight text-foreground">{surface.title}</h1>
                    <Badge variant="outline">{surface.office_label}</Badge>
                </div>
                <p class="max-w-2xl text-sm text-muted-foreground">One active template drives every {surface.title} export. Create and maintain office-approved report layouts here.</p>
            </div>
            <Link href={`/setup/report-templates/${surface.surface_key}/create`}>
                <Button class="min-h-10 gap-2"><Plus class="size-4" /> New Template</Button>
            </Link>
        </header>

        {#if templates.length === 0}
            <section class="rounded-xl border border-dashed border-border bg-card px-6 py-12 text-center">
                <h2 class="text-base font-semibold text-foreground">No templates yet</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">Create an HTML template or upload a DOCX file to configure {surface.title} exports.</p>
                <div class="mt-5"><Link href={`/setup/report-templates/${surface.surface_key}/create`}><Button class="min-h-10 gap-2"><Plus class="size-4" /> New Template</Button></Link></div>
            </section>
        {:else}
            <section class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card" aria-label={`${surface.title} templates`}>
                {#each templates as tpl (tpl.id)}
                    <article class="grid gap-4 p-4 transition-colors hover:bg-muted/25 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:gap-6 sm:p-5">
                        <MaterialDocumentPreview
                            src={tpl.thumbnail_url}
                            kind="report"
                            mode={tpl.mode?.toUpperCase() ?? 'HTML'}
                            title={tpl.name}
                            builder_json={tpl.builder_json}
                            orientation={tpl.orientation ?? 'portrait'}
                            alt={`${tpl.name} template preview`}
                        />
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-foreground">{tpl.name}</h2>
                                {#if tpl.is_active}
                                    <Badge class="bg-emerald-600 text-white hover:bg-emerald-600">Active</Badge>
                                {:else}
                                    <Badge variant="secondary">Inactive</Badge>
                                {/if}
                                <Badge variant="outline" class="font-mono text-[10px] uppercase">{tpl.mode === 'docx' ? 'DOCX' : 'HTML'}</Badge>
                            </div>
                            <p class="text-sm font-medium text-foreground/80">
                                {String(tpl.paper_size ?? '').toUpperCase()} · {tpl.orientation === 'landscape' ? 'Landscape' : 'Portrait'}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            {#if tpl.is_active}
                                <Button variant="outline" size="sm" class="h-9 gap-1.5 px-3 text-xs font-semibold" onclick={() => deactivate(tpl)}><Pause class="size-3.5" /> Deactivate</Button>
                            {:else}
                                <Button variant="outline" size="sm" class="h-9 gap-1.5 px-3 text-xs font-semibold" onclick={() => activate(tpl)}><Play class="size-3.5" /> Activate</Button>
                            {/if}
                            <Link href={`/setup/report-templates/${surface.surface_key}/${tpl.id}/edit`}>
                                <Button variant="outline" size="sm" class="h-9 px-3 text-xs font-semibold">Edit</Button>
                            </Link>
                            <Button variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10 h-9 gap-1.5 px-2.5 text-xs font-semibold" onclick={() => (deleteTarget = tpl)} aria-label={`Delete ${tpl.name}`}>
                                <Trash2 class="size-3.5" /> Delete
                            </Button>
                        </div>
                    </article>
                {/each}
            </section>
        {/if}
    </div>

    {#if deleteTarget}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-report-template-title">
            <div class="w-full max-w-sm rounded-lg bg-card p-6 shadow-lg">
                <h2 id="delete-report-template-title" class="text-base font-semibold">Delete template?</h2>
                <p class="mt-2 text-sm text-muted-foreground">Delete “{deleteTarget.name}”? This action cannot be undone.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" onclick={() => (deleteTarget = null)}>Cancel</Button>
                    <Button variant="destructive" onclick={deleteTemplate}>Delete</Button>
                </div>
            </div>
        </div>
    {/if}
</StaffLayout>
