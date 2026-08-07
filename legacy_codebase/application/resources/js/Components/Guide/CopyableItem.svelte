<script lang="ts">
  import { Copy, Check } from 'lucide-svelte';
  import { success, error } from '@/lib/toast';
  import { onDestroy } from 'svelte';

  let { value, label, class: className = '' } = $props();

  const displayLabel = $derived.by(() => {
    if (label) return label;
    let s = value.replace(/{{|}}/g, '');
    if (s.endsWith('_2')) {
      s = s.replace('_2', ' (Applicant 2)');
    }
    return s.replace(/_/g, ' ');
  });

  let copied = $state(false);

  async function copyToClipboard() {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
      } else {
        const textArea = document.createElement('textarea');
        textArea.value = value;
        textArea.style.position = 'absolute';
        textArea.style.left = '-999999px';
        document.body.prepend(textArea);
        textArea.select();
        try {
          document.execCommand('copy');
        } catch (error) {
          throw error;
        } finally {
          textArea.remove();
        }
      }
      copied = true;
      success('Copied to clipboard');
    } catch (err) {
      error('Failed to copy text');
    }
  }
</script>

<button
  type="button"
  role="button"
  class="inline-flex items-center gap-1.5 rounded-full bg-secondary/50 px-2.5 py-1 text-xs font-mono transition-colors hover:bg-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {className}"
  onclick={copyToClipboard}
  aria-label={`Copy ${displayLabel}`}
>
  {#if copied}
    <Check class="h-3 w-3 text-green-500" />
  {:else}
    <Copy class="h-3 w-3 text-muted-foreground" />
  {/if}
  <span>{displayLabel}</span>
</button>
