<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Badge } from '@/Components/ui/badge';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Trash2, ChevronDown, Filter, Search } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import { createDebouncedWatch, createImmediateWatch } from '@/lib/auto-filter';

  let { users, roles, filters = {} } = $props();


  let viewMode = $state('responsive');

  let filterSearch = $state('');
  let filterRole = $state('');
  let mobileFiltersDetails = $state(null);
  $effect(() => {
    filterSearch = filters.search ?? '';
    filterRole = filters.role ?? '';
  });

  let deleteId = $state(null);

  function applyFilters() {
    if (mobileFiltersDetails) mobileFiltersDetails.open = false;
    router.get('/admin/users', {
      search: filterSearch || undefined,
      role: filterRole || undefined,
      page: 1,
    }, { preserveState: true });
  }

  // Auto-apply: search debounced, role dropdown immediate
  const searchWatch = createDebouncedWatch(() => applyFilters());
  const filterWatch = createImmediateWatch(() => applyFilters());
  $effect(() => { filterSearch; searchWatch(); });
  $effect(() => { filterRole; filterWatch(); });

  function confirmDelete(id) {
    deleteId = id;
  }

  function cancelDelete() {
    deleteId = null;
  }

  function doDelete() {
    if (deleteId) {
      router.delete(`/admin/users/${deleteId}`, { onSuccess: () => (deleteId = null) });
    }
  }

  const breadcrumbs = $derived([{ label: 'Users' }]);
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View and manage system users and their roles</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <Link href="/admin/users/create">
          <Button class="min-h-[44px] gap-2">
            <Plus class="h-4 w-4" />
            <span class="hidden sm:inline">Create User</span>
          </Button>
        </Link>
      </div>
    </div>


    <!-- Filters: one row on desktop; on mobile search + collapsible "Filters" dropdown, Apply always visible -->
    <div class="flex flex-col gap-3">
      <div class="hidden md:flex flex-wrap items-center gap-3">
        <div class="relative">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search users..."
            bind:value={filterSearch}
            class="pl-8 min-w-[160px] max-w-[220px] h-10"
          />
        </div>
        <Select.Root type="single" bind:value={filterRole}>
          <Select.Trigger class="w-[150px] min-h-[40px]">
            {#if filterRole}
              {roles.find(r => r.name === filterRole)?.display_name || 'All roles'}
            {:else}
              <span class="text-muted-foreground">All roles</span>
            {/if}
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="" label="All roles">All roles</Select.Item>
            {#each roles as r}
              <Select.Item value={r.name} label={r.display_name}>{r.display_name}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>

      </div>
      <div class="flex flex-wrap items-center gap-3 md:hidden">
        <div class="relative flex-1 min-w-0">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search users..."
            bind:value={filterSearch}
            class="pl-8 min-h-[44px] w-full"
          />
        </div>
        <details class="relative group" bind:this={mobileFiltersDetails}>
          <summary class="list-none flex items-center gap-2 min-h-[44px] px-4 rounded-md border border-input bg-background text-sm font-medium cursor-pointer hover:bg-muted/50">
            <Filter class="h-4 w-4" />
            <span>Filters</span>
            <ChevronDown class="h-4 w-4 transition-transform group-open:rotate-180" />
          </summary>
          <div class="absolute right-0 top-full z-10 mt-1 w-[min(320px,calc(100vw-2rem))] rounded-lg border border-border bg-card p-4 shadow-lg flex flex-col gap-3">
            <div>
              <label for="filter-role-mob" class="text-sm font-medium block mb-1">Role</label>
              <Select.Root type="single" bind:value={filterRole}>
                <Select.Trigger id="filter-role-mob" class="w-full min-h-[44px]">
                  {#if filterRole}
                    {roles.find(r => r.name === filterRole)?.display_name || 'All roles'}
                  {:else}
                    <span class="text-muted-foreground">All roles</span>
                  {/if}
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="" label="All roles">All roles</Select.Item>
                  {#each roles as r}
                    <Select.Item value={r.name} label={r.display_name}>{r.display_name}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          </div>
        </details>

      </div>
    </div>

    <SwitchableListView bind:viewMode overflow="scroll">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Email</Table.Head>
              <Table.Head class="px-4 py-3">Roles</Table.Head>
              <Table.Head class="text-center px-4 py-3">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each users.data as user (user.id)}
              <Table.Row>
                <Table.Cell>{user.name}</Table.Cell>
                <Table.Cell>{user.email}</Table.Cell>
                <Table.Cell>
                  <span class="inline-flex flex-wrap gap-1">
                    {#each (user.roles ?? []) as r}
                      <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                        {r.display_name}
                      </span>
                    {/each}
                  </span>
                </Table.Cell>
                  <Table.Cell class="text-center">
                    <div class="flex justify-center gap-2">
                      <Link href={`/admin/users/${user.id}/edit`}>
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                          <Pencil class="mr-1.5 h-3.5 w-3.5" />
                          Edit
                        </Button>
                      </Link>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5" onclick={() => confirmDelete(user.id)}>
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        Delete
                      </Button>
                    </div>
                  </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/snippet}

      {#snippet cards()}
        {#if (users?.data ?? []).length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each (users?.data ?? []) as user}
              <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all shadow-sm">
                <div class="min-w-0">
                  <p class="font-semibold truncate">{user.name}</p>
                  <p class="text-sm text-muted-foreground truncate">{user.email}</p>
                </div>
                <div class="flex flex-wrap gap-1">
                  {#each (user.roles ?? []) as r}
                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                      {r.display_name}
                    </span>
                  {/each}
                </div>
                <div class="mt-auto flex gap-2 pt-2">
                  <Link href={`/admin/users/${user.id}/edit`} class="flex-1">
                    <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                      <Pencil class="h-3.5 w-3.5 mr-1.5" />
                      Edit
                    </Button>
                  </Link>
                  <Button variant="outline" size="sm" class="flex-1 min-h-[40px] font-semibold text-destructive border-destructive/20 hover:bg-destructive/5" onclick={() => confirmDelete(user.id)}>
                    <Trash2 class="h-3.5 w-3.5 mr-1.5" />
                    Delete
                  </Button>
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <p class="py-12 text-center text-muted-foreground">No users found.</p>
        {/if}
      {/snippet}
    </SwitchableListView>

    <SimplePagination data={users} variant="table" />
  </div>

  {#if deleteId}
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="delete-title"
    >
      <div class="rounded-lg bg-card p-6 shadow-lg max-w-sm w-full">
        <h2 id="delete-title" class="text-lg font-semibold">Delete user?</h2>
        <p class="mt-2 text-sm text-muted-foreground">
          This cannot be undone.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" onclick={cancelDelete}>Cancel</Button>
          <Button variant="destructive" onclick={doDelete}>Delete</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>