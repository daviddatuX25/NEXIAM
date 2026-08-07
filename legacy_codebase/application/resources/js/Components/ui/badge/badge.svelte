<script module>
	import { cn } from "@/lib/utils.js";
	import { tv } from "tailwind-variants";

	export const badgeVariants = tv({
		base: "inline-flex rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
		variants: {
			variant: {
				default:
					"border-transparent bg-primary text-primary-foreground",
				secondary:
					"border-transparent bg-secondary text-secondary-foreground",
				destructive:
					"border-transparent bg-destructive text-destructive-foreground",
				outline: "border-border text-foreground dark:border-border",
				// Semantic outline variants (readable on any background)
				success:
					"border-emerald-500 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400",
				warning:
					"border-amber-500 text-amber-700 dark:border-amber-400 dark:text-amber-400",
				danger:
					"border-red-500 text-red-700 dark:border-red-400 dark:text-red-400",
				muted:
					"border-border text-muted-foreground dark:border-muted-foreground/50",
			},
		},
		defaultVariants: {
			variant: "default",
		},
	});
</script>

<script>
	let {
		class: className,
		variant = "default",
		children,
		...restProps
	} = $props();
</script>

<span
	class={cn(badgeVariants({ variant }), className)}
	data-slot="badge"
	{...restProps}
>
	{@render children?.()}
</span>
