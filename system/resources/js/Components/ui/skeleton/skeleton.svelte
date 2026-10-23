<script>
	import { cn } from "@/lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		...restProps
	} = $props();
</script>

<div
	bind:this={ref}
	data-slot="skeleton"
	aria-busy="true"
	aria-hidden="true"
	class={cn(
		"skeleton-shimmer rounded-[calc(var(--radius)-2px)] bg-muted",
		className
	)}
	{...restProps}
></div>
