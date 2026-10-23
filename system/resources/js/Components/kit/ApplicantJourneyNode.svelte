<script lang="ts">
  import { AlertTriangle, Check, Circle, Clock3, LoaderCircle } from 'lucide-svelte';
  import { resolveJourneyStatusAppearance } from '@/lib/applicant-journey';

  let { status }: { status: string } = $props();

  const appearance = $derived(resolveJourneyStatusAppearance(status));
  const icon = $derived(status === 'done' ? Check : status === 'action' ? AlertTriangle : status === 'current' ? LoaderCircle : status === 'auto' ? Clock3 : Circle);
</script>

<div class={`relative z-10 flex size-10 shrink-0 items-center justify-center rounded-full border-2 ${appearance.nodeClass}`}>
  {#if appearance.pulse}
    <span class="journey-node-pulse" aria-hidden="true"></span>
  {:else if status === 'upcoming'}
    <span class="journey-node-breathe" aria-hidden="true"></span>
  {/if}
  <svelte:component this={icon} class={`size-5 ${appearance.iconClass}`} aria-hidden="true" />
  <span class="sr-only">{appearance.label}</span>
</div>
<style>
  .journey-node-pulse {
    position: absolute;
    inset: -0.375rem;
    border: 2px solid currentColor;
    border-radius: 9999px;
    opacity: 0;
    pointer-events: none;
    animation: pulseRing 1.8s ease-out infinite;
  }

  .journey-node-breathe {
    position: absolute;
    inset: -0.25rem;
    border: 1px solid currentColor;
    border-radius: 9999px;
    opacity: 0;
    pointer-events: none;
    animation: breathePending 2.8s ease-in-out infinite;
  }

  @keyframes pulseRing {
    0% { transform: scale(0.82); opacity: 0.55; }
    100% { transform: scale(1.45); opacity: 0; }
  }

  @keyframes breathePending {
    0%, 100% { transform: scale(0.96); opacity: 0.12; }
    50% { transform: scale(1.12); opacity: 0.32; }
  }

  @media (prefers-reduced-motion: reduce) {
    .journey-node-pulse,
    .journey-node-breathe {
      animation: none;
      transform: none;
    }
  }
</style>
