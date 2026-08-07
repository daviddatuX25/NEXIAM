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
		"text-card-foreground flex flex-col gap-5 py-5 transition-[box-shadow,transform,border-color] duration-200",
		variant === 'glass'
			? 'glass-panel rounded-xl border-0 shadow-md hover:shadow-lg'
			: 'rounded-xl border border-border/70 bg-card shadow-[0_1px_2px_oklch(0_0_0/0.04),0_8px_24px_-12px_oklch(0.31_0.02_148/0.18)] hover:-translate-y-0.5 hover:border-border hover:shadow-[0_1px_2px_oklch(0_0_0/0.05),0_16px_32px_-16px_oklch(0.31_0.02_148/0.22)]',
		className
	)}
	{...restProps}
>
	{@render children?.()}
</div>
