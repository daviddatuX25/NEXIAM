<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm, router } from '@inertiajs/svelte';

  let { fields = [] } = $props();

  const form = useForm({
    code: '',
    label: '',
    field_kind: 'subject',
    sort_order: 100,
    is_required: false,
    is_active: true,
  });

  function submit() {
    form.post('/setup/grades', {
      onSuccess: () => form.reset('code', 'label'),
    });
  }

  function toggleActive(field) {
    router.put(`/setup/grades/${field.id}`, {
      label: field.label,
      is_active: !field.is_active,
      is_required: field.is_required,
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Intake grades' },
  ];
</script>

<svelte:head><title>Intake grades · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Intake grade fields"
      description="Create grade fields for applications, then activate those shown on intake."
    >
      {#snippet actions()}
        <Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
      {/snippet}
    </PageHeaderBar>

    <form
      class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-5"
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
        <Label for="label">Label</Label>
        <Input id="label" bind:value={form.label} class="min-h-10" />
      </div>
      <div class="space-y-1.5">
        <Label for="kind">Kind</Label>
        <select
          id="kind"
          bind:value={form.field_kind}
          class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
        >
          <option value="gwa">GWA</option>
          <option value="subject">Subject</option>
        </select>
      </div>
      <div class="space-y-1.5">
        <Label for="sort">Sort</Label>
        <Input id="sort" type="number" bind:value={form.sort_order} class="min-h-10" />
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full" disabled={form.processing}>Add field</Button>
      </div>
    </form>

    <Table.Root>
      <Table.Header>
        <Table.Row>
          <Table.Head>Code</Table.Head>
          <Table.Head>Label</Table.Head>
          <Table.Head>Kind</Table.Head>
          <Table.Head>Status</Table.Head>
          <Table.Head class="text-right">Action</Table.Head>
        </Table.Row>
      </Table.Header>
      <Table.Body>
        {#each fields as g (g.id)}
          <Table.Row>
            <Table.Cell class="font-mono text-xs">{g.code}</Table.Cell>
            <Table.Cell>{g.label}</Table.Cell>
            <Table.Cell class="text-xs text-muted-foreground">{g.field_kind}</Table.Cell>
            <Table.Cell>
              <Badge variant={g.is_active ? 'default' : 'secondary'}
                >{g.is_active ? 'Active' : 'Inactive'}</Badge
              >
            </Table.Cell>
            <Table.Cell class="text-right">
              <Button size="sm" variant="outline" onclick={() => toggleActive(g)}>
                {g.is_active ? 'Deactivate' : 'Activate'}
              </Button>
            </Table.Cell>
          </Table.Row>
        {/each}
      </Table.Body>
    </Table.Root>
  </div>
</StaffLayout>
