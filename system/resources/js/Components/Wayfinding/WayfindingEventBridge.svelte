<script lang="ts">
  import { onMount } from 'svelte';
  import { usePage } from '@inertiajs/svelte';
  import VenueCompassHud from '@/Components/Wayfinding/VenueCompassHud.svelte';

  const page = usePage();
  const scope = $derived(`${page.props.auth?.user?.id ?? 'anonymous'}:${page.props.walkthroughProgress?.scope?.key ?? ''}`);
  let activeScope = $state('');
  let open = $state(false);
  let roomId = $state<number | null>(null);
  let launch = $state(0);
  let returnFocus: HTMLElement | null = null;

  $effect(() => {
    if (activeScope === scope) return;
    activeScope = scope;
    open = false;
    returnFocus = null;
  });

  function close(): void {
    open = false;
    if (returnFocus?.isConnected) returnFocus.focus();
    returnFocus = null;
  }

  function handleKeydown(event: KeyboardEvent): void {
    if (open && event.key === 'Escape' && !event.defaultPrevented) {
      event.preventDefault();
      close();
    }
  }

  onMount(() => {
    function openWayfinding(event: Event): void {
      const candidate = (event as CustomEvent<{ roomId?: unknown }>).detail?.roomId;
      roomId = typeof candidate === 'number' && Number.isSafeInteger(candidate) && candidate > 0 ? candidate : null;
      returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      launch += 1;
      open = true;
    }
    window.addEventListener('nexiam:open-wayfinding', openWayfinding);
    return () => window.removeEventListener('nexiam:open-wayfinding', openWayfinding);
  });
</script>
<svelte:window onkeydown={handleKeydown} />


{#if open}
  {#key launch}
    <VenueCompassHud {open} {roomId} onClose={close} />
  {/key}
{/if}
