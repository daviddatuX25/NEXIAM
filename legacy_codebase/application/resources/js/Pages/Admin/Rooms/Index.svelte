<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Pause, Play, Trash2, Search } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import { createDebouncedWatch } from '@/lib/auto-filter';

  let { rooms, filters = {} } = $props();

  let filterSearch = $state('');
  $effect(() => {
    filterSearch = filters.search ?? '';
  });

  function applyFilters() {
    router.get('/admin/rooms', {
      search: filterSearch || undefined,
      page: 1,
    }, { preserveState: true });
  }

  // Auto-apply: search debounced
  const searchWatch = createDebouncedWatch(() => applyFilters());
  $effect(() => { filterSearch; searchWatch(); });

  let deleteId = $state(null);

  function confirmDelete(id) {
    deleteId = id;
  }

  function cancelDelete() {
    deleteId = null;
  }

  function doDelete() {
    if (deleteId) {
      router.delete(`/admin/rooms/${deleteId}`, { onSuccess: () => (deleteId = null) });
    }
  }

  function doToggle(id, currentActive) {
    if (currentActive) {
      router.post(`/admin/rooms/${id}/deactivate`, {}, {
        onSuccess: () => console.log('[Rooms] onSuccess - reload'),
        onError: (errors) => {
          console.log('[Rooms] onError triggered', JSON.stringify(errors));
        },
      });
    } else {
      router.post(`/admin/rooms/${id}/activate`, {}, {
        onSuccess: () => console.log('[Rooms] activate success'),
      });
    }
  }

  function doDeletePermanent(id) {
    router.delete(`/admin/rooms/${id}`, { onSuccess: () => router.reload() });
  }

  function doRestore(id) {
    router.post(`/admin/rooms/${id}/restore`, {}, {
      onSuccess: () => router.reload(),
    });
  }

  let viewMode = $state('responsive');
const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Rooms' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View and manage exam rooms and venues</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <Link href="/admin/rooms/create">
          <Button class="min-h-[44px] gap-2">
            <Plus class="h-4 w-4" />
            <span class="hidden sm:inline">Add Room</span>
          </Button>
        </Link>
      </div>
    </div>

    <!-- Filters: one row on desktop; same on mobile (search + Apply) -->
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative min-w-[160px] max-w-[220px] md:max-w-[220px] flex-1 min-w-0 md:flex-none">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search rooms..."
            bind:value={filterSearch}
            class="pl-8 min-h-[44px] md:min-h-[40px] h-10 w-full"
          />
        </div>

      </div>
    </div>

    <SwitchableListView bind:viewMode overflow="auto">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px]">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Building</Table.Head>
              <Table.Head class="px-4 py-3">Floor</Table.Head>
              <Table.Head class="px-4 py-3">Capacity</Table.Head>
              <Table.Head class="px-4 py-3">Status</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each rooms.data as room (room.id)}
              <Table.Row class={room.is_active ? '' : 'text-muted-foreground/50 transition-colors'}>
                <Table.Cell class="px-4 py-3 font-medium">{room.name}</Table.Cell>
                <Table.Cell class="px-4 py-3">{room.building}</Table.Cell>
                <Table.Cell class="px-4 py-3">{room.floor ?? '—'}</Table.Cell>
                <Table.Cell class="px-4 py-3 font-mono text-xs">{room.capacity}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <Badge variant={room.is_active ? 'success' : 'muted'} class="font-medium">
                    {room.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="text-center px-4 py-3">
                   <div class="w-[280px] inline-grid grid-cols-3 gap-2">
                     <Link href={`/admin/rooms/${room.id}/edit`}>
                       <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                         <Pencil class="mr-1.5 h-3.5 w-3.5" />
                         Edit
                       </Button>
                     </Link>
                     <Button
                       variant="ghost"
                       size="sm"
                       class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5"
                       onclick={() => { if (confirm('Permanently delete this room?')) doDeletePermanent(room.id); }}
                     >
                       <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                       Delete
                     </Button>
                     <Button
                       variant="ghost"
                       size="sm"
                       class="h-8 px-2 text-xs font-semibold {room.is_active
                         ? 'text-amber-600 hover:text-amber-700 hover:bg-amber-50'
                         : 'text-primary hover:text-primary-700 hover:bg-primary/5'}"
                       onclick={() => doToggle(room.id, room.is_active)}
                     >
                       {#if room.is_active}
                         <Pause class="mr-1.5 h-3.5 w-3.5" />
                         Deactivate
                       {:else}
                         <Play class="mr-1.5 h-3.5 w-3.5" />
                         Activate
                       {/if}
                     </Button>
                   </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={6} class="px-4 py-12 text-center text-muted-foreground">
                  No rooms yet. Create one to get started.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
        <SimplePagination data={rooms} variant="table" />
      {/snippet}

      {#snippet cards()}
        {#if (rooms?.data ?? []).length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each (rooms?.data ?? []) as room (room.id)}
              <li
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all {room.is_active ? 'shadow-sm' : 'opacity-60 grayscale-[0.5]'}"
              >
                <div class="flex items-start justify-between gap-2 text-foreground!">
                  <h3 class="font-bold tracking-tight">{room.name}</h3>
                  <Badge variant={room.is_active ? 'success' : 'muted'} class="font-medium shrink-0">{room.is_active ? 'Active' : 'Inactive'}</Badge>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                  <dt class="text-muted-foreground">Building</dt>
                  <dd class="font-medium">{room.building}</dd>
                  <dt class="text-muted-foreground">Floor</dt>
                  <dd>{room.floor ?? '—'}</dd>
                  <dt class="text-muted-foreground">Capacity</dt>
                  <dd class="font-mono">{room.capacity}</dd>
                </dl>
                <div class="mt-auto flex gap-2 pt-2 text-foreground!">
                  <Link href={`/admin/rooms/${room.id}/edit`} class="flex-1">
                    <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                      <Pencil class="h-3.5 w-3.5 mr-1.5" />
                      Edit
                    </Button>
                  </Link>
                  <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 min-h-[40px] font-semibold {room.is_active
                      ? 'text-amber-600 border-amber-200 hover:bg-amber-50 hover:text-amber-700'
                      : 'text-primary border-primary/20 hover:bg-primary/5 hover:text-primary-700'}"
                    onclick={() => doToggle(room.id, room.is_active)}
                  >
                    {#if room.is_active}
                      <Pause class="h-3.5 w-3.5 mr-1.5" />
                      Deactivate
                    {:else}
                      <Play class="h-3.5 w-3.5 mr-1.5" />
                      Activate
                    {/if}
                  </Button>
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <p class="py-12 text-center text-muted-foreground">No rooms yet. Create one to get started.</p>
        {/if}
        <SimplePagination data={rooms} variant="centered" />
      {/snippet}
    </SwitchableListView>
  </div>

  {#if deleteId}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-title"
    >
      <div class="rounded-lg bg-card p-6 shadow-lg max-w-sm w-full">
        <h2 id="delete-title" class="text-lg font-semibold">Delete room?</h2>
        <p class="mt-2 text-sm text-muted-foreground">
          The room will be soft-deleted and hidden from the list. You can restore it later from the edit page.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" onclick={cancelDelete}>Cancel</Button>
          <Button variant="destructive" onclick={doDelete}>Delete</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>