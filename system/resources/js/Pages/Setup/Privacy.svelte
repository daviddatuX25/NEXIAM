<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm, router } from '@inertiajs/svelte';

  let { policies = [] } = $props();

  const form = useForm({
    title: '',
    content: '',
    is_active: false,
  });

  function submit() {
    form.post('/setup/privacy', { onSuccess: () => form.reset() });
  }

  function activate(policy) {
    router.post(`/setup/privacy/${policy.id}/activate`);
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Privacy' },
  ];
</script>

<svelte:head><title>Privacy · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Privacy policy"
      description="Create a policy, then activate one for intake consent."
    >
      {#snippet actions()}
        <Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
      {/snippet}
    </PageHeaderBar>

    <form
      class="grid gap-3 rounded-xl border border-border bg-card p-4"
      onsubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <div class="space-y-1.5">
        <Label for="title">Title</Label>
        <Input id="title" bind:value={form.title} class="min-h-10" />
      </div>
      <div class="space-y-1.5">
        <Label for="content">Content</Label>
        <textarea
          id="content"
          bind:value={form.content}
          rows="6"
          class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
        ></textarea>
      </div>
      <Button type="submit" class="min-h-10 w-fit" disabled={form.processing}>Create policy</Button>
    </form>

    <Table.Root>
      <Table.Header>
        <Table.Row>
          <Table.Head>Title</Table.Head>
          <Table.Head>Status</Table.Head>
          <Table.Head class="text-right">Action</Table.Head>
        </Table.Row>
      </Table.Header>
      <Table.Body>
        {#each policies as p (p.id)}
          <Table.Row>
            <Table.Cell>{p.title}</Table.Cell>
            <Table.Cell>
              <Badge variant={p.is_active ? 'default' : 'secondary'}
                >{p.is_active ? 'Active' : 'Inactive'}</Badge
              >
            </Table.Cell>
            <Table.Cell class="text-right">
              {#if !p.is_active}
                <Button size="sm" variant="outline" onclick={() => activate(p)}>Activate</Button>
              {/if}
            </Table.Cell>
          </Table.Row>
        {/each}
      </Table.Body>
    </Table.Root>
  </div>
</StaffLayout>
