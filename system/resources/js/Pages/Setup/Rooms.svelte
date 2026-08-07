<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm, router } from '@inertiajs/svelte';

  let { rooms = [] } = $props();

  const form = useForm({
    code: '',
    name: '',
    capacity: 30,
    is_active: true,
  });

  function submit() {
    form.post('/setup/rooms', { onSuccess: () => form.reset('code', 'name') });
  }

  function toggleActive(room) {
    router.put(`/setup/rooms/${room.id}`, {
      name: room.name,
      capacity: room.capacity,
      is_active: !room.is_active,
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Rooms' },
  ];
</script>

<svelte:head><title>Rooms · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Rooms & facilities" description="Create rooms, then activate for scheduling.">
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
      <div class="space-y-1.5 sm:col-span-2">
        <Label for="name">Name</Label>
        <Input id="name" bind:value={form.name} class="min-h-10" />
      </div>
      <div class="space-y-1.5">
        <Label for="capacity">Capacity</Label>
        <Input id="capacity" type="number" min="1" bind:value={form.capacity} class="min-h-10" />
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full" disabled={form.processing}>Add room</Button>
      </div>
    </form>

    <Table.Root>
      <Table.Header>
        <Table.Row>
          <Table.Head>Code</Table.Head>
          <Table.Head>Name</Table.Head>
          <Table.Head>Capacity</Table.Head>
          <Table.Head>Status</Table.Head>
          <Table.Head class="text-right">Action</Table.Head>
        </Table.Row>
      </Table.Header>
      <Table.Body>
        {#each rooms as r (r.id)}
          <Table.Row>
            <Table.Cell class="font-mono text-xs">{r.code}</Table.Cell>
            <Table.Cell>{r.name}</Table.Cell>
            <Table.Cell>{r.capacity}</Table.Cell>
            <Table.Cell>
              <Badge variant={r.is_active ? 'default' : 'secondary'}
                >{r.is_active ? 'Active' : 'Inactive'}</Badge
              >
            </Table.Cell>
            <Table.Cell class="text-right">
              <Button size="sm" variant="outline" onclick={() => toggleActive(r)}>
                {r.is_active ? 'Deactivate' : 'Activate'}
              </Button>
            </Table.Cell>
          </Table.Row>
        {/each}
      </Table.Body>
    </Table.Root>
  </div>
</StaffLayout>
