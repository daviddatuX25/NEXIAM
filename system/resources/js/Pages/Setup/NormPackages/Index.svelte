<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Table from '@/Components/ui/table';
    import { Link } from '@inertiajs/svelte';
    import { Eye, FilePlus2, Pencil, ScrollText } from 'lucide-svelte';

    let { page_state = 'ready', norm_packages = [] } = $props();
    const packages = $derived(Array.isArray(norm_packages) ? norm_packages : []);
    const searchParams = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
    const fromKit = searchParams.get('from_kit');
    const version = searchParams.get('version');
    const returnQuery = new URLSearchParams();
    if (fromKit !== null) returnQuery.set('from_kit', fromKit);
    if (version !== null) returnQuery.set('version', version);
    const returnSuffix = returnQuery.size > 0 ? `?${returnQuery.toString()}` : '';
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Norm packages' },
    ];

    function statusLabel(status) {
        if (status === 'approved') return 'Approved';
        if (status === 'draft') return 'Draft';
        return 'Retired';
    }

    function statusVariant(status) {
        if (status === 'approved') return 'default';
        if (status === 'draft') return 'secondary';
        return 'outline';
    }
</script>

<svelte:head><title>Norm packages · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Norm packages"
            description="Manage confidential, versioned norm tables for verified forms. Drafts remain unavailable for conversion until an authorized source review and approval."
        >
            {#snippet actions()}
                <Link href={`/setup/norm-packages/create${returnSuffix}`}>
                    <Button class="min-h-10 gap-2">
                        <FilePlus2 class="h-4 w-4" />Create package
                    </Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <div class="border-border bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm">
            Norm tables are confidential Guidance data. Package names and entered rows do not establish scientific validity or applicability; verify the source and form before approval.
        </div>

        {#if page_state === 'empty' || packages.length === 0}
            <section
                class="craft-card-depth border-border/70 rounded-xl border border-dashed px-6 py-14 text-center"
                aria-labelledby="empty-norm-packages-heading"
            >
                <ScrollText class="text-muted-foreground mx-auto h-8 w-8" aria-hidden="true" />
                <h2 id="empty-norm-packages-heading" class="mt-4 font-semibold">No norm packages yet</h2>
                <p class="text-muted-foreground mx-auto mt-1 max-w-xl text-sm">
                    Create a draft package to record its form, source, population, and conversion tables. The package will remain unapproved until reviewed.
                </p>
                <Link href={`/setup/norm-packages/create${returnSuffix}`} class="mt-5 inline-flex">
                    <Button class="min-h-11 gap-2">
                        <FilePlus2 class="h-4 w-4" />Create first draft
                    </Button>
                </Link>
            </section>
        {:else}
            <div class="craft-card-depth border-border/70 hidden overflow-x-auto rounded-xl border md:block">
                <Table.Root class="min-w-240">
                    <Table.Header>
                        <Table.Row>
                            <Table.Head>Code</Table.Head>
                            <Table.Head>Norm package</Table.Head>
                            <Table.Head>Publisher</Table.Head>
                            <Table.Head>Form</Table.Head>
                            <Table.Head>Version</Table.Head>
                            <Table.Head>Status</Table.Head>
                            <Table.Head class="text-right">Actions</Table.Head>
                        </Table.Row>
                    </Table.Header>
                    <Table.Body>
                        {#each packages as item (item.id)}
                            <Table.Row>
                                <Table.Cell class="font-mono text-sm">{item.code}</Table.Cell>
                                <Table.Cell>
                                    <p class="font-medium">{item.display_name}</p>
                                    {#if item.is_synthetic}
                                        <p class="text-muted-foreground mt-1 text-xs">Synthetic · software only</p>
                                    {/if}
                                </Table.Cell>
                                <Table.Cell>{item.publisher_name || '—'}</Table.Cell>
                                <Table.Cell>{item.form_code || '—'}</Table.Cell>
                                <Table.Cell>{item.version_number ?? item.version ?? '—'}</Table.Cell>
                                <Table.Cell>
                                    <Badge variant={statusVariant(item.status)}>{statusLabel(item.status)}</Badge>
                                </Table.Cell>
                                <Table.Cell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link href={`/setup/norm-packages/${item.id}${returnSuffix}`}>
                                            <Button size="sm" variant="outline" class="min-h-9 gap-1.5">
                                                <Eye class="h-3.5 w-3.5" />View
                                            </Button>
                                        </Link>
                                        <Link href={`/setup/norm-packages/${item.id}/edit${returnSuffix}`}>
                                            <Button size="sm" variant="ghost" class="min-h-9 gap-1.5">
                                                <Pencil class="h-3.5 w-3.5" />Edit
                                            </Button>
                                        </Link>
                                    </div>
                                </Table.Cell>
                            </Table.Row>
                        {/each}
                    </Table.Body>
                </Table.Root>
            </div>

            <ul class="grid grid-cols-1 gap-3 md:hidden" role="list">
                {#each packages as item (item.id)}
                    <li class="border-border bg-card min-w-0 rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="font-semibold">{item.display_name}</h2>
                                <p class="text-muted-foreground mt-1 font-mono text-xs">{item.code}</p>
                            </div>
                            <Badge variant={statusVariant(item.status)}>{statusLabel(item.status)}</Badge>
                        </div>
                        {#if item.is_synthetic}
                            <p class="text-muted-foreground mt-2 text-xs">Synthetic · software only</p>
                        {/if}
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">Publisher</dt>
                                <dd class="font-medium">{item.publisher_name || '—'}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Form</dt>
                                <dd class="font-medium">{item.form_code || '—'}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">Version</dt>
                                <dd class="font-medium">{item.version_number ?? item.version ?? '—'}</dd>
                            </div>
                        </dl>
                        <div class="mt-4 flex gap-2">
                            <Link href={`/setup/norm-packages/${item.id}${returnSuffix}`} class="flex-1">
                                <Button variant="outline" class="min-h-10 w-full gap-2">
                                    <Eye class="h-4 w-4" />View
                                </Button>
                            </Link>
                            <Link href={`/setup/norm-packages/${item.id}/edit${returnSuffix}`} class="flex-1">
                                <Button variant="ghost" class="min-h-10 w-full gap-2">
                                    <Pencil class="h-4 w-4" />Edit
                                </Button>
                            </Link>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
</StaffLayout>
