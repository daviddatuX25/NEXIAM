<script>
  import { FileOutput, ScanSearch } from 'lucide-svelte';

  let {
    kind = 'render',
    variant = 'surface',
    title = 'Working…',
    detail = 'Keep this window open while NEXIAM finishes.',
    class: className = '',
  } = $props();

  const variantClasses = {
    inline: 'inline-flex min-h-10 items-center gap-3 rounded-xl border border-border/80 bg-card px-3 py-2.5',
    surface: 'relative flex min-h-[220px] w-full items-center justify-center rounded-2xl border border-border/80 bg-card/80 p-6',
    overlay: 'absolute inset-0 z-10 flex items-center justify-center bg-card/85 p-6 backdrop-blur-sm',
  };
</script>

<div
  class={`${variantClasses[variant] ?? variantClasses.surface} ${className}`}
  role="status"
  aria-live="polite"
  aria-busy="true"
>
  <div class={variant === 'inline' ? 'flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary' : 'flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary'}>
    <span class="relative flex items-center justify-center">
      <span class="absolute size-full animate-ping rounded-full bg-primary/20 motion-reduce:animate-none"></span>
      {#if kind === 'analysis'}
        <ScanSearch class={`${variant === 'inline' ? 'size-4' : 'size-7'} relative animate-pulse motion-reduce:animate-none`} aria-hidden="true" />
      {:else}
        <FileOutput class={`${variant === 'inline' ? 'size-4' : 'size-7'} relative animate-pulse motion-reduce:animate-none`} aria-hidden="true" />
      {/if}
    </span>
  </div>
  <div class={variant === 'inline' ? 'min-w-0' : 'mt-4 min-w-0 max-w-sm text-center'}>
    <p class={variant === 'inline' ? 'truncate text-xs font-bold text-foreground' : 'text-sm font-black text-foreground'}>{title}</p>
    <p class={variant === 'inline' ? 'mt-0.5 truncate text-[11px] text-muted-foreground' : 'mt-1 text-xs leading-relaxed text-muted-foreground'}>{detail}</p>
  </div>
  {#if variant !== 'inline'}
    <div class="absolute bottom-5 left-1/2 flex w-36 -translate-x-1/2 gap-1" aria-hidden="true">
      <span class="h-1 flex-1 animate-pulse rounded-full bg-primary/30 motion-reduce:animate-none"></span>
      <span class="h-1 flex-1 animate-pulse rounded-full bg-primary/60 [animation-delay:150ms] motion-reduce:animate-none"></span>
      <span class="h-1 flex-1 animate-pulse rounded-full bg-primary [animation-delay:300ms] motion-reduce:animate-none"></span>
    </div>
  {/if}
</div>
