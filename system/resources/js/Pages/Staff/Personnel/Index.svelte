<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Select from '@/Components/ui/select';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Plus, Pencil, Search } from 'lucide-svelte';

  let {
    page_state = 'ready',
    users,
    roles = [],
    filters = {},
    can_full_hr = false,
  } = $props();

  let viewMode = $state('responsive');
  let filterSearch = $state(filters.search ?? '');
  let filterRole = $state(filters.role ?? '');
  let debounceTimer;

  function applyFilters() {
    router.get(
      '/staff/personnel',
      {
        search: filterSearch || undefined,
        role: filterRole || undefined,
        page: 1,
      },
      { preserveState: true },
    );
  }

  function onSearchInput() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
  }

  function deactivate(id) {
    if (!confirm('Deactivate this staff account? They will not be able to sign in.')) return;
    router.post(`/staff/personnel/${id}/deactivate`);
  }

  function activate(id) {
    router.post(`/staff/personnel/${id}/activate`);
  }

  const breadcrumbs = [{ label: 'Personnel' }];
</script>

<svelte:head><title>Personnel</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <PageHeaderBar
        title="Personnel"
        description={can_full_hr
          ? 'Create and manage staff accounts and approved role helpers.'
          : 'Create and manage support staff in your office.'}
      />
      <Link href="/staff/personnel/create">
        <Button class="min-h-11 gap-2">
          <Plus class="h-4 w-4" />
          Create staff
        </Button>
      </Link>
    </div>

    {#if page_state === 'empty' && !filterSearch && !filterRole}
      <p class="text-sm text-muted-foreground">No staff accounts yet.</p>
    {/if}

    <div class="flex flex-wrap items-center gap-3">
      <div class="relative">
        <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
          placeholder="Search staff..."
          class="h-10 min-w-[180px] pl-8"
          bind:value={filterSearch}
          oninput={onSearchInput}
        />
      </div>
      <Select.Root
        type="single"
        value={filterRole}
        onValueChange={(v) => {
          filterRole = v ?? '';
          applyFilters();
        }}
      >
        <Select.Trigger class="min-h-10 w-[180px]">
          {#if filterRole}
            {roles.find((r) => r.name === filterRole)?.display_name || 'All roles'}
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

    <SwitchableListView bind:viewMode>
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Name</Table.Head>
              <Table.Head class="px-4 py-3">Email</Table.Head>
              <Table.Head class="px-4 py-3">Office</Table.Head>
              <Table.Head class="px-4 py-3">Roles</Table.Head>
              <Table.Head class="px-4 py-3">Status</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each users.data as user (user.id)}
              <Table.Row>
                <Table.Cell class="px-4 py-3">{user.name}</Table.Cell>
                <Table.Cell class="px-4 py-3">{user.email}</Table.Cell>
                <Table.Cell class="px-4 py-3">{user.office}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <span class="inline-flex flex-wrap gap-1">
                    {#each user.roles ?? [] as r}
                      <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                        {r.display_name}
                      </span>
                    {/each}
                  </span>
                </Table.Cell>
                <Table.Cell class="px-4 py-3 capitalize">{user.status}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <div class="flex justify-center gap-2">
                    <Link href={`/staff/personnel/${user.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                        <Pencil class="mr-1 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    {#if user.status === 'active'}
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs text-destructive"
                        onclick={() => deactivate(user.id)}
                      >
                        Deactivate
                      </Button>
                    {:else}
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs" onclick={() => activate(user.id)}>
                        Activate
                      </Button>
                    {/if}
                  </div>
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/snippet}

      {#snippet cards()}
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
          {#each users.data ?? [] as user (user.id)}
            <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
              <div class="min-w-0">
                <p class="truncate font-semibold">{user.name}</p>
                <p class="truncate text-sm text-muted-foreground">{user.email}</p>
                <p class="mt-1 text-xs capitalize text-muted-foreground">{user.office} · {user.status}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <Link href={`/staff/personnel/${user.id}/edit`}>
                  <Button variant="outline" size="sm">Edit</Button>
                </Link>
                {#if user.status === 'active'}
                  <Button variant="outline" size="sm" onclick={() => deactivate(user.id)}>Deactivate</Button>
                {:else}
                  <Button variant="outline" size="sm" onclick={() => activate(user.id)}>Activate</Button>
                {/if}
              </div>
            </li>
          {/each}
        </ul>
      {/snippet}
    </SwitchableListView>

    <SimplePagination data={users} />
  </div>
</StaffLayout>
