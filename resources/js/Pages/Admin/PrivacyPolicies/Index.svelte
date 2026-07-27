<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import { Plus, Pencil, Trash2, Pause, Play } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { formatDate } from '@/lib/date-utils';
  import { onMount } from 'svelte';

  let { policies = [] } = $props();

  const page = usePage();
  const authUser = $derived($page.props.auth?.user ?? null);
  const roles = $derived(authUser?.roles?.map((r) => r.name) ?? []);
  function hasRole(r) { return roles.includes(r); }
  const isAdmin = $derived(hasRole('super_admin') || hasRole('registrar_administrator'));
  const canActivate = isAdmin;
  function canModify(policy) { return !policy.is_active || isAdmin; }

  let viewMode = $state('responsive');

  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);
  });

  function doToggle(id, currentActive) {
    if (currentActive) {
      router.post(`/admin/privacy-policies/${id}/deactivate`, {}, {
        onSuccess: () => router.reload(),
      });
    } else {
      router.post(`/admin/privacy-policies/${id}/activate`, {}, {
        onSuccess: () => router.reload(),
      });
    }
  }

  function doDelete(id) {
    if (confirm('Are you sure you want to delete this privacy policy?')) {
      router.delete(`/admin/privacy-policies/${id}`);
    }
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Privacy Policies' },
  ];
</script>

<svelte:head>
  <title>Privacy Policies - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold">Privacy Policies</h2>
        <p class="mt-1 text-sm text-muted-foreground">Manage the privacy policy shown to applicants on the application form.</p>
      </div>
      <Link href="/admin/privacy-policies/create">
        <Button class="min-h-[44px] gap-2">
          <Plus class="h-4 w-4" />
          <span class="hidden sm:inline">Create</span>
        </Button>
      </Link>
    </div>

    <SwitchableListView bind:viewMode overflow="scroll">
      {#snippet table()}
        <Table.Root class="w-full min-w-[540px] text-sm">
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head class="px-4 py-3">Title</Table.Head>
              <Table.Head class="px-4 py-3">Status</Table.Head>
              <Table.Head class="px-4 py-3">Last Updated</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each policies as policy}
              <Table.Row class={policy.is_active ? 'border-t border-border hover:bg-muted/30' : 'border-t border-border text-muted-foreground/50 transition-colors hover:bg-muted/30'}>
                <Table.Cell class="px-4 py-3 font-medium">{policy.title}</Table.Cell>
                <Table.Cell class="px-4 py-3">
                  <Badge variant={policy.is_active ? 'success' : 'muted'} class="font-medium">
                    {policy.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="px-4 py-3 text-muted-foreground">
                  {formatDate(policy.updated_at, 'short')}
                </Table.Cell>
                <Table.Cell class="text-center px-4 py-3">
                  <div class="inline-flex gap-2">
                    {#if canModify(policy)}
                      <Link href={`/admin/privacy-policies/${policy.id}/edit`}>
                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                          <Pencil class="mr-1.5 h-3.5 w-3.5" />
                          Edit
                        </Button>
                      </Link>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5"
                        onclick={() => doDelete(policy.id)}
                      >
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                        Delete
                      </Button>
                    {/if}
                    {#if canActivate}
                      <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2 text-xs font-semibold {policy.is_active
                          ? 'text-amber-600 hover:text-amber-700 hover:bg-amber-50'
                          : 'text-primary hover:text-primary-700 hover:bg-primary/5'}"
                        onclick={() => doToggle(policy.id, policy.is_active)}
                      >
                        {#if policy.is_active}
                          <Pause class="mr-1.5 h-3.5 w-3.5" />
                          Deactivate
                        {:else}
                          <Play class="mr-1.5 h-3.5 w-3.5" />
                          Activate
                        {/if}
                      </Button>
                    {/if}
                  </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan="4" class="px-4 py-12 text-center text-muted-foreground">
                  No privacy policies yet. Create one to display on the application form.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/snippet}

      {#snippet cards()}
        {#if policies.length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each policies as policy}
              <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all {policy.is_active ? 'shadow-sm' : 'opacity-60 grayscale-[0.5]'}">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <h3 class="truncate font-bold tracking-tight">{policy.title}</h3>
                    <p class="text-xs text-muted-foreground mt-1">{formatDate(policy.updated_at, 'short')}</p>
                  </div>
                  <Badge variant={policy.is_active ? 'success' : 'muted'} class="shrink-0 font-medium">
                    {policy.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
                <p class="text-sm text-muted-foreground line-clamp-3">{policy.content}</p>
                <div class="mt-auto flex gap-2 pt-2">
                  {#if canModify(policy)}
                    <Link href={`/admin/privacy-policies/${policy.id}/edit`} class="flex-1">
                      <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                        <Pencil class="h-3.5 w-3.5 mr-1.5" />
                        Edit
                      </Button>
                    </Link>
                  {/if}
                  {#if canActivate}
                    <Button
                      variant="outline"
                      size="sm"
                      class="flex-1 min-h-[40px] font-semibold {policy.is_active
                        ? 'text-amber-600 border-amber-200 hover:bg-amber-50 hover:text-amber-700'
                        : 'text-primary border-primary/20 hover:bg-primary/5 hover:text-primary-700'}"
                      onclick={() => doToggle(policy.id, policy.is_active)}
                    >
                      {#if policy.is_active}
                        <Pause class="h-3.5 w-3.5 mr-1.5" />
                        Deactivate
                      {:else}
                        <Play class="h-3.5 w-3.5 mr-1.5" />
                        Activate
                      {/if}
                    </Button>
                  {/if}
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <p class="py-12 text-center text-muted-foreground">No privacy policies yet. Create one to get started.</p>
        {/if}
      {/snippet}
    </SwitchableListView>
  </div>
</AuthenticatedLayout>
