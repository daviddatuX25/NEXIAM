<script>
    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils.js';

    let {
        ref = $bindable(null),
        class: className,
        child,
        children,
        disabled = false,
        ...restProps
    } = $props();
</script>

<DropdownMenuPrimitive.Item
    bind:ref
    data-slot="dropdown-menu-item"
    class={cn(
        "data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-3.5",
        className,
    )}
    {disabled}
    {child}
    {children}
    {...restProps}
/>
