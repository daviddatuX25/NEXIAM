<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm, router } from '@inertiajs/svelte';

  let { programs = [] } = $props();

  const form = useForm({
    code: '',
    name: '',
    is_active: true,
  });

  function submit() {
    form.post('/setup/programs', { onSuccess: () => form.reset('code', 'name') });
  }

  function toggleActive(program) {
    router.put(`/setup/programs/${program.id}`, {
      name: program.name,
      is_active: !program.is_active,
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Programs' },
  ];
</script>

<svelte:head><title>Programs · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Programs & courses"
      description="Create programs, then activate those open for intake."
    >
      {#snippet actions()}
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
        <Input id="code" bind:value={form.code} class="min-h-10 font-mono" placeholder="BSN" />
      </div>
      <div class="space-y-1.5 sm:col-span-2">
        <Label for="name">Name</Label>
        <Input id="name" bind:value={form.name} class="min-h-10" />
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full" disabled={form.processing}>Add program</Button>
      </div>
    </form>

    <Table.Root>
      <Table.Header>
        <Table.Row>
          <Table.Head>Code</Table.Head>
          <Table.Head>Name</Table.Head>
          <Table.Head>Status</Table.Head>
          <Table.Head class="text-right">Action</Table.Head>
        </Table.Row>
      </Table.Header>
      <Table.Body>
        {#each programs as p (p.id)}
          <Table.Row>
            <Table.Cell class="font-mono text-xs">{p.code}</Table.Cell>
            <Table.Cell>{p.name}</Table.Cell>
            <Table.Cell>
              <Badge variant={p.is_active ? 'default' : 'secondary'}
                >{p.is_active ? 'Active' : 'Inactive'}</Badge
              >
            </Table.Cell>
            <Table.Cell class="text-right">
              <Button size="sm" variant="outline" onclick={() => toggleActive(p)}>
                {p.is_active ? 'Deactivate' : 'Activate'}
              </Button>
            </Table.Cell>
          </Table.Row>
        {/each}
      </Table.Body>
    </Table.Root>
  </div>
</StaffLayout>
