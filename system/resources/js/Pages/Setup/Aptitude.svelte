<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm, router } from '@inertiajs/svelte';

  let { areas = [] } = $props();

  const form = useForm({
    code: '',
    name: '',
    version: 1,
    is_active: true,
  });

  function submit() {
    form.post('/setup/aptitude', { onSuccess: () => form.reset('code', 'name') });
  }

  function toggleActive(area) {
    router.put(`/setup/aptitude/${area.id}`, {
      name: area.name,
      is_active: !area.is_active,
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Aptitude areas' },
  ];
</script>

<svelte:head><title>Aptitude Areas · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Aptitude Areas"
      description="Maintain aptitude constructs. Instrument profiles define form-specific sections, item inventories, and raw scoring rules."
    >
      {#snippet actions()}
        <Link href="/setup/test-instruments"><Button variant="secondary" size="sm">Test instruments</Button></Link>
        <Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
      {/snippet}
    </PageHeaderBar>

    <form
      class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-4"
      onsubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <div class="space-y-1.5">
        <Label for="code">Code</Label>
        <Input id="code" bind:value={form.code} class="min-h-10 font-mono" />
      </div>
      <div class="space-y-1.5">
        <Label for="name">Name</Label>
        <Input id="name" bind:value={form.name} class="min-h-10" />
      </div>
      <div class="space-y-1.5">
        <Label for="version">Version</Label>
        <Input id="version" type="number" min="1" bind:value={form.version} class="min-h-10" />
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full" disabled={form.processing}>Add area</Button>
      </div>
    </form>

    <div class="rounded-xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs overflow-hidden">
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Code</Table.Head>
            <Table.Head>Name</Table.Head>
            <Table.Head>Version</Table.Head>
            <Table.Head>Scoring</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="text-right">Action</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each areas as a (a.id)}
            <Table.Row>
              <Table.Cell class="font-mono text-xs">{a.code}</Table.Cell>
              <Table.Cell>{a.name}</Table.Cell>
              <Table.Cell class="font-mono text-xs">{a.version}</Table.Cell>
              <Table.Cell>
                <Badge variant="outline">
                  {a.max_items !== null ? `${a.max_items} · ${a.conversion_rows_count} row${a.conversion_rows_count === 1 ? '' : 's'}` : 'No table'}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <Badge variant={a.is_active ? 'default' : 'secondary'}
                  >{a.is_active ? 'Active' : 'Inactive'}</Badge
                >
              </Table.Cell>
              <Table.Cell class="text-right">
                <span class="inline-flex gap-2">
                  <Link href={`/setup/aptitude/${a.id}/scoring`}>
                    <Button size="sm" variant="outline">Scoring</Button>
                  </Link>
                  <Button size="sm" variant="outline" onclick={() => toggleActive(a)}>
                    {a.is_active ? 'Deactivate' : 'Activate'}
                  </Button>
                </span>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </div>
</StaffLayout>
