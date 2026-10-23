<script>
  import * as Card from '@/Components/ui/card';
  import { ShieldAlert, Inbox, Info } from 'lucide-svelte';

  let { state = 'ready' } = $props();
</script>

{#if state === 'ready'}
  <p class="sr-only" data-testid="page-state">
    Page state: <span class="text-foreground">{state}</span>
  </p>
{:else if state === 'denied'}
  <div class="my-4" data-testid="page-state">
    <Card.Root class="border-destructive/30 bg-destructive/5">
      <Card.Header class="pb-2">
        <div class="flex items-center gap-2 text-destructive">
          <ShieldAlert class="size-5" />
          <Card.Title class="text-base font-semibold">Access Restricted</Card.Title>
        </div>
        <Card.Description class="text-xs">
          Your current actor role or duty does not have permission to view or manage this workspace.
        </Card.Description>
      </Card.Header>
    </Card.Root>
  </div>
{:else if state === 'empty'}
  <div class="my-4" data-testid="page-state">
    <Card.Root>
      <Card.Content class="flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
        <Inbox class="mb-2 size-8 text-muted-foreground/60" />
        <p class="text-sm font-medium text-foreground">No records available</p>
        <p class="mt-1 text-xs text-muted-foreground">There are no records currently available for this section.</p>
      </Card.Content>
    </Card.Root>
  </div>
{:else}
  <div class="my-4" data-testid="page-state">
    <Card.Root>
      <Card.Content class="flex items-center gap-3 p-4 text-sm text-muted-foreground">
        <Info class="size-5 shrink-0 text-muted-foreground" />
        <div>
          <span class="font-medium capitalize text-foreground">{state.replace(/_/g, ' ')}</span>
          <p class="mt-0.5 text-xs text-muted-foreground">This section is currently in state: {state}.</p>
        </div>
      </Card.Content>
    </Card.Root>
  </div>
{/if}
