<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Trash2, FileText, Upload, RefreshCw, Search } from 'lucide-svelte';
  import ActionDropdown from '@/Components/ActionDropdown.svelte';
  import { formatDate } from '@/lib/date-utils';
  import { createDebouncedWatch } from '@/lib/auto-filter';

  let { documents, filters = {} } = $props();

  const breadcrumbs = [{ label: 'Knowledge Documents' }];


  let filterSearch = $state('');
  let deleteId = $state(null);

  $effect(() => {
    filterSearch = filters.search ?? '';
  });

  function applyFilters() {
    router.get('/admin/knowledge-documents', {
      search: filterSearch || undefined,
      page: 1,
    }, { preserveState: true });
  }

  // Auto-apply: search debounced
  const searchWatch = createDebouncedWatch(() => applyFilters());
  $effect(() => { filterSearch; searchWatch(); });

  function confirmDelete(id) {
    deleteId = id;
  }

  function cancelDelete() {
    deleteId = null;
  }

  function doDelete() {
    if (deleteId) {
      router.delete(`/admin/knowledge-documents/${deleteId}`, { onSuccess: () => (deleteId = null) });
    }
  }

  function retrySync(docId) {
    router.post(`/admin/knowledge-documents/${docId}/retry-sync`, {}, { onSuccess: () => router.reload() });
  }



  const sourceLabel = (source) => (source === 'csv_import' ? 'CSV import' : 'Manual');
  const rows = $derived(documents?.data ?? []);
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="mt-1 text-sm text-muted-foreground">Text and metadata used by the AI companion for data-grounded advice. Metadata defines what each document is (category, year) for retrieval.</p>
      </div>
      <div class="flex gap-2">
        <Link href="/admin/knowledge-documents/import">
          <Button variant="outline" class="min-h-[44px]">
            <Upload class="mr-2 h-4 w-4" />
            Import CSV
          </Button>
        </Link>
        <Link href="/admin/knowledge-documents/create">
          <Button class="min-h-[44px]">
            <Plus class="mr-2 h-4 w-4" />
            Add document
          </Button>
        </Link>
      </div>
    </div>


    <div class="flex flex-wrap items-center gap-3">
      <div class="relative">
        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          type="search"
          placeholder="Search documents..."
          bind:value={filterSearch}
          class="pl-8 min-w-[200px] max-w-[280px] min-h-[44px]"
        />
      </div>

    </div>

    <div class="min-w-0">
      <Table.Root class="w-full min-w-[640px] text-sm">
        <Table.Header class="bg-muted/50">
          <Table.Row>
            <Table.Head class="px-4 py-3">Title</Table.Head>
            <Table.Head class="px-4 py-3">Metadata</Table.Head>
            <Table.Head class="px-4 py-3">Source</Table.Head>
            <Table.Head class="px-4 py-3">Sync</Table.Head>
            <Table.Head class="px-4 py-3">Updated</Table.Head>
            <Table.Head class="text-center">Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each rows as doc (doc.id)}
            <Table.Row>
              <Table.Cell class="px-4 py-3">
                <span class="font-medium">{doc.title}</span>
                {#if doc.content}
                  <p class="text-xs text-muted-foreground mt-0.5 line-clamp-1">{doc.content}</p>
                {/if}
              </Table.Cell>
              <Table.Cell class="px-4 py-3 text-sm text-muted-foreground">{doc.metadata_summary ?? '—'}</Table.Cell>
              <Table.Cell class="px-4 py-3">
                <Badge variant="outline">{sourceLabel(doc.source)}</Badge>
                {#if doc.is_active === false}
                  <Badge variant="muted" class="ml-1">Inactive</Badge>
                {/if}
              </Table.Cell>
                <Table.Cell class="px-4 py-3">
                  {#if doc.mxb_sync_status === 'indexed'}
                    <Badge variant="success">Indexed</Badge>
                  {:else if doc.mxb_sync_status === 'pending'}
                    <Badge variant="warning">Pending</Badge>
                  {:else if doc.mxb_sync_status === 'failed'}
                    <Badge variant="danger">Failed</Badge>
                  {:else}
                    <Badge variant="outline">—</Badge>
                  {/if}
                </Table.Cell>
              <Table.Cell class="px-4 py-3 text-sm text-muted-foreground">{formatDate(doc.updated_at)}</Table.Cell>
                <Table.Cell class="text-center">
                  <div class="flex justify-center gap-2">
                    <Link href={`/admin/knowledge-documents/${doc.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    <ActionDropdown
                      items={[
                        ...(doc.mxb_sync_status === 'failed' ? [{
                          label: 'Retry Sync',
                          icon: RefreshCw,
                          onclick: () => retrySync(doc.id)
                        }] : []),
                        {
                          label: 'Delete',
                          icon: Trash2,
                          onclick: () => confirmDelete(doc.id),
                          class: 'text-destructive'
                        }
                      ]}
                    />
                  </div>
                </Table.Cell>
            </Table.Row>
          {:else}
            <Table.Row>
              <Table.Cell colspan={6} class="px-4 py-12 text-center text-muted-foreground">
                No knowledge documents yet. Add one so the AI companion can use institutional data.
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>

    {#if (documents?.links ?? []).length > 1}
      <div class="flex flex-wrap gap-2 justify-center">
        {#each documents.links as link}
          {#if link.url}
            <Link
              href={link.url}
              class="min-h-[44px] px-3 py-2 rounded border text-sm {link.active ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-muted'}"
            >
              {#if link.label === '&laquo; Previous'}
                Previous
              {:else if link.label === 'Next &raquo;'}
                Next
              {:else}
                {link.label}
              {/if}
            </Link>
          {/if}
        {/each}
      </div>
    {/if}
  </div>

  {#if deleteId}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-title">
      <div class="rounded-lg bg-card border border-border shadow-lg p-6 max-w-sm w-full">
        <h2 id="delete-title" class="text-lg font-semibold">Delete document?</h2>
        <p class="mt-2 text-sm text-muted-foreground">This cannot be undone. The document will no longer be used for AI retrieval.</p>
        <div class="mt-6 flex gap-3 justify-end">
          <Button variant="outline" onclick={cancelDelete}>Cancel</Button>
          <Button variant="destructive" onclick={doDelete}>Delete</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>
