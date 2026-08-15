<script>
  /**
   * Knowledge Show — review + lifecycle actions (approve = admin/counselor capability).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import { Link, router, usePage } from '@inertiajs/svelte';

  let {
    source,
    version = null,
    capabilities = { manage: false, approve: false },
  } = $props();

  const page = usePage();
  const flashStatus = $derived(page.props.flash?.status ?? null);

  let revokeReason = $state('');

  function extract() {
    router.post(`/knowledge/${source.id}/extract`);
  }
  function approve() {
    router.post(`/knowledge/${source.id}/approve`);
  }
  function publish() {
    router.post(`/knowledge/${source.id}/publish`);
  }
  function revoke() {
    if (!confirm('Revoke this active version? Retrieval will deny immediately.')) return;
    router.post(`/knowledge/${source.id}/revoke`, { reason: revokeReason || 'revoked' });
  }

  const breadcrumbs = [
    { label: 'Knowledge', href: '/knowledge' },
    { label: source?.title ?? 'Source' },
  ];
</script>

<svelte:head><title>{source?.title ?? 'Knowledge source'}</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title={source.title}
      description={`Key ${source.key} · ${source.classification} · purpose ${source.purpose}`}
    >
      {#snippet actions()}
        <div class="flex flex-wrap gap-2">
          <Link href="/knowledge">
            <Button variant="outline" class="min-h-10">Back to list</Button>
          </Link>
        </div>
      {/snippet}
    </PageHeaderBar>

    {#if flashStatus}
      <p class="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm">{flashStatus}</p>
    {/if}

    {#if !version}
      <p class="text-sm text-muted-foreground">No version found.</p>
    {:else}
      <div class="flex flex-wrap items-center gap-3">
        <Badge>{version.status}</Badge>
        <span class="text-sm text-muted-foreground">Version {version.version}</span>
        {#if version.publication_digest}
          <span class="font-mono text-xs text-muted-foreground">digest {version.publication_digest.slice(0, 12)}…</span>
        {/if}
      </div>

      <div class="flex flex-wrap gap-2">
        {#if capabilities.manage && ['draft', 'rejected', 'index_failed'].includes(version.status)}
          <Button class="min-h-10" onclick={extract}>Run extraction</Button>
        {/if}
        {#if capabilities.approve && version.status === 'review_required'}
          <Button class="min-h-10" onclick={approve}>
            Approve
          </Button>
        {/if}
        {#if capabilities.approve && ['approved', 'index_failed'].includes(version.status)}
          <Button class="min-h-10" onclick={publish}>Publish to active</Button>
        {/if}
        {#if capabilities.approve && ['active', 'approved', 'indexing'].includes(version.status)}
          <div class="flex flex-wrap items-center gap-2">
            <Input class="min-h-10 w-[200px]" placeholder="Revoke reason" bind:value={revokeReason} />
            <Button variant="destructive" class="min-h-10" onclick={revoke}>Revoke</Button>
          </div>
        {/if}
      </div>

      <section class="space-y-2">
        <h2 class="text-sm font-semibold">Extraction / body</h2>
        <pre class="max-h-64 overflow-auto rounded-lg border border-border bg-muted/30 p-3 text-xs whitespace-pre-wrap">{version.extraction_text || version.body_text}</pre>
      </section>

      {#if (version.fixtures ?? []).length > 0 && version.status === 'review_required'}
        <details class="rounded-lg border border-border bg-muted/20 px-3 py-2 text-sm">
          <summary class="cursor-pointer font-medium text-muted-foreground">
            Review probes (auto-generated — not what Nexi searches in chat)
          </summary>
          <p class="mt-2 text-xs text-muted-foreground">
            Internal publish gate: one probe that should cite, one that should refuse. Sealed into the digest before Active.
          </p>
          <Table.Root class="mt-2 w-full text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-3 py-2">Kind</Table.Head>
                <Table.Head class="px-3 py-2">Probe query</Table.Head>
                <Table.Head class="px-3 py-2">Expected</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each version.fixtures ?? [] as f (f.id)}
                <Table.Row>
                  <Table.Cell class="px-3 py-2">{f.kind}</Table.Cell>
                  <Table.Cell class="px-3 py-2">{f.query_text}</Table.Cell>
                  <Table.Cell class="px-3 py-2">{f.expected_outcome}</Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </details>
      {/if}
    {/if}
  </div>
</StaffLayout>
