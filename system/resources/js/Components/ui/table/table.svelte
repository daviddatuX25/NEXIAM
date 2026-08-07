<script>
	import { cn } from "@/lib/utils.js";

	let {
		ref = $bindable(null),
		scrollable = true,
		class: className,
		children,
		...restProps
	} = $props();
</script>

<div data-slot="table-container" class="relative w-full {scrollable ? 'table-scroll-container' : ''}">
	<table
		bind:this={ref}
		data-slot="table"
		class={cn("w-full caption-bottom text-sm", className)}
		{...restProps}
	>
		{@render children?.()}
	</table>
</div>