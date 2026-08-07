<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';

  let { staff } = $props();

  const breadcrumbs = [{ label: 'Home' }];
</script>

<svelte:head>
  <title>Staff home</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="mx-auto flex max-w-3xl flex-col gap-6">
    <div>
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">E1 identity</p>
      <h1 class="mt-2 text-3xl font-semibold text-foreground">{staff.name}</h1>
      <p class="mt-1 text-muted-foreground">{staff.email}</p>
    </div>

    <Card.Root>
      <Card.Header>
        <Card.Title>Actor scope</Card.Title>
        <Card.Description>GF-21 resource-scope context for this session.</Card.Description>
      </Card.Header>
      <Card.Content>
        <pre class="overflow-x-auto rounded-md bg-muted p-4 text-sm text-foreground">{JSON.stringify(staff.scope, null, 2)}</pre>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Header>
        <Card.Title>Capability grants</Card.Title>
        <Card.Description>Resolved through Spatie persistence; CapabilityAuthorizer decides.</Card.Description>
      </Card.Header>
      <Card.Content>
        <div class="flex flex-wrap gap-2">
          {#each staff.capabilities as capability (capability)}
            <Badge variant="secondary">{capability}</Badge>
          {/each}
        </div>
      </Card.Content>
    </Card.Root>
  </div>
</StaffLayout>
