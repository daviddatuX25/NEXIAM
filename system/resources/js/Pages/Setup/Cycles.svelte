<script>
  /**
   * Academic years list — legacy Admin/AcademicYears/Index feel:
   * show what exists and what is active; Create/Edit are separate pages.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Plus, Pencil, Pause, Play } from 'lucide-svelte';

  let { cycles = [] } = $props();

  let viewMode = $state('responsive');
  const hasActive = $derived((cycles ?? []).some((ay) => ay.is_active));

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Academic years' },
  ];
</script>

<svelte:head><title>Academic years · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Academic years"
      description="View and manage school years and semesters. Activate one so applications can open."
    >
      {#snippet actions()}
        <Link href="/setup/cycles/create">
          <Button class="min-h-10 gap-2">
            <Plus class="size-4" />
            Add academic year
          </Button>
        </Link>
      {/snippet}
    </PageHeaderBar>

    {#if !hasActive}
      <div
        class="rounded-md border border-amber-500/50 bg-amber-500/10 px-4 py-3 text-sm text-foreground"
      >
        No academic year is currently active. Applications stay closed until one is activated.
      </div>
    {/if}

    <SwitchableListView bind:viewMode overflow="auto">
      {#snippet table()}
        <Table.Root class="w-full min-w-[640px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head>Academic year</Table.Head>
              <Table.Head>Application window</Table.Head>
              <Table.Head>Status</Table.Head>
              <Table.Head class="text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each cycles as ay (ay.id)}
              <Table.Row class={ay.is_active ? '' : 'text-muted-foreground/70'}>
                <Table.Cell class="font-semibold tracking-tight">{ay.label}</Table.Cell>
                <Table.Cell class="text-muted-foreground">{ay.application_window}</Table.Cell>
                <Table.Cell>
                  <Badge variant={ay.is_active ? 'default' : 'secondary'}>
                    {ay.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="text-center">
                  <div class="inline-flex flex-wrap justify-center gap-1">
                    <Link href={`/setup/cycles/${ay.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold">
                        <Pencil class="mr-1.5 size-3.5" />
                        Edit
                      </Button>
                    </Link>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs font-semibold {ay.is_active
                        ? 'text-amber-700 hover:bg-amber-50 dark:text-amber-400'
                        : 'text-primary hover:bg-primary/5'}"
                      onclick={() =>
                        ay.is_active
                          ? router.post(`/setup/cycles/${ay.id}/deactivate`)
                          : router.post(`/setup/cycles/${ay.id}/activate`)}
                    >
                      {#if ay.is_active}
                        <Pause class="mr-1.5 size-3.5" />
                        Deactivate
                      {:else}
                        <Play class="mr-1.5 size-3.5" />
                        Activate
                      {/if}
                    </Button>
                  </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={4} class="py-12 text-center text-muted-foreground">
                  No academic years yet.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/snippet}

      {#snippet cards()}
        {#if cycles.length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each cycles as ay (ay.id)}
              <li
                class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 {ay.is_active
                  ? 'shadow-sm'
                  : 'opacity-70'}"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate font-semibold tracking-tight">{ay.label}</p>
                    <p class="text-sm text-muted-foreground">{ay.application_window}</p>
                  </div>
                  <Badge variant={ay.is_active ? 'default' : 'secondary'} class="shrink-0">
                    {ay.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
                <div class="mt-auto flex gap-2 pt-2">
                  <Link href={`/setup/cycles/${ay.id}/edit`} class="flex-1">
                    <Button variant="outline" size="sm" class="min-h-10 w-full font-semibold">
                      <Pencil class="mr-1.5 size-3.5" />
                      Edit
                    </Button>
                  </Link>
                  <Button
                    variant="outline"
                    size="sm"
                    class="min-h-10 flex-1 font-semibold"
                    onclick={() =>
                      ay.is_active
                        ? router.post(`/setup/cycles/${ay.id}/deactivate`)
                        : router.post(`/setup/cycles/${ay.id}/activate`)}
                  >
                    {#if ay.is_active}
                      <Pause class="mr-1.5 size-3.5" />
                      Deactivate
                    {:else}
                      <Play class="mr-1.5 size-3.5" />
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
      {/snippet}
    </SwitchableListView>
  </div>
</StaffLayout>
