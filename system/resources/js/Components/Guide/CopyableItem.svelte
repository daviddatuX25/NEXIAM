<script>
  import { Copy, Check, FileText, Keyboard, ClipboardPaste } from 'lucide-svelte';
  import { success, error } from '@/lib/toast';
  import { onDestroy } from 'svelte';

  let { value, label = '', class: className = '' } = $props();

  const formattedValue = $derived(value.startsWith('{{') ? value : `{{${value}}}`);
  const displayLabel = $derived(label || value.replace(/{{|}}/g, '').replace(/_/g, ' '));

  let copied = $state(false);
  let cueStage = $state('idle');
  let timer = null;

  onDestroy(() => clearTimeout(timer));

  async function copyToClipboard() {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(formattedValue);
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = formattedValue;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        textArea.remove();
      }

      copied = true;
      cueStage = 'open';
      success(`Copied ${formattedValue}`);
      clearTimeout(timer);
      timer = setTimeout(() => {
        cueStage = 'paste';
        timer = setTimeout(() => {
          cueStage = 'done';
          timer = setTimeout(() => {
            cueStage = 'idle';
            copied = false;
          }, 600);
        }, 450);
      }, 350);
    } catch {
      error('Failed to copy to clipboard');
    }
  }
</script>

<div class="relative inline-flex items-center">
  <button
    type="button"
    class="inline-flex items-center gap-1.5 rounded-md border border-border/80 bg-muted/40 px-2 py-1 font-mono text-[11px] font-medium text-foreground transition-all hover:border-primary/40 hover:bg-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring {copied ? 'border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-300' : ''} {className}"
    onclick={copyToClipboard}
    title={`Click to copy ${formattedValue}`}
    aria-label={`Copy ${displayLabel}`}
  >
    {#if copied}
      <Check class="size-3 text-green-600 dark:text-green-400" />
      <span class="text-green-700 dark:text-green-300 font-sans text-[10px]">Copied</span>
    {:else}
      <Copy class="size-3 text-muted-foreground opacity-60" />
      <span>{formattedValue}</span>
    {/if}
  </button>

  {#if cueStage !== 'idle'}
    <span
      class="paste-cue absolute left-1/2 top-full z-20 mt-2 flex -translate-x-1/2 items-center gap-1.5 whitespace-nowrap rounded-md border border-border bg-card px-2 py-1.5 text-[10px] font-medium text-foreground shadow-md"
      role="status"
      aria-live="polite"
    >
      {#if cueStage === 'open'}
        <FileText class="size-3.5 text-primary" />
        <span>Open document</span>
      {:else if cueStage === 'paste'}
        <Keyboard class="size-3.5 text-primary" />
        <kbd class="rounded border border-border bg-muted px-1 font-mono">Ctrl</kbd>
        <span>+</span>
        <kbd class="rounded border border-border bg-muted px-1 font-mono">V</kbd>
      {:else}
        <ClipboardPaste class="size-3.5 text-green-600" />
        <Check class="size-3.5 text-green-600" />
        <span class="text-green-700 dark:text-green-300">Ready</span>
      {/if}
    </span>
  {/if}
</div>

<style>
  .paste-cue {
    animation: paste-cue-in 140ms ease-out both;
  }

  @keyframes paste-cue-in {
    from { opacity: 0; transform: translate(-50%, -3px) scale(0.96); }
    to { opacity: 1; transform: translate(-50%, 0) scale(1); }
  }

  @media (prefers-reduced-motion: reduce) {
    .paste-cue { animation: none; }
  }
</style>
