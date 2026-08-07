<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Pause, Play, Trash2 } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';

  let { academic_years } = $props();
  const list = $derived(academic_years?.data ?? []);
  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Academic Years' }];

  let viewMode = $state('responsive');

  function doDeactivate(id) {
    router.post(`/admin/academic-years/${id}/deactivate`, {}, { preserveScroll: true });
  }

  function doDeletePermanent(id) {
    if (confirm('Permanently delete this academic year?')) {
      router.delete(`/admin/academic-years/${id}`, { onSuccess: () => router.reload() });
    }
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View and manage academic years and semesters</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <Link href="/admin/academic-years/create">
          <Button class="min-h-[44px] gap-2">
            <Plus class="h-4 w-4" />
            <span class="hidden sm:inline">Add Academic Year</span>
          </Button>
        </Link>
      </div>
    </div>

    {#if !list.some((ay) => ay.is_active)}
      <div class="mb-4 rounded-md border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-foreground">
        No academic year is currently active. Applications are closed until one is activated.
      </div>
    {/if}

    <SwitchableListView bind:viewMode overflow="auto">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head>Academic Year</Table.Head>
              <Table.Head>Application window</Table.Head>
              <Table.Head>Status</Table.Head>
              <Table.Head>Applications</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each list as ay}
              <Table.Row class={ay.is_active ? '' : 'text-muted-foreground/50 transition-colors'}>
                <Table.Cell class="font-bold tracking-tight">
                  {ay.label ?? (ay.academic_year ?? '—') + ' – ' + (ay.semester_label ?? '—')}
                </Table.Cell>
                <Table.Cell class="text-muted-foreground font-medium">
                  {ay.application_window ?? '— — —'}
                </Table.Cell>
                <Table.Cell>
                  <Badge variant={ay.is_active ? 'success' : 'muted'} class="font-medium">
                    {ay.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="font-mono text-xs">
                  {ay.applications_count ?? 0}
                </Table.Cell>
                <Table.Cell class="text-center px-4 py-3">
                  <div class="w-[280px] inline-grid grid-cols-3 gap-2">
                    <Link href={`/admin/academic-years/${ay.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5"
                      onclick={() => doDeletePermanent(ay.id)}
                    >
                      <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                      Delete
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs font-semibold {ay.is_active
                        ? 'text-amber-600 hover:text-amber-700 hover:bg-amber-50'
                        : 'text-primary hover:text-primary-700 hover:bg-primary/5'}"
                      onclick={() => ay.is_active ? doDeactivate(ay.id) : router.post(`/admin/academic-years/${ay.id}/activate`)}
                    >
                      {#if ay.is_active}
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
                <Table.Cell colspan={5} class="py-12 text-center text-muted-foreground">
                  No academic years yet.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
        <SimplePagination data={academic_years} variant="table" />
      {/snippet}

      {#snippet cards()}
        {#if list.length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each list as ay}
              <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all {ay.is_active ? 'shadow-sm' : 'opacity-60 grayscale-[0.5]'}">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="font-bold tracking-tight truncate">{ay.label ?? (ay.academic_year ?? '—') + ' – ' + (ay.semester_label ?? '—')}</p>
                    <p class="text-sm text-muted-foreground">{ay.application_window ?? '— — —'}</p>
                  </div>
                  <Badge variant={ay.is_active ? 'success' : 'muted'} class="shrink-0 font-medium">
                    {ay.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-mono text-muted-foreground">{ay.applications_count ?? 0} apps</span>
                </div>
                <div class="mt-auto flex gap-2 pt-2">
                  <Link href={`/admin/academic-years/${ay.id}/edit`} class="flex-1">
                    <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                      <Pencil class="h-3.5 w-3.5 mr-1.5" />
                      Edit
                    </Button>
                  </Link>
                  <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 min-h-[40px] font-semibold {ay.is_active
                      ? 'text-amber-600 border-amber-200 hover:bg-amber-50 hover:text-amber-700'
                      : 'text-primary border-primary/20 hover:bg-primary/5 hover:text-primary-700'}"
                    onclick={() => ay.is_active ? doDeactivate(ay.id) : router.post(`/admin/academic-years/${ay.id}/activate`)}
                  >
                    {#if ay.is_active}
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
          <p class="py-12 text-center text-muted-foreground">No academic years yet.</p>
        {/if}
        <SimplePagination data={academic_years} variant="centered" />
      {/snippet}
    </SwitchableListView>
  </div>
</AuthenticatedLayout>