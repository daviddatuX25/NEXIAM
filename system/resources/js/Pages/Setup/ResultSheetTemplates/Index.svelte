<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import { Plus, Pencil, Trash2, Play, Pause } from 'lucide-svelte';

  let { templates = [] } = $props();

  let deleteTarget = $state(null);

  const paperLabels = { a4: 'A4', legal: 'Legal', letter: 'Letter' };
  const unitLabels = { full: 'Full page', half_a4: 'Half A4', half_legal: 'Half Legal', half_letter: 'Half Letter' };

  function confirmDelete(template) {
    deleteTarget = template;
  }

  function closeDelete() {
    deleteTarget = null;
  }

  function doDelete() {
    if (!deleteTarget) return;
    router.delete(`/setup/result-templates/${deleteTarget.id}`, { onSuccess: () => (deleteTarget = null) });
  }

  function activate(t) {
    router.post(`/setup/result-templates/${t.id}/activate`);
  }

  function deactivate(t) {
    router.post(`/setup/result-templates/${t.id}/deactivate`);
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Result Sheet Templates' },
  ];
</script>

<svelte:head><title>Result Sheet Templates · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Result sheet templates"
      description="One active template drives every result-sheet render. HTML templates are edited here; Word/ODT templates are uploaded and validated."
    >
      {#snippet actions()}
        <Link href="/setup/result-templates/create">
          <Button class="min-h-10 gap-2">
            <Plus class="size-4" />
            New template
          </Button>
        </Link>
      {/snippet}
    </PageHeaderBar>

    <div class="rounded-xl border border-border bg-card">
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Name</Table.Head>
            <Table.Head>Mode</Table.Head>
            <Table.Head>Paper / Unit</Table.Head>
            <Table.Head>Watermark</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="text-right">Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each templates as t (t.id)}
            <Table.Row>
              <Table.Cell class="font-medium">{t.name}</Table.Cell>
              <Table.Cell>
                <Badge variant={t.mode === 'docx' ? 'secondary' : 'default'}>
                  {t.mode === 'docx' ? `Word (${t.document_extension ?? ''})` : 'HTML'}
                </Badge>
              </Table.Cell>
              <Table.Cell class="text-sm text-muted-foreground">
                {paperLabels[t.paper_size] ?? t.paper_size} · {unitLabels[t.logical_unit] ?? t.logical_unit}
              </Table.Cell>
              <Table.Cell class="text-sm text-muted-foreground">{t.watermark_text ?? '—'}</Table.Cell>
              <Table.Cell>
                <Badge variant={t.is_active ? 'default' : 'secondary'}>
                  {t.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </Table.Cell>
              <Table.Cell class="text-right">
                <div class="flex justify-end gap-2">
                  <Link href={`/setup/result-templates/${t.id}/edit`}>
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 px-2 text-xs">
                      <Pencil class="size-3.5" />
                      Edit
                    </Button>
                  </Link>
                  {#if t.is_active}
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 px-2 text-xs" onclick={() => deactivate(t)}>
                      <Pause class="size-3.5" />
                      Deactivate
                    </Button>
                  {:else}
                    <Button variant="outline" size="sm" class="h-8 gap-1.5 px-2 text-xs" onclick={() => activate(t)}>
                      <Play class="size-3.5" />
                      Activate
                    </Button>
                  {/if}
                  <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 gap-1.5 px-2 text-xs text-destructive"
                    onclick={() => confirmDelete(t)}
                  >
                    <Trash2 class="size-3.5" />
                  </Button>
                </div>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </div>

  {#if deleteTarget}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true">
      <div class="w-full max-w-sm rounded-lg bg-card p-6 shadow-lg">
        <h2 class="text-base font-semibold">Delete template</h2>
        <p class="mt-2 text-sm text-muted-foreground">
          Delete “{deleteTarget.name}”? Printed sheets already generated are not affected.
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" onclick={closeDelete}>Cancel</Button>
          <Button variant="destructive" onclick={doDelete}>Delete</Button>
        </div>
      </div>
    </div>
  {/if}
</StaffLayout>
