<script>
    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';
    import { cn } from '@/lib/utils.js';

    let {
        ref = $bindable(null),
        class: className,
        child,
        children,
        ...restProps
    } = $props();
</script>

<DropdownMenuPrimitive.Trigger
    bind:ref
    data-slot="dropdown-menu-trigger"
    class={cn('', className)}
    {child}
    {children}
    {...restProps}
/>
