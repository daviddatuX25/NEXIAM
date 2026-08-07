<script>
  /**
   * Flash → toast bridge (Inertia Svelte 3 safe).
   *
   * Inertia `page` is $state via usePage() — not a classic store.
   * Do not use page.subscribe().
   */
  import { toasts, dismiss, success, error, info } from '@/lib/toast.js';
  import { fly } from 'svelte/transition';
  import { usePage } from '@inertiajs/svelte';
  import { CheckCircle, XCircle, Info, X } from 'lucide-svelte';

  const page = usePage();

  let lastStatus = $state(null);
  let lastSuccess = $state(null);
  let lastError = $state(null);

  $effect(() => {
    const flash = page.props?.flash;
    if (!flash) {
      return;
    }

    if (flash.status && flash.status !== lastStatus) {
      lastStatus = flash.status;
      info(flash.status);
    }
    if (flash.success && flash.success !== lastSuccess) {
      lastSuccess = flash.success;
      success(flash.success);
    }
    if (flash.error && flash.error !== lastError) {
      lastError = flash.error;
      error(flash.error);
    }
  });
</script>

<div class="fixed top-4 right-4 z-[9999] flex max-w-sm flex-col gap-2">
  {#each $toasts as toast (toast.id)}
    <div
      class="toast-{toast.type} flex items-center gap-3 rounded-lg border px-4 py-3 shadow-xl backdrop-blur-sm transition-all duration-200"
      transition:fly={{ y: -20, duration: 200 }}
      role="alert"
    >
      <div class="flex-shrink-0">
        {#if toast.type === 'success'}
          <CheckCircle class="h-5 w-5 text-success" />
        {:else if toast.type === 'error'}
          <XCircle class="h-5 w-5 text-destructive" />
        {:else}
          <Info class="h-5 w-5 text-primary" />
        {/if}
      </div>

      <p class="flex-1 text-sm font-medium text-foreground">{toast.message}</p>

      <button
        type="button"
        class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md transition-colors hover:bg-accent"
        onclick={() => dismiss(toast.id)}
        title="Close"
      >
        <X class="h-4 w-4 text-muted-foreground" />
      </button>
    </div>
  {/each}
</div>

<style>
  .toast-success {
    background-color: color-mix(in srgb, var(--success) 10%, transparent);
    border-color: color-mix(in srgb, var(--success) 30%, transparent);
  }

  .toast-error {
    background-color: color-mix(in srgb, var(--destructive) 10%, transparent);
    border-color: color-mix(in srgb, var(--destructive) 30%, transparent);
  }

  .toast-info {
    background-color: color-mix(in srgb, var(--info, var(--primary)) 10%, transparent);
    border-color: color-mix(in srgb, var(--info, var(--primary)) 30%, transparent);
  }
</style>
