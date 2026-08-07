<script module>
	import { tv } from "tailwind-variants";

	export const tabsListVariants = tv({
		base: "group/tabs-list inline-flex w-fit items-center justify-center text-muted-foreground group-data-horizontal/tabs:h-auto group-data-[orientation=vertical]/tabs:h-fit group-data-[orientation=vertical]/tabs:flex-col",
		variants: {
			variant: {
				/** Soft segmented control (legacy default) */
				default: "cn-tabs-list-variant-default rounded-lg bg-muted p-1",
				/** Institutional underline track — preferred for staff chrome */
				line: "cn-tabs-list-variant-line gap-0 rounded-none border-b border-border bg-transparent p-0",
			},
		},
		defaultVariants: {
			variant: "line",
		},
	});
</script>

<script>
	import { Tabs as TabsPrimitive } from "bits-ui";
	import { cn } from "@/lib/utils.js";

	let {
		ref = $bindable(null),
		variant = "line",
		class: className,
		...restProps
	} = $props();
</script>

<TabsPrimitive.List
	bind:ref
	data-slot="tabs-list"
	data-variant={variant}
	class={cn(tabsListVariants({ variant }), className)}
	{...restProps}
/>
