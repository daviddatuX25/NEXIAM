<script>
	import { cn } from "@/lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		variant = 'default',
		children,
		...restProps
	} = $props();
</script>

<div
	bind:this={ref}
	data-slot="card"
	class={cn(
		"text-card-foreground flex flex-col gap-6 py-6",
		variant === 'glass' ? 'glass-panel rounded-2xl border border-border/50 shadow-sm' : 'bg-card rounded-xl border shadow-sm',
		className
	)}
	{...restProps}
>
	{@render children?.()}
</div>