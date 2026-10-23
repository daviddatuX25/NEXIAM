<script>
  import { onMount } from 'svelte';
  import { Clock } from 'lucide-svelte';

  let signInButton = $state(null);

  onMount(() => {
    signInButton?.focus();
  });

  function signInAgain() {
    window.location.replace('/login?expired=1');
  }
</script>

<div class="fixed inset-0 z-50 flex items-center justify-center bg-background/95 backdrop-blur-md p-4">
  <div
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="session-expired-title"
    aria-describedby="session-expired-desc"
    class="w-full max-w-md rounded-2xl border border-border/60 bg-card p-6 text-center shadow-2xl"
  >
    <div class="mb-5 flex size-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500 mx-auto">
      <Clock class="size-6" aria-hidden="true" />
    </div>
    <h1 id="session-expired-title" class="text-xl font-bold tracking-tight text-foreground">
      Session expired
    </h1>
    <p id="session-expired-desc" class="mt-2 text-sm text-muted-foreground">
      For campus security and data privacy, this workstation was locked due to inactivity.
    </p>
    <p class="mt-4 rounded-lg border border-border/60 bg-muted/40 p-3 text-xs text-muted-foreground">
      Changes not submitted before the session ended may not have been saved.
    </p>
    <button
      bind:this={signInButton}
      type="button"
      onclick={signInAgain}
      class="mt-6 inline-flex h-11 w-full items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground shadow-xs transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
    >
      Sign in again
    </button>
  </div>
</div>
