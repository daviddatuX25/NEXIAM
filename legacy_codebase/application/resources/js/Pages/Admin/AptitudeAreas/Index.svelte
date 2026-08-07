<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, ArrowUp, ArrowDown, Info, Trash2, Calculator, Table2 } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';

  let { aptitude_areas = [] } = $props();

  const list = $derived(Array.isArray(aptitude_areas) ? aptitude_areas : []);

  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Aptitude Areas' }];

  let ordering = $state(list.map(a => a.id));
  $effect(() => {
    ordering = list.map(a => a.id);
  });

  function moveUp(index) {
    if (index <= 0) return;
    const newOrder = [...ordering];
    [newOrder[index - 1], newOrder[index]] = [newOrder[index], newOrder[index - 1]];
    ordering = newOrder;
    saveOrder();
  }

  function moveDown(index) {
    if (index >= ordering.length - 1) return;
    const newOrder = [...ordering];
    [newOrder[index], newOrder[index + 1]] = [newOrder[index + 1], newOrder[index]];
    ordering = newOrder;
    saveOrder();
  }

  function saveOrder() {
    router.post('/admin/aptitude-areas/reorder', { order: ordering }, { replace: true });
  }

  function doDeletePermanent(id) {
    if (confirm('Permanently delete this aptitude area?')) {
      router.delete(`/admin/aptitude-areas/${id}`, { onSuccess: () => router.reload() });
    }
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <Link href="/admin/aptitude-areas/create">
          <Button class="min-h-[44px]">
            <Plus class="mr-2 h-4 w-4" />
            Add aptitude area
          </Button>
        </Link>
      </div>
    </div>

    <div class="min-w-0">
      <div class="w-full min-w-0 overflow-x-auto scrollbar-hide">
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="w-24 text-center px-4 py-3">
                <div class="group relative inline-flex items-center gap-1.5 cursor-help">
                  <span class="text-xs text-muted-foreground font-normal">Order</span>
                  <Info class="h-4 w-4 text-muted-foreground" />
                  <div class="absolute top-full left-0 mt-1 w-64 rounded-md border border-border bg-background px-3 py-2 text-left text-xs text-foreground shadow-lg opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-normal">
                    Reorder aptitude areas for precise grading experience — e.g., using Tab to quickly input scores.
                  </div>
                </div>
              </Table.Head>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Code</Table.Head>
              <Table.Head class="px-4 py-3">Max items</Table.Head>
              <Table.Head class="px-4 py-3">Method</Table.Head>
              <Table.Head>Status</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each ordering as id, index}
              {@const area = list.find(a => a.id === id)}
              {#if area}
                <Table.Row>
                  <Table.Cell>
                    <div class="flex items-center gap-1">
                      <button
                        type="button"
                        onclick={() => moveUp(index)}
                        disabled={index === 0}
                        class="rounded p-1 hover:bg-muted disabled:opacity-30 disabled:cursor-not-allowed"
                        title="Move up"
                      >
                        <ArrowUp class="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        onclick={() => moveDown(index)}
                        disabled={index === ordering.length - 1}
                        class="rounded p-1 hover:bg-muted disabled:opacity-30 disabled:cursor-not-allowed"
                        title="Move down"
                      >
                        <ArrowDown class="h-4 w-4" />
                      </button>
                    </div>
                  </Table.Cell>
                  <Table.Cell class="font-medium">{area.name ?? '—'}</Table.Cell>
                  <Table.Cell class="font-mono text-muted-foreground">{area.code ?? '—'}</Table.Cell>
                  <Table.Cell>{area.max_items ?? '—'}</Table.Cell>
                  <Table.Cell>
                    {#if area.scoring_method === 'conversion_table'}
                      <Badge variant="secondary" class="gap-1">
                        <Table2 class="h-3 w-3" />
                        Table ({area.percentile_conversions_count ?? 0} rows)
                      </Badge>
                    {:else}
                      <Badge variant="outline" class="gap-1">
                        <Calculator class="h-3 w-3" />
                        Formula
                      </Badge>
                    {/if}
                  </Table.Cell>
                  <Table.Cell>
                    <Badge variant={area.is_active ? 'success' : 'muted'}>
                      {area.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </Table.Cell>
                  <Table.Cell class="text-center px-4 py-3">
                    <div class="w-[180px] inline-grid grid-cols-2 gap-2">
                      <Link href={`/admin/aptitude-areas/${area.id}/edit`}>
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                          <Pencil class="mr-1.5 h-3.5 w-3.5" />
                          Edit
                        </Button>
                      </Link>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs text-destructive hover:text-destructive hover:bg-destructive/5"
                        onclick={() => doDeletePermanent(area.id)}
                      >
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        Delete
                      </Button>
                    </div>
                  </Table.Cell>
                </Table.Row>
              {/if}
            {:else}
              <Table.Row>
                <Table.Cell colspan={7} class="py-12 text-center text-muted-foreground">
                  No aptitude areas yet. Add one to use in grading and result templates.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      </div>
    </div>
  </div>
</AuthenticatedLayout>
