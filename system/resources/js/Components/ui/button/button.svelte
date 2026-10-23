<script module>
	import { cn } from "@/lib/utils.js";
	import { tv } from "tailwind-variants";

	export const buttonVariants = tv({
		base: "focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:aria-invalid:border-destructive/50 rounded-lg border border-transparent bg-clip-padding text-sm font-medium focus-visible:ring-3 active:not-aria-[haspopup]:translate-y-px aria-invalid:ring-3 [&_svg:not([class*='size-'])]:size-4 group/button inline-flex shrink-0 items-center justify-center whitespace-nowrap transition-all outline-none select-none disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0",
		variants: {
			variant: {
				default: "bg-primary text-primary-foreground font-bold border border-primary/85 shadow-sm shadow-primary/20 [background-image:linear-gradient(180deg,rgba(255,255,255,0.18)_0%,rgba(255,255,255,0)_100%)] [box-shadow:inset_0_1px_0_0_rgba(255,255,255,0.28),0_2px_4px_0_rgba(0,0,0,0.1),0_4px_12px_-2px_rgba(16,185,129,0.35)] hover:brightness-105 active:brightness-95",
				outline: "border-border/80 bg-card/85 text-foreground hover:bg-muted hover:border-primary/50 dark:bg-card/60 dark:border-border/80 dark:hover:bg-muted/40 [box-shadow:inset_0_1px_0_0_rgba(255,255,255,0.08),0_1px_2px_0_rgba(0,0,0,0.05)]",
				secondary: "bg-secondary text-secondary-foreground hover:bg-secondary/80",
				ghost: "hover:bg-muted hover:text-foreground dark:hover:bg-muted/50",
				destructive: "bg-destructive/10 hover:bg-destructive/20 text-destructive border-destructive/30",
				link: "text-primary underline-offset-4 hover:underline",
			},
			size: {
				default: "h-8 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
				xs: "h-6 gap-1 rounded-lg px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
				sm: "h-7 gap-1 rounded-lg px-2.5 text-[0.8rem] in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3.5",
				lg: "h-9 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
				icon: "size-8",
				"icon-xs": "size-6 rounded-lg in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3",
				"icon-sm": "size-7 rounded-lg in-data-[slot=button-group]:rounded-lg",
				"icon-lg": "size-9",
			},
		},
		defaultVariants: {
			variant: "default",
			size: "default",
		},
	});

</script>

<script>
	import { sound } from '@/lib/sound.js';

	let {
		class: className,
		variant = "default",
		size = "default",
		ref = $bindable(null),
		href = undefined,
		type = "button",
		disabled,
		children,
		...restProps
	} = $props();

	function handleClick(e) {
		if (!disabled) {
			sound.press();
		}
		restProps.onclick?.(e);
	}
</script>

{#if href}
	<a
		bind:this={ref}
		data-slot="button"
		class={cn(buttonVariants({ variant, size }), "motion-safe:hover:-translate-y-0.5 motion-safe:active:scale-[0.96] transition-transform", className)}
		href={disabled ? undefined : href}
		aria-disabled={disabled}
		role={disabled ? "link" : undefined}
		tabindex={disabled ? -1 : undefined}
		onclick={handleClick}
		{...restProps}
	>
		{@render children?.()}
	</a>
{:else}
	<button
		bind:this={ref}
		data-slot="button"
		class={cn(buttonVariants({ variant, size }), "motion-safe:hover:-translate-y-0.5 motion-safe:active:scale-[0.96] transition-transform", className)}
		{type}
		{disabled}
		onclick={handleClick}
		{...restProps}
	>
		{@render children?.()}
	</button>
{/if}