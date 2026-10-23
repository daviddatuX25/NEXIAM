<script>
    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';
    import DropdownMenuPortal from './dropdown-menu-portal.svelte';
    import { cn } from '@/lib/utils.js';

    let {
        ref = $bindable(null),
        class: className,
        sideOffset = 4,
        align = 'end',
        collisionPadding = 8,
        preventScroll = false,
        strategy = 'fixed',
        portalProps,
        portal = true,
        children,
        ...restProps
    } = $props();
</script>

{#snippet content()}
    <DropdownMenuPrimitive.Content
        bind:ref
        data-slot="dropdown-menu-content"
        {sideOffset}
        {align}
        {collisionPadding}
        {preventScroll}
        {strategy}
        style="max-height: min(24rem, var(--bits-floating-available-height), calc(100dvh - 1rem));"
        class={cn(
            'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 border-border z-50 min-w-48 space-y-0.5 overflow-y-auto rounded-xl border p-1 text-xs shadow-lg outline-hidden',
            className,
        )}
        {...restProps}
    >
        {@render children?.()}
    </DropdownMenuPrimitive.Content>
{/snippet}

{#if portal}
    <DropdownMenuPortal {...portalProps}>
        {@render content()}
    </DropdownMenuPortal>
{:else}
    {@render content()}
{/if}
