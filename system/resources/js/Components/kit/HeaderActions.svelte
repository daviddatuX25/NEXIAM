<script>
  import * as Popover from '@/Components/ui/popover';
  import { Button } from '@/Components/ui/button';
  import { MoreVertical } from 'lucide-svelte';
  import ActionMenuProvider from '@/Components/kit/ActionMenuProvider.svelte';

  let {
    primaryAction = null,
    secondaryActions = null,
    alertAction = null,
  } = $props();

  let open = $state(false);
</script>

<div class="flex items-center gap-2">
  <div class="hidden items-center gap-2 sm:flex">
    {#if secondaryActions}
      {@render secondaryActions()}
    {/if}
    {#if alertAction}
      {@render alertAction()}
    {/if}
    {#if primaryAction}
      {@render primaryAction()}
    {/if}
  </div>

  <div class="flex min-w-0 flex-1 items-center gap-2 sm:hidden">
    {#if primaryAction}
      <div class="min-w-0 flex-1 [&_a]:block [&_button]:w-full [&_button]:justify-center">
        {@render primaryAction()}
      </div>
    {/if}
    {#if alertAction}
      <div class="shrink-0">
        {@render alertAction()}
      </div>
    {/if}
    {#if secondaryActions}
      <Popover.Root bind:open={open}>
        <Popover.Trigger>
          <Button variant="outline" size="icon" class="min-h-10 min-w-10 shrink-0" aria-label="More actions">
            <MoreVertical class="size-4" />
          </Button>
        </Popover.Trigger>
        <Popover.Content class="w-56 p-2 space-y-1 [&_a]:block [&_a]:w-full [&_button]:w-full [&_button]:justify-start" align="end">
          <div onclick={() => (open = false)} role="presentation" class="contents">
            <ActionMenuProvider>
              {@render secondaryActions()}
            </ActionMenuProvider>
          </div>
        </Popover.Content>
      </Popover.Root>
    {/if}
  </div>
</div>
