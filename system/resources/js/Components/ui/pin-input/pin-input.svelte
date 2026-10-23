<script>
  import { PinInput as BitsPinInput } from "bits-ui";
  import { cn } from "@/lib/utils.js";

  let {
    value = $bindable(""),
    maxlength = 6,
    disabled = false,
    autofocus = false,
    onComplete,
    class: className,
    ...restProps
  } = $props();

  function pasteTransformer(text) {
    return text.replace(/\D/g, "").slice(0, maxlength);
  }
</script>

<BitsPinInput.Root
  bind:value
  {maxlength}
  {disabled}
  {autofocus}
  {onComplete}
  {pasteTransformer}
  class={cn("flex items-center gap-1.5 sm:gap-2 cursor-text", className)}
  {...restProps}
>
  {#snippet children({ cells })}
    <div class="flex gap-1.5 sm:gap-2">
      {#each cells.slice(0, 3) as cell}
        <BitsPinInput.Cell
          {cell}
          class={cn(
            "relative flex size-10 sm:size-12 items-center justify-center rounded-lg border bg-background/50 font-mono text-base sm:text-lg font-semibold shadow-xs transition-all pointer-events-none select-none",
            cell.isActive
              ? "border-primary ring-2 ring-primary/40 ring-offset-1 ring-offset-background"
              : "border-input data-[active]:border-primary data-[active]:ring-2 data-[active]:ring-primary/40"
          )}
        >
          {#if cell.char !== null}
            {cell.char}
          {/if}
          {#if cell.hasFakeCaret}
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-5 sm:h-6 w-[2px] animate-caret-blink bg-primary rounded-full pointer-events-none"
                style="animation: caret-blink 1.2s ease-out infinite;"
              ></div>
            </div>
          {:else if cell.isActive}
            <div class="pointer-events-none absolute inset-x-2 bottom-1.5 flex justify-center">
              <div
                class="h-0.5 w-4 animate-caret-blink bg-primary rounded-full pointer-events-none"
                style="animation: caret-blink 1.2s ease-out infinite;"
              ></div>
            </div>
          {/if}
        </BitsPinInput.Cell>
      {/each}
    </div>
    <div class="flex size-4 items-center justify-center text-muted-foreground/60 font-bold">•</div>
    <div class="flex gap-1.5 sm:gap-2">
      {#each cells.slice(3, 6) as cell}
        <BitsPinInput.Cell
          {cell}
          class={cn(
            "relative flex size-10 sm:size-12 items-center justify-center rounded-lg border bg-background/50 font-mono text-base sm:text-lg font-semibold shadow-xs transition-all pointer-events-none select-none",
            cell.isActive
              ? "border-primary ring-2 ring-primary/40 ring-offset-1 ring-offset-background"
              : "border-input data-[active]:border-primary data-[active]:ring-2 data-[active]:ring-primary/40"
          )}
        >
          {#if cell.char !== null}
            {cell.char}
          {/if}
          {#if cell.hasFakeCaret}
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
              <div
                class="h-5 sm:h-6 w-[2px] animate-caret-blink bg-primary rounded-full pointer-events-none"
                style="animation: caret-blink 1.2s ease-out infinite;"
              ></div>
            </div>
          {:else if cell.isActive}
            <div class="pointer-events-none absolute inset-x-2 bottom-1.5 flex justify-center">
              <div
                class="h-0.5 w-4 animate-caret-blink bg-primary rounded-full pointer-events-none"
                style="animation: caret-blink 1.2s ease-out infinite;"
              ></div>
            </div>
          {/if}
        </BitsPinInput.Cell>
      {/each}
    </div>
  {/snippet}
</BitsPinInput.Root>
