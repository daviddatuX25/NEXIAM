<script>
	import { cn } from "@/lib/utils.js";
	let {
		ref = $bindable(null),
		class: className,
		children,
		...restProps
	} = $props();
</script>

<th
	bind:this={ref}
	data-slot="table-head"
	class={cn(
		"text-muted-foreground/90 bg-clip-padding py-2 px-3 text-center align-middle text-[11px] font-semibold uppercase tracking-wider whitespace-nowrap [&:has([role=checkbox])]:pe-0",
		className
	)}
	{...restProps}
>
	{@render children?.()}
</th>