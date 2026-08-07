<script>
	import { Command as CommandPrimitive } from "bits-ui";
	import { cn } from "@/lib/utils.js";
	import SearchIcon from '@lucide/svelte/icons/search';

	let {
		ref = $bindable(null),
		class: className,
		value = $bindable(""),
		...restProps
	} = $props();
</script>

<div class="flex items-center border-b px-3" data-slot="command-input-wrapper">
	<SearchIcon class="mr-2 size-4 shrink-0 opacity-50" />
	<CommandPrimitive.Input
		bind:value
		bind:ref
		data-slot="command-input"
		class={cn(
			"flex h-11 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50",
			className
		)}
		{...restProps}
	/>
</div>