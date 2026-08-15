<script>
  /**
   * Knowledge sources Index — legacy Admin/KnowledgeDocuments list feel;
   * status is AD-25 lifecycle (never soft is_active).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Plus, Search } from 'lucide-svelte';

  let {
    page_state = 'ready',
    sources = [],
    filters = {},
    statuses = [],
    capabilities = { manage: false, approve: false },
  } = $props();

  let filterSearch = $state(filters.search ?? '');
  let filterStatus = $state(filters.status ?? '');
  let debounceTimer;

  function applyFilters() {
    router.get(
      '/knowledge',
      {
        search: filterSearch || undefined,
        status: filterStatus || undefined,
      },
      { preserveState: true },
    );
  }

  function onSearchInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
  }

  function statusVariant(status) {
    if (status === 'active') return 'default';
    if (status === 'review_required' || status === 'approved') return 'secondary';
    if (status === 'revoked' || status === 'rejected' || status === 'index_failed') return 'destructive';
    return 'outline';
  }

  const breadcrumbs = [{ label: 'Knowledge' }];
</script>

<svelte:head><title>Knowledge sources</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Knowledge sources"
      description="Governed institutional policy corpus. Versions move draft → review → approve → active. No soft active toggle."
    >
      {#snippet actions()}
        {#if capabilities.manage}
          <Link href="/knowledge/create">
            <Button class="min-h-10 gap-2">
              <Plus class="size-4" />
              Add source
            </Button>
          </Link>
        {/if}
      {/snippet}
    </PageHeaderBar>

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to manage knowledge sources.</p>
    {:else}
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="knowledge-search">Search</label>
          <div class="relative">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              id="knowledge-search"
              type="search"
              placeholder="Title or key..."
              class="min-h-10 min-w-[180px] pl-8"
              bind:value={filterSearch}
              oninput={onSearchInput}
            />
          </div>
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="knowledge-status">Status</label>
          <Select.Root
            type="single"
            value={filterStatus}
            onValueChange={(v) => {
              filterStatus = v ?? '';
              applyFilters();
            }}
          >
            <Select.Trigger id="knowledge-status" class="min-h-10 w-[200px]">
              {statuses.find((s) => s.value === filterStatus)?.label ?? 'All statuses'}
            </Select.Trigger>
            <Select.Content>
              {#each statuses as s (s.value)}
                <Select.Item value={s.value}>{s.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      {#if page_state === 'empty' || sources.length === 0}
        <p class="text-sm text-muted-foreground">No knowledge sources yet. Add a policy draft to start the lifecycle.</p>
      {:else}
        <div class="min-w-0 overflow-x-auto">
          <Table.Root class="w-full min-w-[640px] text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Title</Table.Head>
                <Table.Head class="px-4 py-3">Key</Table.Head>
                <Table.Head class="px-4 py-3">Lifecycle</Table.Head>
                <Table.Head class="px-4 py-3">Updated</Table.Head>
                <Table.Head class="text-center">Actions</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each sources as row (row.id)}
                <Table.Row>
                  <Table.Cell class="px-4 py-3 font-medium">{row.title}</Table.Cell>
                  <Table.Cell class="px-4 py-3 text-muted-foreground">{row.key}</Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <Badge variant={statusVariant(row.latest_version?.status)}>
                      {row.latest_version?.status ?? '—'}
                    </Badge>
                    <span class="ml-2 text-xs text-muted-foreground">v{row.latest_version?.version ?? '—'}</span>
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3 text-muted-foreground">
                    {row.latest_version?.updated_at ?? '—'}
                  </Table.Cell>
                  <Table.Cell class="text-center">
                    <Link href={`/knowledge/${row.id}`} class="text-sm text-primary underline-offset-4 hover:underline">
                      Open
                    </Link>
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      {/if}
    {/if}
  </div>
</StaffLayout>
