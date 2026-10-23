<script>
  import { DropdownMenu } from 'bits-ui';
  import { Ellipsis } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';
  import { Link } from '@inertiajs/svelte';

  let {
    user,
    items = [],
    busy = false,
    onAction,
    primaryHref = null,
    primaryLabel = 'Edit',
  } = $props();
</script>

<div class="flex items-center justify-end gap-2">
  {#if primaryHref}
    <Link href={primaryHref}><Button type="button" variant="outline" class="h-9 min-h-9 text-xs">{primaryLabel}</Button></Link>
  {/if}
  <DropdownMenu.Root>
    <DropdownMenu.Trigger>
      {#snippet child({ props })}
        <Button
          {...props}
          type="button"
          variant="ghost"
          class="size-9 p-0"
          aria-label={`More actions for ${user.name}`}
          disabled={busy || items.every((item) => item.disabled)}
        >
          <Ellipsis class="size-4" aria-hidden="true" />
        </Button>
      {/snippet}
    </DropdownMenu.Trigger>
    <DropdownMenu.Portal>
      <DropdownMenu.Content
        sideOffset={6}
        align="end"
        collisionPadding={8}
        class="z-[100] max-h-[min(24rem,calc(100vh-1rem))] min-w-52 overflow-y-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-md"
      >
        {#each items as item (item.id)}
          {#if item.separator}
            <DropdownMenu.Separator class="my-1 h-px bg-border" />
          {:else}
            <DropdownMenu.Item
              disabled={busy || item.disabled}
              onSelect={() => onAction?.(item.id, user)}
              class="flex min-h-9 cursor-default select-none items-center rounded-sm px-2.5 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 {item.destructive ? 'text-destructive focus:text-destructive' : ''}"
            >
              {item.label}
            </DropdownMenu.Item>
          {/if}
        {/each}
      </DropdownMenu.Content>
    </DropdownMenu.Portal>
  </DropdownMenu.Root>
</div>
