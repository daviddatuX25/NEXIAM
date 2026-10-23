<script>
    import { Link } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { interpolateTemplateText, DEFAULT_SAMPLE_CONTEXT } from '@/lib/noticeLivePreview';
    let { family, versions = [], domain, back_url, authoring_mode = 'builtin', can_edit = false, builtin_message = null, builtin_inspection = null, draft = null, endpoints = {} } = $props();
    const isBuiltin = $derived(authoring_mode === 'builtin');
    const message = $derived(builtin_message && typeof builtin_message === 'object' ? builtin_message : null);
    const inspectionTitle = $derived(builtin_inspection?.title ?? null);
    const inspectionBody = $derived(builtin_inspection?.body ?? null);
    const previewBody = $derived(message?.body ? interpolateTemplateText(message.body, DEFAULT_SAMPLE_CONTEXT) : '');
    const deskUrl = $derived(back_url || `/setup/notifications/${String(domain).toLowerCase()}`);
    const breadcrumbs = $derived([
        { label: 'Setup', href: '/setup' },
        { label: `${domain.charAt(0).toUpperCase()}${domain.slice(1).toLowerCase()} Notices`, href: deskUrl },
        { label: family.name },
    ]);
</script>

<svelte:head><title>{family.name} · {isBuiltin ? 'System notice preview' : 'Notice metadata'}</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar title={family.name} description={isBuiltin ? 'Sent automatically according to institutional policy.' : 'Message details are hidden for this metadata-only view.'} />
        <div class="flex flex-wrap gap-2" aria-label="Family authoring capabilities">
            <Badge variant="outline">{isBuiltin ? 'System Template (Automated)' : 'Metadata only'}</Badge>
            {#if !can_edit}<Badge variant="secondary">No authoring access</Badge>{/if}
        </div>
        {#if !isBuiltin && can_edit && endpoints?.edit}
            <div class="flex flex-wrap gap-3">
                <Link href={endpoints.edit} role="button" class="inline-flex min-h-10 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground">
                    {draft ? 'Resume draft' : 'Edit message'}
                </Link>
            </div>
        {/if}
        {#if builtin_inspection}
            <section class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="builtin-archive-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="builtin-archive-heading" class="text-base font-semibold">Protected historical inspection</h2>
                    <Badge variant="secondary">{builtin_inspection.kind === 'draft' ? 'Retained draft' : 'Published history'}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">This historical content is read-only and does not alter the configured message, publication pointer, or deliveries.</p>
                {#if inspectionTitle !== null}<div><h3 class="text-sm font-medium">Historical title</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">{inspectionTitle}</p></div>{/if}
                {#if inspectionBody !== null}<div><h3 class="text-sm font-medium">Historical body</h3><p class="mt-1 break-words whitespace-pre-wrap text-sm">{inspectionBody}</p></div>{/if}
                {#if inspectionTitle === null && inspectionBody === null}<p class="text-sm text-muted-foreground">No historical title or body is available in this projection.</p>{/if}
            </section>
        {:else if isBuiltin}
            <section class="mx-auto w-full max-w-3xl overflow-hidden rounded-xl border border-border bg-card shadow-sm" aria-label="Email preview">
                <header class="space-y-2 border-b border-border bg-muted/40 p-5 sm:p-6">
                    <h2 class="break-words text-lg font-semibold">Subject: {message?.title ?? family.name}</h2>
                    <p class="break-words text-sm text-muted-foreground">From: {family.name} &lt;notifications@institution&gt;</p>
                </header>
                {#if message}
                    <div class="min-h-48 whitespace-pre-wrap break-words p-6 text-sm leading-7 text-foreground sm:p-8">{previewBody || 'No configured message body is available.'}</div>
                {:else}
                    <div class="space-y-3 p-6 text-sm">
                        {#if family.purpose}<div><h3 class="font-medium">Purpose</h3><p class="mt-1 text-muted-foreground">{family.purpose}</p></div>{/if}
                        {#if family.type ?? family.message_type}<div><h3 class="font-medium">Type</h3><p class="mt-1 text-muted-foreground">{family.type ?? family.message_type}</p></div>{/if}
                    </div>
                {/if}
            </section>
            <p class="mx-auto max-w-3xl text-sm text-muted-foreground">System templates are automated workflow notices configured by the system. They follow institutional policy and do not require manual editing.</p>
        {:else}
            <section class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" aria-labelledby="notice-metadata-heading">
                <h2 id="notice-metadata-heading" class="text-base font-semibold">Notice metadata</h2>
                <p class="text-sm text-muted-foreground">This view does not expose message content, assets, authoring state, or document sources.</p>
            </section>
        {/if}
        {#if !builtin_inspection && draft}
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4" aria-label="Retained draft">
                <div class="min-w-0 space-y-2"><h2 class="text-base font-semibold">Retained historical draft</h2><p class="text-sm text-muted-foreground">A draft is retained for this notice.</p></div>
                {#if draft.inspect_url}<Link href={draft.inspect_url} class="inline-flex min-h-10 items-center rounded-md border px-3 text-sm">Inspect retained draft</Link>{/if}
            </section>
        {/if}
        <section class="min-w-0 space-y-3" aria-labelledby="published-versions">
            <h2 id="published-versions" class="text-lg font-semibold">Retained published history</h2>
            <div class="divide-y divide-border rounded-xl border border-border bg-card">
                {#each versions as version (version.id)}
                    <article class="grid min-w-0 gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div class="space-y-2"><div class="flex flex-wrap gap-2"><h3 class="text-base font-semibold">Published notice</h3><Badge variant={version.version === family.active_version ? 'outline' : 'secondary'}>{version.version === family.active_version ? 'Current' : 'Historical'}</Badge></div></div>
                        {#if version.inspect_url}<Link href={version.inspect_url} class="inline-flex min-h-10 items-center rounded-md border px-3 text-sm">Inspect published notice</Link>{/if}
                    </article>
                {:else}<p class="p-5 text-sm text-muted-foreground">No published history is available.</p>{/each}
            </div>
        </section>
        <Link href={back_url} class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Back to {domain} notices</Link>
    </div>
</StaffLayout>

