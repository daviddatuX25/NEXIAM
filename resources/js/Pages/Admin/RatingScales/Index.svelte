<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Trash2 } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';

  let { rating_scales = [] } = $props();

  const list = $derived(Array.isArray(rating_scales) ? rating_scales : []);

  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Rating Scales' }];

  function rangesPreview(ranges) {
    if (!Array.isArray(ranges)) return '—';
    return ranges.map(r => `${r.label} (${r.min}–${r.max})`).join(', ');
  }

  function doDelete(id) {
    if (confirm('Delete this rating scale?')) {
      router.delete(`/admin/setup/rating-scales/${id}`, { onSuccess: () => router.reload() });
    }
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <Link href="/admin/setup/rating-scales/create">
          <Button class="min-h-[44px]">
            <Plus class="mr-2 h-4 w-4" />
            Create Rating Scale
          </Button>
        </Link>
      </div>
    </div>

    <div class="min-w-0">
      <div class="w-full min-w-0 overflow-x-auto scrollbar-hide">
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Ranges</Table.Head>
              <Table.Head class="px-4 py-3">Default</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each list as scale (scale.id)}
              <Table.Row>
                <Table.Cell class="font-medium">{scale.name ?? '—'}</Table.Cell>
                <Table.Cell class="text-muted-foreground text-xs max-w-md truncate">
                  {rangesPreview(scale.ranges)}
                </Table.Cell>
                <Table.Cell>
                  {#if scale.is_default}
                    <Badge variant="success">Default</Badge>
                  {/if}
                </Table.Cell>
                <Table.Cell class="text-center px-4 py-3">
                  <div class="w-[180px] inline-grid grid-cols-2 gap-2">
                    <Link href={`/admin/setup/rating-scales/${scale.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs text-destructive hover:text-destructive hover:bg-destructive/5"
                      onclick={() => doDelete(scale.id)}
                    >
                      <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                      Delete
                    </Button>
                  </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={4} class="py-12 text-center text-muted-foreground">
                  No rating scales yet. Create one to map percentiles to rating labels.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      </div>
    </div>
  </div>
</AuthenticatedLayout>
